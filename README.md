# 🏍️ es21plus · Sistema de Gestión de Inventario para Talleres de Motos

> Aplicación web full-stack multi-tenant para la gestión de inventario de talleres mecánicos de motos.
> Permite registrar piezas, controlar el stock en tiempo real y gestionar movimientos de entrada y salida,
> con un panel de superadministrador centralizado para gestionar múltiples negocios desde un único acceso.

---

## 🚀 Funcionalidades del panel de empresa

### Inventario y productos
- Registro y gestión completa de productos con CRUD, imágenes, referencia, precio y stock
- Control de stock en tiempo real con alertas automáticas por stock mínimo
- Entradas y salidas de inventario con historial completo de movimientos
- Búsqueda avanzada y filtrado por nombre, referencia, categoría y marca
- Organización por categorías y marcas personalizables
- Compatibilidad por modelo de moto (marca y año)
- Importación masiva de productos desde CSV
- Exportación de movimientos a CSV y PDF
- Eliminación segura mediante soft-delete

### Gestión del negocio
- Gestión de empleados con roles diferenciados (`admin` / `employee`)
- Gestión de proveedores y pedidos
- Auditoría de acciones: registro de quién hizo qué y cuándo
- Sistema de soporte con tickets hacia el superadmin (prioridad, categoría, hilo de conversación)
- Perfil de empresa personalizable: logo, color de marca, datos de contacto
- Onboarding guiado en 3 pasos para nuevas empresas

### Seguridad y acceso
- Autenticación con roles y permisos por ruta
- Protección contra fuerza bruta en el login (bloqueo por IP)
- Tokens CSRF en todos los formularios
- Sesiones con verificación de estado activo en cada request
- Cambio de contraseña y cierre de sesión desde el menú de usuario

---

## 🛡️ Panel Superadmin (`/superadmin`)

Panel independiente accesible únicamente con el rol `superadmin`, con acceso exclusivo y autenticación separada.

### Gestión de negocios
- Alta, edición, activación/desactivación y acceso impersonado a cualquier empresa
- Vista de detalle por empresa: empleados, métricas propias, movimientos recientes
- Gestión de planes (`free` / `basic` / `pro`) con fecha de vencimiento
- Creación automática de empleado admin y datos de demo al registrar una empresa
- Middleware de plan: bloqueo de funcionalidades según los límites contratados

### Dashboard global
- KPIs con variación porcentual respecto al período anterior
- Gráficos con Chart.js: negocios por mes, actividad por empresa, distribución de planes, mensajes por semana
- Feed de actividad reciente en tiempo real (polling cada 30 s)
- Alertas visuales de planes vencidos o próximos a vencer

### Soporte y comunicación
- Bandeja de tickets con filtros por estado, prioridad, categoría y empresa
- Hilo de conversación por ticket con adjuntos y respuesta por email
- Métricas de soporte: tiempo medio de respuesta, tasa de resolución en 24 h
- Campañas de email masivo segmentadas por plan y estado de empresa
- Anuncios globales con banner en el dashboard de todas las empresas

### Facturación y reportes
- Generación y gestión de facturas por empresa (PDF descargable con Dompdf)
- Configuración de precios por plan con límites de funcionalidades
- Reportes exportables a CSV y PDF: negocios, facturación, actividad y soporte

### Configuración y seguridad avanzada
- Configuración global del sistema desde panel: nombre, SMTP, modo mantenimiento, registro abierto/cerrado
- Notificaciones internas con campana y badge en el header (polling cada 60 s)
- Registro de intentos de login y gestión de IPs bloqueadas
- Log de sesiones: login, logout y sesiones expiradas
- Auditoría de acciones críticas con valores antes/después en metadata JSON

---

## 🛠️ Tecnologías

| Capa | Tecnologías |
|------|-------------|
| **Backend** | PHP 8.1, PDO, arquitectura MVC |
| **Base de datos** | MySQL 8.0 |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript, Chart.js |
| **Email** | PHPMailer 6.x (SMTP) |
| **PDF** | FPDF / Dompdf |
| **Tests** | PHPUnit 11 (Unit + Integration) |
| **Infraestructura** | Docker, Docker Compose, Apache |
| **CI/CD** | GitHub Actions |

---

## 🗄️ Base de datos — Tablas principales

| Tabla | Descripción |
|-------|-------------|
| `businesses` | Empresas registradas en el sistema (multi-tenant) |
| `employees` | Empleados de cada empresa con rol y estado |
| `products` | Catálogo de productos por empresa |
| `categories` | Categorías de productos por empresa |
| `movements` | Historial de entradas y salidas de stock |
| `alerts` | Alertas de stock mínimo por empresa |
| `marcas` | Marcas de motos |
| `modelos_moto` | Modelos de moto por marca y año |
| `proveedores` | Proveedores por empresa |
| `pedidos` | Pedidos a proveedores |
| `support_tickets` | Tickets de soporte de las empresas |
| `ticket_messages` | Hilo de mensajes por ticket |
| `invoices` | Facturas por empresa |
| `plan_prices` | Precios y límites de cada plan |
| `email_campaigns` | Campañas de email masivo |
| `announcements` | Anuncios globales del superadmin |
| `activity_logs` | Registro de actividad por empresa y empleado |
| `superadmin_notifications` | Notificaciones internas del superadmin |
| `system_settings` | Configuración global del sistema |
| `login_attempts` | Intentos fallidos de login por IP |
| `session_logs` | Registro de sesiones abiertas y cerradas |

---

## 🐳 Instalación con Docker

### Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución
- Git

### Paso a paso

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/es21plus.git
cd es21plus/sistema-inventario-motos

# 2. Levantar los contenedores (primera vez ~3-5 min)
docker compose up -d

# 3. Acceder a la aplicación
# http://localhost:8090
```

Docker se encarga automáticamente de:
- Instalar PHP 8 con Apache y todas las extensiones necesarias
- Levantar MySQL 8 e importar el schema completo
- Instalar las dependencias de Composer (`vendor/`)

### Accesos por defecto

| Recurso | URL / Datos |
|---------|-------------|
| Aplicación | `http://localhost:8090` |
| Panel superadmin | `http://localhost:8090/superadmin` |
| Base de datos (externo) | `localhost:3308` · usuario: `admin` · pass: `luigi21plus` |

> Las credenciales de acceso al panel se crean al ejecutar las migraciones de seed. Consulta `database/migrations/seed_superadmin_test.sql` para el usuario superadmin de prueba.

### Comandos útiles

```bash
# Arrancar en segundo plano
docker compose up -d

# Ver logs en tiempo real
docker compose logs -f

# Parar sin borrar datos
docker compose stop

# Parar y eliminar contenedores (los datos de BD se conservan en el volumen)
docker compose down

# Resetear completamente incluyendo la base de datos
docker compose down -v && docker compose up -d

# Reconstruir tras cambios en Dockerfile o composer.json
docker compose up -d --build

# Abrir terminal dentro del contenedor PHP
docker compose exec web bash
```

---

## 📁 Estructura del proyecto

```
sistema-inventario-motos/
├── Dockerfile                    # Imagen PHP + Apache + Composer
├── docker-compose.yml            # Orquestación de servicios
├── apache.conf                   # Configuración de Apache
├── php.ini                       # Configuración de PHP
├── composer.json                 # Dependencias PHP
├── phpunit.xml                   # Configuración de tests
├── .env.example                  # Plantilla de variables de entorno
├── .github/workflows/tests.yml   # CI con GitHub Actions
├── cron/
│   └── send_campaigns.php        # Procesador de campañas programadas
├── database/
│   ├── schema.sql                # DDL base (carga automática en Docker)
│   └── migrations/               # Migraciones por fases
└── src/
    ├── core/                     # Servicios transversales
    │   ├── AuthController.php
    │   ├── Session.php
    │   ├── Settings.php
    │   ├── Mailer.php
    │   ├── SecurityService.php
    │   ├── NotificationService.php
    │   ├── ActivityLogger.php
    │   ├── BusinessSeeder.php
    │   ├── CampaignService.php
    │   ├── CsvExporter.php
    │   └── PdfGenerator.php
    ├── middleware/
    │   ├── RoleMiddleware.php     # Control de acceso por rol
    │   └── BusinessMiddleware.php # Aislamiento multi-tenant y límites de plan
    ├── includes/                 # Modelos de dominio
    │   ├── Database.php          # Conexión PDO singleton
    │   ├── Producto.php
    │   ├── Categoria.php
    │   ├── Movimiento.php
    │   ├── Marca.php
    │   ├── ModeloMoto.php
    │   ├── Proveedor.php
    │   ├── Pedido.php
    │   ├── AlertaStock.php
    │   ├── Auditoria.php
    │   ├── Usuario.php
    │   └── AppException.php
    ├── api/                      # Endpoints REST (respuesta JSON estándar)
    ├── soporte/                  # Sistema de tickets del panel de empresa
    ├── superadmin/               # Módulo completo del panel superadmin
    │   ├── dashboard.php
    │   ├── businesses.php
    │   ├── employees.php
    │   ├── tickets.php
    │   ├── billing.php
    │   ├── reports.php
    │   ├── campaigns.php
    │   ├── announcements.php
    │   ├── logs.php
    │   └── settings.php
    ├── css/
    ├── js/
    ├── uploads/                  # Imágenes de productos, logos, adjuntos de tickets
    └── tests/                    # Tests unitarios e integración
```

---

## ⚙️ Variables de entorno

Copia `.env.example` a `.env` y ajusta los valores reales. Las variables ya están inyectadas en `docker-compose.yml` para el entorno de desarrollo, por lo que en local no es necesario crear el `.env` manualmente.

```env
# Base de datos
DB_HOST=db
DB_NAME=inventario_motos
DB_USER=admin
DB_PASS=

# Aplicación
APP_ENV=development
APP_KEY=              # openssl rand -base64 32

# SMTP (para emails de contacto, tickets y campañas)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_NAME=es21plus

# Superadmin
SUPERADMIN_EMAIL=
STOCK_MINIMO_ALERTA=5
```

> 🔒 El archivo `.env` está en `.gitignore`. Nunca lo incluyas en el repositorio.

---

## 🧪 Tests

El proyecto usa **PHPUnit 11** con suites separadas para tests unitarios e integración.

```bash
# Ejecutar todos los tests
docker compose exec web ./vendor/bin/phpunit

# Solo tests unitarios
docker compose exec web ./vendor/bin/phpunit --testsuite Unit

# Solo tests de integración
docker compose exec web ./vendor/bin/phpunit --testsuite Integration

# Con reporte de cobertura
docker compose exec web ./vendor/bin/phpunit --coverage-text
```

Los tests se ejecutan automáticamente en cada push a `main` mediante el workflow de GitHub Actions definido en `.github/workflows/tests.yml`.

---

## 🌐 API REST

Todos los endpoints devuelven JSON con el formato estándar:

```json
{
  "success": true,
  "data": { },
  "message": "Descripción de la operación"
}
```

Endpoints disponibles bajo `/api/`:

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/productos.php` | Listar productos de la empresa |
| `POST` | `/api/productos.php` | Crear producto |
| `PUT` | `/api/productos.php` | Editar producto |
| `DELETE` | `/api/productos.php` | Eliminar producto |
| `GET` | `/api/categorias.php` | Listar categorías |
| `GET/POST` | `/api/movimientos.php` | Listar / registrar movimientos |
| `GET` | `/api/proveedores.php` | Listar proveedores |
| `GET` | `/api/empleados.php` | Listar empleados |
| `POST` | `/api/contact_send.php` | Enviar mensaje / crear ticket |
| `POST` | `/api/change_password.php` | Cambiar contraseña |
| `GET` | `/api/check_slug.php` | Verificar disponibilidad de slug |

---

## 🔁 Cron — Campañas de email programadas

Para procesar campañas con envío programado, configura una tarea cron en el servidor:

```bash
# Ejecutar cada 5 minutos
*/5 * * * * php /ruta/al/proyecto/cron/send_campaigns.php
```

En entorno de desarrollo puedes ejecutarlo manualmente:

```bash
docker compose exec web php /var/www/html/../../../cron/send_campaigns.php
```

---

## 👥 Roles y accesos

| Rol | Acceso |
|-----|--------|
| `superadmin` | Solo panel `/superadmin`. Gestiona todas las empresas. |
| `admin` | Panel de empresa completo. Gestiona empleados, productos y configuración. |
| `employee` | Panel de empresa con permisos limitados. Sin gestión de usuarios ni configuración. |

---

## 👤 Autor

**Carlos Vico Díaz**
Proyecto *es21plus* · DAW · 2026
