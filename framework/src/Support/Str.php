<?php

/**
 * Str.php
 *
 * Conversión de nombres para los generadores: StudlyCase, snake_case, kebab-case, camelCase y plurales
 * con reglas del inglés (Task → tasks, Category → categories). Para otros idiomas, --table.
 *
 * @package    BootZen\Support
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Support;

/**
 * @example Str::plural('OrderItem') // 'OrderItems'
 * @example Str::snake('OrderItem')  // 'order_item'
 */
final class Str
{
    private const IRREGULAR = [
        'person' => 'people',
        'man' => 'men',
        'woman' => 'women',
        'child' => 'children',
        'mouse' => 'mice',
        'goose' => 'geese',
        'foot' => 'feet',
        'tooth' => 'teeth',
        'leaf' => 'leaves',
        'knife' => 'knives',
        'wife' => 'wives',
        'life' => 'lives',
        'half' => 'halves',
        'wolf' => 'wolves',
        'shelf' => 'shelves',
        'hero' => 'heroes',
        'potato' => 'potatoes',
        'tomato' => 'tomatoes',
        'echo' => 'echoes',
        'quiz' => 'quizzes',
    ];

    private const UNCOUNTABLE = [
        'data', 'equipment', 'feedback', 'fish', 'information', 'media', 'metadata', 'money', 'news',
        'rice', 'series', 'sheep', 'species', 'software', 'staff',
    ];

    /**
     * Plural en inglés de la última palabra, conservando el formato: 'OrderItem' => 'OrderItems'.
     */
    public static function plural(string $value): string
    {
        $words = preg_split('/(?=[A-Z])|_|-/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value];
        $last = (string) end($words);
        $plural = self::pluralWord(strtolower($last));

        // Conserva mayúscula inicial y el separador original
        if (ctype_upper($last[0] ?? '')) {
            $plural = ucfirst($plural);
        }

        return substr($value, 0, strlen($value) - strlen($last)) . $plural;
    }

    /**
     * 'order item', 'order_item' u 'orderItem' => 'OrderItem'.
     */
    public static function studly(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(static fn (string $word): string => ucfirst($word), $words));
    }

    /**
     * 'OrderItem' => 'orderItem'.
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * 'OrderItem' => 'order_item'.
     */
    public static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', self::studly($value)));
    }

    /**
     * 'OrderItem' => 'order-item'.
     */
    public static function kebab(string $value): string
    {
        return str_replace('_', '-', self::snake($value));
    }

    /**
     * 'due_at' => 'Due at' (para etiquetas de formularios).
     */
    public static function headline(string $value): string
    {
        return ucfirst(str_replace('_', ' ', self::snake($value)));
    }

    private static function pluralWord(string $word): string
    {
        if (in_array($word, self::UNCOUNTABLE, true)) {
            return $word;
        }

        if (isset(self::IRREGULAR[$word])) {
            return self::IRREGULAR[$word];
        }

        return match (true) {
            preg_match('/[^aeiou]y$/', $word) === 1 => substr($word, 0, -1) . 'ies',
            preg_match('/(s|x|z|ch|sh)$/', $word) === 1 => $word . 'es',
            default => $word . 's',
        };
    }
}
