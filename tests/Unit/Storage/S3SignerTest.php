<?php

declare(strict_types=1);

use BootZen\Storage\S3Signer;

/**
 * Ejemplos de la documentación de AWS ("Signature Calculations for the Authorization Header" y
 * "Authenticating Requests: Using Query Parameters"), con sus credenciales y firmas publicadas.
 */
beforeEach(function (): void {
    $this->signer = new S3Signer('AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'us-east-1');
    $this->time = new DateTimeImmutable('2013-05-24 00:00:00', new DateTimeZone('UTC'));
});

it('firma un GET con Range como el ejemplo de AWS', function (): void {
    $headers = $this->signer->sign('GET', '/test.txt', [], [
        'Host' => 'examplebucket.s3.amazonaws.com',
        'Range' => 'bytes=0-9',
    ], hash('sha256', ''), $this->time);

    expect($headers['authorization'])->toBe(
        'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, '
        . 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, '
        . 'Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41'
    );
});

it('firma un PUT con contenido como el ejemplo de AWS', function (): void {
    $headers = $this->signer->sign('PUT', '/test%24file.text', [], [
        'Host' => 'examplebucket.s3.amazonaws.com',
        'Date' => 'Fri, 24 May 2013 00:00:00 GMT',
        'x-amz-storage-class' => 'REDUCED_REDUNDANCY',
    ], hash('sha256', 'Welcome to Amazon S3.'), $this->time);

    expect($headers['authorization'])->toEndWith('Signature=98ad721746da40c64f1a55b78f14c238d841ea1380cd77a1b5971af0ece108bd');
});

it('prefirma una URL como el ejemplo de AWS', function (): void {
    $query = $this->signer->presign('GET', 'examplebucket.s3.amazonaws.com', '/test.txt', 86400, $this->time);

    expect($query)->toContain('X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404')
        ->toContain('X-Amz-Credential=AKIAIOSFODNN7EXAMPLE%2F20130524%2Fus-east-1%2Fs3%2Faws4_request');
});
