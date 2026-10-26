<?php

declare(strict_types=1);

namespace App\Controllers;

use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Página de inicio de la aplicación.
 */
class HomeController
{
    /**
     * Muestra la página de bienvenida.
     */
    public function index(Request $request): Response
    {
        // Temporal hasta que llegue el motor de vistas (fase 2): incluye la vista con output buffering
        ob_start();
        require base_path('resources/views/welcome.php');

        return Response::html((string) ob_get_clean());
    }
}
