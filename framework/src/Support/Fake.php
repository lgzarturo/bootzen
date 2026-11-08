<?php

/**
 * Fake.php
 *
 * Datos falsos para factories, seeders y tests, sin librerías: nombres, correos, textos, números y
 * fechas en español o en inglés según el idioma activo.
 *
 * @package    BootZen\Support
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.5.0
 */

declare(strict_types=1);

namespace BootZen\Support;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Con semilla los valores se repiten igual en cada corrida (en los tests la semilla es fija). Los
 * correos usan dominios reservados para pruebas (example.com), así nunca le llega nada a nadie.
 *
 * @example fake()->name(); // 'Lucía Ramírez'
 * @example fake()->unique()->email(); // nunca repite en la misma aplicación
 */
final class Fake
{
    private const DATA = [
        'es' => [
            'first' => ['Ana', 'Luis', 'María', 'Carlos', 'Lucía', 'Jorge', 'Sofía', 'Miguel', 'Valeria', 'Diego', 'Camila', 'Andrés', 'Fernanda', 'Javier', 'Daniela', 'Ricardo', 'Paula', 'Héctor', 'Regina', 'Emilio', 'Ximena', 'Arturo', 'Mariana', 'Raúl'],
            'last' => ['García', 'Hernández', 'López', 'Martínez', 'González', 'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Rivera', 'Gómez', 'Díaz', 'Cruz', 'Morales', 'Reyes', 'Ortiz', 'Castillo', 'Mendoza'],
            'words' => ['casa', 'trabajo', 'proyecto', 'cliente', 'reunión', 'informe', 'pago', 'factura', 'pedido', 'equipo', 'ventas', 'agenda', 'tarea', 'revisión', 'entrega', 'presupuesto', 'contrato', 'llamada', 'correo', 'inventario', 'oficina', 'propuesta', 'calendario', 'compra', 'servicio', 'plan', 'avance', 'nota', 'lista', 'mercado'],
            'verbs' => ['revisar', 'enviar', 'preparar', 'aprobar', 'pagar', 'organizar', 'actualizar', 'confirmar', 'comprar', 'terminar', 'planear', 'corregir'],
            'objects' => ['el informe', 'la factura', 'el pedido', 'la reunión', 'el presupuesto', 'el contrato', 'la propuesta', 'el inventario', 'la entrega', 'el pago', 'la agenda', 'el reporte', 'la cotización', 'el correo', 'la lista'],
            'connectors' => ['de', 'para'],
            'companies' => ['Grupo', 'Comercial', 'Servicios', 'Distribuidora', 'Soluciones', 'Industrias'],
            'cities' => ['Ciudad de México', 'Guadalajara', 'Monterrey', 'Puebla', 'Mérida', 'Querétaro', 'Oaxaca', 'León', 'Tijuana', 'Cancún'],
            'streets' => ['Av. Reforma', 'Calle Hidalgo', 'Av. Juárez', 'Calle Morelos', 'Av. Insurgentes', 'Calle Allende', 'Av. Universidad'],
            'countries' => ['México', 'España', 'Argentina', 'Colombia', 'Chile', 'Perú'],
            'phone' => '55 #### ####',
        ],
        'en' => [
            'first' => ['Emma', 'Liam', 'Olivia', 'Noah', 'Ava', 'James', 'Sophia', 'Lucas', 'Mia', 'Henry', 'Grace', 'Jack', 'Chloe', 'Owen', 'Ella', 'Ryan', 'Lily', 'Ethan', 'Nora', 'Leo'],
            'last' => ['Smith', 'Johnson', 'Brown', 'Taylor', 'Miller', 'Wilson', 'Moore', 'Clark', 'Walker', 'Hall', 'Young', 'King', 'Wright', 'Hill', 'Green', 'Baker', 'Adams', 'Nelson'],
            'words' => ['home', 'work', 'project', 'client', 'meeting', 'report', 'payment', 'invoice', 'order', 'team', 'sales', 'schedule', 'task', 'review', 'delivery', 'budget', 'contract', 'call', 'email', 'inventory', 'office', 'proposal', 'calendar', 'purchase', 'service', 'plan', 'update', 'note', 'list', 'market'],
            'verbs' => ['review', 'send', 'prepare', 'approve', 'pay', 'organize', 'update', 'confirm', 'buy', 'finish', 'plan', 'fix'],
            'objects' => ['the report', 'the invoice', 'the order', 'the meeting', 'the budget', 'the contract', 'the proposal', 'the inventory', 'the delivery', 'the payment', 'the schedule', 'the quote', 'the email', 'the list'],
            'connectors' => ['for', 'about'],
            'companies' => ['Group', 'Trading', 'Services', 'Supply', 'Solutions', 'Industries'],
            'cities' => ['New York', 'Chicago', 'Austin', 'Denver', 'Seattle', 'Boston', 'Portland', 'Miami', 'Atlanta', 'Phoenix'],
            'streets' => ['Main St', 'Oak Ave', 'Pine St', 'Maple Ave', 'Cedar Rd', 'Elm St', 'Lake Dr'],
            'countries' => ['United States', 'Canada', 'United Kingdom', 'Australia', 'Ireland', 'New Zealand'],
            'phone' => '(###) ###-####',
        ],
    ];

    private const UNIQUE_TRIES = 10_000;

    private Randomizer $random;

    /**
     * @var array<string, array<string, true>> Valores ya entregados por unique(), por método
     */
    private array $used = [];

    private bool $unique = false;

    /**
     * @param int|null $seed Semilla para repetir los mismos valores; null los da al azar
     * @param (Closure(): string)|string $locale Idioma ('es' o 'en'; otros usan español)
     */
    public function __construct(?int $seed = null, private Closure|string $locale = 'es')
    {
        $this->random = $seed === null ? new Randomizer() : new Randomizer(new Mt19937($seed));
    }

    /**
     * Reinicia la secuencia: con la misma semilla salen los mismos valores.
     */
    public function seed(int $seed): self
    {
        $this->random = new Randomizer(new Mt19937($seed));
        $this->used = [];

        return $this;
    }

    /**
     * El siguiente valor no repite ninguno que este mismo método ya haya dado con unique(). Si ya no
     * quedan valores distintos, el método lanza InvalidArgumentException.
     */
    public function unique(): self
    {
        $clone = clone $this;
        $clone->unique = true;
        // Comparte el registro de usados y el generador con el original
        $clone->used = &$this->used;
        $clone->random = &$this->random;

        return $clone;
    }

    public function firstName(): string
    {
        return $this->one(fn (): string => $this->pick($this->data('first')), __FUNCTION__);
    }

    public function lastName(): string
    {
        return $this->one(fn (): string => $this->pick($this->data('last')), __FUNCTION__);
    }

    /**
     * Nombre y apellido.
     */
    public function name(): string
    {
        return $this->one(fn (): string => $this->pick($this->data('first')) . ' ' . $this->pick($this->data('last')), __FUNCTION__);
    }

    /**
     * Correo en un dominio reservado para pruebas (example.com, example.org o example.net).
     */
    public function email(): string
    {
        return $this->one(function (): string {
            $user = self::ascii($this->pick($this->data('first')) . '.' . $this->pick($this->data('last')), '.');

            return $user . $this->random->getInt(1, 999) . '@' . $this->pick(['example.com', 'example.org', 'example.net']);
        }, __FUNCTION__);
    }

    public function userName(): string
    {
        return $this->one(fn (): string => self::ascii($this->pick($this->data('first')), '_') . $this->random->getInt(10, 9999), __FUNCTION__);
    }

    public function phoneNumber(): string
    {
        return $this->one(fn (): string => (string) preg_replace_callback('/#/', fn (): string => (string) $this->random->getInt(0, 9), $this->data('phone')), __FUNCTION__);
    }

    public function company(): string
    {
        return $this->one(function (): string {
            $kind = $this->pick($this->data('companies'));
            $name = $this->pick($this->data('last'));

            return $this->isEnglish() ? "{$name} {$kind}" : "{$kind} {$name}";
        }, __FUNCTION__);
    }

    public function city(): string
    {
        return $this->one(fn (): string => $this->pick($this->data('cities')), __FUNCTION__);
    }

    public function country(): string
    {
        return $this->one(fn (): string => $this->pick($this->data('countries')), __FUNCTION__);
    }

    public function address(): string
    {
        return $this->one(fn (): string => $this->isEnglish()
            ? $this->random->getInt(10, 9999) . ' ' . $this->pick($this->data('streets')) . ', ' . $this->pick($this->data('cities'))
            : $this->pick($this->data('streets')) . ' ' . $this->random->getInt(1, 999) . ', ' . $this->pick($this->data('cities')), __FUNCTION__);
    }

    public function word(): string
    {
        return $this->one(fn (): string => $this->pick($this->data('words')), __FUNCTION__);
    }

    /**
     * @return list<string>
     */
    public function words(int $count = 3): array
    {
        return array_map(fn (): string => $this->pick($this->data('words')), range(1, max(1, $count)));
    }

    /**
     * Frase corta con sentido aparente, buena para títulos: 'Revisar la factura de ventas'. $words es
     * el mínimo de palabras.
     */
    public function sentence(int $words = 4): string
    {
        return $this->one(function () use ($words): string {
            $parts = [$this->pick($this->data('verbs')), $this->pick($this->data('objects'))];

            // 'Revisar la factura de ventas': el complemento va sin artículo para no fallar el género
            while (count(explode(' ', implode(' ', $parts))) < $words) {
                $parts[] = $this->pick($this->data('connectors'));
                $parts[] = $this->pick($this->data('words'));
            }

            return self::upperFirst(implode(' ', $parts));
        }, __FUNCTION__);
    }

    /**
     * Varias frases terminadas en punto.
     */
    public function paragraph(int $sentences = 3): string
    {
        return implode(' ', array_map(fn (): string => $this->sentence($this->random->getInt(3, 7)) . '.', range(1, max(1, $sentences))));
    }

    /**
     * Texto de hasta $maxLength caracteres, cortado en una palabra completa.
     */
    public function text(int $maxLength = 200): string
    {
        $text = $this->paragraph(max(1, intdiv($maxLength, 30)));

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), ' ,.') . '.';
    }

    /**
     * Entero entre $min y $max (incluidos).
     */
    public function number(int $min = 0, int $max = 100): int
    {
        return $this->one(fn (): int => $this->random->getInt(min($min, $max), max($min, $max)), __FUNCTION__);
    }

    /**
     * Decimal entre $min y $max con $decimals decimales.
     */
    public function decimal(float $min = 0, float $max = 1000, int $decimals = 2): float
    {
        return $this->one(fn (): float => round($min + ($max - $min) * ($this->random->getInt(0, 1_000_000_000) / 1_000_000_000), $decimals), __FUNCTION__);
    }

    /**
     * true con probabilidad $chance (porcentaje).
     */
    public function boolean(int $chance = 50): bool
    {
        return $this->random->getInt(1, 100) <= $chance;
    }

    /**
     * Fecha 'Y-m-d' entre $from y $to (cualquier texto que entienda strtotime).
     */
    public function date(string $from = '-1 year', string $to = 'now'): string
    {
        return $this->dateTimeBetween($from, $to)->format('Y-m-d');
    }

    /**
     * Fecha y hora 'Y-m-d H:i:s' entre $from y $to.
     */
    public function dateTime(string $from = '-1 year', string $to = 'now'): string
    {
        return $this->dateTimeBetween($from, $to)->format('Y-m-d H:i:s');
    }

    /**
     * Un elemento al azar de la lista.
     *
     * @template T
     * @param array<array-key, T> $items
     * @return T
     * @throws InvalidArgumentException Si la lista está vacía
     */
    public function randomElement(array $items): mixed
    {
        if ($items === []) {
            throw new InvalidArgumentException('randomElement() necesita una lista con al menos un elemento.');
        }

        $values = array_values($items);

        return $this->one(fn (): mixed => $values[$this->random->getInt(0, count($values) - 1)], __FUNCTION__ . ':' . md5(serialize($values)));
    }

    /**
     * UUID versión 4.
     */
    public function uuid(): string
    {
        $bytes = $this->random->getBytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * URL en un dominio reservado para pruebas.
     */
    public function url(): string
    {
        return 'https://' . $this->pick(['example.com', 'example.org', 'example.net']) . '/' . self::ascii($this->pick($this->data('words')));
    }

    private function dateTimeBetween(string $from, string $to): DateTimeImmutable
    {
        $start = strtotime($from);
        $end = strtotime($to);

        if ($start === false || $end === false) {
            throw new InvalidArgumentException("Rango de fechas inválido [{$from}, {$to}]: usa textos como '-1 year', 'now' o '2026-01-01'.");
        }

        return (new DateTimeImmutable())->setTimestamp($this->random->getInt(min($start, $end), max($start, $end)));
    }

    /**
     * Genera un valor; con unique() reintenta hasta que no esté repetido.
     *
     * @template T
     * @param Closure(): T $generate
     * @return T
     */
    private function one(Closure $generate, string $key): mixed
    {
        if (! $this->unique) {
            return $generate();
        }

        for ($try = 0; $try < self::UNIQUE_TRIES; $try++) {
            $value = $generate();
            $hash = is_scalar($value) ? (string) $value : serialize($value);

            if (! isset($this->used[$key][$hash])) {
                $this->used[$key][$hash] = true;

                return $value;
            }
        }

        throw new InvalidArgumentException("unique() ya no encuentra valores distintos para {$key}: pide menos registros o usa otro dato.");
    }

    /**
     * @param list<string> $items
     */
    private function pick(array $items): string
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    /**
     * @return ($key is 'phone' ? string : list<string>)
     */
    private function data(string $key): array|string
    {
        return self::DATA[$this->isEnglish() ? 'en' : 'es'][$key];
    }

    /**
     * Minúsculas sin acentos y con $separator entre palabras (para correos y usuarios).
     */
    private static function ascii(string $text, string $separator = '-'): string
    {
        $text = strtr(mb_strtolower($text), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/[^a-z0-9]+/', $separator, $text), $separator);
    }

    private static function upperFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private function isEnglish(): bool
    {
        $locale = $this->locale instanceof Closure ? ($this->locale)() : $this->locale;

        return str_starts_with($locale, 'en');
    }
}
