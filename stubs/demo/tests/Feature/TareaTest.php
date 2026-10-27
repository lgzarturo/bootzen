<?php

declare(strict_types=1);

use App\Models\Tarea;
use App\Models\User;
use BootZen\Auth\Hash;

/**
 * Usuario de prueba (créalo después de client(): cada client() trae una base nueva).
 */
function usuario(string $nombre = 'Ana'): User
{
    return User::create([
        'nombre' => $nombre,
        'email' => strtolower($nombre) . '@ejemplo.com',
        'password' => Hash::make('secreto-largo'),
    ]);
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
    Tarea::create(['user_id' => $ana->id, 'titulo' => 'Comprar pan']);
    Tarea::create(['user_id' => $beto->id, 'titulo' => 'Tarea de Beto']);

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
    $tarea = Tarea::create(['user_id' => $ana->id, 'titulo' => 'Comprar pan']);

    $client->actingAs($ana)->from('/tareas')->patch('/tareas/' . $tarea->id . '/completar')
        ->assertRedirect('/tareas')
        ->assertSessionHas('status', 'Tarea hecha.');
    expect(Tarea::findOrFail($tarea->id)->hecha)->toBeTrue();

    $client->patch('/tareas/' . $tarea->id . '/completar')->assertSessionHas('status', 'Tarea pendiente otra vez.');
    expect(Tarea::findOrFail($tarea->id)->hecha)->toBeFalse();
});

it('no deja ver ni tocar tareas ajenas', function (): void {
    $client = client();
    $ajena = Tarea::create(['user_id' => usuario('Beto')->id, 'titulo' => 'Privada']);
    $client->actingAs(usuario('Ana'));

    $client->get('/tareas/' . $ajena->id . '/edit')->assertNotFound();
    $client->put('/tareas/' . $ajena->id, ['titulo' => 'Robada'])->assertNotFound();
    $client->patch('/tareas/' . $ajena->id . '/completar')->assertNotFound();
    $client->delete('/tareas/' . $ajena->id)->assertNotFound();
    $client->assertDatabaseHas('tareas', ['titulo' => 'Privada', 'hecha' => 0]);
});

it('responde JSON paginado y filtra por estado', function (): void {
    $client = client();
    $ana = usuario();
    foreach (range(1, 7) as $i) {
        Tarea::create(['user_id' => $ana->id, 'titulo' => "Tarea {$i}", 'hecha' => $i <= 2]);
    }

    $client->actingAs($ana)->getJson('/tareas?per_page=3&page=2')
        ->assertOk()
        ->assertJson(['total' => 7, 'per_page' => 3, 'current_page' => 2, 'last_page' => 3])
        ->assertJsonCount(3, 'data');

    $client->getJson('/tareas?estado=hechas')->assertJson(['total' => 2])->assertJsonPath('data.0.hecha', true);
    $client->getJson('/tareas?estado=otra')->assertUnprocessable();
    $client->get('/tareas?estado=pendientes')->assertOk()->assertSee('Tarea 7')->assertDontSee('Tarea 1<');
});
