#!/usr/bin/env php
<?php

/**
 * Script para generar archivos base de Controller, Model, Service, Migration y Test.
 *
 * Uso:
 *   php scripts/make.php [controller|model|service|migration|test] NombreClase
 *
 * Parámetros:
 *   controller|model|service|migration|test   Tipo de archivo a generar
 *   NombreClase                               Nombre base para la clase
 *
 * Resultado:
 *   Crea el archivo correspondiente en la ruta adecuada con la estructura básica.
 */

declare(strict_types=1);

$type = $argv[1] ?? null;
$name = $argv[2] ?? null;

if (!$type || !$name) {
    echo "Parámetros insuficientes.\n";
    echo "Uso: php scripts/make.php [controller|model|service|migration|test] NombreClase\n";
    echo "Ejemplo: php scripts/make.php controller User\nEste comando crea src/Controllers/UserController.php\n";
    exit(1);
}

$templates = [
    'controller' => 'Controller',
    'model' => 'Model',
    'service' => 'Service',
    'migration' => 'Migration',
    'test' => 'Test',
];

if (!isset($templates[$type])) {
    echo "Tipo inválido. Use: controller, model, service, migration o test\n";
    exit(1);
}

$className = getResilientClassName($name, $templates[$type]);
$namespace = match($type) {
    'controller' => 'BootZen\\Controllers',
    'model' => 'BootZen\\Models',
    'service' => 'BootZen\\Services',
    'migration' => 'BootZen\\Database\\Migrations',
    'test' => 'Tests\\Feature',
};

$path = match($type) {
    'controller' => "src/Controllers/{$className}.php",
    'model' => "src/Models/{$className}.php",
    'service' => "src/Services/{$className}.php",
    'migration' => "src/Database/Migrations/" . date('Y_m_d_His') . "_{$name}.php",
    'test' => "tests/Feature/{$className}.php",
};

$content = match($type) {
    'controller' => generateController($className, $namespace),
    'model' => generateModel($className, $namespace, $name),
    'service' => generateService($className, $namespace),
    'migration' => generateMigration($name),
    'test' => generateTest($className, $name),
};

file_put_contents($path, $content);
echo "✅ {$className} creado en {$path}\n";

/**
 * Genera el nombre de clase evitando duplicar el sufijo del tipo.
 * Por ejemplo, si el nombre ya termina con Controller, no agrega Controller de nuevo.
 *
 * @param string $name Nombre base proporcionado por el usuario.
 * @param string $suffix Sufijo del tipo (Controller, Model, etc).
 * @return string Nombre de clase resiliente.
 */
function getResilientClassName(string $name, string $suffix): string {
    $ucName = ucfirst($name);
    if (str_ends_with($ucName, $suffix)) {
        return $ucName;
    }
    return $ucName . $suffix;
}

/**
 * Genera el contenido de un Controller base.
 *
 * @param string $className Nombre de la clase Controller.
 * @param string $namespace Namespace de la clase.
 * @return string Código PHP del Controller.
 */
function generateController($className, $namespace) {
    // TODO: Crear métodos CRUD básicos, un controller más completo, de momento solo es un esqueleto.
    return <<<PHP
<?php

namespace {$namespace};

use BootZen\Core\Controller;
use BootZen\Core\Request;
use BootZen\Core\Response;

class {$className} extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request \$request): Response
    {
        \$data = \$this->cache->remember('items_list', 3600, function() {
            // Fetch from database
            return [];
        });

        return \$this->view('index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request \$request): Response
    {
        return \$this->view('create');
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request \$request): Response
    {
        \$validated = \$request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            \$this->db->beginTransaction();

            // Insert logic here

            \$this->db->commit();
            \$this->cache->forget('items_list');

            return \$this->redirect('/')->with('success', 'Item created successfully');
        } catch (\Exception \$e) {
            \$this->db->rollBack();
            return \$this->back()->with('error', 'Failed to create item');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request \$request, int \$id): Response
    {
        \$item = \$this->cache->remember("item_{\$id}", 3600, function() use (\$id) {
            \$stmt = \$this->db->prepare("SELECT * FROM items WHERE id = ?");
            \$stmt->execute([\$id]);
            return \$stmt->fetch();
        });

        if (!item) {
            return \$this->notFound();
        }

        return \$this->view('show', compact('item'));
    }

    /**
     * Update the specified resource.
     */
    public function update(Request \$request, int \$id): Response
    {
        \$validated = \$request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            \$this->db->beginTransaction();

            \$stmt = \$this->db->prepare("UPDATE items SET name = ?, updated_at = NOW() WHERE id = ?");
            \$stmt->execute([\$validated['name'], \$id]);

            \$this->db->commit();
            \$this->cache->forget("item_{\$id}");
            \$this->cache->forget('items_list');

            return \$this->json(['success' => true, 'message' => 'Updated successfully']);
        } catch (\Exception \$e) {
            \$this->db->rollBack();
            return \$this->json(['success' => false, 'message' => 'Update failed'], 500);
        }
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(Request \$request, int \$id): Response
    {
        try {
            \$stmt = \$this->db->prepare("DELETE FROM items WHERE id = ?");
            \$stmt->execute([\$id]);

            \$this->cache->forget("item_{\$id}");
            \$this->cache->forget('items_list');

            return \$this->json(['success' => true, 'message' => 'Deleted successfully']);
        } catch (\Exception \$e) {
            return \$this->json(['success' => false, 'message' => 'Delete failed'], 500);
        }
    }
}
PHP;
}

/**
 * Genera el contenido de un Model base.
 *
 * @param string $className Nombre de la clase Model.
 * @param string $namespace Namespace de la clase.
 * @param string $name Nombre base para la tabla.
 * @return string Código PHP del Model.
 */
function generateModel($className, $namespace, $name) {
    $table = strtolower($name) . 's';
    // TODO: Crear un Entidad con lo básico, de momento solo es un esqueleto, pensando en el multi-tenant.
    return <<<PHP
<?php

namespace {$namespace};

use BootZen\Core\Model;

class {$className} extends Model
{
    protected string \$table = '{$table}';

    protected array \$fillable = [
        'name',
        'description',
        'tenant_id',
    ];

    protected array \$hidden = [
        'password',
    ];

    protected array \$casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all records with caching
     */
    public function all(): array
    {
        return \$this->cache->remember("{\$this->table}_all", 3600, function() {
            \$stmt = \$this->db->query("SELECT * FROM {\$this->table} WHERE tenant_id = ?");
            \$stmt->execute([\$this->getTenantId()]);
            return \$stmt->fetchAll();
        });
    }

    /**
     * Find record by ID
     */
    public function find(int \$id): ?array
    {
        return \$this->cache->remember("{\$this->table}_{\$id}", 3600, function() use (\$id) {
            \$stmt = \$this->db->prepare("SELECT * FROM {\$this->table} WHERE id = ? AND tenant_id = ?");
            \$stmt->execute([\$id, \$this->getTenantId()]);
            return \$stmt->fetch() ?: null;
        });
    }

    /**
     * Create new record
     */
    public function create(array \$data): int
    {
        \$data['tenant_id'] = \$this->getTenantId();
        \$data['created_at'] = date('Y-m-d H:i:s');
        \$data['updated_at'] = date('Y-m-d H:i:s');

        \$columns = array_keys(\$data);
        \$values = array_map(fn(\$col) => ":\$col", \$columns);

        \$sql = "INSERT INTO {\$this->table} (" . implode(', ', \$columns) . ")
                VALUES (" . implode(', ', \$values) . ")";

        \$stmt = \$this->db->prepare(\$sql);
        \$stmt->execute(\$data);

        \$this->clearCache();

        return (int) \$this->db->lastInsertId();
    }

    /**
     * Update record
     */
    public function update(int \$id, array \$data): bool
    {
        \$data['updated_at'] = date('Y-m-d H:i:s');

        \$sets = array_map(fn(\$col) => "\$col = :\$col", array_keys(\$data));

        \$sql = "UPDATE {\$this->table} SET " . implode(', ', \$sets) . "
                WHERE id = :id AND tenant_id = :tenant_id";

        \$data['id'] = \$id;
        \$data['tenant_id'] = \$this->getTenantId();

        \$stmt = \$this->db->prepare(\$sql);
        \$result = \$stmt->execute(\$data);

        \$this->clearCache(\$id);

        return \$result;
    }

    /**
     * Delete record
     */
    public function delete(int \$id): bool
    {
        \$stmt = \$this->db->prepare("DELETE FROM {\$this->table} WHERE id = ? AND tenant_id = ?");
        \$result = \$stmt->execute([\$id, \$this->getTenantId()]);

        \$this->clearCache(\$id);

        return \$result;
    }

    /**
     * Clear cache
     */
    protected function clearCache(?int \$id = null): void
    {
        \$this->cache->forget("{\$this->table}_all");
        if (\$id) {
            \$this->cache->forget("{\$this->table}_{\$id}");
        }
    }
}
PHP;
}

/**
 * Genera el contenido de un Service base.
 *
 * @param string $className Nombre de la clase Service.
 * @param string $namespace Namespace de la clase.
 * @return string Código PHP del Service.
 */
function generateService($className, $namespace) {
    // TODO: Solo es un equeleto del service, agregar lógica de negocio real. Injección de dependencias, validaciones, etc.
    return <<<PHP
<?php

namespace {$namespace};

use BootZen\Core\Service;
use BootZen\Core\Cache;
use BootZen\Core\Database;

class {$className} extends Service
{
    private Cache \$cache;
    private Database \$db;

    public function __construct()
    {
        \$this->cache = Cache::getInstance();
        \$this->db = Database::getInstance();
    }

    /**
     * Process business logic
     */
    public function process(array \$data): array
    {
        // Validate input
        \$this->validate(\$data);

        // Process data
        \$result = \$this->performOperation(\$data);

        // Cache result
        \$this->cacheResult(\$result);

        return \$result;
    }

    /**
     * Validate input data
     */
    private function validate(array \$data): void
    {
        if (empty(\$data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        // Add validation logic
    }

    /**
     * Perform the main operation
     */
    private function performOperation(array \$data): array
    {
        // Business logic here
        return [
            'success' => true,
            'data' => \$data,
            'timestamp' => time(),
        ];
    }

    /**
     * Cache the result
     */
    private function cacheResult(array \$result): void
    {
        \$key = 'service_' . md5(json_encode(\$result));
        \$this->cache->set(\$key, \$result, 3600);
    }
}
PHP;
}

/**
 * Genera el contenido de una Migration base.
 *
 * @param string $name Nombre base para la tabla.
 * @return string Código PHP de la Migration.
 */
function generateMigration($name) {
    // TODO: Esqueleto no funcional, para crear migraciones reales se necesita más lógica.
    $table = strtolower($name) . 's';
    return <<<PHP
<?php

namespace BootZen\Database\Migrations;

use BootZen\Core\Migration;

class Create{$name}Table extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        \$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        \$this->execute(\$sql);
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        \$this->execute("DROP TABLE IF EXISTS {$table}");
    }
}
PHP;
}

/**
 * Genera el contenido de un Test base.
 *
 * @param string $className Nombre de la clase Test.
 * @param string $name Nombre base para el recurso a testear.
 * @return string Código PHP del Test.
 */
function generateTest($className, $name) {
    // TODO: Base muy simple para crear pruebas, agregar más casos de prueba según la lógica de negocio.
    // Usar PestPHP o PHPUnit según convenga.
    return <<<PHP
<?php

namespace Tests\Feature;

use Tests\TestCase;

class {$className} extends TestCase
{
    /**
     * @test
     */
    public function it_can_create_{$name}(): void
    {
        // Arrange
        \$data = [
            'name' => 'Test {$name}',
            'description' => 'Test description',
        ];

        // Act
        \$result = \$this->post('/{$name}', \$data);

        // Assert
        expect(\$result)
            ->toBeArray()
            ->toHaveKey('success', true)
            ->and(\$result['data'])
            ->toHaveKey('name', \$data['name']);
    }

    /**
     * @test
     */
    public function it_can_retrieve_{$name}(): void
    {
        // Arrange
        \$id = 1;

        // Act
        \$result = \$this->get('/{$name}/{\$id}');

        // Assert
        expect(\$result)
            ->toBeArray()
            ->toHaveKey('id', \$id);
    }

    /**
     * @test
     */
    public function it_can_update_{$name}(): void
    {
        // Arrange
        \$id = 1;
        \$data = ['name' => 'Updated {$name}'];

        // Act
        \$result = \$this->put('/{$name}/{\$id}', \$data);

        // Assert
        expect(\$result)
            ->toBeArray()
            ->toHaveKey('success', true);
    }

    /**
     * @test
     */
    public function it_can_delete_{$name}(): void
    {
        // Arrange
        \$id = 1;

        // Act
        \$result = \$this->delete('/{$name}/{\$id}');

        // Assert
        expect(\$result)
            ->toBeArray()
            ->toHaveKey('success', true);
    }
}
PHP;
}
