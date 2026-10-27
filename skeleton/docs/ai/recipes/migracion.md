# Migraciones

**Cuándo:** crear una tabla o cambiar una existente.

## Pasos

```bash
php bin/bootzen make:migration crear_facturas            # trae el CREATE TABLE con id y timestamps
php bin/bootzen make:migration agregar_telefono_a_clientes   # plantilla vacía con up() y down()
php bin/bootzen migrate
php bin/bootzen migrate:status
php bin/bootzen migrate:rollback      # revierte el último lote (prueba que down() funcione)
```

Los tests aplican todas las migraciones solos en una base en memoria.

## Agregar una columna

```php
return new class () extends Migration {
    public function up(Connection $db): void
    {
        $db->exec('ALTER TABLE clientes ADD COLUMN telefono TEXT');
    }

    public function down(Connection $db): void
    {
        $db->exec('ALTER TABLE clientes DROP COLUMN telefono');
    }
};
```

Después: agrega la columna a `$fillable`, al `@property` y, si es fecha,
número o booleano, a `$casts` del modelo.

## SQLite: lo que conviene saber

- `ADD COLUMN NOT NULL` necesita un `DEFAULT`
  (`ADD COLUMN activo INTEGER NOT NULL DEFAULT 1`).
- `DROP COLUMN` y `RENAME COLUMN` funcionan desde SQLite 3.35/3.25. Para
  cambiar el tipo de una columna: crea una tabla nueva, copia
  (`INSERT INTO nueva SELECT ... FROM vieja`), borra la vieja y renombra.
- Booleanos como `INTEGER` 0/1 y fechas como `TEXT` (`'Y-m-d H:i:s'`); los
  `$casts` del modelo los convierten.
- Índices: `CREATE INDEX idx_clientes_email ON clientes (email)` o
  `CREATE UNIQUE INDEX ...`.
- Si `DB_CONNECTION=mysql`, escribe SQL de MySQL.

## Errores comunes

- Editar una migración que ya corrió en otro lado: nadie más la vuelve a
  correr. Crea una nueva.
- Dejar `down()` vacío: si no se puede revertir, borra el método (el
  rollback avisará).
- Concatenar datos en el SQL de una migración de datos: usa `$db->statement($sql, [$valor])`.
