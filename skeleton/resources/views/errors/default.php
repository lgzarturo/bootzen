<?php
/**
 * Página de error genérica. Variables: $status (int), $title (string), $message (string).
 * Para una página propia de un código, crea errors/{codigo}.php (ver 404.php).
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($status) ?> · <?= e($title) ?></title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { text-align: center; padding: 2rem; max-width: 32rem; }
        .status { font-size: 4rem; font-weight: 700; margin: 0; color: #64748b; }
        h1 { font-size: 1.5rem; margin: .5rem 0; }
        p { color: #475569; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <main>
        <p class="status"><?= e($status) ?></p>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <p><a href="/">Volver al inicio</a></p>
    </main>
</body>
</html>
