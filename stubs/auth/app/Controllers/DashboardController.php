<?php

declare(strict_types=1);

namespace App\Controllers;

use BootZen\Core\Response;

/**
 * Página principal para usuarios autenticados.
 */
final class DashboardController
{
    public function index(): Response
    {
        return view('dashboard', ['user' => auth()->user()]);
    }
}
