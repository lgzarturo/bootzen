<?php

declare(strict_types=1);

use BootZen\Auth\Hash;
use BootZen\Auth\PasswordReset;
use BootZen\Core\Cache;
use BootZen\Core\Config;
use BootZen\Core\Container;
use BootZen\Core\HttpException;
use BootZen\Core\Logger;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Pipeline;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\LogMailer;
use BootZen\Mail\Message;
use BootZen\Middleware\RateLimit;
use BootZen\Middleware\SecurityHeaders;
use BootZen\Middleware\VerifyCsrfToken;
use BootZen\Security\RateLimiter;
use BootZen\Session\ArraySessionStore;
use BootZen\Session\Session;

/**
 * Ejecuta un middleware con un destino que responde 'ok'.
 */
function runMiddleware(MiddlewareInterface $middleware, Request $request): Response
{
    return $middleware->process($request, static fn (): Response => Response::html('ok'));
}

it('hace y verifica hashes de contraseña', function (): void {
    $hash = Hash::make('secreto-largo');

    expect($hash)->not->toBe('secreto-largo')
        ->and(Hash::check('secreto-largo', $hash))->toBeTrue()
        ->and(Hash::check('otro', $hash))->toBeFalse()
        ->and(Hash::check('x', ''))->toBeFalse()
        ->and(Hash::needsRehash($hash))->toBeFalse()
        ->and(Hash::needsRehash(password_hash('x', PASSWORD_BCRYPT, ['cost' => 4])))->toBe(defined('PASSWORD_ARGON2ID'));
});

it('cuenta intentos por ventana y se reinicia', function (): void {
    $limiter = new RateLimiter(new Cache(null));

    expect($limiter->tooManyAttempts('login', 2))->toBeFalse()
        ->and($limiter->hit('login', 60))->toBe(1)
        ->and($limiter->hit('login', 60))->toBe(2)
        ->and($limiter->attempts('login'))->toBe(2)
        ->and($limiter->remaining('login', 3))->toBe(1)
        ->and($limiter->tooManyAttempts('login', 2))->toBeTrue()
        ->and($limiter->availableIn('login'))->toBeGreaterThan(55)->toBeLessThanOrEqual(60);

    $limiter->clear('login');

    expect($limiter->attempts('login'))->toBe(0)
        ->and($limiter->availableIn('login'))->toBe(0)
        ->and($limiter->tooManyAttempts('login', 2))->toBeFalse();
});

it('guarda la caché en memoria sin directorio', function (): void {
    $cache = new Cache(null);
    $cache->set('a', [1, 2]);
    $cache->set('vencida', 'x', -1);

    expect($cache->get('a'))->toBe([1, 2])
        ->and($cache->has('vencida'))->toBeFalse()
        ->and($cache->increment('n'))->toBe(1);

    $cache->forget('a');
    expect($cache->has('a'))->toBeFalse();

    $cache->flush();
    expect($cache->has('n'))->toBeFalse();
});

it('limita las peticiones con throttle:max,minutos', function (): void {
    $container = new Container();
    $container->instance(RateLimiter::class, new RateLimiter(new Cache(null)));
    $pipeline = new Pipeline($container);
    $pipeline->alias('throttle', RateLimit::class);
    $request = new Request('GET', '/api', [], [], [], ['REMOTE_ADDR' => '203.0.113.5']);
    $send = static fn (): Response => $pipeline->run(['throttle:2,1'], $request, static fn (): Response => Response::html('ok'));

    $primera = $send();

    expect($primera->getHeader('X-RateLimit-Limit'))->toBe('2')
        ->and($primera->getHeader('X-RateLimit-Remaining'))->toBe('1')
        ->and($send()->getHeader('X-RateLimit-Remaining'))->toBe('0');

    try {
        $send();
        $this->fail('Debió responder 429');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(429)
            ->and($e->getHeaders()['Retry-After'])->toMatch('/^\d+$/')
            ->and($e->getMessage())->toContain('Demasiadas peticiones');
    }

    // Otra IP tiene su propio contador
    $otra = new Request('GET', '/api', [], [], [], ['REMOTE_ADDR' => '203.0.113.6']);
    expect($pipeline->run(['throttle:2,1'], $otra, static fn (): Response => Response::html('ok'))->getStatusCode())->toBe(200);
});

it('rechaza parámetros inválidos de throttle y middleware sin parámetros', function (): void {
    $container = new Container();
    $container->instance(RateLimiter::class, new RateLimiter(new Cache(null)));
    $pipeline = new Pipeline($container);
    $destino = static fn (): Response => Response::html('ok');

    expect(fn () => $pipeline->run([RateLimit::class . ':muchos'], new Request('GET', '/'), $destino))
        ->toThrow(InvalidArgumentException::class, 'throttle espera números positivos')
        ->and(fn () => $pipeline->run([SecurityHeaders::class . ':x'], new Request('GET', '/'), $destino))
        ->toThrow(InvalidArgumentException::class, 'no acepta parámetros')
        ->and($pipeline->expand(['throttle:5,1']))->toBe(['throttle:5,1']);

    $pipeline->alias('throttle', RateLimit::class);
    expect($pipeline->expand(['throttle:5,1']))->toBe([RateLimit::class . ':5,1']);
});

it('agrega las cabeceras de seguridad sin pisar las propias', function (): void {
    $middleware = new SecurityHeaders(new Config());
    $response = runMiddleware($middleware, new Request('GET', '/'));

    expect($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->getHeader('Content-Security-Policy'))->toBe(SecurityHeaders::DEFAULT_CSP)
        ->and($response->getHeader('Strict-Transport-Security'))->toBeNull();

    $https = $middleware->process(
        new Request('GET', '/', [], [], [], ['HTTPS' => 'on']),
        static fn (): Response => Response::html('ok')->withHeader('X-Frame-Options', 'DENY')
    );

    expect($https->getHeader('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
        ->and($https->getHeader('X-Frame-Options'))->toBe('DENY');

    $sinCsp = runMiddleware(new SecurityHeaders(new Config(['security' => ['csp' => null, 'hsts' => false]])), new Request('GET', '/', [], [], [], ['HTTPS' => 'on']));

    expect($sinCsp->getHeader('Content-Security-Policy'))->toBeNull()
        ->and($sinCsp->getHeader('Strict-Transport-Security'))->toBeNull();
});

describe('VerifyCsrfToken', function (): void {
    beforeEach(function (): void {
        $this->session = new Session(new ArraySessionStore(), Session::generateId());
        $this->csrf = new VerifyCsrfToken(new Config(['app' => ['env' => 'production'], 'session' => ['csrf_except' => ['webhooks/*']]]));
        $this->post = fn (array $body = [], array $headers = [], string $uri = '/tareas'): Request
            => (new Request('POST', $uri, $headers, $body))->withSession($this->session);
    });

    it('deja pasar el token correcto en el campo o en la cabecera', function (): void {
        $token = $this->session->token();

        expect(runMiddleware($this->csrf, ($this->post)(['_token' => $token]))->getBody())->toBe('ok')
            ->and(runMiddleware($this->csrf, ($this->post)([], ['X-CSRF-TOKEN' => $token]))->getBody())->toBe('ok')
            ->and(runMiddleware($this->csrf, new Request('GET', '/'))->getBody())->toBe('ok');
    });

    it('responde 419 sin token o con uno incorrecto', function (array $body): void {
        $this->session->token();

        try {
            runMiddleware($this->csrf, ($this->post)($body));
            $this->fail('Debió responder 419');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(419)
                ->and($e->getMessage())->toContain('La sesión expiró');
        }
    })->with([
        'sin token' => [[]],
        'incorrecto' => [['_token' => 'falso']],
        'no es texto' => [['_token' => ['x']]],
    ]);

    it('no verifica rutas excluidas ni en APP_ENV=testing', function (): void {
        expect(runMiddleware($this->csrf, ($this->post)([], [], '/webhooks/stripe'))->getBody())->toBe('ok')
            ->and(runMiddleware(new VerifyCsrfToken(new Config(['app' => ['env' => 'testing']])), new Request('POST', '/'))->getBody())->toBe('ok');
    });
});

it('guarda los correos en memoria o en el log', function (): void {
    $array = new ArrayMailer();
    $array->send(new Message('ana@ejemplo.com', 'Hola', 'Texto'));

    expect($array->sent())->toHaveCount(1)
        ->and($array->sent()[0]->subject)->toBe('Hola');

    $dir = makeTempDir('bootzen-mail');
    (new LogMailer(new Logger($dir)))->send(new Message('ana@ejemplo.com', 'Recupera tu contraseña', 'Enlace: /reset/abc'));
    $log = (string) file_get_contents((string) glob($dir . '/*.log')[0]);
    removeDir($dir);

    expect($log)->toContain('Correo para ana@ejemplo.com: Recupera tu contraseña')
        ->and($log)->toContain('Enlace: /reset/abc');
});

it('crea, valida y borra tokens de recuperación guardando solo el hash', function (): void {
    $db = memoryDb(false);
    $db->exec('CREATE TABLE password_resets (email TEXT PRIMARY KEY, token TEXT NOT NULL, created_at TEXT NOT NULL)');
    $resets = new PasswordReset($db);

    $primero = $resets->create('ana@ejemplo.com');
    $token = $resets->create('ana@ejemplo.com');

    expect($db->table('password_resets')->count())->toBe(1)
        ->and($db->table('password_resets')->value('token'))->toBe(hash('sha256', $token))
        ->and($resets->isValid('ana@ejemplo.com', $token))->toBeTrue()
        ->and($resets->isValid('ana@ejemplo.com', $primero))->toBeFalse()
        ->and($resets->isValid('otro@ejemplo.com', $token))->toBeFalse();

    $db->table('password_resets')->where('email', 'ana@ejemplo.com')->update(['created_at' => date('Y-m-d H:i:s', time() - 7200)]);
    expect($resets->isValid('ana@ejemplo.com', $token))->toBeFalse();

    $resets->delete('ana@ejemplo.com');
    expect($db->table('password_resets')->count())->toBe(0);
});
