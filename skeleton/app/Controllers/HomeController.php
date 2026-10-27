<?php

declare(strict_types=1);

namespace App\Controllers;

use BootZen\Core\Response;

/**
 * Página de inicio de la aplicación.
 */
class HomeController
{
    /**
     * Muestra la página de bienvenida (resources/views/welcome.php).
     */
    public function index(): Response
    {
        return view('welcome');
    }
}
