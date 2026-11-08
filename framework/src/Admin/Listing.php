<?php

/**
 * Listing.php
 *
 * Listado de un panel de administración: búsqueda, orden por columna, filtros, paginación y
 * exportación a CSV a partir de los parámetros de la petición (q, sort, dir, filtro[...]).
 *
 * @package    BootZen\Admin
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.9.0
 */

declare(strict_types=1);

namespace BootZen\Admin;

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Database\Model;
use BootZen\Database\ModelQuery;
use BootZen\Database\Paginator;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Las columnas que se buscan, ordenan y filtran las decide el código, nunca la petición: un sort o un
 * filtro que no está en la lista se ignora. Los valores van siempre como parámetros.
 *
 * @template TModel of Model
 *
 * @example
 *   $listado = Listing::from(Producto::query(), $request)
 *       ->search(['nombre', 'descripcion'])
 *       ->filters(['activo' => 'bool', 'alta' => 'date'])
 *       ->sortable(['nombre', 'precio', 'created_at']);
 *   $productos = $listado->paginate(20);
 *   return $listado->csv('productos.csv', ['id' => 'ID', 'nombre' => __('Nombre')]);
 */
final class Listing
{
    private string $term;

    private string $sort = 'id';

    private string $direction = 'desc';

    /**
     * Filtros aplicados: parámetro => valor ('activo' => '1', 'alta_desde' => '2026-01-01').
     *
     * @var array<string, string>
     */
    private array $applied = [];

    /**
     * @param ModelQuery<TModel> $query
     */
    private function __construct(private readonly ModelQuery $query, private readonly Request $request)
    {
        $term = $request->getQuery('q', '');
        $this->term = is_string($term) ? mb_substr(trim($term), 0, 100) : '';
    }

    /**
     * @template T of Model
     * @param ModelQuery<T> $query
     * @return self<T>
     */
    public static function from(ModelQuery $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * Busca el texto de ?q= en estas columnas.
     *
     * @param list<string> $columns
     * @return self<TModel>
     */
    public function search(array $columns): self
    {
        $this->query->whereLike($columns, $this->term);

        return $this;
    }

    /**
     * Filtros por columna: 'bool' (filtro[activo]=1|0) o 'date' (filtro[alta_desde] y filtro[alta_hasta],
     * AAAA-MM-DD). Valores inválidos se ignoran.
     *
     * @param array<string, string> $filters Columna => 'bool' o 'date'
     * @return self<TModel>
     * @throws InvalidArgumentException Si un tipo no es bool ni date
     */
    public function filters(array $filters): self
    {
        $input = $this->request->getQuery('filtro', []);
        $input = is_array($input) ? $input : [];

        foreach ($filters as $column => $type) {
            if ($type === 'bool') {
                $value = $input[$column] ?? null;

                if ($value === '1' || $value === '0') {
                    $this->query->where($column, $value === '1');
                    $this->applied[$column] = $value;
                }

                continue;
            }

            if ($type !== 'date') {
                throw new InvalidArgumentException("Tipo de filtro [{$type}] desconocido para {$column}: usa 'bool' o 'date'.");
            }

            foreach (['desde' => '>=', 'hasta' => '<='] as $suffix => $operator) {
                $value = $input["{$column}_{$suffix}"] ?? null;
                $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

                if ($date !== false && $date->format('Y-m-d') === $value) {
                    $this->query->where($column, $operator, $suffix === 'desde' ? $value : $value . ' 23:59:59');
                    $this->applied["{$column}_{$suffix}"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Columnas por las que se puede ordenar (?sort=nombre&dir=asc) y el orden por defecto.
     *
     * @param list<string> $columns
     * @return self<TModel>
     */
    public function sortable(array $columns, string $default = 'id', string $direction = 'desc'): self
    {
        $sort = $this->request->getQuery('sort');
        $dir = $this->request->getQuery('dir');

        $this->sort = is_string($sort) && in_array($sort, $columns, true) ? $sort : $default;
        $this->direction = $dir === 'asc' || $dir === 'desc' ? $dir : ($this->sort === $default ? $direction : 'asc');
        $this->query->orderBy($this->sort, $this->direction);

        if ($this->sort !== 'id') {
            // Desempate estable: con valores repetidos, la paginación no salta ni repite filas
            $this->query->orderBy('id', 'desc');
        }

        return $this;
    }

    /**
     * Página actual (?page=) con la búsqueda, el orden y los filtros en los enlaces.
     *
     * @return Paginator<TModel>
     */
    public function paginate(int $perPage = 20): Paginator
    {
        return $this->query->paginate($perPage, $this->request->integer('page', 1))->appends($this->parameters());
    }

    /**
     * Descarga en CSV de todo lo que coincide (no solo la página), escrito por bloques.
     *
     * @param array<string, string> $columns Columna => encabezado
     */
    public function csv(string $filename, array $columns): Response
    {
        $query = $this->query;
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?? 'export.csv';

        return Response::stream(static function () use ($query, $columns): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            // BOM: Excel abre bien los acentos
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns), ',', '"', '');

            foreach ($query->cursor() as $model) {
                fputcsv($out, array_map(static fn (string $column): string => self::cell($model->getAttribute($column)), array_keys($columns)), ',', '"', '');
            }

            fclose($out);
        }, 200, [
            'content-type' => 'text/csv; charset=utf-8',
            'content-disposition' => "attachment; filename=\"{$safe}\"",
            'cache-control' => 'no-store',
        ]);
    }

    public function term(): string
    {
        return $this->term;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    /**
     * Valor de un filtro aplicado (para dejarlo seleccionado en el formulario).
     */
    public function filter(string $name): string
    {
        return $this->applied[$name] ?? '';
    }

    /**
     * Query string que ordena por $column (si ya ordena por ella, invierte la dirección).
     */
    public function sortUrl(string $column): string
    {
        $direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';

        return '?' . http_build_query(['sort' => $column, 'dir' => $direction] + $this->parameters());
    }

    /**
     * Búsqueda, orden y filtros actuales como parámetros (para enlaces, como el de exportar).
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return array_filter([
            'q' => $this->term,
            'sort' => $this->sort,
            'dir' => $this->direction,
            'filtro' => $this->applied,
        ], static fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    private static function translate(string $text): string
    {
        return Application::hasInstance() ? __($text) : $text;
    }

    /**
     * Texto seguro para una celda: fechas legibles, sí/no y sin fórmulas de hoja de cálculo.
     */
    private static function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => self::translate($value ? 'Sí' : 'No'),
            $value instanceof DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i'),
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };

        // Un valor que empieza con = + - @ se ejecutaría como fórmula al abrirlo en Excel
        return ! is_numeric($text) && preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'" . $text : $text;
    }
}
