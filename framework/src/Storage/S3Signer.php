<?php

/**
 * S3Signer.php
 *
 * Firma AWS Signature Version 4 para S3 y servicios compatibles (R2, Spaces, B2, Wasabi, MinIO,
 * GCS interoperable): cabecera Authorization y URLs prefirmadas.
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

use DateTimeImmutable;
use DateTimeZone;
use SensitiveParameter;

/**
 * La URI llega ya codificada (cada segmento con rawurlencode); el firmador no la vuelve a codificar.
 *
 * @internal Lo usa S3Disk.
 */
final class S3Signer
{
    public const UNSIGNED = 'UNSIGNED-PAYLOAD';

    public function __construct(
        private readonly string $key,
        #[SensitiveParameter]
        private readonly string $secret,
        private readonly string $region,
        private readonly string $service = 's3'
    ) {
    }

    /**
     * Cabeceras firmadas para una petición: las recibidas más x-amz-date, x-amz-content-sha256 y Authorization.
     *
     * @param array<string, string> $query
     * @param array<string, string> $headers Deben incluir host
     * @return array<string, string>
     */
    public function sign(string $method, string $uri, array $query, array $headers, string $payloadHash, DateTimeImmutable $time): array
    {
        $amzDate = self::utc($time)->format('Ymd\THis\Z');
        $headers = array_change_key_case($headers, CASE_LOWER) + ['x-amz-date' => $amzDate, 'x-amz-content-sha256' => $payloadHash];
        ksort($headers);

        $signedHeaders = implode(';', array_keys($headers));
        $canonical = implode("\n", [
            $method,
            $uri,
            self::canonicalQuery($query),
            implode('', array_map(static fn (string $name, string $value): string => $name . ':' . trim((string) preg_replace('/\s+/', ' ', $value)) . "\n", array_keys($headers), $headers)),
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = $this->scope($time);
        $signature = $this->signature($canonical, $time, $scope);
        $headers['authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return $headers;
    }

    /**
     * Query string de una URL prefirmada (válida $expires segundos desde $time).
     *
     * @param array<string, string> $extra Parámetros adicionales que también se firman (por ejemplo response-content-disposition)
     */
    public function presign(string $method, string $host, string $uri, int $expires, DateTimeImmutable $time, array $extra = []): string
    {
        $scope = $this->scope($time);
        $query = $extra + [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$this->key}/{$scope}",
            'X-Amz-Date' => self::utc($time)->format('Ymd\THis\Z'),
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];

        $canonical = implode("\n", [$method, $uri, self::canonicalQuery($query), "host:{$host}\n", 'host', self::UNSIGNED]);
        $query['X-Amz-Signature'] = $this->signature($canonical, $time, $scope);

        return self::canonicalQuery($query);
    }

    /**
     * @param array<string, string> $query
     */
    public static function canonicalQuery(array $query): string
    {
        ksort($query, SORT_STRING);

        return implode('&', array_map(
            static fn (string $key, string $value): string => rawurlencode($key) . '=' . rawurlencode($value),
            array_keys($query),
            $query
        ));
    }

    private function signature(string $canonical, DateTimeImmutable $time, string $scope): string
    {
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', self::utc($time)->format('Ymd\THis\Z'), $scope, hash('sha256', $canonical)]);

        $key = hash_hmac('sha256', self::utc($time)->format('Ymd'), 'AWS4' . $this->secret, true);
        foreach ([$this->region, $this->service, 'aws4_request'] as $part) {
            $key = hash_hmac('sha256', $part, $key, true);
        }

        return hash_hmac('sha256', $stringToSign, $key);
    }

    private function scope(DateTimeImmutable $time): string
    {
        return self::utc($time)->format('Ymd') . "/{$this->region}/{$this->service}/aws4_request";
    }

    private static function utc(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
