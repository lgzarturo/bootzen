<?php

declare(strict_types=1);

use App\Models\User;

it('lista, busca y filtra usuarios por rol', function (): void {
    $client = client();
    $admin = User::factory()->admin()->create(['nombre' => 'Admin']);
    User::factory()->create(['nombre' => 'Ana López', 'email' => 'ana@example.com']);
    User::factory()->create(['nombre' => 'Beto Ruiz', 'email' => 'beto@example.com']);
    $client->actingAs($admin);

    $client->get('/admin')->assertRedirect('/admin/usuarios');
    $client->get('/admin/usuarios?q=ana')->assertOk()->assertSee('Ana López')->assertDontSee('Beto Ruiz');
    $client->get('/admin/usuarios?rol=admin')->assertSee('Admin')->assertDontSee('Ana López');
    $client->get('/admin/usuarios?rol=no-existe')->assertSee('Ana López')->assertSee('Beto Ruiz');
});

it('cambia el rol y la verificación de otro usuario', function (): void {
    $client = client();
    $ana = User::factory()->sinVerificar()->create();
    $client->actingAs(User::factory()->admin()->create());

    $client->put('/admin/usuarios/' . $ana->id, ['role' => 'admin', 'verificado' => '1'])
        ->assertRedirect('/admin/usuarios')
        ->assertSessionHas('status', 'Usuario actualizado.');

    $ana = User::findOrFail($ana->id);
    expect($ana->role)->toBe('admin')->and($ana->email_verified_at)->not->toBeNull();

    $client->from('/admin/usuarios/' . $ana->id . '/edit')->put('/admin/usuarios/' . $ana->id, ['role' => 'jefe'])->assertSessionHasErrors(['role']);
});

it('no deja que un admin se quite su propio rol', function (): void {
    $client = client();
    $admin = User::factory()->admin()->create();

    $client->actingAs($admin)->from('/admin/usuarios/' . $admin->id . '/edit')
        ->put('/admin/usuarios/' . $admin->id, ['role' => 'usuario', 'verificado' => '1'])
        ->assertSessionHasErrors(['role']);

    expect(User::findOrFail($admin->id)->role)->toBe('admin');
});

it('exporta usuarios sin la contraseña y muestra el enlace del panel solo a admins', function (): void {
    $client = client();
    $admin = User::factory()->admin()->create();

    $csv = $client->actingAs($admin)->get('/admin/usuarios/exportar')->assertOk()->body();

    expect($csv)->toContain('ID,Nombre,Correo,Rol')
        ->and($csv)->not->toContain('$argon')
        ->and($csv)->not->toContain('$2y$');
    $client->get('/tareas')->assertSee('href="/admin"', false);
    $client->actingAs(User::factory()->create())->get('/tareas')->assertDontSee('href="/admin"', false);
});
