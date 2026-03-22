# CLAUDE.md – es21plus · Sistema de Inventario para Mecánico de Motos

> **Autor:** Carlos Vico
> **Stack:** PHP 8 · MySQL 8 · Docker · Vanilla JS · HTML5 · CSS3
> **Entorno:** Docker Compose (web en puerto 8080, DB en 3306)

---

## 1. Contexto del Proyecto

Sistema web de gestión de inventario para un taller mecánico de motos.
Permite controlar productos (accesorios/repuestos), stock y movimientos de entrada/salida.

### Estructura de carpetas

```
sistema-inventario-motos/
├── CLAUDE.md                  ← este archivo
├── .env.example               ← variables de entorno (NO incluir .env en git)
├── Dockerfile
├── docker-compose.yml
├── database/
│   └── schema.sql             ← DDL completo de la base de datos
└── src/
    ├── index.php              ← listado principal de productos
    ├── nuevo_producto.php     ← formulario crear producto
    ├── editar_producto.php    ← formulario editar producto   [PENDIENTE]
    ├── eliminar_producto.php  ← lógica de eliminación        [PENDIENTE]
    ├── movimientos.php        ← historial de movimientos     [PENDIENTE]
    ├── categorias.php         ← CRUD de categorías           [PENDIENTE]
    ├── buscar.php             ← búsqueda y filtros           [PENDIENTE]
    ├── api/                   ← endpoints REST JSON          [PENDIENTE]
    │   ├── productos.php
    │   ├── categorias.php
    │   └── movimientos.php
    ├── includes/
    │   ├── Database.php       ← conexión PDO (usar variables de entorno)
    │   ├── Producto.php       ← modelo productos
    │   ├── Categoria.php      ← modelo categorías
    │   └── Movimiento.php     ← modelo movimientos           [PENDIENTE]
    ├── css/
    │   └── estilos.css
    └── js/
        └── app.js
```

---

## 2. Reglas de Código Obligatorias

Claude debe aplicar estas reglas en **CADA archivo** que cree o modifique:

### PHP
- **PHPDoc** en todas las clases y métodos públicos. Incluir `@author Carlos Vico`.
- Usar **PDO** con prepared statements (NO mysqli).
- **Variables de entorno** para credenciales: `$_ENV['DB_HOST']`, `$_ENV['DB_USER']`, etc.
- Funciones < 30 líneas (Clean Code).
- Manejo centralizado de errores: clase `AppException` o handler global.
- Respuesta JSON estándar en la API: `{ "success": bool, "data": mixed, "message": string }`.
- Verbos HTTP correctos: GET (listar/obtener), POST (crear), PUT (actualizar), DELETE (eliminar).
- Escapar siempre la salida HTML con `htmlspecialchars()`.
- **Nunca** incluir passwords o tokens en el código fuente.

### JavaScript
- **JSDoc** en todas las funciones.
- `camelCase` para variables y funciones.
- Fetch API con manejo de errores centralizado.
- Módulos separados por responsabilidad.

### Base de Datos
- Tablas en **plural y minúsculas**: `productos`, `categorias`, `movimientos`.
- Columnas en **snake_case**: `categoria_id`, `fecha_creacion`.
- Usar **Eager Loading** (LEFT JOIN) para evitar consultas N+1.
- Toda modificación al schema va en `database/schema.sql`.

---

## 3. Variables de Entorno

Leer siempre desde `.env` (ver `.env.example`). Nunca hardcodear credenciales.

```php
// Correcto
$host = $_ENV['DB_HOST'];

// PROHIBIDO
$host = 'localhost';
$pass = 'luigi21plus';
```

---

## 4. Tareas Pendientes (Backlog Priorizado)

### 🔴 PRIORIDAD ALTA – Core del sistema

#### TAREA 1: Refactorizar Database.php a PDO + variables de entorno
- Eliminar mysqli
- Usar PDO con DSN desde `$_ENV`
- Singleton pattern para la conexión

#### TAREA 2: Crear editar_producto.php
- Formulario prellenado con datos actuales
- Validación del lado servidor
- Redirect con mensaje de éxito/error

#### TAREA 3: Crear eliminar_producto.php
- Verificar que no tenga movimientos activos antes de eliminar
- Confirmación vía modal JS antes de enviar
- Soft delete (añadir columna `deleted_at` a la tabla `productos`)

#### TAREA 4: Crear movimientos.php
- Mostrar historial de entradas/salidas de un producto
- Formulario para registrar nuevo movimiento (entrada/salida + cantidad + observaciones)
- Actualización automática del stock en tabla `productos`

#### TAREA 5: Crear categorias.php
- CRUD completo de categorías
- Indicar cuántos productos tiene cada categoría

### 🟡 PRIORIDAD MEDIA – Mejoras funcionales

#### TAREA 6: Búsqueda y filtros en index.php
- Buscar por nombre de producto
- Filtrar por categoría (select)
- Filtrar por stock bajo (< umbral configurable)
- Sin recarga de página (fetch + JS)

#### TAREA 7: Capa API REST en src/api/
- `GET    /api/productos.php`          → listar productos
- `GET    /api/productos.php?id=X`     → obtener uno
- `POST   /api/productos.php`          → crear
- `PUT    /api/productos.php`          → actualizar
- `DELETE /api/productos.php?id=X`     → eliminar
- Misma estructura para categorias y movimientos

#### TAREA 8: Modelo Movimiento.php
- `registrar($productoId, $tipo, $cantidad, $observaciones)`
- `listarPorProducto($productoId)`
- `resumenStock($productoId)` → suma entradas - salidas

### 🟢 PRIORIDAD BAJA – Mejoras de UX y extras

#### TAREA 9: Alertas de stock bajo
- Destacar en rojo productos con stock < 5 (configurable)
- Badge contador en el navbar

#### TAREA 10: Dashboard / página de inicio
- Total de productos registrados
- Productos con stock bajo
- Últimos 5 movimientos registrados
- Gráfico simple de movimientos (Chart.js)

#### TAREA 11: Mejorar estilos CSS
- Diseño responsive (mobile-first)
- Paleta de colores coherente (azul/gris oscuro, estilo mecánico)
- Mensajes flash de éxito/error

---

## 5. Schema de Base de Datos (versión objetivo)

```sql
-- Añadir a productos
ALTER TABLE productos ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE productos ADD COLUMN imagen VARCHAR(255) NULL;
ALTER TABLE productos ADD COLUMN stock_minimo INT DEFAULT 5;
ALTER TABLE productos ADD COLUMN codigo_ref VARCHAR(50) NULL;
ALTER TABLE productos ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE productos ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Añadir a categorias
ALTER TABLE categorias ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Añadir a movimientos
ALTER TABLE movimientos ADD COLUMN usuario VARCHAR(100) DEFAULT 'admin';
```

---

## 6. Cómo Levantar el Entorno

```bash
# 1. Copiar variables de entorno
cp .env.example .env

# 2. Levantar contenedores
docker-compose up -d

# 3. Acceder a la app
# http://localhost:8080

# 4. Ver logs
docker-compose logs -f web

# 5. Parar contenedores
docker-compose down
```

---

## 7. Flujo de Trabajo con Claude Code

Cuando Claude Code trabaje en este proyecto debe:

1. Leer este `CLAUDE.md` completo antes de escribir código.
2. Implementar las tareas **en el orden de prioridad** indicado.
3. En cada archivo creado incluir la cabecera PHPDoc con `@author Carlos Vico`.
4. Tras cada tarea, verificar que el código sea coherente con los archivos existentes.
5. Nunca modificar `.env` (solo `.env.example`).
6. Mantener `database/schema.sql` actualizado con cada cambio de estructura.

---

## 8. Ejemplo de Estructura PHPDoc Esperada

```php
<?php
/**
 * Gestión de productos del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */
class Producto
{
    /**
     * Lista todos los productos activos con su categoría.
     *
     * Usa LEFT JOIN para evitar N+1 y excluye soft-deleted.
     *
     * @return array<int, array<string, mixed>> Filas de productos.
     */
    public function listar(): array
    {
        // ... implementación
    }
}
```

---

*Última actualización: Marzo 2026 · Carlos Vico*
