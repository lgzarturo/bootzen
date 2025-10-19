# Proceso de desarrollo

Un framework profesional no es el que tiene más features, sino el que asegura **claridad, seguridad básica, escalabilidad simple y poca fricción**.

Con BootZen busco que encaje más en la filosofía de **micro-frameworks** tipo Slim o Lumen: router limpio, capa mínima, modularidad, y que las cosas "difíciles" puedas enchufarlas después si realmente las necesitas.

Me baso en el siguiente criterio para decidir qué incluir en el core y qué dejar como módulo o plugin:

- Si algo lo vas a necesitar en el 80% de tus proyectos → mételo en el core.
- Si es algo más especializado → hazlo plugin/módulo.

Perfecto, aquí tienes un **checklist práctico** para que compares tu framework personal en PHP con lo que debería cubrir un framework profesional pero minimalista. La idea es que vayas marcando lo que ya tienes y lo que falta por implementar.

---

## ✅ Checklist de funcionalidades

### Núcleo básico

- [x] Router HTTP con soporte a GET, POST, PUT, DELETE.
- [x] Rutas con parámetros dinámicos (`/user/{id}`).
- [x] Soporte para middlewares en rutas (ej: auth, logs).
- [x] Controladores con separación clara de lógica.
- [x] Sistema de vistas (plantillas simples o PHP nativo organizado).
- [x] Manejo centralizado de configuración con variables de entorno (`.env`).
- [x] Respuesta JSON lista para APIs.

### Utilidades de desarrollo

- [x] CLI básica (crear proyectos, levantar servidor local, generar controladores, correr migraciones).
- [ ] Migraciones de base de datos con control de versiones.
- [ ] Query Builder ligero o integración con PDO segura (evitar SQL injection).
- [x] Logger centralizado con niveles (`info`, `error`, `debug`).
- [x] Soporte para PHPUnit y Pest (tests unitarios).

### Robustez y seguridad

- [ ] Middleware de seguridad contra CSRF.
- [ ] Sanitización automática de inputs (para prevenir XSS).
- [x] Manejo centralizado de errores y excepciones.
- [x] Validación de datos de formularios y requests.
- [ ] Autenticación básica con sesiones.
- [x] Hash seguro para contraseñas (bcrypt/argon2).

### Modularidad y extensión

- [x] Estructura modular de carpetas (controllers, models, views, config, etc.).
- [ ] Sistema de eventos u “observers” para ampliar funcionalidades sin tocar el núcleo.
- [x] Posibilidad de cargar módulos externos (sin sobrecargar con dependencias).
- [x] Cache opcional (archivo/memoria) que pueda enchufarse fácilmente.
- [ ] Colas/trabajos en background (opcional, si se necesita).

### Opcionales que marcan diferencia

- [x] Generador de proyectos base (tu instalador tipo `composer create-project`).
- [ ] Internacionalización básica (archivos de idioma).
- [ ] Soporte para JWT en APIs.
- [x] Servidor embebido de desarrollo (`php -S localhost:8000`).
- [x] Tests de integración (simular requests y validar respuestas).
- [x] Integración básica con Tailwind CSS y workflow de NPM.
- [ ] Soporte para WebSockets (notificaciones en tiempo real).
- [x] Documentación generada automáticamente (PHPDoc o similar).
