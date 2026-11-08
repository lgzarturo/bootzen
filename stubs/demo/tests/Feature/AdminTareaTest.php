<?php

declare(strict_types=1);

use App\Models\Tarea;
use App\Models\User;

/**
 * Datos válidos como los enviaría el formulario del panel.
 *
 * @return array<string, string>
 */
function adminTareaData(): array
{
    return [
        'titulo' => 'Ejemplo de titulo',
        'notas' => 'Texto de ejemplo',
        'hecha' => '1',
        'vence' => '2026-01-15',
    ];
}

it('solo deja entrar a administradores', function (): void {
    $client = client();

    $client->get('/admin/tareas')->assertRedirect('/login');
    $client->actingAs(User::factory()->create())->get('/admin/tareas')->assertForbidden();
    $client->actingAs(User::factory()->admin()->create())->get('/admin/tareas')->assertOk();
});

it('lista, busca y ordena', function (): void {
    $client = client();
    $tarea = Tarea::factory()->create();
    Tarea::factory()->count(3)->create();

    $client->actingAs(User::factory()->admin()->create())
        ->get('/admin/tareas?sort=id&dir=asc')
        ->assertOk()->assertSee($tarea->titulo);
});

it('crea, edita y borra desde el panel', function (): void {
    $client = client();
    $client->actingAs(User::factory()->admin()->create());

    $client->post('/admin/tareas', adminTareaData())->assertRedirect('/admin/tareas');
    $client->assertDatabaseCount('tareas', 1);

    $client->get('/admin/tareas/1/edit')->assertOk();
    $client->put('/admin/tareas/1', adminTareaData())->assertRedirect('/admin/tareas');

    $client->delete('/admin/tareas/1')->assertRedirect('/admin/tareas');
    $client->assertDatabaseCount('tareas', 0);
});

it('exporta a CSV', function (): void {
    $client = client();
    Tarea::factory()->count(2)->create();

    $csv = $client->actingAs(User::factory()->admin()->create())->get('/admin/tareas/exportar')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=utf-8')
        ->body();

    expect(explode("\n", trim($csv)))->toHaveCount(3);
});
