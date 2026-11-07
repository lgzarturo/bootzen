<?php

/**
 * Formatter.php
 *
 * Números, moneda y fechas según el idioma activo. Usa la extensión intl si está instalada y, si no
 * (muchos hostings no la traen), un formato propio para español e inglés.
 *
 * @package    BootZen\Translation
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.4.0
 */

declare(strict_types=1);

namespace BootZen\Translation;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use InvalidArgumentException;
use NumberFormatter;

/**
 * La región de cada idioma ('es' => 'es_MX', 'en' => 'en_US') decide separadores y moneda; se
 * configura en config/app.php ('regions').
 *
 * @example format_money(1234.5); // '$1,234.50' en es_MX
 */
final class Formatter
{
    private const MONTHS = [
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    /**
     * Separadores (miles, decimales) sin intl. Las regiones que no están usan coma y punto.
     */
    private const SEPARATORS = [
        'es_ES' => ['.', ','], 'es_AR' => ['.', ','], 'es_CO' => ['.', ','], 'es_CL' => ['.', ','],
        'pt_BR' => ['.', ','], 'de_DE' => ['.', ','], 'fr_FR' => [' ', ','], 'it_IT' => ['.', ','],
    ];

    private const SYMBOLS = ['MXN' => '$', 'USD' => 'US$', 'EUR' => '€', 'GBP' => '£', 'COP' => '$', 'ARS' => '$', 'CLP' => '$'];

    /**
     * @param string $region Región para formatear (es_MX, en_US...)
     * @param bool|null $intl null usa intl si está instalada; false fuerza el formato propio
     */
    public function __construct(
        private readonly string $region,
        private readonly string $currency = 'MXN',
        private ?bool $intl = null,
        private readonly ?string $timezone = null
    ) {
        $this->intl ??= class_exists(NumberFormatter::class);
    }

    /**
     * Número con separadores de miles y $decimals decimales.
     */
    public function number(int|float $number, int $decimals = 0): string
    {
        if ($this->intl) {
            $formatter = new NumberFormatter($this->region, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format($number);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        [$thousands, $decimal] = self::SEPARATORS[$this->region] ?? [',', '.'];

        return number_format($number, $decimals, $decimal, $thousands);
    }

    /**
     * Cantidad con símbolo de moneda (por defecto APP_CURRENCY).
     */
    public function money(int|float $amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?? $this->currency);

        if ($this->intl) {
            $formatted = (new NumberFormatter($this->region, NumberFormatter::CURRENCY))->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $number = $this->number(abs($amount), 2);
        $sign = $amount < 0 ? '-' : '';
        $symbol = self::SYMBOLS[$currency] ?? $currency . ' ';

        // En español de España el euro va después; en lo demás, antes
        return in_array($this->region, ['es_ES', 'fr_FR', 'de_DE', 'it_IT'], true)
            ? $sign . $number . ' ' . trim($symbol)
            : $sign . $symbol . $number;
    }

    /**
     * Fecha (y hora con $withTime) en estilo short (07/11/2026), medium (7 nov 2026) o long
     * (7 de noviembre de 2026).
     *
     * @throws InvalidArgumentException Si el estilo no es short, medium o long
     */
    public function date(DateTimeInterface|string $date, string $style = 'medium', bool $withTime = false): string
    {
        if (! in_array($style, ['short', 'medium', 'long'], true)) {
            throw new InvalidArgumentException("Estilo de fecha [{$style}] inválido: usa short, medium o long.");
        }

        $timezone = new DateTimeZone($this->timezone ?? date_default_timezone_get());
        $date = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date, $timezone);
        $date = DateTimeImmutable::createFromInterface($date)->setTimezone($timezone);

        if ($this->intl) {
            $formatter = new IntlDateFormatter(
                $this->region,
                ['short' => IntlDateFormatter::SHORT, 'medium' => IntlDateFormatter::MEDIUM, 'long' => IntlDateFormatter::LONG][$style],
                $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                $timezone
            );
            $formatted = $formatter->format($date);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return $this->plainDate($date, $style) . ($withTime ? ' ' . $date->format(str_starts_with($this->region, 'en') ? 'g:i A' : 'H:i') : '');
    }

    private function plainDate(DateTimeImmutable $date, string $style): string
    {
        $english = str_starts_with($this->region, 'en');
        $month = self::MONTHS[$english ? 'en' : 'es'][(int) $date->format('n') - 1];
        $day = $date->format('j');
        $year = $date->format('Y');

        return match ($style) {
            'short' => $date->format($this->region === 'en_US' ? 'm/d/Y' : 'd/m/Y'),
            'medium' => $english ? substr($month, 0, 3) . " {$day}, {$year}" : "{$day} " . mb_substr($month, 0, 3) . " {$year}",
            default => $english ? "{$month} {$day}, {$year}" : "{$day} de {$month} de {$year}",
        };
    }
}
