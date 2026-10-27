<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(config('app.name')) ?></title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { text-align: center; padding: 2rem; }
        h1 { font-size: 2.5rem; margin: 0 0 .5rem; }
        p { color: #475569; margin: 0; }
    </style>
</head>
<body>
    <main>
        <h1>Hola desde <?= e(config('app.name')) ?></h1>
        <p>Proyecto creado con BootZen. Empieza por <code>routes/web.php</code>.</p>
    </main>
</body>
</html>
