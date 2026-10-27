<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;
use BootZen\Database\Migrator;
use BootZen\Database\Model;

/**
 * @property int $id
 * @property string $titulo
 * @property bool $fijada
 */
final class CrudTestNota extends Model
{
    protected static string $table = 'notas';

    protected static array $fillable = ['titulo', 'fijada'];

    protected static array $casts = ['fijada' => 'bool'];
}

final class CrudTestNotasController
{
    public function index(Request $request): Response
    {
        $notas = CrudTestNota::query()->orderBy('id')->paginate(2, $request->integer('page', 1));

        return $request->wantsJson() ? Response::json($notas) : view('notas.index', ['notas' => $notas]);
    }

    public function store(Request $request): Response
    {
        $nota = CrudTestNota::create($request->only(['titulo', 'fijada']));

        return redirect(route('notas.show', ['id' => $nota->id]));
    }

    public function show(int $id): Response
    {
        return view('notas.show', ['nota' => CrudTestNota::findOrFail($id)]);
    }

    public function update(Request $request, int $id): Response
    {
        CrudTestNota::findOrFail($id)->update($request->only(['titulo', 'fijada']));

        return redirect(route('notas.show', ['id' => $id]));
    }

    public function destroy(int $id): Response
    {
        CrudTestNota::findOrFail($id)->delete();

        return redirect(route('notas.index'));
    }
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-crud');
    $skeleton = dirname(__DIR__, 2) . '/skeleton';

    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]];");
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear_notas.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Migration;

        return new class () extends Migration {
            public function up(Connection $db): void
            {
                $db->exec('CREATE TABLE notas (id INTEGER PRIMARY KEY AUTOINCREMENT, titulo TEXT NOT NULL, fijada INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)');
            }
        };
        PHP);
    writeFile($this->project . '/resources/views/components/pagination.php', (string) file_get_contents($skeleton . '/resources/views/components/pagination.php'));
    writeFile($this->project . '/resources/views/notas/index.php', <<<'PHP'
        <ul>
        @foreach ($notas as $nota)
            <li>{{ $nota->titulo }}@if ($nota->fijada) (fijada)@endif</li>
        @empty
            <li>Sin notas</li>
        @endforeach
        </ul>
        <x-pagination :paginator="$notas" />
        PHP);
    writeFile($this->project . '/resources/views/notas/show.php', '<h1>{{ $nota->titulo }}</h1>');
    writeFile($this->project . '/resources/views/errors/404.php', '<h1>No existe: {{ $message }}</h1>');

    $this->app = new Application($this->project);
    $this->app->routes(static fn (Router $router) => $router->resource('notas', CrudTestNotasController::class, ['except' => ['create', 'edit']]));
    $this->app->make(Migrator::class)->run();

    $this->send = fn (string $method, string $uri, array $body = [], array $headers = []): Response
        => $this->app->run(new Request($method, $uri, $headers, $body));
});

afterEach(function (): void {
    removeDir($this->project);
});

it('crea, lista, muestra, actualiza y borra contra SQLite', function (): void {
    $creada = ($this->send)('POST', '/notas', ['titulo' => 'Comprar pan', 'fijada' => '1', 'id' => 99]);

    expect($creada->getStatusCode())->toBe(302)
        ->and($creada->getHeader('location'))->toBe('/notas/1')
        ->and(($this->send)('GET', '/notas/1')->getBody())->toBe('<h1>Comprar pan</h1>')
        ->and(($this->send)('GET', '/notas')->getBody())->toContain('Comprar pan (fijada)');

    $actualizada = ($this->send)('POST', '/notas/1', ['_method' => 'PUT', 'titulo' => 'Comprar pan integral', 'fijada' => '0']);

    expect($actualizada->getHeader('location'))->toBe('/notas/1')
        ->and(CrudTestNota::findOrFail(1)->titulo)->toBe('Comprar pan integral')
        ->and(CrudTestNota::findOrFail(1)->fijada)->toBeFalse();

    $borrada = ($this->send)('POST', '/notas/1', ['_method' => 'DELETE']);

    expect($borrada->getHeader('location'))->toBe('/notas')
        ->and(CrudTestNota::query()->count())->toBe(0)
        ->and(($this->send)('GET', '/notas')->getBody())->toContain('Sin notas');
});

it('responde 404 con la vista de error si el registro no existe', function (): void {
    $response = ($this->send)('GET', '/notas/99');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getBody())->toBe('<h1>No existe: Registro no encontrado.</h1>')
        ->and(($this->send)('POST', '/notas/99', ['_method' => 'DELETE'])->getStatusCode())->toBe(404);
});

it('pagina el listado en HTML y en JSON', function (): void {
    foreach (['Uno', 'Dos', 'Tres'] as $titulo) {
        CrudTestNota::create(['titulo' => $titulo]);
    }

    $pagina1 = ($this->send)('GET', '/notas')->getBody();
    $pagina2 = ($this->send)('GET', '/notas?page=2')->getBody();
    $json = json_decode(($this->send)('GET', '/notas?page=2', [], ['Accept' => 'application/json'])->getBody(), true);

    expect($pagina1)->toContain('Uno')->not->toContain('Tres')
        ->and($pagina1)->toContain('Página 1 de 2')
        ->and($pagina1)->toContain('href="?page=2"')
        ->and($pagina2)->toContain('Tres')
        ->and($pagina2)->toContain('href="?page=1"')
        ->and($json['total'])->toBe(3)
        ->and($json['data'][0]['titulo'])->toBe('Tres')
        ->and($json['data'][0]['fijada'])->toBeFalse();
});
