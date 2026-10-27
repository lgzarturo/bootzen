# Hosting compartido

Para hostings con panel (cPanel, Plesk, DirectAdmin, Hostinger y similares) donde
subes archivos y no tienes terminal. Si tu hosting sí da SSH, sigue estos pasos
pero ejecuta `php bin/bootzen migrate` en lugar del instalador web.

## Antes de empezar

En el panel, elige PHP 8.2 o superior y revisa que estén activas las extensiones
`pdo_sqlite`, `mbstring` y `fileinfo` (casi siempre lo están).

## 1. Arma el artefacto en tu máquina

```bash
php bin/bootzen deploy:build --version=1.0.0
```

Te deja `build/<app>-1.0.0.zip`.

## 2. Súbelo

Con el gestor de archivos del panel, sube el `.zip` y descomprímelo. Adentro hay
una carpeta `<app>-1.0.0/`; lo que importa es su contenido. Tienes dos opciones:

**A. Si el panel te deja elegir la carpeta raíz del dominio** (lo más limpio):
mueve el contenido a una carpeta fuera de `public_html`, por ejemplo
`/home/usuario/tienda`, y apunta el dominio a `/home/usuario/tienda/public`.

**B. Si no puedes cambiarla**: mueve el contenido directo a `public_html`. El
`.htaccess` de la raíz manda cada petición a `public/` y deja fuera de la web
`.env`, `database/`, `storage/`, `framework/` y `config/` (responden 404).
Necesita `mod_rewrite`, que todos los hostings con Apache o LiteSpeed tienen.

## 3. Crea el `.env`

En el gestor de archivos, copia `.env.example` como `.env` y edita:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
APP_KEY=base64:...        # una clave propia (ver docs/deploy/README.md)
INSTALL_ENABLED=true      # solo para el paso 4
```

Si el gestor no muestra archivos ocultos, busca la opción "mostrar archivos
ocultos" (dotfiles) en su configuración.

## 4. Instala desde el navegador

Abre `https://tu-dominio.com/_bootzen/instalar`, pega la `APP_KEY` y pulsa
**Instalar**. Crea la base SQLite y ejecuta las migraciones. La página se bloquea
sola (`storage/install.lock`) y desde ahí responde 404. Tiene un límite de 5
intentos cada 10 minutos.

## 5. Apaga el instalador

Pon `INSTALL_ENABLED=false` en `.env`. Listo: abre `https://tu-dominio.com/up`
y deberías ver `{"status":"ok"}`.

## Actualizar a una versión nueva

1. `php bin/bootzen deploy:build --version=1.1.0` en tu máquina.
2. Sube el `.zip`, descomprímelo y mueve el contenido de `<app>-1.1.0/` encima
   de lo que ya tienes, reemplazando archivos. No pisa `.env`, la base ni los
   archivos subidos porque el artefacto no los trae.
3. Si la versión trae migraciones nuevas: borra `storage/install.lock`, pon
   `INSTALL_ENABLED=true`, entra a `/_bootzen/instalar` (solo corre las
   pendientes) y vuelve a ponerlo en `false`.

No uses `config:cache` en un hosting sin terminal: tendrías que regenerarlo cada
vez que cambies `.env`, y sin terminal no puedes.

## Respaldos

Descarga `database/app.sqlite` desde el gestor de archivos de vez en cuando. Si
el panel tiene tareas programadas (cron), agrega una diaria:

```
php /home/usuario/tienda/bin/bootzen db:backup
```

Deja las copias en `storage/backups/`; bájalas a tu máquina cada tanto.

## Si algo falla

- **Error 500 o página en blanco**: revisa `storage/logs/app-<fecha>.log`. Si no
  hay log, `storage/` no tiene permiso de escritura (ponle 755 desde el panel).
- **Todo da 404 salvo la portada**: falta `mod_rewrite` o el hosting ignora el
  `.htaccess`. Pregúntale al soporte si permite `AllowOverride`.
- **"Falta la APP_KEY" en el instalador**: el `.env` no tiene `APP_KEY` o no se
  llama exactamente `.env`.
