# Referencia de BootZen

> Generada con `bin/bootzen-reference` a partir del código de `framework/`. No la edites a mano.
> Firmas públicas con la primera línea de su PHPDoc. Para el cómo y el porqué, lee `AGENTS.md`
> y las recetas de `docs/ai/recipes/`.

## Helpers globales

- `__(string $text, array $replace = [], ?string $locale = null): string`: Traduce un texto escrito en español al idioma activo y reemplaza :marcadores.
- `abort(int $statusCode = 404, string $message = '', array $headers = []): never`: Interrumpe la petición lanzando una HttpException con el código indicado.
- `active_session(): ?Session`: Sesión de la petición actual, o null si la ruta no pasa por StartSession (grupo web).
- `app(?string $abstract = null): mixed`: Devuelve la Application actual o resuelve un servicio del contenedor.
- `array_flatten(array $array): array`: Aplana un arreglo multidimensional en uno simple.
- `array_group_by(array $array, callable $keySelector): array`: Agrupa los elementos de un arreglo según una función clave.
- `asset(string $path): string`: Path de un recurso público con versión según su fecha de modificación: '/assets/app.css?v=...'.
- `auth(): Auth`: Autenticación con sesión: user(), id(), check(), attempt(), login(), logout().
- `authorize(string $ability, Model|string|null $subject = null, int $status = 403): void`: Corta la petición con 403 si el usuario no puede hacer $ability. Con $status = 404 no revela que el registro existe.
- `back(int $status = 302): Response`: Redirige a la última página vista (o a '/' si no hay una). Debe retornarse desde el handler.
- `base_path(string $path = ''): string`: Obtiene la ruta absoluta a la raíz del proyecto.
- `cache(): Cache`: Devuelve la caché de archivos de la aplicación.
- `can(string $ability, Model|string|null $subject = null): bool`: Indica si el usuario con sesión puede hacer $ability: con un modelo decide su política (app/Policies/TareaPolicy.php); sin modelo, los permisos de su rol (config/auth.php).
- `class_list(array $classes): string`: Arma una lista de clases CSS con condiciones, útil para Tailwind en las vistas.
- `config(string $key, mixed $default = null): mixed`: Obtiene un valor de configuración con notación de puntos.
- `csrf_field(): string`: Campo oculto con el token CSRF (lo genera @csrf).
- `csrf_token(): string`: Token CSRF de la sesión actual ('' si la ruta no tiene sesión).
- `current_path(): string`: Path de la petición actual ('/admin/productos'), para marcar el enlace activo de un menú.
- `db(): Connection`: Conexión a la base de datos de la aplicación.
- `dd(mixed ...$args): never`: Vuelca los valores recibidos y detiene la ejecución lanzando una excepción.
- `dispatch(Job $job, int $delay = 0): int`: Encola un job; con $delay (segundos) no se ejecuta antes. Con QUEUE_DRIVER=sync se ejecuta en el momento.
- `download(string $filePath, ?string $fileName = null): Response`: Crea una respuesta que fuerza la descarga de un archivo. Debe retornarse desde el handler.
- `e(Stringable|string|int|float|bool|null $value): string`: Escapa un valor para salida segura en HTML.
- `env(string $key, mixed $default = null): mixed`: Obtiene el valor de una variable de entorno.
- `fake(): Fake`: Datos falsos (nombres, correos, textos, números, fechas) en el idioma activo. En tests la semilla es fija: cada client() empieza con la misma secuencia.
- `file_url(string $path, ?string $disk = null): string`: URL pública de un archivo guardado con storePublic() (o del disco indicado).
- `format_bytes(int $bytes, int $precision = 2): string`: Formatea una cantidad de bytes en una cadena legible (KB, MB, etc.).
- `format_date(DateTimeInterface|string $date, string $style = 'medium', bool $withTime = false): string`: Fecha en el idioma activo: short (07/11/2026), medium (7 nov 2026) o long (7 de noviembre de 2026).
- `format_money(int|float $amount, ?string $currency = null): string`: Cantidad en la moneda indicada (por defecto APP_CURRENCY) con el formato del idioma activo.
- `format_number(int|float $number, int $decimals = 0): string`: Número con los separadores del idioma activo.
- `formatter(): Formatter`: Formateador de números, moneda y fechas para el idioma activo (lo usan format_number(), format_money() y format_date()).
- `gate(): Gate`: Permisos y políticas (Gate).
- `get_client_ip(): string`: IP del cliente de la petición actual. Las cabeceras X-Forwarded-* solo cuentan si la conexión viene de un proxy de confianza (config security.trusted_proxies); igual que $request->getClientIp().
- `home_url(): string`: URL a donde va un usuario autenticado: config auth.home, que puede ser un nombre de ruta ('dashboard', respeta el idioma de $router->localized()) o una ruta ('/panel').
- `hreflang_links(): HtmlString`: Etiquetas <link rel="alternate" hreflang> de la página actual en cada idioma (para SEO). Vacío si la página no es de $router->localized(). Va en el <head> del layout.
- `is_ajax(): bool`: Verifica si la petición actual es AJAX.
- `json_response(array $data, int $status = 200, array $headers = []): Response`: Crea una respuesta JSON. Debe retornarse desde el handler.
- `locale(): string`: Idioma activo ('es', 'en'...). Úsalo en <html lang="{{ locale() }}">.
- `locale_url(string $locale): string`: URL de la página actual en otro idioma: la ruta con su prefijo si es de $router->localized(), o la misma página con ?lang=xx si no.
- `logger(string $message, string $level = 'info', array $context = []): void`: Registra un mensaje en el log de la aplicación (storage/logs/app-AAAA-MM-DD.log).
- `mailer(): MailService`: Servicio de correo configurado en config/mail.php: send() envía ahora, queue() lo deja en la cola.
- `mime_type(string $filePath): string`: Obtiene el tipo MIME de un archivo.
- `old(string $key, mixed $default = ''): mixed`: Valor que el usuario envió en el formulario anterior (tras un error de validación). Admite notación de puntos: old('items.0.precio').
- `public_path(string $path = ''): string`: Obtiene la ruta absoluta al directorio public.
- `queue(): Queue`: Cola configurada en config/queue.php (sync o database).
- `redirect(string $url, int $status = 302): Response`: Crea una respuesta de redirección. Debe retornarse desde el handler.
- `route(string $name, array $parameters = []): string`: Genera el path de una ruta con nombre. Los parámetros sobrantes se agregan como query string.
- `session(?string $key = null, mixed $default = null): mixed`: Sin argumentos devuelve la sesión; con una clave, su valor (o el valor por defecto si no hay sesión).
- `signed_route(string $name, array $parameters = [], int $minutes = 60): string`: URL completa y firmada de una ruta con nombre, que vence en $minutes. La ruta lleva ->middleware('signed'); si alguien cambia un parámetro o el enlace venció, responde 403.
- `slugify(string $text): string`: Convierte un texto en un slug apto para URLs.
- `storage(?string $disk = null): Storage`: Disco de config/storage.php; sin nombre, el privado por defecto (STORAGE_DISK).
- `storage_path(string $path = ''): string`: Obtiene la ruta absoluta al directorio storage.
- `str_random(int $length = 16): string`: Genera una cadena aleatoria alfanumérica criptográficamente segura.
- `temporary_url(string $path, int $minutes = 30, ?string $disk = null): string`: URL que permite descargar un archivo privado durante $minutes minutos (del disco privado por defecto).
- `trans_choice(string $text, int|float $count, array $replace = []): string`: Elige singular o plural según la cantidad: 'una tarea|:count tareas' (o '{0} ninguna|una|:count').
- `translator(): Translator`: Traductor de la aplicación (idioma activo, idiomas permitidos).
- `url(string $path = ''): string`: Genera una URL absoluta basada en config('app.url') o, en su defecto, APP_URL.
- `validate_csrf_token(?string $token): bool`: Compara un token con el de la sesión. VerifyCsrfToken ya lo hace en el grupo web.
- `view(string $name, array $data = [], int $status = 200): Response`: Renderiza una vista y la devuelve como respuesta HTML.

## HTTP

### Request

Clase Request

- `::fromGlobals(?string $rawBody = null): self`: Crea una instancia de Request a partir de las variables globales PHP.
- `getMethod(): string`: Obtiene el método HTTP de la solicitud
- `getUri(): string`: Obtiene la URI completa de la solicitud
- `getPath(): string`: Obtiene la ruta (path) de la solicitud
- `getQuery(?string $key = null, mixed $default = null): mixed`: Obtiene los parámetros de consulta de la solicitud
- `getHeader(string $name): ?string`: Obtiene el valor de una cabecera HTTP
- `getHeaders(): array`: Obtiene todas las cabeceras de la solicitud
- `getBody(?string $key = null, mixed $default = null): mixed`: Obtiene los datos del cuerpo de la solicitud
- `getFile(string $key): ?array`: Obtiene un archivo subido por la solicitud
- `file(string $key): ?UploadedFile`: Archivo subido como objeto (tipo detectado, store(), storePublic()), o null si no llegó.
- `getFiles(): array`: Obtiene todos los archivos subidos por la solicitud
- `getServer(?string $key = null, mixed $default = null): mixed`: Obtiene las variables del servidor
- `getCookie(string $name, mixed $default = null): mixed`: Obtiene el valor de una cookie
- `withAttributes(array $attributes): self`: Devuelve una copia con atributos agregados (los parámetros de ruta, por ejemplo).
- `withRoute(Route $route): self`: Devuelve una copia asociada a la ruta que la resolvió.
- `session(): Session`: Sesión de la petición.
- `hasSession(): bool`
- `validate(array $rules, array $messages = [], array $attributes = []): array`: Valida la entrada (cuerpo, query string y archivos) y devuelve solo los campos validados. Si falla lanza ValidationException: en formularios vuelve a la página anterior con los errores y el input; en peticiones JSON responde 422 con los errores.
- `getRoute(): ?Route`: Ruta que resolvió la petición, o null si aún no se enruta.
- `param(string $name, mixed $default = null): mixed`: Obtiene un parámetro de ruta.
- `params(): array`: Todos los parámetros de ruta.
- `input(?string $key = null, mixed $default = null): mixed`: Obtiene un dato de entrada: cuerpo o query string (el cuerpo tiene prioridad). Acepta notación de puntos para arrays anidados: 'items.0.precio'.
- `all(): array`: Todos los datos de entrada (query string + cuerpo), sin el campo interno _method.
- `only(array $keys): array`: Solo las claves indicadas que estén presentes.
- `except(array $keys): array`: Todos los datos de entrada excepto las claves indicadas.
- `has(string $key): bool`: Indica si la clave está presente en la entrada (aunque esté vacía).
- `filled(string $key): bool`: Indica si la clave está presente y no está vacía.
- `boolean(string $key, bool $default = false): bool`: Interpreta un dato como booleano ('1', 'true', 'on', 'yes' son true).
- `integer(string $key, int $default = 0): int`: Interpreta un dato como entero; devuelve el valor por defecto si no es numérico.
- `wantsJson(): bool`: Indica si el cliente espera una respuesta JSON (Accept o Content-Type JSON).
- `isJson(): bool`: Determina si la solicitud es de tipo JSON
- `isAjax(): bool`: Determina si la solicitud es AJAX
- `::setTrustedProxies(array $proxies): void`: Define los proxies de confianza (lo hace Application desde config security.trusted_proxies). Solo de ellos se aceptan X-Forwarded-For y X-Forwarded-Proto.
- `::trustedProxies(): array`: Proxies de confianza ya expandidos ('private' se convierte en sus rangos).
- `isSecure(): bool`: Determina si la solicitud es segura (HTTPS). X-Forwarded-Proto solo cuenta si la conexión viene de un proxy de confianza.
- `getClientIp(): string`: Obtiene la IP del cliente. Sin proxies de confianza es siempre la de la conexión (REMOTE_ADDR): las cabeceras las escribe quien quiera y con ellas se saltarían los límites de peticiones. Con proxies de confianza se recorre X-Forwarded-For desde el final y se devuelve la primera IP que no es un proxy.

### Response

Clase Response

- `::stream(Closure $callback, int $statusCode = 200, array $headers = []): self`: Respuesta que se escribe mientras se genera (echo dentro de $callback): exportaciones grandes sin cargarlas en memoria.
- `isStreamed(): bool`: Indica si el cuerpo se escribe en streaming.
- `::json(JsonSerializable|array $data, int $statusCode = 200, array $headers = []): self`: Crea una respuesta JSON
- `::html(string $html, int $statusCode = 200, array $headers = []): self`: Crea una respuesta HTML
- `::redirect(string $url, int $statusCode = 302): self`: Crea una respuesta de redirección HTTP
- `::noContent(array $headers = []): self`: Crea una respuesta 204 No Content (sin cuerpo).
- `::notFound(string $message = 'Not Found'): self`: Crea una respuesta 404 Not Found
- `::serverError(string $message = 'Internal Server Error'): self`: Crea una respuesta 500 Internal Server Error
- `::unauthorized(string $message = 'Unauthorized'): self`: Crea una respuesta 401 Unauthorized
- `::forbidden(string $message = 'Forbidden'): self`: Crea una respuesta 403 Forbidden
- `::badRequest(string $message = 'Bad Request'): self`: Crea una respuesta 400 Bad Request
- `::tooManyRequests(string $message = 'Too Many Requests'): self`: Crea una respuesta 429 Too Many Requests
- `getStatusCode(): int`: Obtiene el código de estado HTTP de la respuesta
- `getStatusText(): string`: Obtiene el texto descriptivo del código de estado HTTP
- `getBody(): string`: Obtiene el cuerpo de la respuesta
- `getHeaders(): array`: Obtiene todas las cabeceras de la respuesta
- `getHeader(string $name): ?string`: Obtiene el valor de una cabecera HTTP
- `withStatus(int $statusCode): self`: Devuelve una nueva instancia con el código de estado modificado
- `withHeader(string $name, string $value): self`: Devuelve una nueva instancia con una cabecera añadida o modificada
- `withHeaders(array $headers): self`: Devuelve una nueva instancia con varias cabeceras agregadas o reemplazadas
- `withBody(string $body): self`: Devuelve una nueva instancia con el cuerpo modificado
- `with(array|string $key, mixed $value = null): self`: Agrega datos flash: estarán en session() solo en la siguiente petición. Necesita el middleware StartSession (grupo web).
- `withErrors(array $errors): self`: Agrega errores de validación para la siguiente petición (los lee @error y $errors).
- `withInput(array $input): self`: Guarda el input para que old() lo devuelva en la siguiente petición.
- `getFlash(): array`: Datos flash pendientes de guardar en la sesión.
- `getCookies(): array`: Cookies de la respuesta: nombre => [value, expires, path, domain, secure, httponly, samesite].
- `withCookie(string $name, string $value, int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true, string $sameSite = 'Lax'): self`: Devuelve una nueva instancia con una cookie añadida
- `send(): void`: Envía la respuesta HTTP al cliente (status, headers, cookies y body)
- `isSuccessful(): bool`: Determina si la respuesta es exitosa (2xx)
- `isRedirect(): bool`: Determina si la respuesta es una redirección (3xx)
- `isClientError(): bool`: Determina si la respuesta es un error del cliente (4xx)
- `isServerError(): bool`: Determina si la respuesta es un error del servidor (5xx)

### UploadedFile

Se obtiene con $request->file('campo'). Valida antes con las reglas file, image, mimes y max.

- `::fromArray(?array $file): ?self`: Crea el archivo desde una entrada de $_FILES, o null si el campo llegó sin archivo.
- `isValid(): bool`: Indica si el archivo llegó completo y sin errores.
- `error(): int`
- `path(): string`: Ruta temporal del archivo.
- `clientName(): string`: Nombre original que envió el navegador (solo para mostrar; no lo uses como ruta).
- `size(): int`: Tamaño en bytes.
- `mimeType(): string`: Tipo MIME detectado por el contenido (no el que dice el navegador).
- `extension(): string`: Extensión con la que se guarda: la del tipo detectado, o la del nombre si es inofensiva; si no, 'bin'.
- `store(string $directory = 'uploads', ?string $disk = null): string`: Guarda el archivo en un disco privado (por defecto STORAGE_DISK: storage/app) y devuelve su ruta en el disco, por ejemplo 'contratos/a81b...pdf'. Para entregarlo: temporary_url($ruta) o storage()->download($ruta).
- `storePublic(string $directory = '', ?string $disk = null): string`: Guarda el archivo en el disco público (por defecto STORAGE_PUBLIC_DISK: public/) bajo uploads/ y devuelve su ruta, por ejemplo 'uploads/avatares/3f9c...jpg'. Se muestra con file_url($ruta). Solo para imágenes, PDFs y archivos que cualquiera puede ver.
- `resize(int $maxWidth, ?int $maxHeight = null, int $quality = 85): self`: Copia de la imagen reducida para caber en $maxWidth x $maxHeight (nunca la agranda), sin metadatos EXIF y con la orientación de la cámara aplicada. Se guarda igual: ->resize(1600)->storePublic('productos').
- `cover(int $width, int $height, int $quality = 85): self`: Copia de la imagen recortada al centro para llenar $width x $height (avatares, miniaturas), sin metadatos EXIF. Ejemplo: ->cover(300, 300)->storePublic('avatares').
- `contents(): string`: Contenido del archivo subido.

### Router

Router HTTP.

- `get(string $path, mixed $handler): Route`
- `post(string $path, mixed $handler): Route`
- `put(string $path, mixed $handler): Route`
- `patch(string $path, mixed $handler): Route`
- `delete(string $path, mixed $handler): Route`
- `options(string $path, mixed $handler): Route`
- `match(array $methods, string $path, mixed $handler): Route`: Registra una ruta para varios métodos.
- `any(string $path, mixed $handler): Route`: Registra una ruta para todos los métodos comunes.
- `group(array $attributes, Closure $callback): void`: Agrupa rutas con atributos comunes.
- `localized(Closure $callback): void`: Rutas en varios idiomas para SEO: el idioma por defecto (APP_LOCALE) sin prefijo y los demás de APP_LOCALES con el suyo (/en/...). Cada grupo fija su idioma con el middleware locale:xx; las rutas de otros idiomas se nombran 'en::nombre' y route('nombre') elige la del idioma activo.
- `localizedName(string $name, string $locale): ?string`: Nombre de la ruta en otro idioma si viene de localized() ('en::precios' → 'precios' para el idioma por defecto). Null si la ruta no tiene versiones por idioma.
- `resource(string $name, string $controller, array $options = []): array`: Registra las rutas CRUD de un recurso, con nombres '{nombre}.{accion}'.
- `aliasMiddleware(string $name, string $class): void`: Registra un alias de middleware.
- `middlewareGroup(string $name, array $middleware): void`: Registra un grupo de middleware.
- `getPipeline(): Pipeline`
- `resolve(Request $request): Response`: Resuelve la petición: busca la ruta, ejecuta su middleware y el handler.
- `url(string $name, array $parameters = []): string`: Genera el path de una ruta con nombre.
- `getRoute(string $name): ?Route`: Busca una ruta por nombre.
- `getRoutes(): array`: Todas las rutas registradas, en orden.
- `::normalizePath(string $path): string`: Normaliza un path: empieza con '/', sin barras repetidas y sin '/' final (salvo la raíz).

### Route

Ruta registrada en el Router.

- `getMethods(): array`: Métodos HTTP aceptados.
- `allowsMethod(string $method): bool`: Indica si la ruta acepta el método. HEAD se atiende con las rutas GET.
- `getPath(): string`
- `getHandler(): mixed`
- `getMiddleware(): array`
- `getName(): ?string`
- `name(string $name): self`: Asigna el nombre de la ruta (se le antepone el prefijo 'as' de los grupos).
- `middleware(MiddlewareInterface|array|string $middleware): self`: Agrega middleware a la ruta (clase, alias o grupo).
- `where(array|string $parameter, ?string $pattern = null): self`: Restringe uno o varios parámetros con expresiones regulares.
- `getParameterNames(): array`: Nombres de los parámetros del patrón, en orden.
- `matches(string $path): ?array`: Compara la ruta con un path y devuelve sus parámetros, o null si no coincide.
- `url(array $parameters = []): string`: Genera el path de la ruta con los parámetros dados. Los sobrantes se agregan como query string.

## Validación

### Validator

Valida un array de datos contra un conjunto de reglas.

- `::make(array $data, array $rules, array $messages = [], array $attributes = []): self`
- `::setConnectionResolver(?Closure $resolver): void`
- `passes(): bool`
- `fails(): bool`
- `errors(): array`: Errores por campo. Los campos con comodín se reportan con su índice real: 'items.0.precio'.
- `validate(): array`: Devuelve los datos validados o lanza ValidationException.
- `validated(): array`: Solo los campos que tienen reglas. Los vacíos opcionales quedan como null.

### ValidationException

Lleva los errores por campo.

- `::withMessages(array $messages): self`: Crea la excepción con mensajes escritos a mano.
- `errors(): array`

## Base de datos

### Model

Modelo base.

- `::setConnectionResolver(?Closure $resolver): void`: Define cómo obtener la conexión (lo hace Application).
- `::connection(): Connection`
- `::table(): string`
- `::primaryKey(): string`
- `::query(): ModelQuery`: Consulta sobre la tabla del modelo.
- `::all(): array`
- `::find(string|int $id): ?static`
- `::findOrFail(string|int $id): static`
- `::where(string $column, mixed $operator, mixed $value = null): ModelQuery`: Inicia una consulta con una condición. Con dos argumentos el operador es '='.
- `::create(array $attributes): static`: Crea y guarda un registro con los valores de $fillable.
- `::factory(): Factory`: Factory del modelo: database/factories/{Modelo}Factory.php (make:factory la crea).
- `::hydrate(array $row): static`: Crea una instancia a partir de una fila de la base de datos.
- `fill(array $attributes): static`: Asigna valores de $fillable; el resto se ignora (por ejemplo _token o campos ajenos).
- `save(): bool`: Inserta o actualiza el registro. Solo se envían las columnas que cambiaron.
- `update(array $attributes): bool`: Asigna valores de $fillable y guarda.
- `delete(): bool`: Borra el registro. Devuelve false si no estaba guardado.
- `refresh(): static`: Vuelve a leer el registro de la base de datos.
- `getKey(): string|int|null`
- `exists(): bool`: Indica si el registro está guardado en la base de datos.
- `getDirty(): array`: Columnas modificadas desde la última lectura o guardado.
- `isDirty(): bool`
- `getAttribute(string $key): mixed`: Valor de una columna con su conversión de $casts.
- `setAttribute(string $key, mixed $value): void`: Asigna una columna convirtiendo el valor al formato de la base de datos.
- `toArray(): array`: Valores con sus conversiones, sin las columnas de $hidden. Las fechas como 'Y-m-d H:i:s'.
- `jsonSerialize(): array`

### ModelQuery

Consulta de modelos.

- `where(string $column, mixed $operator, mixed $value = null): self`
- `orWhere(string $column, mixed $operator, mixed $value = null): self`
- `whereIn(string $column, array $values): self`
- `whereNotIn(string $column, array $values): self`
- `whereNull(string $column): self`
- `whereNotNull(string $column): self`
- `whereLike(array $columns, string $term): self`: Busca $term dentro de cualquiera de las columnas (ver QueryBuilder::whereLike).
- `whereRaw(string $sql, array $bindings = []): self`
- `orderBy(string $column, string $direction = 'asc'): self`
- `latest(string $column = 'created_at'): self`
- `limit(int $limit): self`
- `offset(int $offset): self`
- `get(): array`
- `cursor(int $chunk = 500): Generator`: Recorre los modelos en bloques de $chunk filas, sin cargarlos todos en memoria.
- `first(): ?Model`
- `firstOrFail(): Model`
- `find(string|int $id): ?Model`
- `findOrFail(string|int $id): Model`
- `count(): int`
- `exists(): bool`
- `pluck(string $column): array`
- `paginate(int $perPage = 15, int $page = 1): Paginator`
- `update(array $values): int`: Actualiza todas las filas de la consulta (sin pasar por $fillable). Requiere un where.
- `delete(): int`: Borra todas las filas de la consulta. Requiere un where.
- `toBase(): QueryBuilder`: Query builder subyacente, para lo que no cubre ModelQuery.

### QueryBuilder

Consulta fluida sobre una tabla.

- `select(string ...$columns): self`: Columnas a seleccionar. Admite 'tabla.columna' y 'columna as alias'.
- `selectRaw(string $expression, array $bindings = []): self`: Agrega una expresión SQL libre a la selección.
- `where(string $column, mixed $operator, mixed $value = null): self`: Condición AND. Con dos argumentos el operador es '='; comparar con null genera IS NULL.
- `orWhere(string $column, mixed $operator, mixed $value = null): self`: Condición OR.
- `whereIn(string $column, array $values): self`
- `whereNotIn(string $column, array $values): self`
- `whereNull(string $column): self`
- `whereNotNull(string $column): self`
- `whereRaw(string $sql, array $bindings = []): self`: Condición SQL libre con parámetros.
- `whereLike(array $columns, string $term): self`: Busca $term dentro de cualquiera de las columnas (LIKE '%term%'). Los comodines % y _ que escriba el usuario se buscan literales. Sin columnas o con $term vacío no filtra.
- `join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self`
- `leftJoin(string $table, string $first, string $operator, string $second): self`
- `orderBy(string $column, string $direction = 'asc'): self`
- `latest(string $column = 'created_at'): self`: Ordena de más reciente a más antiguo.
- `limit(int $limit): self`
- `offset(int $offset): self`
- `get(): array`
- `cursor(int $chunk = 500): Generator`: Recorre los resultados en bloques de $chunk filas (LIMIT/OFFSET), sin cargarlos todos en memoria. Respeta el orden de la consulta; ignora su limit y offset. Sirve para exportar o procesar tablas grandes; dentro del recorrido se pueden hacer otras consultas.
- `first(): ?array`
- `find(string|int $id, string $key = 'id'): ?array`
- `value(string $column): mixed`: Valor de una columna de la primera fila.
- `pluck(string $column): array`: Valores de una columna.
- `count(): int`
- `exists(): bool`
- `paginate(int $perPage = 15, int $page = 1): Paginator`: Página de resultados.
- `insert(array $values): string`: Inserta una fila y devuelve el id generado.
- `update(array $values): int`: Actualiza las filas que cumplen los where y devuelve cuántas cambió.
- `delete(): int`: Borra las filas que cumplen los where y devuelve cuántas borró.
- `toSql(): string`: SQL de la consulta SELECT (con ? en lugar de valores).
- `getBindings(): array`: Valores enlazados de la consulta SELECT, en orden.

### Connection

Conexión PDO con helpers para consultas, transacciones y el query builder.

- `::fromConfig(array $config, string $basePath): self`: Crea la conexión a partir de la configuración de config/database.php.
- `pdo(): PDO`
- `driver(): string`: Driver de la conexión: sqlite o mysql.
- `table(string $table): QueryBuilder`: Inicia un query builder para una tabla.
- `select(string $sql, array $bindings = []): array`: Ejecuta un SELECT y devuelve todas las filas.
- `selectOne(string $sql, array $bindings = []): ?array`: Ejecuta un SELECT y devuelve la primera fila o null.
- `scalar(string $sql, array $bindings = []): mixed`: Ejecuta un SELECT y devuelve el valor de la primera columna de la primera fila.
- `statement(string $sql, array $bindings = []): int`: Ejecuta INSERT, UPDATE o DELETE y devuelve las filas afectadas.
- `insert(string $sql, array $bindings = []): string`: Ejecuta un INSERT y devuelve el id generado.
- `exec(string $sql): void`: Ejecuta SQL sin parámetros (puede tener varias sentencias). Pensado para migraciones.
- `transaction(Closure $callback): mixed`: Ejecuta el callback dentro de una transacción: confirma si termina bien y revierte si lanza. Las transacciones anidadas se integran a la exterior.
- `quote(string $identifier): string`: Encierra un identificador (tabla o columna) entre comillas del driver. Admite 'tabla.columna' y '*'.

### Paginator

Página de resultados.

- `items(): array`
- `total(): int`
- `perPage(): int`
- `currentPage(): int`
- `lastPage(): int`
- `hasPages(): bool`: Indica si hay más de una página.
- `previousPage(): ?int`
- `nextPage(): ?int`
- `isEmpty(): bool`
- `appends(array $query): self`: Conserva parámetros de query string en los enlaces.
- `url(int $page): string`: Enlace relativo a una página (query string), conservando los parámetros de appends().
- `count(): int`
- `getIterator(): Traversable`
- `toArray(): array`
- `jsonSerialize(): array`

### Factory

Un valor de definition() puede ser otra factory (se crea antes y se usa su id) o un closure que recibe los demás valores ya resueltos. A diferencia de Modelo::create(), la factory asigna todas las columnas, también las que no están en $fillable (user_id, role...).

- `definition(): array`: Valores de una fila de ejemplo. Usa fake() para que cada registro sea distinto.
- `state(Closure|array $state): static`: Cambia valores de la definición. Un closure recibe los valores y la posición en count().
- `afterCreating(Closure $callback): static`: Ejecuta $callback con cada modelo recién guardado (por ejemplo, para crearle registros hijos).
- `count(int $count): FactoryBatch`: Varios registros: ->count(10)->create() devuelve una lista.
- `make(array $attributes = []): Model`: Un modelo con los valores de la definición, sin guardarlo.
- `create(array $attributes = []): Model`: Crea y guarda un registro.

### Listing

Las columnas que se buscan, ordenan y filtran las decide el código, nunca la petición: un sort o un filtro que no está en la lista se ignora. Los valores van siempre como parámetros.

- `::from(ModelQuery $query, Request $request): self`
- `search(array $columns): self`: Busca el texto de ?q= en estas columnas.
- `filters(array $filters): self`: Filtros por columna: 'bool' (filtro[activo]=1|0) o 'date' (filtro[alta_desde] y filtro[alta_hasta], AAAA-MM-DD). Valores inválidos se ignoran.
- `sortable(array $columns, string $default = 'id', string $direction = 'desc'): self`: Columnas por las que se puede ordenar (?sort=nombre&dir=asc) y el orden por defecto.
- `paginate(int $perPage = 20): Paginator`: Página actual (?page=) con la búsqueda, el orden y los filtros en los enlaces.
- `csv(string $filename, array $columns): Response`: Descarga en CSV de todo lo que coincide (no solo la página), escrito por bloques.
- `term(): string`
- `sort(): string`
- `direction(): string`
- `filter(string $name): string`: Valor de un filtro aplicado (para dejarlo seleccionado en el formulario).
- `sortUrl(string $column): string`: Query string que ordena por $column (si ya ordena por ella, invierte la dirección).
- `parameters(): array`: Búsqueda, orden y filtros actuales como parámetros (para enlaces, como el de exportar).

### FactoryBatch

- `state(Closure|array $state): self`: Igual que Factory::state(), para todos los registros del lote.
- `make(array $attributes = []): array`: Arma los modelos sin guardarlos.
- `create(array $attributes = []): array`: Crea y guarda los registros en una transacción.

### Fake

Con semilla los valores se repiten igual en cada corrida (en los tests la semilla es fija). Los correos usan dominios reservados para pruebas (example.com), así nunca le llega nada a nadie.

- `seed(int $seed): self`: Reinicia la secuencia: con la misma semilla salen los mismos valores.
- `unique(): self`: El siguiente valor no repite ninguno que este mismo método ya haya dado con unique(). Si ya no quedan valores distintos, el método lanza InvalidArgumentException.
- `firstName(): string`
- `lastName(): string`
- `name(): string`: Nombre y apellido.
- `email(): string`: Correo en un dominio reservado para pruebas (example.com, example.org o example.net).
- `userName(): string`
- `phoneNumber(): string`
- `company(): string`
- `city(): string`
- `country(): string`
- `address(): string`
- `word(): string`
- `words(int $count = 3): array`
- `sentence(int $words = 4): string`: Frase corta con sentido aparente, buena para títulos: 'Revisar la factura de ventas'. $words es el mínimo de palabras.
- `paragraph(int $sentences = 3): string`: Varias frases terminadas en punto.
- `text(int $maxLength = 200): string`: Texto de hasta $maxLength caracteres, cortado en una palabra completa.
- `number(int $min = 0, int $max = 100): int`: Entero entre $min y $max (incluidos).
- `decimal(float $min = 0.0, float $max = 1000.0, int $decimals = 2): float`: Decimal entre $min y $max con $decimals decimales.
- `boolean(int $chance = 50): bool`: true con probabilidad $chance (porcentaje).
- `date(string $from = '-1 year', string $to = 'now'): string`: Fecha 'Y-m-d' entre $from y $to (cualquier texto que entienda strtotime).
- `dateTime(string $from = '-1 year', string $to = 'now'): string`: Fecha y hora 'Y-m-d H:i:s' entre $from y $to.
- `randomElement(array $items): mixed`: Un elemento al azar de la lista.
- `uuid(): string`: UUID versión 4.
- `url(): string`: URL en un dominio reservado para pruebas.

## Sesión, autenticación y seguridad

### Session

Datos de sesión. Guarda solo escalares y arrays (los objetos no se restauran).

- `::generateId(): string`: Genera un id nuevo: 40 caracteres hexadecimales aleatorios.
- `::isValidId(string $id): bool`
- `getId(): string`
- `get(string $key, mixed $default = null): mixed`
- `put(array|string $key, mixed $value = null): void`
- `has(string $key): bool`
- `forget(string $key): void`
- `pull(string $key, mixed $default = null): mixed`: Devuelve el valor y lo borra de la sesión.
- `all(): array`
- `flash(string $key, mixed $value): void`: Guarda un valor que solo estará disponible en la siguiente petición.
- `getOldInput(string $key, mixed $default = null): mixed`: Valor anterior de un campo de formulario (tras un error de validación).
- `errors(): array`: Errores de validación de la petición anterior: campo => mensajes.
- `token(): string`: Token CSRF de la sesión. Se genera la primera vez que se pide.
- `regenerateToken(): string`
- `regenerate(): void`: Cambia el id de la sesión conservando los datos. Se usa al iniciar sesión para evitar fijación de sesión.
- `invalidate(): void`: Borra todos los datos, cambia el id y genera un token CSRF nuevo (cerrar sesión).
- `previousUrl(): ?string`: URL de la última página vista (GET), para volver tras un error de validación.
- `shouldPersist(): bool`: Indica si hay algo que guardar: una sesión existente o una nueva con datos. Así las peticiones que no usan la sesión (health checks, bots) no crean archivos ni cookies.
- `save(): void`: Guarda la sesión en el almacén.

### Auth

Guard de sesión. Se obtiene con auth() en rutas que pasan por el grupo web.

- `forgetRememberedDevices(Model $user): void`: Borra los "recordarme" del usuario en todos sus dispositivos. Úsalo al restablecer la contraseña: las sesiones abiertas ya se cierran solas porque cambia la huella, pero una cookie de recordarme volvería a entrar.
- `user(): ?Model`: Usuario autenticado, o null si es un invitado (o si la petición no tiene sesión, como un 404).
- `id(): string|int|null`: Id del usuario autenticado, sin consultar la base de datos.
- `check(): bool`
- `guest(): bool`
- `attempt(array $credentials, bool $remember = false): bool`: Busca al usuario por las credenciales (todas menos password) y verifica la contraseña. Si coincide, inicia sesión. Si el hash está desactualizado, lo rehace.
- `validate(array $credentials): ?Model`: Devuelve el usuario si las credenciales son correctas, sin iniciar sesión.
- `login(Model $user, bool $remember = false): void`: Inicia sesión como el usuario. Cambia el id de la sesión y el token CSRF para evitar fijación de sesión. Con $remember deja una cookie que vuelve a iniciar la sesión al regresar (necesita la tabla remember_tokens y el middleware RememberMe en el grupo web).
- `loginUsingId(string|int $id, bool $remember = false): ?Model`: Inicia sesión con el id del usuario; null si no existe.
- `logout(): void`: Cierra la sesión: borra todos sus datos, cambia el id y olvida la cookie de "recordarme".
- `logoutOtherDevices(string $password): bool`: Cierra la sesión en los demás dispositivos: pide la contraseña actual, la vuelve a cifrar (las otras sesiones ven otra huella y se cierran) y borra todos los "recordarme". Esta sesión sigue abierta.
- `intended(string $default = '/'): string`: URL que el usuario intentaba visitar antes de que lo mandaran al login (la guarda el middleware auth).

### Hash

- `::make(string $password): string`
- `::check(string $password, string $hash): bool`
- `::needsRehash(string $hash): bool`: Indica si el hash se hizo con otro algoritmo u opciones y conviene rehacerlo.

### PasswordReset

Necesita la tabla password_resets (email, token, created_at) que crea la migración del scaffold --auth.

- `create(string $email): string`: Crea un token nuevo para el correo (reemplaza al anterior) y lo devuelve en claro.
- `isValid(string $email, string $token): bool`: Indica si el token corresponde al correo y no expiró.
- `delete(string $email): void`: Borra el token del correo (después de usarlo).

### Gate

Orden de la decisión: 1. Sin usuario, no. 2. Si su rol tiene '*', sí (el admin pasa todo, también las políticas). 3. Si hay un modelo y su política tiene el método $ability, decide la política. 4. Si no, decide la lista de permisos del rol ('tareas.*' cubre 'tareas.borrar').

- `allows(?Model $user, string $ability, Model|string|null $subject = null): bool`: Indica si el usuario puede hacer $ability (sobre $subject, si se da).
- `authorize(?Model $user, string $ability, Model|string|null $subject = null, int $status = 403): void`: Lanza 403 (o $status, por ejemplo 404 para no revelar que el registro existe) si no puede.
- `hasRole(?Model $user, string ...$roles): bool`: Indica si el usuario tiene alguno de los roles.
- `roles(): array`: Roles definidos en config/auth.php.
- `permissions(Model $user): array`: Permisos del rol del usuario.

### Authorizable

Usa la columna role y los permisos de config/auth.php (roles).

- `can(string $ability, Model|string|null $subject = null): bool`: Indica si este usuario puede hacer $ability (sobre $subject, si se da).
- `hasRole(string ...$roles): bool`: Indica si tiene alguno de los roles.

### RateLimiter

Limitador de intentos.

- `hit(string $key, int $decaySeconds = 60): int`: Suma un intento. La ventana empieza con el primer intento y dura $decaySeconds. Devuelve el total de intentos en la ventana.
- `attempt(string $key, int $maxAttempts, int $decaySeconds = 60): bool`: Cuenta un intento si todavía hay cupo y dice si se puede seguir. Revisar y contar van en un solo paso, así las peticiones en paralelo no pasan todas antes de que suba el contador. Úsalo ANTES de la operación que proteges (verificar una contraseña, enviar un correo).
- `attempts(string $key): int`
- `tooManyAttempts(string $key, int $maxAttempts): bool`
- `remaining(string $key, int $maxAttempts): int`
- `availableIn(string $key): int`: Segundos que faltan para que la ventana se reinicie.
- `clear(string $key): void`: Reinicia los intentos (por ejemplo, después de un login correcto).

### SignedUrl

La firma cubre el nombre de la ruta (sin prefijo de idioma) y todos los parámetros, no el dominio ni la carpeta: el enlace sigue siendo válido detrás de un proxy, en una subcarpeta o en /en/.

- `sign(string $route, array $parameters, int $expires): array`: Parámetros con expires y signature agregados.
- `check(Request $request, int $now): bool`: Comprueba la firma y el vencimiento de la petición actual (ruta, parámetros y query string).

## Vistas, correo, caché y log

### ComponentAttributeBag

Bolsa de atributos de un componente.

- `all(): array`
- `get(string $key, mixed $default = null): mixed`
- `has(string $key): bool`
- `only(array $keys): self`
- `except(array $keys): self`
- `merge(array $defaults): self`: Combina con atributos por defecto. Las clases y los estilos se suman; el resto de atributos recibidos reemplaza a los valores por defecto.
- `class(array|string $classes): self`: Agrega clases, admitiendo clases condicionales: ['p-4', 'bg-red-50' => $error].
- `::classList(array|string $classes): string`: Arma una lista de clases a partir de un array con condiciones.
- `getIterator(): Traversable`

### Component

Componente de vista con lógica.

- `data(): array`: Datos adicionales para la vista del componente.

### Message

Los métodos cc(), bcc(), replyTo(), from(), attach() y attachData() modifican el mensaje y lo devuelven, así que se pueden encadenar.

- `cc(string ...$emails): self`: Agrega destinatarios en copia (visibles para todos).
- `bcc(string ...$emails): self`: Agrega destinatarios en copia oculta (no aparecen en las cabeceras).
- `replyTo(string $email): self`: Dirección a la que llegan las respuestas.
- `from(string $email, ?string $name = null): self`: Remitente para este correo, en lugar del de config/mail.php.
- `attach(string $path, ?string $name = null, ?string $mime = null): self`: Adjunta un archivo del disco. El nombre y el tipo se deducen del archivo si no se indican.
- `attachData(string $contents, string $name, string $mime = 'application/octet-stream'): self`: Adjunta contenido generado en memoria (por ejemplo un CSV o un PDF recién creado).
- `getCc(): array`: Destinatarios en copia.
- `getBcc(): array`: Destinatarios en copia oculta.
- `getReplyTo(): ?string`: Dirección para las respuestas, si se indicó una.
- `getFrom(): ?array`: Remitente propio de este correo, o null para usar el de config/mail.php.
- `getAttachments(): array`: Adjuntos en el orden en que se agregaron.
- `toArray(): array`: El mensaje como array de valores simples, para guardarlo en la cola (mailer()->queue()).
- `::fromArray(array $data): self`: Reconstruye un mensaje guardado con toArray().
- `recipients(): array`: Todas las direcciones que reciben el correo: destinatario, copias y copias ocultas, sin repetir.
- `$to` (string)
- `$subject` (string)
- `$text` (string)
- `$html` (?string)

### MailException

La lanza mailer()->send() cuando el correo no sale; atrápala donde un fallo de envío no deba romper la página.


### MailService

Con QUEUE_DRIVER=sync, queue() envía en el momento (igual que send).

- `send(Message $message): void`: Envía el correo ahora.
- `queue(Message $message, int $delay = 0): int`: Encola el correo para enviarlo en segundo plano (con $delay, no antes de esos segundos). Devuelve el id del job.

### ArrayMailer

- `send(Message $message): void`
- `sent(): array`

### Cache

Caché clave/valor persistida en archivos.

- `get(string $key, mixed $default = null): mixed`: Obtiene un valor o el valor por defecto si no existe o expiró.
- `set(string $key, mixed $value, ?int $ttl = 3600): void`: Guarda un valor.
- `has(string $key): bool`: Indica si existe un valor vigente para la clave.
- `forget(string $key): void`: Elimina una clave.
- `remember(string $key, ?int $ttl, Closure $callback): mixed`: Devuelve el valor cacheado o lo calcula, lo guarda y lo devuelve.
- `increment(string $key, int $by = 1, ?int $ttl = null): int`: Incrementa un contador y devuelve el nuevo valor. Conserva la expiración existente. Es atómico: dos peticiones a la vez no leen el mismo valor.
- `atomic(string $key, Closure $callback): mixed`: Ejecuta $callback con un candado exclusivo sobre la clave: mientras corre, ningún otro proceso entra a atomic() ni a increment() con esa clave. Sirve para "leer, decidir y escribir" sin carreras.
- `flush(): void`: Elimina todas las claves.

### Logger

Logger de archivo diario (storage/logs/app-AAAA-MM-DD.log).

- `debug(Stringable|string $message, array $context = []): void`
- `info(Stringable|string $message, array $context = []): void`
- `notice(Stringable|string $message, array $context = []): void`
- `warning(Stringable|string $message, array $context = []): void`
- `error(Stringable|string $message, array $context = []): void`
- `critical(Stringable|string $message, array $context = []): void`
- `alert(Stringable|string $message, array $context = []): void`
- `emergency(Stringable|string $message, array $context = []): void`
- `log(string $level, Stringable|string $message, array $context = []): void`: Registra un mensaje. Los marcadores {clave} del mensaje se reemplazan con el contexto.

## Archivos

### Storage

Las rutas no pueden salir del disco ('..'). En un disco público no se guardan archivos que el navegador o el servidor ejecutarían (php, html, svg, js, .htaccess).

- `put(string $path, string $contents, ?string $mime = null): void`: Guarda el contenido en la ruta. El tipo se detecta del contenido si no se indica.
- `putFile(string $path, string $localFile, ?string $mime = null): void`: Copia un archivo del servidor al disco.
- `get(string $path): string`: Contenido completo del archivo.
- `exists(string $path): bool`: Indica si el archivo existe en el disco.
- `delete(string $path): bool`: Borra el archivo. Devuelve false si no existía.
- `size(string $path): int`: Tamaño en bytes.
- `url(string $path): string`: URL pública del archivo (disco público).
- `temporaryUrl(string $path, int $minutes = 30, ?string $downloadName = null): string`: URL que permite descargar el archivo durante $minutes minutos, sin sesión. Con $downloadName se descarga con ese nombre.
- `download(string $path, ?string $name = null, bool $inline = false): Response`: Respuesta que entrega el archivo: para descargar (por defecto) o para verlo en el navegador con $inline (solo imágenes, PDF y texto; lo demás siempre se descarga).
- `isPublic(): bool`
- `disk(): Disk`: El driver por debajo (LocalDisk, S3Disk, FtpDisk o SftpDisk).
- `::detectMime(string $contents, string $path = ''): string`: Tipo MIME por contenido (finfo) o, si no se puede, por extensión.
- `$name` (string)

### Image

Formatos: JPEG, PNG (con transparencia), WebP y GIF (primer cuadro). Nunca agranda una imagen. Rechaza imágenes de más de 40 megapíxeles antes de decodificarlas, para no agotar la memoria.

- `::fromString(string $contents): self`: Abre una imagen desde su contenido.
- `width(): int`: Ancho en píxeles.
- `height(): int`: Alto en píxeles.
- `type(): string`: Formato original: jpeg, png, webp o gif.
- `resize(int $maxWidth, ?int $maxHeight = null): self`: Reduce la imagen para que quepa en $maxWidth x $maxHeight conservando la proporción. Si ya es más chica, la deja igual.
- `cover(int $width, int $height): self`: Recorta al centro y escala para llenar exactamente $width x $height (avatares, miniaturas). No agranda: si la imagen es más chica, el resultado sale proporcionalmente más chico.
- `encode(?string $type = null, int $quality = 85): string`: Codifica en el formato indicado (por defecto el original). $quality de 0 a 100 para JPEG y WebP.

## Idiomas

### Translator

Un texto sin traducción se muestra en español: la app nunca enseña una clave cruda.

- `get(string $text, array $replace = [], ?string $locale = null): string`: Traduce el texto al idioma activo (o al indicado) y reemplaza :marcadores.
- `choice(string $text, int|float $count, array $replace = [], ?string $locale = null): string`: Elige la forma según la cantidad: 'una tarea|:count tareas' o '{0} ninguna|una|:count' (con tres formas, la primera es para 0). :count se reemplaza solo.
- `locale(): string`
- `setLocale(string $locale): void`: Cambia el idioma activo.
- `defaultLocale(): string`
- `locales(): array`
- `isSupported(string $locale): bool`
- `sourceLocale(): string`: Idioma de los textos del código (las claves).
- `currentPath(): string`: Path de la petición actual.
- `currentRoute(): ?Route`
- `currentParameters(): array`
- `all(?string $locale = null): array`: Todas las traducciones de un idioma (útil para pasarlas a JavaScript).
- `routeSegments(string $locale): array`: Traducción de segmentos de URL para $router->localized(), de lang/{locale}/rutas.php: ['tareas' => 'tasks', 'registro' => 'sign-up']. La app sobrescribe al framework.

### Formatter

La región de cada idioma ('es' => 'es_MX', 'en' => 'en_US') decide separadores y moneda; se configura en config/app.php ('regions').

- `number(int|float $number, int $decimals = 0): string`: Número con separadores de miles y $decimals decimales.
- `money(int|float $amount, ?string $currency = null): string`: Cantidad con símbolo de moneda (por defecto APP_CURRENCY).
- `date(DateTimeInterface|string $date, string $style = 'medium', bool $withTime = false): string`: Fecha (y hora con $withTime) en estilo short (07/11/2026), medium (7 nov 2026) o long (7 de noviembre de 2026).

## Cola y tareas programadas

### Job

handle() recibe sus dependencias del contenedor (por ejemplo Mailer o Connection). Si lanza una excepción, se reintenta $tries veces esperando $backoff segundos; después pasa a los fallidos y se llama a failed().

- `failed(Throwable $exception): void`: Se llama una sola vez, cuando el job agotó sus intentos.
- `$tries` (int)
- `$backoff` (int)
- `$queue` (string)

### Schedule

- `command(string $command): Task`: Comando de bin/bootzen, con sus opciones: 'db:backup', 'queue:work --stop-when-empty'.
- `job(Job $job): Task`: Encola un job (con QUEUE_DRIVER=sync se ejecuta en el momento).
- `call(Closure $callback, string $name): Task`: Función propia; recibe sus dependencias del contenedor.

### Task

Sin frecuencia corre cada minuto. Las horas son de APP_TIMEZONE.

- `everyMinute(): self`: Cada minuto (lo de siempre si no se indica otra frecuencia).
- `everyFiveMinutes(): self`: Cada 5 minutos.
- `everyTenMinutes(): self`: Cada 10 minutos.
- `everyFifteenMinutes(): self`: Cada 15 minutos.
- `everyThirtyMinutes(): self`: Cada 30 minutos.
- `hourly(): self`: Cada hora en punto.
- `hourlyAt(int $minute): self`: Cada hora en el minuto indicado.
- `daily(): self`: Todos los días a medianoche.
- `dailyAt(string $time): self`: Todos los días a la hora indicada ('03:00').
- `twiceDaily(int $first = 1, int $second = 13): self`: Todos los días a dos horas en punto (por ejemplo 9 y 18).
- `weekly(): self`: Los domingos a medianoche.
- `weeklyOn(int $day, string $time = '00:00'): self`: Un día de la semana (0 domingo, 1 lunes... 6 sábado) a la hora indicada.
- `weekdaysAt(string $time): self`: De lunes a viernes a la hora indicada.
- `monthly(): self`: El día 1 de cada mes a medianoche.
- `monthlyOn(int $day, string $time = '00:00'): self`: Un día del mes a la hora indicada.
- `cron(string $expression): self`: Expresión cron de 5 campos.
- `name(string $description): self`: Nombre para la salida de schedule:run y schedule:list.
- `description(): string`
- `expression(): CronExpression`

## Tests

### TestClient

En los tests del proyecto se obtiene con client() (tests/Pest.php).

- `get(string $uri, array $headers = []): TestResponse`
- `post(string $uri, array $data = [], array $headers = []): TestResponse`
- `put(string $uri, array $data = [], array $headers = []): TestResponse`
- `patch(string $uri, array $data = [], array $headers = []): TestResponse`
- `delete(string $uri, array $data = [], array $headers = []): TestResponse`
- `getJson(string $uri): TestResponse`: GET pidiendo JSON (cabecera Accept: application/json).
- `postJson(string $uri, array $data = []): TestResponse`
- `putJson(string $uri, array $data = []): TestResponse`
- `deleteJson(string $uri, array $data = []): TestResponse`
- `::file(string $name, string $contents): array`: Archivo para enviar en un formulario: client()->post('/fotos', ['foto' => TestClient::file('a.png', $bytes)]).
- `::image(string $name = 'imagen.png'): array`: Imagen PNG válida de 1x1 píxel, para probar las reglas image y mimes.
- `request(string $method, string $uri, array $data = [], array $headers = [], array $files = []): TestResponse`: Envía una petición. Los archivos (TestClient::file()) pueden ir en $data o en $files.
- `withHeaders(array $headers): self`: Agrega cabeceras a todas las peticiones siguientes.
- `from(string $uri): self`: Simula que el usuario viene de esa página (cabecera Referer): tras un error de validación, la respuesta redirige ahí.
- `followingRedirects(): self`: Las siguientes peticiones siguen las redirecciones (hasta 5) y devuelven la página final.
- `closeBrowser(): self`: Simula cerrar el navegador: olvida la cookie de sesión y conserva las demás (como "recordarme").
- `cookies(): array`: Cookies que el cliente tiene guardadas: nombre => valor.
- `actingAs(Model $user): self`: Inicia sesión como el usuario sin pasar por el formulario de login.
- `sessionData(): array`: Datos de la sesión actual del cliente ([] si todavía no hay sesión).
- `assertAuthenticated(): self`
- `assertGuest(): self`
- `assertDatabaseHas(string $table, array $values): self`: Comprueba que exista al menos una fila con esos valores.
- `assertDatabaseMissing(string $table, array $values): self`
- `assertDatabaseCount(string $table, int $count): self`
- `$app` (Application)

### TestResponse

- `status(): int`
- `body(): string`
- `header(string $name): ?string`
- `isRedirect(): bool`
- `json(?string $path = null): mixed`: Cuerpo JSON decodificado; con $path, el valor en esa ruta ('data.0.titulo').
- `assertStatus(int $status): self`
- `assertOk(): self`
- `assertCreated(): self`
- `assertNoContent(): self`
- `assertUnauthorized(): self`
- `assertForbidden(): self`
- `assertNotFound(): self`
- `assertUnprocessable(): self`
- `assertRedirect(?string $uri = null): self`: Comprueba que sea una redirección y, si se indica, a qué path (se ignora el dominio).
- `assertSee(string $text, bool $escape = true): self`: Comprueba que el cuerpo contenga el texto (escapado como lo imprime {{ }}, salvo $escape = false).
- `assertDontSee(string $text, bool $escape = true): self`
- `assertJson(array $subset): self`: Comprueba que el JSON contenga estos valores (puede tener más).
- `assertJsonPath(string $path, mixed $expected): self`
- `assertJsonCount(int $count, ?string $path = null): self`: Cantidad de elementos del JSON o de la lista en $path.
- `assertHeader(string $name, ?string $value = null): self`
- `assertSessionHas(string $key, mixed $value = null): self`: Comprueba que la sesión tenga la clave (y el valor, si se indica). Sirve para mensajes flash.
- `assertSessionMissing(string $key): self`
- `assertSessionHasErrors(array|string $fields = []): self`: Comprueba que haya errores de validación en esos campos (o en alguno, si no se indican).
- `assertSessionHasNoErrors(): self`
- `$response` (Response)
