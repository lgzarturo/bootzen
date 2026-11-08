<?php

declare(strict_types=1);

use BootZen\Admin\Listing;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Database\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 * @property float $precio
 * @property DateTimeImmutable|null $alta
 */
final class ListingProducto extends Model
{
    protected static string $table = 'productos';

    protected static array $fillable = ['nombre', 'activo', 'precio', 'alta'];

    protected static array $casts = ['activo' => 'bool', 'precio' => 'float', 'alta' => 'date'];

    protected static bool $timestamps = false;
}

beforeEach(function (): void {
    $this->db = memoryDb();
    $this->db->exec('CREATE TABLE productos (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, activo INTEGER NOT NULL, precio REAL NOT NULL, alta TEXT)');
    Model::setConnectionResolver(fn () => $this->db);

    $datos = [
        ['Café 100% arábica', true, 120.5, '2026-01-10'],
        ['Té verde', true, 80, '2026-02-15'],
        ['Taza_grande', false, 95, '2026-02-20'],
        ['=HYPERLINK("http://x")', true, -5, null],
        ['Café molido', false, 60, '2026-03-01'],
    ];
    foreach ($datos as [$nombre, $activo, $precio, $alta]) {
        ListingProducto::create(['nombre' => $nombre, 'activo' => $activo, 'precio' => $precio, 'alta' => $alta]);
    }

    $this->listado = fn (array $query): Listing => Listing::from(ListingProducto::query(), new Request('GET', '/admin/productos?' . http_build_query($query)))
        ->search(['nombre'])
        ->filters(['activo' => 'bool', 'alta' => 'date'])
        ->sortable(['nombre', 'precio']);
    $this->nombres = fn (Listing $listado): array => array_map(static fn (ListingProducto $p): string => $p->nombre, $listado->paginate(10)->items());
});

afterEach(fn () => Model::setConnectionResolver(null));

it('busca sin interpretar % y _ como comodines', function (): void {
    expect(($this->nombres)(($this->listado)(['q' => 'café'])))->toBe(['Café molido', 'Café 100% arábica'])
        ->and(($this->nombres)(($this->listado)(['q' => '100%'])))->toBe(['Café 100% arábica'])
        ->and(($this->nombres)(($this->listado)(['q' => 'a_g'])))->toBe(['Taza_grande'])
        ->and(($this->nombres)(($this->listado)(['q' => '%'])))->toBe(['Café 100% arábica'])
        ->and(($this->nombres)(($this->listado)(['q' => '   '])))->toHaveCount(5);
});

it('ordena solo por columnas permitidas e ignora lo demás', function (): void {
    $porPrecio = ($this->listado)(['sort' => 'precio', 'dir' => 'asc']);

    expect(($this->nombres)($porPrecio))->toBe(['=HYPERLINK("http://x")', 'Café molido', 'Té verde', 'Taza_grande', 'Café 100% arábica'])
        ->and($porPrecio->sortUrl('precio'))->toContain('sort=precio&dir=desc')
        ->and($porPrecio->sortUrl('nombre'))->toContain('sort=nombre&dir=asc')
        // Una columna no permitida (o un intento de SQL) cae al orden por defecto
        ->and(($this->listado)(['sort' => 'id; DROP TABLE productos', 'dir' => 'x'])->sort())->toBe('id')
        ->and(($this->listado)(['sort' => 'password'])->direction())->toBe('desc')
        ->and(ListingProducto::query()->count())->toBe(5);
});

it('filtra por booleanos y rangos de fecha y descarta valores inválidos', function (): void {
    $inactivos = ($this->listado)(['filtro' => ['activo' => '0']]);
    $febrero = ($this->listado)(['filtro' => ['alta_desde' => '2026-02-01', 'alta_hasta' => '2026-02-20']]);
    $basura = ($this->listado)(['filtro' => ['activo' => 'tal vez', 'alta_desde' => '2026-02-31', 'otro' => '1']]);

    expect(($this->nombres)($inactivos))->toBe(['Café molido', 'Taza_grande'])
        ->and($inactivos->filter('activo'))->toBe('0')
        ->and(($this->nombres)($febrero))->toBe(['Taza_grande', 'Té verde'])
        ->and(($this->nombres)($basura))->toHaveCount(5)
        ->and($basura->parameters())->toBe(['sort' => 'id', 'dir' => 'desc'])
        ->and(fn () => Listing::from(ListingProducto::query(), new Request('GET', '/'))->filters(['x' => 'rango']))->toThrow(InvalidArgumentException::class, "usa 'bool' o 'date'");
});

it('pagina conservando búsqueda, orden y filtros en los enlaces', function (): void {
    $pagina = Listing::from(ListingProducto::query(), new Request('GET', '/?q=t&sort=nombre&dir=asc&filtro[activo]=1&page=2'))
        ->search(['nombre'])->filters(['activo' => 'bool'])->sortable(['nombre'])->paginate(1);

    expect($pagina->currentPage())->toBe(2)
        ->and($pagina->total())->toBe(2)
        ->and(urldecode($pagina->url(1)))->toBe('?q=t&sort=nombre&dir=asc&filtro[activo]=1&page=1');
});

it('exporta a CSV en streaming, con todo lo filtrado y sin fórmulas', function (): void {
    $respuesta = ($this->listado)(['filtro' => ['activo' => '1']])->csv('productos 2026.csv', [
        'id' => 'ID', 'nombre' => 'Nombre', 'activo' => 'Activo', 'precio' => 'Precio', 'alta' => 'Alta',
    ]);

    expect($respuesta)->toBeInstanceOf(Response::class)
        ->and($respuesta->isStreamed())->toBeTrue()
        ->and($respuesta->getHeader('content-disposition'))->toBe('attachment; filename="productos_2026.csv"')
        ->and($respuesta->getHeader('content-type'))->toBe('text/csv; charset=utf-8');

    $lineas = explode("\n", trim(substr($respuesta->getBody(), 3)));

    expect(substr($respuesta->getBody(), 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($lineas)->toBe([
            'ID,Nombre,Activo,Precio,Alta',
            '4,"\'=HYPERLINK(""http://x"")",Sí,-5,',
            '2,"Té verde",Sí,80,2026-02-15',
            '1,"Café 100% arábica",Sí,120.5,2026-01-10',
        ])
        ->and($respuesta->isStreamed())->toBeFalse();
});

it('recorre por bloques con cursor() y deja hacer consultas a mitad', function (): void {
    $vistos = [];

    foreach (ListingProducto::query()->orderBy('id')->cursor(2) as $producto) {
        $vistos[] = $producto->id;
        ListingProducto::query()->count();
    }

    expect($vistos)->toBe([1, 2, 3, 4, 5])
        ->and(iterator_to_array($this->db->table('productos')->where('activo', false)->orderBy('id')->limit(1)->cursor(10), false))->toHaveCount(2);
});

it('Response::stream escribe al enviar y withBody lo reemplaza', function (): void {
    $respuesta = Response::stream(static function (): void {
        echo 'uno,';
        echo 'dos';
    }, 200, ['content-type' => 'text/plain']);

    ob_start();
    $respuesta->send();
    $enviado = ob_get_clean();

    expect($enviado)->toBe('uno,dos')
        ->and($respuesta->withBody('x')->getBody())->toBe('x');
});
