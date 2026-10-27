# Subir archivos

**Cuándo:** foto de perfil, adjuntos, documentos.

## Pasos

1. Columna para la ruta: `ALTER TABLE clientes ADD COLUMN foto TEXT`
   (`migracion.md`). Agrégala a `$fillable` y al `@property` del modelo.
2. Formulario con `enctype` y un input de archivo:

   ```php
   <x-form :action="route('clientes.update', ['id' => $cliente->id])" method="PUT" enctype="multipart/form-data">
       <x-input name="foto" type="file" label="Foto" accept="image/*" />
       <x-button type="submit">Guardar</x-button>
   </x-form>
   ```

3. Valida y guarda. `file()` devuelve un `UploadedFile` (o `null` si no
   llegó archivo):

   ```php
   $request->validate(['foto' => 'nullable|image|max:2048']); // KB

   $foto = $request->file('foto');
   if ($foto !== null) {
       $cliente->update(['foto' => $foto->storePublic('clientes')]); // 'uploads/clientes/3f9c...jpg'
   }
   ```

4. Muéstrala con `asset()`:

   ```php
   @if ($cliente->foto)
       <img src="{{ asset($cliente->foto) }}" alt="Foto de {{ $cliente->nombre }}" class="h-16 w-16 rounded-full object-cover">
   @endif
   ```

## Público o privado

| Método                    | Dónde queda                   | Cuándo                                   |
| ------------------------- | ----------------------------- | ---------------------------------------- |
| `storePublic('carpeta')`  | `public/uploads/carpeta/`     | Imágenes que cualquiera puede ver        |
| `store('carpeta')`        | `storage/app/carpeta/`        | Contratos, facturas: solo con permiso    |

Un archivo privado se entrega desde un controlador que primero revisa
permisos:

```php
public function descargar(int $id): Response
{
    $factura = Factura::where('user_id', auth()->id())->where('id', $id)->firstOrFail();

    return download(storage_path('app/' . $factura->archivo), 'factura-' . $factura->id . '.pdf');
}
```

El nombre en disco siempre es aleatorio y la extensión sale del contenido
real (un `.php` disfrazado de imagen se guarda como `.bin`). `storePublic`
rechaza `html`, `svg` y `js`. Para borrar el anterior al reemplazarlo:
`@unlink(public_path($cliente->foto))` antes de guardar el nuevo.

## Test

```php
use BootZen\Testing\TestClient;

it('sube la foto del cliente', function (): void {
    $client = client();
    $cliente = Cliente::create([...]);

    $client->put('/clientes/' . $cliente->id, ['nombre' => 'Ana', 'foto' => TestClient::image('foto.png')])
        ->assertRedirect('/clientes/' . $cliente->id);

    expect(Cliente::findOrFail($cliente->id)->foto)->toStartWith('uploads/clientes/');
});

it('rechaza archivos que no son imágenes', function (): void {
    $client = client();
    $cliente = Cliente::create([...]);

    $client->from('/clientes/' . $cliente->id . '/edit')
        ->put('/clientes/' . $cliente->id, ['nombre' => 'Ana', 'foto' => TestClient::file('virus.png', '<?php echo 1;')])
        ->assertSessionHasErrors(['foto']);
});
```

Los archivos que guardan los tests quedan en `public/uploads`; bórralos al
final del test si molestan (`@unlink(public_path($ruta))`).

## Errores comunes

- Olvidar `enctype="multipart/form-data"`: el archivo nunca llega.
- Usar el nombre original como ruta (`$foto->clientName()`): solo para mostrarlo.
- Guardar documentos privados con `storePublic`: quedan accesibles por URL.
- `move_uploaded_file` a mano: usa `store()` o `storePublic()`.
