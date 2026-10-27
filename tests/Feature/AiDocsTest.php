<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

it('mantiene la referencia compacta al día con el código', function () use ($root): void {
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/bootzen-reference') . ' --check 2>&1', $output, $status);

    expect($status)->toBe(0, implode("\n", $output) . "\nRegenera con: php bin/bootzen-reference");
});

it('tiene las mismas skills en .claude y .agents', function () use ($root): void {
    $claude = glob($root . '/skeleton/.claude/skills/*/SKILL.md') ?: [];

    expect($claude)->toHaveCount(3);

    foreach ($claude as $skill) {
        $mirror = str_replace('/.claude/', '/.agents/', $skill);
        $contents = (string) file_get_contents($skill);

        expect($mirror)->toBeFile()
            ->and(file_get_contents($mirror))->toBe($contents, basename(dirname($skill)) . ' difiere entre .claude y .agents')
            ->and($contents)->toMatch('/^---\nname: ' . preg_quote(basename(dirname($skill)), '/') . '\ndescription: .+\n---\n/');
    }
});

it('no tiene enlaces rotos en la guía, las recetas y llms.txt', function () use ($root): void {
    $files = [
        $root . '/skeleton/AGENTS.md',
        $root . '/skeleton/llms.txt',
        ...(glob($root . '/skeleton/docs/ai/recipes/*.md') ?: []),
        ...(glob($root . '/skeleton/.claude/skills/*/SKILL.md') ?: []),
    ];

    foreach ($files as $file) {
        preg_match_all('/\]\(([^)#]+)\)/', (string) file_get_contents($file), $links);

        foreach ($links[1] as $link) {
            if (! str_starts_with($link, 'http')) {
                expect(dirname($file) . '/' . $link)->toBeReadableFile();
            }
        }

        // Los archivos que la guía menciona entre comillas invertidas también existen
        preg_match_all('#`((?:docs/ai|\.claude/skills)/[^`*]+\.md)`#', (string) file_get_contents($file), $mentions);
        foreach ($mentions[1] as $mention) {
            expect($root . '/skeleton/' . $mention)->toBeFile();
        }
    }
});
