<?php

declare(strict_types=1);

use BootZen\Deploy\FrameworkUpdater;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-proyecto');
    $this->home = makeTempDir('bootzen-home');

    writeFile($this->project . '/framework/bootstrap.php', '<?php // 2.0.0');
    writeFile($this->project . '/framework/src/Viejo.php', '<?php // se borra en 2.1');
    writeFile($this->project . '/framework/VERSION', "2.0.0\n");
    FrameworkUpdater::writeManifest($this->project . '/framework', '2.0.0');

    writeFile($this->home . '/VERSION', "2.1.0\n");
    writeFile($this->home . '/framework/bootstrap.php', '<?php // 2.1.0');
    writeFile($this->home . '/framework/src/Nuevo.php', '<?php // nuevo');

    $this->updater = new FrameworkUpdater($this->project);
});

afterEach(function (): void {
    removeDir($this->project);
    removeDir($this->home);
});

it('escribe el manifiesto y detecta cambios locales', function (): void {
    $manifest = json_decode((string) file_get_contents($this->project . '/framework/MANIFEST.json'), true);

    expect($manifest['version'])->toBe('2.0.0')
        ->and(array_keys($manifest['files']))->toBe(['VERSION', 'bootstrap.php', 'src/Viejo.php'])
        ->and($this->updater->localChanges())->toBe([]);

    file_put_contents($this->project . '/framework/bootstrap.php', '<?php // editado a mano');
    writeFile($this->project . '/framework/src/Propio.php', '<?php');
    unlink($this->project . '/framework/src/Viejo.php');

    expect($this->updater->localChanges())->toBe(['bootstrap.php', 'src/Propio.php', 'src/Viejo.php']);
});

it('actualiza, deja respaldo y un manifiesto nuevo', function (): void {
    $result = $this->updater->update($this->home);

    expect($result['from'])->toBe('2.0.0')
        ->and($result['to'])->toBe('2.1.0')
        ->and($result['backup'])->toStartWith('storage/backups/framework-2.0.0-')
        ->and((string) file_get_contents($this->project . '/framework/bootstrap.php'))->toContain('2.1.0')
        ->and($this->project . '/framework/src/Nuevo.php')->toBeFile()
        ->and($this->project . '/framework/src/Viejo.php')->not->toBeFile()
        ->and(trim((string) file_get_contents($this->project . '/framework/VERSION')))->toBe('2.1.0')
        ->and($this->updater->currentVersion())->toBe('2.1.0')
        ->and($this->updater->localChanges())->toBe([])
        ->and($this->project . '/' . $result['backup'] . '/src/Viejo.php')->toBeFile();
});

it('no hace nada si ya está al día', function (): void {
    file_put_contents($this->home . '/VERSION', "2.0.0\n");

    expect($this->updater->update($this->home)['backup'])->toBeNull()
        ->and($this->project . '/framework/src/Viejo.php')->toBeFile();
});

it('no pisa cambios locales ni baja de versión sin --force', function (): void {
    file_put_contents($this->project . '/framework/bootstrap.php', '<?php // editado');

    expect(fn () => $this->updater->update($this->home))->toThrow(RuntimeException::class, "Hay cambios locales en framework/ que se perderían:\n  - bootstrap.php")
        ->and($this->updater->update($this->home, true)['to'])->toBe('2.1.0');

    file_put_contents($this->home . '/VERSION', "1.9.0\n");

    expect(fn () => $this->updater->update($this->home))->toThrow(RuntimeException::class, 'es anterior a la del proyecto (2.1.0)');
});

it('explica qué hacer si no hay instalación local', function (): void {
    $this->updater->update($this->home . '/no-existe');
})->throws(RuntimeException::class, 'No hay una instalación de BootZen');

it('actualiza aunque el proyecto no tenga manifiesto (proyectos viejos)', function (): void {
    unlink($this->project . '/framework/MANIFEST.json');

    expect($this->updater->localChanges())->toBeNull()
        ->and($this->updater->update($this->home)['to'])->toBe('2.1.0');
});
