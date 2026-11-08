<?php

/**
 * Panel de administración (make:admin). Las rutas están en el grupo /admin de routes/web.php.
 */

declare(strict_types=1);

return [
    // Enlaces de la barra lateral, en este orden. La etiqueta se traduce con __()
    'menu' => [
        ['route' => 'admin.usuarios.index', 'label' => 'Usuarios'],
        ['route' => 'admin.tareas.index', 'label' => 'Tareas'],
        // make:admin agrega aquí cada recurso
    ],
];
