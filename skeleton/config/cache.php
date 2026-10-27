<?php

/**
 * Caché (cache()) y límites de peticiones (throttle). file guarda en storage/cache/data; array, en memoria.
 */

declare(strict_types=1);

return [
    'driver' => env('CACHE_DRIVER', 'file'),
];
