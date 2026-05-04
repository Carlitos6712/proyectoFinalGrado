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

## 4. Plan de Desarrollo por Fases

> Las fases son secuenciales. No pasar a la siguiente hasta completar la anterior.
> Issues referenciados: #5 Exportación, #6 Imágenes, #7 Búsqueda avanzada, #8 Alertas email, #9 Tests, #10 Auditoría.

---

### 🔴 FASE 1 — Fundación Core
> Base técnica. Sin esta fase nada más funciona correctamente.

#### 1.1 Refactorizar `Database.php` a PDO
- Eliminar mysqli completamente
- PDO con DSN construido desde `$_ENV['DB_HOST']`, `$_ENV['DB_NAME']`, etc.
- Singleton pattern para la conexión
- Lanzar `AppException` en caso de fallo de conexión

#### 1.2 Crear modelo `Movimiento.php`
- `registrar(int $productoId, string $tipo, int $cantidad, string $observaciones): bool`
- `listarPorProducto(int $productoId): array`
- `resumenStock(int $productoId): int` → suma entradas − salidas
- PHPDoc completo con `@author Carlos Vico`

#### 1.3 Aplicar migraciones de schema
- Añadir columnas faltantes en `productos`: `deleted_at`, `imagen`, `stock_minimo`, `codigo_ref`, `created_at`, `updated_at`
- Añadir `created_at` en `categorias`
- Añadir `usuario` en `movimientos`
- Actualizar `database/schema.sql` con cada cambio

---

### 🔴 FASE 2 — CRUD Completo de Entidades
> Funcionalidad básica del sistema. Alta prioridad.

#### 2.1 Crear `editar_producto.php`
- Formulario prellenado con datos actuales del producto
- Validación server-side (nombre requerido, precio > 0, stock ≥ 0)
- Redirect con mensaje flash de éxito/error
- Proteger contra XSS con `htmlspecialchars()`

#### 2.2 Crear `eliminar_producto.php`
- Verificar que el producto no tenga movimientos activos antes de eliminar
- Modal de confirmación JS antes de enviar el formulario
- Soft delete: setear `deleted_at = NOW()` (no DELETE físico)
- Excluir soft-deleted en todas las consultas de `Producto::listar()`

#### 2.3 Crear `movimientos.php`
- Tabla con historial de entradas/salidas de un producto (paginado)
- Formulario para registrar nuevo movimiento: tipo (entrada/salida), cantidad, observaciones
- Actualización automática del stock en `productos` tras cada movimiento
- Validar que salida no deje stock negativo

#### 2.4 Crear `categorias.php`
- CRUD completo: listar, crear, editar, eliminar categoría
- Mostrar contador de productos activos por categoría (LEFT JOIN)
- No permitir eliminar categoría con productos activos

---

### 🟡 FASE 3 — Capa API REST
> Desacopla frontend del backend. Necesaria para AJAX y futuros clientes.

#### 3.1 `api/productos.php`
- `GET /api/productos.php` → listar (con paginación `?page=&limit=`)
- `GET /api/productos.php?id=X` → obtener uno
- `POST /api/productos.php` → crear
- `PUT /api/productos.php` → actualizar
- `DELETE /api/productos.php?id=X` → soft delete
- Respuesta estándar: `{ "success": bool, "data": mixed, "message": string }`

#### 3.2 `api/categorias.php`
- CRUD completo con mismos verbos HTTP
- `GET /api/categorias.php` incluye contador de productos por categoría

#### 3.3 `api/movimientos.php`
- `GET /api/movimientos.php?producto_id=X` → historial paginado
- `POST /api/movimientos.php` → registrar movimiento
- Validar stock suficiente antes de registrar salida

---

### 🟡 FASE 4 — Búsqueda Avanzada y Filtros *(Issue #7)*
> Panel de filtros combinables con URL bookmarkeable.

#### 4.1 Backend `buscar.php`
- Aceptar parámetros: `q` (nombre), `categoria_id`, `precio_min`, `precio_max`, `stock_min`, `stock_max`, `stock_bajo`, `orden`
- Construir query dinámicamente con prepared statements
- Soportar ordenación: nombre A-Z, precio ↑↓, stock ↑↓, fecha creación

#### 4.2 API endpoint búsqueda
- `GET /api/productos.php?q=&precio_min=&precio_max=&orden=precio_asc`
- Todos los filtros combinables entre sí
- Respeta paginación

#### 4.3 Frontend filtros
- Panel colapsable "Filtros avanzados" bajo el toolbar
- Todos los filtros son AJAX (sin recarga de página)
- URL se actualiza con `history.pushState` para compartir búsquedas
- Botón "Limpiar filtros" resetea todo
- Vista tabla / tarjetas (grid) toggleable

---

### 🟡 FASE 5 — Imágenes y Exportación *(Issues #5 y #6)*

#### 5.1 Subida y gestión de imágenes *(Issue #6)*
- Campo `<input type="file">` en crear/editar producto
- Validación: solo JPG/PNG/WebP, máximo 2MB
- Renombrar a `producto_{id}_{timestamp}.webp` y convertir con GD
- Guardar en `src/uploads/productos/` (directorio en `.gitignore`)
- Miniatura 40×40px en tabla; imagen completa en edición
- SVG placeholder si el producto no tiene imagen
- Métodos en `Producto.php`: `subirImagen($file, $id)`, `eliminarImagen($id)`

#### 5.2 Exportación CSV *(Issue #5)*
- Botón "Exportar CSV" en toolbar de productos
- Respeta filtros activos
- Columnas: Ref, Nombre, Categoría, Precio, Stock, Stock Mínimo, Estado
- Encoding UTF-8 con BOM para compatibilidad Excel
- Endpoint: `GET /api/productos.php?export=csv`

#### 5.3 Exportación PDF *(Issue #5)*
- Librería **FPDF** o **mPDF** vía Composer
- Encabezado con nombre del taller y fecha de generación
- Tabla formateada + resumen de stock bajo al final
- Endpoint: `GET /api/productos.php?export=pdf`

#### 5.4 Exportación movimientos
- Historial de movimientos exportable a CSV
- Rango de fechas configurable

---

### 🟢 FASE 6 — Dashboard y UX

#### 6.1 Alertas de stock bajo (UI)
- Resaltar en rojo productos con `stock < stock_minimo`
- Badge contador en el navbar con total de productos en stock bajo
- Umbral configurable por producto via columna `stock_minimo`

#### 6.2 Dashboard `index.php` / página de inicio
- Total de productos registrados
- Productos con stock bajo
- Últimos 5 movimientos registrados
- Gráfico simple de movimientos con **Chart.js**

#### 6.3 Mejoras de estilos CSS
- Diseño responsive (mobile-first)
- Paleta coherente: azul/gris oscuro, estilo mecánico
- Mensajes flash de éxito/error
- HTML5 semántico en todas las páginas

---

### 🟢 FASE 7 — Backend Avanzado *(Issues #8 y #10)*

#### 7.1 Alertas automáticas por email *(Issue #8)*
- Verificar tras cada movimiento de salida si `stock <= stock_minimo`
- Enviar email con **PHPMailer** (Composer); destinatario en `ALERT_EMAIL` (`.env`)
- Tabla `alertas_stock` para evitar duplicados por episodio
- Schema:
  ```sql
  CREATE TABLE alertas_stock (
      id INT AUTO_INCREMENT PRIMARY KEY,
      producto_id INT NOT NULL,
      enviada_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      stock_al_enviar INT,
      FOREIGN KEY (producto_id) REFERENCES productos(id)
  );
  ```
- Email incluye: nombre producto, stock actual, enlace al panel
- Se puede deshabilitar por producto

#### 7.2 Historial de auditoría *(Issue #10)*
- Registrar en `Producto::crear()`, `actualizar()`, `eliminar()` y equivalentes de `Categoria`
- Schema:
  ```sql
  CREATE TABLE auditoria (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      tabla VARCHAR(50) NOT NULL,
      registro_id INT NOT NULL,
      accion ENUM('crear','actualizar','eliminar') NOT NULL,
      datos_anteriores JSON,
      datos_nuevos JSON,
      usuario VARCHAR(100) DEFAULT 'admin',
      ip VARCHAR(45),
      fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );
  ```
- Página `auditoria.php`: tabla paginada filtrable por tabla, acción y rango de fechas
- Mostrar diff entre datos anteriores y nuevos
- Los logs no se pueden borrar desde la UI
- Enlace en sidebar

---

### 🟢 FASE 8 — Testing y CI/CD *(Issue #9)*

#### 8.1 Configuración PHPUnit
- Instalar `phpunit/phpunit ^11` vía Composer
- `phpunit.xml` en raíz del proyecto
- Comando: `composer test`

#### 8.2 Tests unitarios `tests/Unit/`
- `ProductoTest`: crear, obtener, actualizar, soft-delete, buscar, stock insuficiente
- `CategoriaTest`: CRUD, error al eliminar con productos activos
- `MovimientoTest`: registrar entrada/salida, validar stock insuficiente lanza excepción
- SQLite en memoria para tests unitarios

#### 8.3 Tests de integración `tests/Integration/`
- `ApiProductosTest`: todos los endpoints HTTP (GET/POST/PUT/DELETE)
- `ApiMovimientosTest`: registrar movimiento actualiza stock
- Base de datos MySQL de test separada

#### 8.4 GitHub Actions CI/CD
- Archivo `.github/workflows/tests.yml`
- Ejecutar en cada push a `main` y en cada PR
- Cobertura mínima 70% en modelos

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
3. En cada archivo creado incluir la cabecera PHPDoc con `@author Carlitos6712`.
4. Tras cada tarea, verificar que el código sea coherente con los archivos existentes.
5. Nunca modificar `.env` (solo `.env.example`).
6. Mantener `database/schema.sql` actualizado con cada cambio de estructura.

### Autoría en commits

- **Nunca** incluir `Co-Authored-By: Claude` ni ninguna variante de Claude/Anthropic en los mensajes de commit.
- **Nunca** añadir `@author Claude` ni `@author Anthropic` en PHPDoc ni en ningún archivo.
- El único autor del proyecto es **Carlitos6712**. Claude Code es una herramienta, no un colaborador.

---

## 8. Ejemplo de Estructura PHPDoc Esperada

```php
<?php
/**
 * Gestión de productos del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
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

*Última actualización: Mayo 2026 · Carlos Vico*
