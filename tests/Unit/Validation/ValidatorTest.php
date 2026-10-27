<?php

declare(strict_types=1);

use BootZen\Core\Request;
use BootZen\Validation\ValidationException;
use BootZen\Validation\Validator;

/**
 * Primer error de un campo, o null si pasa.
 *
 * @param array<string, mixed> $data
 */
function firstError(array $data, string $rules, string $field = 'campo'): ?string
{
    return Validator::make($data, [$field => $rules])->errors()[$field][0] ?? null;
}

afterEach(fn () => Validator::setConnectionResolver(null));

it('devuelve solo los campos validados y deja null los opcionales vacíos', function (): void {
    $validator = Validator::make(
        ['titulo' => 'Pan', 'nota' => '', 'extra' => 'x', 'admin' => '1'],
        ['titulo' => 'required|string|max:20', 'nota' => 'nullable|string', 'falta' => 'string']
    );

    expect($validator->passes())->toBeTrue()
        ->and($validator->fails())->toBeFalse()
        ->and($validator->validate())->toBe(['titulo' => 'Pan', 'nota' => null]);
});

it('lanza ValidationException con los errores por campo', function (): void {
    try {
        Validator::make(['email' => 'no-es-correo'], ['email' => 'required|email', 'nombre' => 'required'])->validate();
        $this->fail('Debió lanzar ValidationException');
    } catch (ValidationException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->errors())->toBe([
                'email' => ['El campo email debe ser un correo electrónico válido.'],
                'nombre' => ['El campo nombre es obligatorio.'],
            ]);
    }
});

it('aplica cada regla', function (array $data, string $rules, ?string $error): void {
    expect(firstError($data, $rules))->toBe($error);
})->with([
    'required vacío' => [['campo' => '  '], 'required', 'El campo campo es obligatorio.'],
    'required falta' => [[], 'required', 'El campo campo es obligatorio.'],
    'required cero' => [['campo' => '0'], 'required', null],
    'string' => [['campo' => ['a']], 'string', 'El campo campo debe ser texto.'],
    'integer ok' => [['campo' => '42'], 'integer', null],
    'integer decimal' => [['campo' => '4.2'], 'integer', 'El campo campo debe ser un número entero.'],
    'numeric' => [['campo' => 'abc'], 'numeric', 'El campo campo debe ser un número.'],
    'numeric ok' => [['campo' => '4.5'], 'numeric', null],
    'boolean ok' => [['campo' => 'on'], 'boolean', null],
    'boolean' => [['campo' => 'quizá'], 'boolean', 'El campo campo debe ser verdadero o falso.'],
    'array' => [['campo' => 'x'], 'array', 'El campo campo debe ser una lista.'],
    'email ok' => [['campo' => 'ana@ejemplo.com'], 'email', null],
    'url ok' => [['campo' => 'https://bootzen.dev/x'], 'url', null],
    'url javascript' => [['campo' => 'javascript:alert(1)'], 'url', 'El campo campo debe ser una URL válida.'],
    'min texto' => [['campo' => 'ab'], 'min:3', 'El campo campo debe tener al menos 3 caracteres.'],
    'min texto multibyte' => [['campo' => 'ñúé'], 'min:3', null],
    'min número' => [['campo' => '2'], 'numeric|min:3', 'El campo campo debe ser al menos 3.'],
    'max texto' => [['campo' => 'abcd'], 'max:3', 'El campo campo no puede tener más de 3 caracteres.'],
    'max número' => [['campo' => '10'], 'integer|max:9', 'El campo campo no puede ser mayor que 9.'],
    'max lista' => [['campo' => [1, 2, 3]], 'array|max:2', 'El campo campo no puede tener más de 2 elementos.'],
    'min lista' => [['campo' => [1]], 'array|min:2', 'El campo campo debe tener al menos 2 elementos.'],
    'between' => [['campo' => '15'], 'numeric|between:1,10', 'El campo campo debe estar entre 1 y 10.'],
    'between texto' => [['campo' => 'a'], 'between:2,4', 'El campo campo debe tener entre 2 y 4 caracteres.'],
    'between lista' => [['campo' => [1]], 'array|between:2,4', 'El campo campo debe tener entre 2 y 4 elementos.'],
    'in' => [['campo' => 'rojo'], 'in:alta,media,baja', 'El valor de campo no es válido.'],
    'in ok' => [['campo' => 'media'], 'in:alta,media,baja', null],
    'confirmed' => [['campo' => 'secreto', 'campo_confirmation' => 'otro'], 'confirmed', 'La confirmación de campo no coincide.'],
    'confirmed ok' => [['campo' => 'secreto', 'campo_confirmation' => 'secreto'], 'confirmed', null],
    'date' => [['campo' => 'ayer no'], 'date', 'El campo campo debe ser una fecha válida.'],
    'date ok' => [['campo' => '2026-10-27'], 'date', null],
    'regex' => [['campo' => 'ABC'], 'regex:/^[a-z]+$/', 'El formato de campo no es válido.'],
    'regex con barras y comas' => [['campo' => 'a,b'], 'regex:/^[a-z],[a-z]$/', null],
    'opcional vacío ignora reglas' => [['campo' => ''], 'email|min:5', null],
]);

it('valida campos con comodín y reporta el índice real', function (): void {
    $validator = Validator::make(
        ['items' => [['precio' => '10'], ['precio' => 'gratis'], ['nombre' => 'x']]],
        ['items' => 'required|array', 'items.*.precio' => 'required|numeric']
    );

    expect($validator->errors())->toBe([
        'items.1.precio' => ['El campo precio debe ser un número.'],
        'items.2.precio' => ['El campo precio es obligatorio.'],
    ]);

    $ok = Validator::make(['items' => [['precio' => '10', 'otro' => 'x']]], ['items.*.precio' => 'numeric']);

    expect($ok->validated())->toBe(['items' => [['precio' => '10']]])
        ->and(Validator::make([], ['items.*.precio' => 'required'])->passes())->toBeTrue();
});

it('usa mensajes y nombres de campo propios', function (): void {
    $errors = Validator::make(
        ['correo_electronico' => 'x', 'edad' => '', 'items' => [['precio' => 'a']]],
        ['correo_electronico' => 'email', 'edad' => 'required', 'items.*.precio' => 'numeric', 'nombre' => 'required'],
        ['edad.required' => 'Dinos tu edad.', 'items.*.precio.numeric' => 'Precio inválido.', 'required' => ':attribute falta.'],
        ['correo_electronico' => 'correo']
    )->errors();

    expect($errors)->toBe([
        'correo_electronico' => ['El campo correo debe ser un correo electrónico válido.'],
        'edad' => ['Dinos tu edad.'],
        'items.0.precio' => ['Precio inválido.'],
        'nombre' => ['nombre falta.'],
    ]);
});

it('acepta closures como reglas', function (): void {
    $par = static fn (mixed $value, string $field): ?string => (int) $value % 2 === 0 ? null : "El {$field} debe ser par.";

    expect(Validator::make(['n' => '3'], ['n' => ['required', 'integer', $par]])->errors())->toBe(['n' => ['El n debe ser par.']])
        ->and(Validator::make(['n' => '4'], ['n' => ['required', $par]])->passes())->toBeTrue();
});

it('falla con reglas desconocidas o sin parámetros', function (string $rule, string $message): void {
    expect(fn () => Validator::make(['campo' => 'x'], ['campo' => $rule])->errors())
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['requerido', 'Regla de validación [requerido] desconocida'],
    ['max', 'La regla max necesita parámetros'],
]);

it('valida unique y exists contra la base de datos', function (): void {
    $db = memoryDb();
    $db->table('proyectos')->insert(['nombre' => 'Casa']);
    $db->table('proyectos')->insert(['nombre' => 'Oficina']);
    Validator::setConnectionResolver(fn () => $db);

    expect(firstError(['nombre' => 'Casa'], 'unique:proyectos', 'nombre'))->toBe('Ya existe un registro con ese nombre.')
        ->and(firstError(['nombre' => 'Jardín'], 'unique:proyectos', 'nombre'))->toBeNull()
        ->and(firstError(['titulo' => 'Casa'], 'unique:proyectos,nombre', 'titulo'))->not->toBeNull()
        ->and(firstError(['nombre' => 'Casa'], 'unique:proyectos,nombre,1', 'nombre'))->toBeNull()
        ->and(firstError(['nombre' => 'Casa'], 'unique:proyectos,nombre,2,id', 'nombre'))->not->toBeNull()
        ->and(firstError(['proyecto_id' => '2'], 'exists:proyectos,id', 'proyecto_id'))->toBeNull()
        ->and(firstError(['proyecto_id' => '9'], 'exists:proyectos,id', 'proyecto_id'))->toBe('El proyecto id seleccionado no existe.');
});

it('pide una conexión para unique y exists', function (): void {
    firstError(['nombre' => 'Casa'], 'unique:proyectos', 'nombre');
})->throws(LogicException::class, 'necesitan una conexión');

describe('archivos', function (): void {
    beforeEach(function (): void {
        $this->dir = makeTempDir('bootzen-uploads');
        $this->upload = function (string $name, string $contents, int $error = UPLOAD_ERR_OK): array {
            $path = $this->dir . '/' . bin2hex(random_bytes(4));
            file_put_contents($path, $contents);

            return ['name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => $error, 'size' => strlen($contents)];
        };
    });
    afterEach(fn () => removeDir($this->dir));

    it('valida file, image, mimes y tamaño en KB', function (): void {
        // PNG de 1x1 píxel
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $imagen = ($this->upload)('foto.png', $png);
        $texto = ($this->upload)('notas.txt', str_repeat('a', 3000));
        $falso = ($this->upload)('virus.png', '<?php echo 1;');

        expect(firstError(['f' => $imagen], 'file|image|mimes:png,jpg', 'f'))->toBeNull()
            ->and(firstError(['f' => $falso], 'image', 'f'))->toBe('El archivo f debe ser una imagen (jpg, png, gif o webp).')
            ->and(firstError(['f' => $falso], 'mimes:png', 'f'))->toBe('El archivo f debe ser de tipo: png.')
            ->and(firstError(['f' => $texto], 'mimes:txt', 'f'))->toBeNull()
            ->and(firstError(['f' => $texto], 'mimes:md', 'f'))->not->toBeNull()
            ->and(firstError(['f' => $texto], 'file|max:2', 'f'))->toBe('El archivo f no puede pesar más de 2 KB.')
            ->and(firstError(['f' => $texto], 'file|min:1', 'f'))->toBeNull()
            ->and(firstError(['f' => 'texto'], 'file', 'f'))->toBe('El campo f debe ser un archivo.');
    });

    it('trata un input de archivo sin archivo como vacío', function (): void {
        $vacio = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];

        expect(firstError(['f' => $vacio], 'required|file', 'f'))->toBe('El campo f es obligatorio.')
            ->and(Validator::make(['f' => $vacio], ['f' => 'file'])->validated())->toBe(['f' => null]);
    });

    it('valida archivos desde el Request', function (): void {
        $request = new Request('POST', '/', [], ['titulo' => 'x'], ['foto' => ($this->upload)('a.txt', 'hola')]);

        expect(fn () => $request->validate(['foto' => 'required|image']))->toThrow(ValidationException::class);
    });
});

it('valida desde el Request y devuelve los datos limpios', function (): void {
    $request = new Request('POST', '/tareas?page=2', [], ['titulo' => 'Pan', 'admin' => '1', '_method' => 'POST']);

    expect($request->validate(['titulo' => 'required|string', 'page' => 'integer']))
        ->toBe(['titulo' => 'Pan', 'page' => '2']);
});

it('crea la excepción con mensajes escritos a mano', function (): void {
    $e = ValidationException::withMessages(['email' => 'Credenciales incorrectas.', 'x' => ['a', 'b']]);

    expect($e->errors())->toBe(['email' => ['Credenciales incorrectas.'], 'x' => ['a', 'b']])
        ->and($e->getMessage())->toBe('Los datos enviados no son válidos.');
});
