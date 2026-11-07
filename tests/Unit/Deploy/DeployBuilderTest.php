<?php

declare(strict_types=1);

use BootZen\Deploy\DeployBuilder;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-deploy');
    $files = [
        'app/Controllers/HomeController.php' => '<?php // controlador',
        'bootstrap/app.php' => '<?php // app',
        'bin/bootzen' => "#!/usr/bin/env php\n<?php",
        'config/app.php' => '<?php return [];',
        'database/migrations/2026_01_01_000000_crear.php' => '<?php // migración',
        'database/seeds/DatabaseSeeder.php' => '<?php // seeder',
        'database/app.sqlite' => 'datos reales',
        'database/.gitignore' => "*.sqlite\n",
        'framework/bootstrap.php' => '<?php // framework',
        'public/index.php' => '<?php // front controller',
        'public/assets/app.css' => 'body{}',
        'public/uploads/.gitignore' => "*\n",
        'public/uploads/.htaccess' => 'Require all denied',
        'public/uploads/foto-de-un-usuario.jpg' => 'jpg',
        'resources/views/inicio.php' => '<h1>{{ $titulo }}</h1>',
        'resources/views/components/card.php' => '<div>{{ $slot }}</div>',
        'resources/css/app.css' => '@import "tailwindcss";',
        'routes/web.php' => '<?php // rutas',
        'storage/logs/.gitignore' => "*\n",
        'storage/logs/app-2026-01-01.log' => 'log viejo',
        'storage/sessions/.gitignore' => "*\n",
        'storage/sessions/sess_abc' => 'sesión',
        'storage/cache/views/vieja.php' => 'vista vieja',
        'tests/Feature/HomeTest.php' => '<?php // test',
        'vendor/autoload.php' => '<?php // vendor',
        '.env' => 'APP_KEY=secreta',
        '.env.example' => 'APP_KEY=',
        '.htaccess' => 'RewriteEngine On',
        'composer.json' => '{}',
        'AGENTS.md' => '# guía',
    ];
    foreach ($files as $path => $contents) {
        writeFile($this->project . '/' . $path, $contents);
    }
    chmod($this->project . '/bin/bootzen', 0755);
});

afterEach(fn () => removeDir($this->project));

it('arma la carpeta del artefacto sin nada de desarrollo ni datos', function (): void {
    $result = (new DeployBuilder($this->project))->build('tienda', '1.2.0');
    $dir = $this->project . '/build/tienda-1.2.0';

    expect($result['dir'])->toBe($dir)
        ->and($result['views'])->toBe(2);

    foreach (['app/Controllers/HomeController.php', 'bootstrap/app.php', 'bin/bootzen', 'config/app.php', 'database/migrations/2026_01_01_000000_crear.php', 'database/seeds/DatabaseSeeder.php', 'framework/bootstrap.php', 'public/index.php', 'public/assets/app.css', 'public/uploads/.htaccess', 'resources/views/inicio.php', 'routes/web.php', 'storage/logs/.gitignore', '.env.example', '.htaccess', 'composer.json', 'BUILD'] as $path) {
        expect($dir . '/' . $path)->toBeFile();
    }

    foreach (['.env', 'database/app.sqlite', 'public/uploads/foto-de-un-usuario.jpg', 'resources/css/app.css', 'storage/logs/app-2026-01-01.log', 'storage/sessions/sess_abc', 'storage/cache/views/vieja.php', 'tests', 'vendor', 'AGENTS.md'] as $path) {
        expect(file_exists($dir . '/' . $path))->toBeFalse($path . ' no debería ir en el artefacto');
    }

    expect(is_executable($dir . '/bin/bootzen'))->toBeTrue()
        ->and(glob($dir . '/storage/cache/views/*.php'))->toHaveCount(2)
        ->and((string) file_get_contents($dir . '/BUILD'))->toContain('version=1.2.0');
});

it('comprime el artefacto conservando permisos y la carpeta raíz', function (): void {
    $archive = (new DeployBuilder($this->project))->build('tienda', '1.2.0')['archive'];

    expect($archive)->toEndWith('/build/tienda-1.2.0.zip');

    $zip = new ZipArchive();
    $zip->open($archive);
    $zip->getExternalAttributesName('tienda-1.2.0/bin/bootzen', $opsys, $attributes);

    expect($zip->locateName('tienda-1.2.0/public/index.php'))->not->toBeFalse()
        ->and($zip->locateName('tienda-1.2.0/.env'))->toBeFalse()
        ->and(($attributes >> 16) & 0777)->toBe(0755);

    $zip->close();
});

it('usa las vistas precompiladas aunque el proyecto viva en otra ruta', function (): void {
    $dir = (new DeployBuilder($this->project))->build('tienda', '1')['dir'];
    $server = makeTempDir('bootzen-servidor');
    exec('cp -a ' . escapeshellarg($dir) . '/. ' . escapeshellarg($server));
    $before = array_map('filemtime', glob($server . '/storage/cache/views/*.php') ?: []);

    $html = (new BootZen\View\View($server . '/resources/views', $server . '/storage/cache/views'))->render('inicio', ['titulo' => 'Hola']);

    expect($html)->toBe('<h1>Hola</h1>')
        ->and(array_map('filemtime', glob($server . '/storage/cache/views/*.php') ?: []))->toBe($before);

    removeDir($server);
});

it('reemplaza un artefacto anterior con la misma versión', function (): void {
    $builder = new DeployBuilder($this->project);
    $builder->build('tienda', '1');
    writeFile($this->project . '/build/tienda-1/sobrante.txt', 'x');

    $builder->build('tienda', '1');

    expect($this->project . '/build/tienda-1/sobrante.txt')->not->toBeFile();
});

it('rechaza nombres o versiones inválidos', function (string $name, string $version): void {
    (new DeployBuilder($this->project))->build($name, $version);
})->throws(InvalidArgumentException::class)->with([['../x', '1'], ['tienda', '1/../2'], ['', '1']]);
