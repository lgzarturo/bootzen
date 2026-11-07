<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Router;
use BootZen\Middleware\StartSession;

final class LocaleFeatureController
{
    public function precios(): string
    {
        return __('Precios') . '|' . locale() . '|' . route('precios') . '|' . locale_url('es') . '|' . locale_url('en');
    }

    public function producto(int $id): string
    {
        return route('producto', ['id' => $id]) . "\n" . hreflang_links();
    }

    public function panel(): string
    {
        return __('Precios') . '|' . locale() . '|' . locale_url('en');
    }
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-locale');
    writeFile($this->project . '/config/app.php', "<?php return ['locale' => 'es', 'locales' => 'es,en', 'url' => 'https://tienda.mx', 'key' => 'base64:x'];");
    writeFile($this->project . '/config/session.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/lang/en.php', "<?php return ['Precios' => 'Pricing'];");

    $this->app = new Application($this->project);
    $this->app->routes(static function (Router $router): void {
        $router->localized(static function (Router $router): void {
            $router->get('/precios', [LocaleFeatureController::class, 'precios'])->name('precios');
            $router->get('/productos/{id}', [LocaleFeatureController::class, 'producto'])->name('producto');
        });

        $router->group(['middleware' => [StartSession::class, 'locale']], static function (Router $router): void {
            $router->get('/panel', [LocaleFeatureController::class, 'panel'])->name('panel');
        });
    });
    $this->get = fn (string $uri, array $headers = []): string => $this->app->run(new Request('GET', $uri, $headers))->getBody();
});

afterEach(fn () => removeDir($this->project));

it('sirve el idioma por defecto sin prefijo y los demás con el suyo', function (): void {
    expect(($this->get)('/precios'))->toBe('Precios|es|/precios|/precios|/en/precios')
        ->and(($this->get)('/en/precios'))->toBe('Pricing|en|/en/precios|/precios|/en/precios')
        ->and($this->app->run(new Request('GET', '/en/precios'))->getHeader('content-language'))->toBe('en')
        // La siguiente petición vuelve al idioma por defecto
        ->and(($this->get)('/precios'))->toBe('Precios|es|/precios|/precios|/en/precios')
        ->and($this->app->run(new Request('GET', '/fr/precios'))->getStatusCode())->toBe(404);
});

it('genera los enlaces hreflang de cada idioma con la URL completa', function (): void {
    expect(($this->get)('/en/productos/7'))->toBe(
        "/en/productos/7\n"
        . '<link rel="alternate" hreflang="es" href="https://tienda.mx/productos/7">' . "\n"
        . '<link rel="alternate" hreflang="en" href="https://tienda.mx/en/productos/7">' . "\n"
        . '<link rel="alternate" hreflang="x-default" href="https://tienda.mx/productos/7">'
    );
});

it('sin prefijo usa el navegador, ?lang= y lo recuerda en la sesión', function (): void {
    expect(($this->get)('/panel'))->toBe('Precios|es|/panel?lang=en')
        ->and(($this->get)('/panel', ['Accept-Language' => 'fr-CA,en-US;q=0.8,es;q=0.5']))->toStartWith('Pricing|en')
        ->and(($this->get)('/panel', ['Accept-Language' => 'de']))->toStartWith('Precios|es');

    $response = $this->app->run(new Request('GET', '/panel?lang=en'));
    $cookies = array_map(static fn (array $cookie): string => (string) $cookie['value'], $response->getCookies());

    expect($response->getBody())->toStartWith('Pricing|en')
        ->and($cookies)->not->toBe([])
        ->and($this->app->run(new Request('GET', '/panel', [], [], [], [], $cookies))->getBody())->toStartWith('Pricing|en');
});

it('muestra los errores en el idioma del prefijo aunque ninguna ruta coincida', function (): void {

    expect(($this->get)('/en/no-existe'))->toContain('lang="en"')
        ->and(($this->get)('/no-existe'))->toContain('lang="es"')
        ->and(locale())->toBe('es');
});
