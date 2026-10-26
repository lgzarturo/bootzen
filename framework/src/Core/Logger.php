<?php

/**
 * Logger.php
 *
 * Registro de eventos en archivos diarios con los niveles de PSR-3, sin dependencias externas.
 * Escribe una línea por evento: [fecha] nivel: mensaje {contexto JSON}.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use InvalidArgumentException;
use Stringable;
use Throwable;

/**
 * Logger de archivo diario (storage/logs/app-AAAA-MM-DD.log).
 *
 * @example $logger->error('Pago rechazado', ['orden' => $id]);
 */
final class Logger
{
    /**
     * Niveles PSR-3 ordenados de menor a mayor severidad.
     *
     * @var array<string, int>
     */
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    private readonly int $minimumLevel;

    /**
     * @param string $directory Directorio de logs
     * @param string $minimumLevel Nivel mínimo que se escribe (los inferiores se descartan)
     * @param string $channel Prefijo del nombre de archivo
     */
    public function __construct(
        private readonly string $directory,
        string $minimumLevel = 'debug',
        private readonly string $channel = 'app'
    ) {
        $this->minimumLevel = self::severity($minimumLevel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Registra un mensaje. Los marcadores {clave} del mensaje se reemplazan con el contexto.
     *
     * @param string $level Nivel PSR-3
     * @param array<string, mixed> $context Datos adicionales; 'exception' puede ser un Throwable
     * @throws InvalidArgumentException Si el nivel no existe
     */
    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        if (self::severity($level) < $this->minimumLevel) {
            return;
        }

        $line = sprintf(
            '[%s] %s.%s: %s%s',
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $this->interpolate((string) $message, $context),
            $this->formatContext($context)
        );

        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $file = sprintf('%s/%s-%s.log', rtrim($this->directory, '/'), $this->channel, date('Y-m-d'));

        if (@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            // Último recurso para no perder el evento si storage/ no es escribible
            error_log($line);
        }
    }

    /**
     * Devuelve la severidad numérica de un nivel.
     *
     * @throws InvalidArgumentException Si el nivel no existe
     */
    private static function severity(string $level): int
    {
        $level = strtolower($level);

        if (! isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException(
                "Nivel de log desconocido [{$level}]. Usa: " . implode(', ', array_keys(self::LEVELS)) . '.'
            );
        }

        return self::LEVELS[$level];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable || $value === null) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key] = sprintf(
                    '%s: %s en %s:%d',
                    $value::class,
                    $value->getMessage(),
                    $value->getFile(),
                    $value->getLine()
                );
            }
        }

        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? '' : ' ' . $json;
    }
}
