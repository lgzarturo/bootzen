<?php

/**
 * Almacenamiento de archivos. Cada disco tiene un driver (local, s3, ftp o sftp), una visibilidad
 * (public: con URL pública; private: se entrega con temporary_url() o download()) y un 'prefix'
 * opcional (carpeta dentro del disco).
 *
 * Cambia el destino de toda la app desde .env: STORAGE_DISK para los privados (store()) y
 * STORAGE_PUBLIC_DISK para los públicos (storePublic()).
 */

declare(strict_types=1);

return [
    'default' => env('STORAGE_DISK', 'local'),
    'public' => env('STORAGE_PUBLIC_DISK', 'public'),

    'disks' => [
        // storage/app: privado, fuera de la web
        'local' => [
            'driver' => 'local',
            'root' => 'storage/app',
            'visibility' => 'private',
        ],

        // public/: storePublic() guarda en public/uploads y la URL sale de APP_URL
        'public' => [
            'driver' => 'local',
            'root' => 'public',
            'visibility' => 'public',
        ],

        // AWS S3 o compatibles: Cloudflare R2 (S3_REGION=auto), DigitalOcean Spaces, Backblaze B2,
        // Wasabi, MinIO (S3_PATH_STYLE=true) y Google Cloud Storage con claves HMAC
        's3' => [
            'driver' => 's3',
            'key' => env('S3_KEY'),
            'secret' => env('S3_SECRET'),
            'region' => env('S3_REGION', 'us-east-1'),
            'bucket' => env('S3_BUCKET'),
            'endpoint' => env('S3_ENDPOINT'),
            'path_style' => env('S3_PATH_STYLE', false),
            'visibility' => 'private',
        ],

        // Mismo proveedor para archivos públicos: otro bucket (o el mismo con una carpeta pública)
        // y su URL pública (dominio propio, CDN o la URL pública del bucket)
        's3-public' => [
            'driver' => 's3',
            'key' => env('S3_KEY'),
            'secret' => env('S3_SECRET'),
            'region' => env('S3_REGION', 'us-east-1'),
            'bucket' => env('S3_PUBLIC_BUCKET', env('S3_BUCKET')),
            'endpoint' => env('S3_ENDPOINT'),
            'path_style' => env('S3_PATH_STYLE', false),
            'url' => env('S3_PUBLIC_URL'),
            'visibility' => 'public',
        ],

        // FTP o FTPS (FTP_SSL=true). FTP_URL: dirección web si esa carpeta se sirve públicamente
        'ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'port' => env('FTP_PORT', 21),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            // Relativa a la carpeta del usuario (vacía = su carpeta); con / al inicio, absoluta
            'root' => env('FTP_ROOT', ''),
            'ssl' => env('FTP_SSL', true),
            'url' => env('FTP_URL'),
            'visibility' => env('FTP_URL') ? 'public' : 'private',
        ],

        // SFTP (necesita la extensión ssh2). Con contraseña o llave; SFTP_FINGERPRINT verifica el servidor
        'sftp' => [
            'driver' => 'sftp',
            'host' => env('SFTP_HOST'),
            'port' => env('SFTP_PORT', 22),
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),
            'private_key' => env('SFTP_PRIVATE_KEY'),
            'passphrase' => env('SFTP_PASSPHRASE'),
            'fingerprint' => env('SFTP_FINGERPRINT'),
            'root' => env('SFTP_ROOT', ''),
            'url' => env('SFTP_URL'),
            'visibility' => env('SFTP_URL') ? 'public' : 'private',
        ],
    ],
];
