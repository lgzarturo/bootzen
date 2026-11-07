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
   $request->validate(['foto' => 'nullable|image|max:5120']); // KB

   $foto = $request->file('foto');
   if ($foto !== null) {
       $cliente->update(['foto' => $foto->resize(1600)->storePublic('clientes')]); // 'uploads/clientes/3f9c...jpg'
   }
   ```

4. Muéstrala con `file_url()` (funciona igual en disco, S3 o FTP):

   ```php
   @if ($cliente->foto)
       <img src="{{ file_url($cliente->foto) }}" alt="Foto de {{ $cliente->nombre }}" class="h-16 w-16 rounded-full object-cover">
   @endif
   ```

## Público o privado

| Método                    | Disco (por defecto)                  | Cuándo                                   |
| ------------------------- | ------------------------------------ | ---------------------------------------- |
| `storePublic('carpeta')`  | `STORAGE_PUBLIC_DISK` (`public/uploads/`) | Imágenes que cualquiera puede ver    |
| `store('carpeta')`        | `STORAGE_DISK` (`storage/app/`)      | Contratos, facturas: solo con permiso    |

Las dos guardan la ruta, no la URL: guarda lo que devuelven en la base. Para
otro disco: `store('contratos', 's3')` o `storePublic('fotos', 's3-public')`.

Un archivo privado se entrega de dos formas:

```php
// 1. Desde un controlador que revisa permisos (descarga directa)
public function descargar(int $id): Response
{
    $factura = Factura::where('user_id', auth()->id())->where('id', $id)->firstOrFail();

    return storage()->download($factura->archivo, 'factura-' . $factura->id . '.pdf');
}

// 2. Con un enlace que vence (revisa permisos antes de mostrarlo)
<a href="{{ temporary_url($factura->archivo, 10) }}">Descargar factura</a>
```

En S3 la URL temporal es prefirmada por el proveedor; en disco local, FTP y
SFTP pasa por `/_bootzen/archivo`, firmada con la `APP_KEY`.

## Imágenes

Con la extensión `gd`, antes de guardar:

```php
$foto = $request->file('foto');
$producto->update([
    'foto' => $foto->resize(1600)->storePublic('productos'),            // cabe en 1600 px de ancho, sin agrandar
    'miniatura' => $foto->cover(300, 300)->storePublic('productos/mini'), // recorta al centro
]);
```

`resize()` y `cover()` aplican la orientación de la cámara y quitan los
metadatos EXIF (incluida la ubicación GPS). Rechazan imágenes de más de 40
megapíxeles. Un GIF animado queda en su primer cuadro.

## Cambiar el destino (S3, R2, FTP, SFTP)

Sin tocar el código: en `.env` cambia los discos por defecto y completa sus
variables (lista completa en `config/storage.php`):

```dotenv
STORAGE_DISK=s3                 # privados (store)
STORAGE_PUBLIC_DISK=s3-public   # públicos (storePublic)
S3_KEY=...
S3_SECRET=...
S3_BUCKET=tienda-privado
S3_PUBLIC_BUCKET=tienda-publico
S3_PUBLIC_URL=https://archivos.tienda.mx
S3_REGION=auto                  # Cloudflare R2; en AWS, la región del bucket
S3_ENDPOINT=https://<cuenta>.r2.cloudflarestorage.com
```

`php bin/bootzen doctor` prueba la conexión. FTP necesita la extensión `ftp`
y SFTP la extensión `ssh2` (configura `SFTP_FINGERPRINT` en producción).

Para usar un disco directamente:

```php
storage('s3')->put('reportes/marzo.csv', $csv);
storage('s3')->get('reportes/marzo.csv');
storage('s3')->delete($producto->foto);
storage()->exists($ruta);
```

El nombre en disco siempre es aleatorio y la extensión sale del contenido
real (un `.php` disfrazado de imagen se guarda como `.bin`). Los discos
públicos rechazan `php`, `html`, `svg`, `js` y `.htaccess`. Al reemplazar
un archivo, borra el anterior: `storage((string) config('storage.public'))->delete($cliente->foto)`.

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
final del test si molestan (`storage('public')->delete($ruta)`).

## Errores comunes

- Olvidar `enctype="multipart/form-data"`: el archivo nunca llega.
- Usar el nombre original como ruta (`$foto->clientName()`): solo para mostrarlo.
- Guardar documentos privados con `storePublic`: quedan accesibles por URL.
- `move_uploaded_file` a mano: usa `store()` o `storePublic()`.
- Guardar la URL en la base en lugar de la ruta: si cambias de disco, las
  URLs viejas se rompen. Guarda la ruta y usa `file_url()`.
- Instalar un SDK de AWS o Flysystem: los discos `s3`, `ftp` y `sftp` ya
  vienen en BootZen.
