<?php

declare(strict_types=1);

use App\Models\Tarea;
use App\Models\User;

/**
 * Usuario de prueba (créalo después de client(): cada client() trae una base nueva).
 */
function usuario(string $nombre = 'Ana'): User
{
    return User::factory()->create(['nombre' => $nombre]);
}

it('manda a los invitados al login', function (): void {
    $client = client();

    $client->get('/tareas')->assertRedirect('/login');
    $client->getJson('/tareas')->assertUnauthorized();
});

it('lista solo las tareas del usuario', function (): void {
    $client = client();
    $ana = usuario('Ana');
    $beto = usuario('Beto');
    Tarea::factory()->create(['user_id' => $ana->id, 'titulo' => 'Comprar pan']);
    Tarea::factory()->create(['user_id' => $beto->id, 'titulo' => 'Tarea de Beto']);

    $client->actingAs($ana)->get('/tareas')
        ->assertOk()
        ->assertSee('Comprar pan')
        ->assertDontSee('Tarea de Beto')
        ->assertSee('data-modal-open="borrar-1"', false);
});

it('crea, edita y borra una tarea', function (): void {
    $client = client();
    $ana = usuario();
    $client->actingAs($ana);

    $client->post('/tareas', ['titulo' => 'Pagar la luz', 'vence' => '2026-12-01', 'hecha' => '0'])
        ->assertRedirect('/tareas')
        ->assertSessionHas('status', 'Tarea creada.');
    $client->assertDatabaseHas('tareas', ['titulo' => 'Pagar la luz', 'user_id' => $ana->id, 'hecha' => 0]);

    $client->get('/tareas/1/edit')->assertOk()->assertSee('value="2026-12-01"', false);
    $client->put('/tareas/1', ['titulo' => 'Pagar el agua', 'hecha' => '1'])->assertRedirect('/tareas');
    $client->assertDatabaseHas('tareas', ['titulo' => 'Pagar el agua', 'hecha' => 1]);

    $client->delete('/tareas/1')->assertRedirect('/tareas');
    $client->assertDatabaseCount('tareas', 0);
});

it('valida el título', function (): void {
    $client = client();
    $client->actingAs(usuario())->from('/tareas/create')->post('/tareas', ['titulo' => ''])
        ->assertRedirect('/tareas/create')
        ->assertSessionHasErrors(['titulo']);

    $client->assertDatabaseCount('tareas', 0);
});

it('marca y desmarca una tarea como hecha', function (): void {
    $client = client();
    $ana = usuario();
    $tarea = Tarea::factory()->create(['user_id' => $ana->id, 'hecha' => false]);

    $client->actingAs($ana)->from('/tareas')->patch('/tareas/' . $tarea->id . '/completar')
        ->assertRedirect('/tareas')
        ->assertSessionHas('status', 'Tarea hecha.');
    expect(Tarea::findOrFail($tarea->id)->hecha)->toBeTrue();

    $client->patch('/tareas/' . $tarea->id . '/completar')->assertSessionHas('status', 'Tarea pendiente otra vez.');
    expect(Tarea::findOrFail($tarea->id)->hecha)->toBeFalse();
});

it('no deja ver ni tocar tareas ajenas', function (): void {
    $client = client();
    $ajena = Tarea::factory()->create(['titulo' => 'Privada', 'hecha' => false]);
    $client->actingAs(usuario('Ana'));

    $client->get('/tareas/' . $ajena->id . '/edit')->assertNotFound();
    $client->put('/tareas/' . $ajena->id, ['titulo' => 'Robada'])->assertNotFound();
    $client->patch('/tareas/' . $ajena->id . '/completar')->assertNotFound();
    $client->delete('/tareas/' . $ajena->id)->assertNotFound();
    $client->assertDatabaseHas('tareas', ['titulo' => 'Privada', 'hecha' => 0]);
});

it('deja al admin corregir tareas ajenas (TareaPolicy y el rol con *)', function (): void {
    $client = client();
    $ajena = Tarea::factory()->create(['titulo' => 'Con error']);
    $admin = User::factory()->admin()->create();

    expect($admin->can('update', $ajena))->toBeTrue()
        ->and(usuario('Beto')->can('update', $ajena))->toBeFalse();

    $client->actingAs($admin)->get('/tareas/' . $ajena->id . '/edit')->assertOk();
    $client->put('/tareas/' . $ajena->id, ['titulo' => 'Corregida'])->assertRedirect('/tareas');
    $client->assertDatabaseHas('tareas', ['id' => $ajena->id, 'titulo' => 'Corregida']);
});

it('responde JSON paginado y filtra por estado', function (): void {
    $client = client();
    $ana = usuario();
    Tarea::factory()->count(7)
        ->state(fn (array $valores, int $i): array => ['titulo' => 'Tarea ' . ($i + 1), 'hecha' => $i < 2])
        ->create(['user_id' => $ana->id]);

    $client->actingAs($ana)->getJson('/tareas?per_page=3&page=2')
        ->assertOk()
        ->assertJson(['total' => 7, 'per_page' => 3, 'current_page' => 2, 'last_page' => 3])
        ->assertJsonCount(3, 'data');

    $client->getJson('/tareas?estado=hechas')->assertJson(['total' => 2])->assertJsonPath('data.0.hecha', true);
    $client->getJson('/tareas?estado=otra')->assertUnprocessable();
    $client->get('/tareas?estado=pendientes')->assertOk()->assertSee('Tarea 7')->assertDontSee('Tarea 1<');
});

it('maneja las tareas en inglés con las URLs traducidas', function (): void {
    $client = client();
    $client->actingAs(usuario());

    $client->from('/en/tasks/create')->post('/en/tasks', ['titulo' => ''])->assertRedirect('/en/tasks/create');
    $client->get('/en/tasks/create')->assertSee('The title field is required.')->assertSee('New task');

    $client->post('/en/tasks', ['titulo' => 'Pay the bills', 'vence' => '2026-12-01'])
        ->assertRedirect('/en/tasks')
        ->assertSessionHas('status', 'Task created.');
    $client->get('/en/tasks')->assertSee('My tasks')->assertSee('Due ')->assertSee('Delete this task?')
        ->assertSee('action="/en/tasks/1/toggle"', false);
    $client->get('/en/tareas')->assertStatus(301)->assertRedirect('/en/tasks');
});

it('el seeder crea a Ana con 50 tareas', function (): void {
    $client = client();
    $runner = $client->app->make(BootZen\Database\SeederRunner::class);
    assert($runner instanceof BootZen\Database\SeederRunner);
    $runner->run();

    $ana = User::where('email', 'ana@ejemplo.com')->firstOrFail();
    $client->assertDatabaseCount('tareas', 50);
    $client->actingAs($ana)->getJson('/tareas')->assertJson(['total' => 50]);
});
