<?php

/**
 * ViewCompiler.php
 *
 * Compila vistas .php con una lista cerrada de extensiones a PHP plano.
 *
 * | Sintaxis                              | Resultado                                   |
 * | ------------------------------------- | ------------------------------------------- |
 * | {{ $expr }}                           | Salida escapada                             |
 * | {!! $expr !!}                         | Salida sin escapar                          |
 * | {{-- comentario --}}                  | Se elimina                                  |
 * | @{{ texto }}                          | {{ texto }} literal (para JS)               |
 * | @if / @elseif / @else / @endif        | if de PHP                                   |
 * | @foreach / @empty / @endforeach       | foreach con rama vacía opcional             |
 * | @csrf, @method('PUT')                 | Campos ocultos de formulario                |
 * | @error('campo') ... @enderror         | Bloque con $message si el campo tiene error |
 * | @props([...])                         | Props del componente                        |
 * | <x-nombre attr="x" :attr="$expr">     | Componente resources/views/components/...   |
 * | <x-slot:nombre> ... </x-slot:nombre>  | Slot con nombre                             |
 *
 * Cualquier otra cosa se deja intacta: el resto se escribe en PHP plano.
 * Cada reemplazo conserva los saltos de línea, así la línea de un error en la vista compilada
 * es la misma línea de la vista original.
 *
 * @package    BootZen\View
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\View;

/**
 * Compilador de plantillas BootZen.
 */
final class ViewCompiler
{
    /**
     * Directivas soportadas => si requieren argumentos entre paréntesis.
     */
    private const DIRECTIVES = [
        'if' => true,
        'elseif' => true,
        'else' => false,
        'endif' => false,
        'foreach' => true,
        'empty' => false,
        'endforeach' => false,
        'csrf' => false,
        'method' => true,
        'error' => true,
        'enderror' => false,
        'props' => true,
    ];

    private const COMPONENT_NAME = '[a-z0-9][a-z0-9\-.]*';

    private const ATTRIBUTE = '[^\s"\'=<>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'))?';

    private string $path = '';

    /**
     * Fragmentos de PHP ya compilados, reemplazados por marcadores.
     *
     * @var array<string, string>
     */
    private array $stash = [];

    /**
     * Bloques abiertos (@if, @foreach, @error) para validar el anidamiento.
     *
     * @var list<array{type: string, line: int, id: int, empty: bool}>
     */
    private array $stack = [];

    private int $loops = 0;

    /**
     * Compila el código fuente de una vista.
     *
     * @param string $source Contenido de la vista
     * @param string $path Ruta de la vista, para los mensajes de error
     * @throws ViewException Si hay bloques sin cerrar, cierres sin apertura o directivas mal formadas
     */
    public function compile(string $source, string $path = ''): string
    {
        $this->path = $path;
        $this->stash = [];
        $this->stack = [];
        $this->loops = 0;

        $source = str_replace("\r\n", "\n", $source);
        $source = $this->compileComments($source);
        $source = $this->protectEscapedEchos($source);
        $source = $this->compileComponents($source);
        $source = $this->compileDirectives($source);
        $source = $this->compileEchos($source);

        $open = $this->openBlock();
        if ($open !== null) {
            throw $this->error("falta @end{$open['type']} para el @{$open['type']} abierto", $open['line']);
        }

        return $this->restore($source);
    }

    /**
     * Los comentarios no se imprimen. Sus saltos de línea quedan dentro de un bloque PHP vacío
     * (y el salto que los sigue lo descarta PHP), así no dejan líneas en blanco en el HTML.
     */
    private function compileComments(string $source): string
    {
        return (string) preg_replace_callback(
            '/\{\{--.*?--\}\}(\n)?/s',
            fn (array $m): string => $this->hold(
                '<?php /*' . str_repeat("\n", substr_count($m[0], "\n") - (isset($m[1]) ? 1 : 0)) . '*/ ?>' . ($m[1] ?? ''),
                $m[0]
            ),
            $source
        );
    }

    /**
     * @{{ ... }} y @{!! ... !!} se imprimen tal cual (sin la @).
     */
    private function protectEscapedEchos(string $source): string
    {
        return (string) preg_replace_callback(
            '/@(\{\{.*?\}\}|\{!!.*?!!\})/s',
            fn (array $m): string => $this->hold($m[1], $m[1]),
            $source
        );
    }

    private function compileComponents(string $source): string
    {
        $source = (string) preg_replace_callback(
            '/<x-slot:([\w\-]+)\s*>/',
            fn (array $m): string => $this->hold("<?php \$__env->startSlot('{$m[1]}'); ?>", $m[0]),
            $source
        );

        $source = (string) preg_replace_callback(
            '/<\/x-slot(?::[\w\-]+)?\s*>/',
            fn (array $m): string => $this->hold('<?php $__env->endSlot(); ?>', $m[0]),
            $source
        );

        $subject = $source;
        $source = (string) preg_replace_callback(
            '/<x-(' . self::COMPONENT_NAME . ')((?:\s+' . self::ATTRIBUTE . ')*)\s*(\/?)>(\n)?/i',
            function (array $m) use ($subject): string {
                $line = $this->lineOf(substr($subject, 0, $m[0][1]));
                $newline = $m[4][0] ?? '';
                $start = sprintf(
                    "\$__env->startComponent('%s', %s);",
                    strtolower($m[1][0]),
                    $this->compileAttributes($m[2][0], $line)
                );

                if ($m[3][0] !== '/') {
                    return $this->hold("<?php {$start} ?>" . $newline, $m[0][0]);
                }

                return $this->hold($this->echo("{$start} echo \$__env->renderComponent()", $newline), $m[0][0]);
            },
            $source,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );

        return (string) preg_replace_callback(
            '/<\/x-' . self::COMPONENT_NAME . '\s*>(\n)?/i',
            fn (array $m): string => $this->hold($this->echo('echo $__env->renderComponent()', $m[1] ?? ''), $m[0]),
            $source
        );
    }

    /**
     * Convierte los atributos de un componente en un array PHP.
     *
     * - nombre="texto": string, admite {{ $expr }} dentro
     * - :nombre="$expr": expresión PHP
     * - nombre (sin valor): true
     *
     * @throws ViewException Si un atributo :enlazado no tiene valor
     */
    private function compileAttributes(string $attributes, int $line): string
    {
        preg_match_all(
            '/([^\s"\'=<>\/]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?/',
            $attributes,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
        );

        $items = [];

        foreach ($matches as $match) {
            $name = (string) $match[1];
            $value = $match[2] ?? $match[3];

            if (str_starts_with($name, ':')) {
                if ($value === null || trim($value) === '') {
                    throw $this->error("el atributo {$name} necesita una expresión PHP", $line);
                }

                $items[] = var_export(substr($name, 1), true) . ' => ' . $value;

                continue;
            }

            $items[] = var_export($name, true) . ' => ' . ($value === null ? 'true' : $this->interpolate($value));
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * Convierte un texto con {{ $expr }} en una expresión PHP de string.
     */
    private function interpolate(string $value): string
    {
        $parts = preg_split('/\{\{\s*(.+?)\s*\}\}/s', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$value];
        $compiled = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $compiled[] = "\$__env->toAttribute({$part})";
            } elseif ($part !== '') {
                $compiled[] = var_export($part, true);
            }
        }

        return $compiled === [] ? "''" : implode(' . ', $compiled);
    }

    /**
     * @throws ViewException Si una directiva está mal formada o mal anidada
     */
    private function compileDirectives(string $source): string
    {
        $result = '';
        $offset = 0;
        $lastDirectiveEnd = -1;

        while (preg_match('/@(\w+)/', $source, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $name = $match[1][0];
            $position = $match[0][1];
            $end = $position + strlen($match[0][0]);

            // Una @ pegada a una palabra (correo@dominio) no es directiva, salvo que siga a otra directiva
            $glued = $position > 0 && preg_match('/\w/', $source[$position - 1]) === 1 && $position !== $lastDirectiveEnd;

            if ($glued || ! array_key_exists($name, self::DIRECTIVES)) {
                $result .= substr($source, $offset, $end - $offset);
                $offset = $end;

                continue;
            }

            $line = $this->lineOf(substr($source, 0, $position));
            $arguments = null;

            if (self::DIRECTIVES[$name]) {
                [$arguments, $end] = $this->extractArguments($source, $end, $name, $line);
            }

            $original = substr($source, $position, $end - $position);
            $result .= substr($source, $offset, $position - $offset);
            $result .= $this->hold($this->compileDirective($name, $arguments, $line), $original);
            $offset = $end;
            $lastDirectiveEnd = $end;
        }

        return $result . substr($source, $offset);
    }

    /**
     * Extrae el texto entre paréntesis balanceados, respetando strings.
     *
     * @return array{0: string, 1: int} Argumentos sin los paréntesis y posición final
     * @throws ViewException Si faltan los paréntesis o no se cierran
     */
    private function extractArguments(string $source, int $start, string $name, int $line): array
    {
        $position = $start;
        $length = strlen($source);

        while ($position < $length && ($source[$position] === ' ' || $source[$position] === "\t")) {
            $position++;
        }

        if (($source[$position] ?? '') !== '(') {
            throw $this->error("@{$name} necesita argumentos entre paréntesis, por ejemplo @{$name}(...)", $line);
        }

        $depth = 0;
        $quote = null;

        for ($i = $position; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return [substr($source, $position + 1, $i - $position - 1), $i + 1];
            }
        }

        throw $this->error("falta cerrar el paréntesis de @{$name}", $line);
    }

    /**
     * @throws ViewException Si la directiva está mal anidada
     */
    private function compileDirective(string $name, ?string $arguments, int $line): string
    {
        switch ($name) {
            case 'if':
                $this->open('if', $line);

                return "<?php if ({$arguments}): ?>";
            case 'elseif':
                $this->expect('if', '@elseif', $line);

                return "<?php elseif ({$arguments}): ?>";
            case 'else':
                $this->expect('if', '@else', $line);

                return '<?php else: ?>';
            case 'endif':
                $this->close('if', $line);

                return '<?php endif; ?>';
            case 'foreach':
                $id = ++$this->loops;
                $this->open('foreach', $line, $id);

                return "<?php \$__empty_{$id} = true; foreach ({$arguments}): \$__empty_{$id} = false; ?>";
            case 'empty':
                $loop = $this->expect('foreach', '@empty', $line);
                $this->stack[count($this->stack) - 1]['empty'] = true;

                return "<?php endforeach; if (\$__empty_{$loop['id']}): ?>";
            case 'endforeach':
                $loop = $this->close('foreach', $line);

                return $loop['empty'] ? '<?php endif; ?>' : '<?php endforeach; ?>';
            case 'csrf':
                return '<?php echo csrf_field(); ?>';
            case 'method':
                return "<?php echo \$__env->methodField({$arguments}); ?>";
            case 'error':
                $this->open('error', $line);

                return "<?php if (\$__env->hasError({$arguments})): \$message = \$__env->firstError({$arguments}); ?>";
            case 'enderror':
                $this->close('error', $line);

                return '<?php unset($message); endif; ?>';
            default: // props
                return "<?php [\$__props, \$attributes] = \$__env->resolveProps(\$attributes ?? null, {$arguments}); "
                    . 'extract($__props); unset($__props); ?>';
        }
    }

    private function compileEchos(string $source): string
    {
        $source = (string) preg_replace_callback(
            '/\{!!\s*(.+?)\s*!!\}(\n)?/s',
            fn (array $m): string => $this->hold($this->echo("echo {$m[1]}", $m[2] ?? ''), $m[0]),
            $source
        );

        return (string) preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}(\n)?/s',
            fn (array $m): string => $this->hold($this->echo("echo \$__env->escape({$m[1]})", $m[2] ?? ''), $m[0]),
            $source
        );
    }

    /**
     * Genera un bloque PHP que imprime algo. Si en la vista lo seguía un salto de línea, lo imprime:
     * PHP descarta el salto que va justo después de "?>".
     */
    private function echo(string $statement, string $newline): string
    {
        return $newline === ''
            ? "<?php {$statement}; ?>"
            : "<?php {$statement} . \"\\n\"; ?>\n";
    }

    /**
     * Guarda un fragmento compilado y devuelve un marcador con los mismos saltos de línea que el original.
     */
    private function hold(string $compiled, string $original): string
    {
        $newlines = substr_count($original, "\n");
        $missing = $newlines - substr_count($compiled, "\n");

        if ($missing > 0) {
            // Los saltos de línea del original quedan dentro del bloque PHP
            $compiled = (string) preg_replace('/\?>(\n?)$/', str_repeat("\n", $missing) . '?>$1', $compiled, 1);
        }

        $marker = "\x00BZ" . count($this->stash) . "\x00";
        $this->stash[$marker . str_repeat("\n", $newlines)] = $compiled;

        return $marker . str_repeat("\n", $newlines);
    }

    private function restore(string $source): string
    {
        return strtr($source, $this->stash);
    }

    private function open(string $type, int $line, int $id = 0): void
    {
        $this->stack[] = ['type' => $type, 'line' => $line, 'id' => $id, 'empty' => false];
    }

    /**
     * @return array{type: string, line: int, id: int, empty: bool}
     * @throws ViewException Si el bloque abierto no es del tipo esperado
     */
    private function expect(string $type, string $directive, int $line): array
    {
        $top = end($this->stack);

        if ($top === false || $top['type'] !== $type) {
            throw $this->error("{$directive} sin un @{$type} abierto", $line);
        }

        return $top;
    }

    /**
     * @return array{type: string, line: int, id: int, empty: bool}
     * @throws ViewException Si se cierra un bloque distinto al abierto
     */
    private function close(string $type, int $line): array
    {
        $top = $this->expect($type, "@end{$type}", $line);
        array_pop($this->stack);

        return $top;
    }

    /**
     * Último bloque abierto, o null si no hay ninguno.
     *
     * @return array{type: string, line: int, id: int, empty: bool}|null
     */
    private function openBlock(): ?array
    {
        $top = end($this->stack);

        return $top === false ? null : $top;
    }

    private function lineOf(string $before): int
    {
        return substr_count($before, "\n") + 1;
    }

    private function error(string $message, int $line): ViewException
    {
        $where = $this->path !== '' ? $this->path . ':' . $line : 'línea ' . $line;

        return new ViewException("Error de sintaxis en la vista ({$where}): {$message}.", $this->path, $line);
    }
}
