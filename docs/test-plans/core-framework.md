# Plan de Pruebas — Núcleo del Framework BootZen (Core)

Este documento define la estrategia, el estándar de cobertura y el conjunto de pruebas detalladas necesarias para garantizar la robustez, cumplimiento de estándares y óptimo funcionamiento del núcleo (`core/src`) del framework BootZen.

---

## Test Plan — Core Framework (Núcleo de BootZen)

### Scope

El alcance de este plan de pruebas comprende todos los componentes fundamentales de la arquitectura MVC y el contenedor de inyección de dependencias ubicados en [core/src/Core/](file:///C:/Users/R2D2/Documents/GitHub/bootzen/core/src/Core/) y [core/src/Helpers/](file:///C:/Users/R2D2/Documents/GitHub/bootzen/core/src/Helpers/). El objetivo es validar que el código actual cumpla con los estándares, buenas prácticas y requerimientos del framework de forma óptima, estableciendo un estándar de cobertura y garantizando que se alcancen los mínimos definidos.

### Estándar de Cobertura de Código

Para garantizar la estabilidad del framework a largo plazo y evitar regresiones, se establece el siguiente estándar de cobertura:
- **Cobertura General de Líneas (Line Coverage):** Mínimo **80%**.
- **Componentes Críticos (Container, Router, Request, Response):** Mínimo **90%**.

La cobertura se calculará sobre el directorio `core/src/Core/` y `core/src/Helpers/`. Quedan excluidos por diseño:
- **`core/stub.php`**: El punto de entrada del phar.
- **`core/src/Components/`** y **`core/src/Controllers/`**: Código legacy marcado para extracción a plugins independientes en la Fase 2 (según `plan/01_PLAN-MAESTRO.md`).

---

### Unit Tests

| Test ID | Target | Scenario | Expected Result |
| ------- | ------ | -------- | --------------- |
| **U-001** | `Container` | Registrar y resolver un binding simple (`bind`). | Retorna una nueva instancia de la clase resuelta en cada llamada. |
| **U-002** | `Container` | Registrar y resolver un binding singleton (`singleton`). | Retorna exactamente la misma instancia en sucesivas llamadas. |
| **U-003** | `Container` | Resolver una clase no enlazada explícitamente mediante autowiring. | Resuelve la clase y sus dependencias recursivamente analizando el constructor. |
| **U-004** | `Request` | Instanciar petición desde variables del servidor (`fromGlobals`). | Inicializa el objeto mapeando correctamente `$_GET`, `$_POST`, `$_SERVER` y cabeceras. |
| **U-005** | `Request` | Parsear cuerpo de petición con formato JSON. | Decodifica el JSON y permite acceder a los datos de manera transparente. |
| **U-006** | `Response` | Crear una respuesta HTML simple. | Establece la cabecera `Content-Type: text/html` y el contenido provisto. |
| **U-007** | `Response` | Crear una respuesta JSON (`json()`). | Convierte la estructura de datos a JSON y define la cabecera `Content-Type: application/json`. |
| **U-008** | `Router` | Registrar ruta básica con callback Closure. | Agrega la ruta internamente en la colección del método correspondiente. |
| **U-009** | `Route` | Extraer parámetros dinámicos de un patrón (ej. `/users/{id}`). | Identifica y asocia el valor de `{id}` cuando la URI coincide con el patrón. |
| **U-010** | `Helper (env)`| Obtener variable de entorno con helper `env()`. | Retorna el valor actual de `$_ENV` o el valor por defecto si no existe. |
| **U-011** | `Helper (config)`| Obtener configuración usando notación de puntos (`config()`). | Resuelve de forma anidada (ej. `database.connections.sqlite`). |

### Integration Tests

| Test ID | Components | Scenario | Expected Result |
| ------- | ---------- | -------- | --------------- |
| **I-001** | `Application` + `Router` + `Request` + `Response` | Ciclo HTTP completo: Recibir Request, pasar por Middleware global, Router empareja ruta, ejecuta Handler y devuelve Response. | La salida de la Response es enviada al cliente con el código de estado y cuerpo esperado. |
| **I-002** | `PluginLoader` + `Application` | Arrancar la aplicación con plugins instalados en `vendor/` o `plugins/`. | El cargador detecta los Service Providers, ejecuta `register()` en todos y luego `boot()` en todos en el orden correspondiente. |
| **I-003** | `Application` + Error Handler | Generar una excepción no controlada dentro de un controlador de ruta. | El controlador global de errores intercepta el fallo y devuelve una vista estructurada (HTML o JSON según cabecera `Accept`). |

### Contract Tests

| Test ID | Endpoint/Event | Property | Expected Value |
| ------- | -------------- | -------- | -------------- |
| **C-001** | `MiddlewareInterface` | Firma del método `process`. | Debe recibir `Request` y un callback `next`, retornando estrictamente un `Response`. |
| **C-002** | `ServiceProviderInterface` | Firmas de `register` y `boot`. | Deben aceptar un objeto `Application` y tener tipo de retorno `void`. |

### Edge Cases

| Test ID | Input/Condition | Expected Behavior |
| ------- | --------------- | ----------------- |
| **E-001** | Inyección de dependencias con referencia circular en constructor. | El `Container` detecta el ciclo infinito y lanza una excepción explicativa (`CircularDependencyException`). |
| **E-002** | Petición con método HTTP no soportado/inválido. | El `Router` rechaza la petición lanzando una excepción de tipo Method Not Allowed (HTTP 405). |
| **E-003** | Colisión de rutas dinámicas y estáticas (ej: `/posts/active` vs `/posts/{slug}`). | El `Router` prioriza el orden de registro o el patrón más específico para evitar falsos positivos. |
| **E-004** | Carga de un archivo de configuración mal formateado o vacío. | El sistema de `Config` maneja la lectura de forma segura y expone un array vacío sin romper la inicialización. |

### Regression Cases

| Test ID | Reference | Scenario | Must Not Happen |
| ------- | --------- | -------- | --------------- |
| **R-001** | Bug Histórico del Stub | El boot del stub de phar intenta cargar un `index.php` inexistente. | El stub de phar (`stub.php`) debe cargar únicamente recursos presentes del core (`Helpers/helpers.php`). |
| **R-002** | Pérdida de cabeceras CORS | El Router no responde de forma óptima a peticiones OPTIONS. | El router procesa la petición OPTIONS a nivel de middleware antes de despachar el endpoint para evitar fallos del navegador. |

### Coverage Targets

- **Cobertura General de Líneas (Line Coverage) mínima:** **80%**
- **Cobertura de Componentes Críticos mínima:** **90%**
- **Escenarios de Integración:** 3 escenarios (Ciclo HTTP completo, Detección de plugins, Manejo global de excepciones)
- **Validaciones de Contrato:** 2 contratos (MiddlewareInterface y ServiceProviderInterface)

### Out of Scope

- Pruebas del generador de proyectos (`init_project.sh`) fuera del flujo de testeo del core.
- Pruebas de persistencia de datos (base de datos real), correspondientes al plugin `bootzen/database`.
- Validación visual de componentes UI (Alerts, Forms, etc.) por su futura extracción.
