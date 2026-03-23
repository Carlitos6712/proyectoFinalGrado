# 🏍️ es21plus · Sistema de Gestión de Inventario

> Aplicación web para el control de inventario de un taller mecánico de motos.
> Gestiona productos, stock y movimientos de entrada/salida de forma sencilla y eficiente.

---

## 🚀 Características principales

- 📦 Registro y gestión completa de productos (CRUD)
- 📊 Control de stock en tiempo real con alertas de stock bajo
- ➕➖ Entradas y salidas de inventario con historial de movimientos
- 🔍 Búsqueda y filtrado de productos por nombre, referencia y categoría
- 🧾 Organización por categorías personalizables
- 🗑️ Eliminación segura mediante soft-delete
- 🌐 Interfaz web responsive accesible desde el navegador

---

## 🛠️ Tecnologías

| Capa | Tecnologías |
|------|-------------|
| **Backend** | PHP 8, PDO, MySQL 8 |
| **Frontend** | Vanilla JavaScript, HTML5, CSS3 |
| **Infraestructura** | Docker, Docker Compose |
| **Control de versiones** | Git, GitHub |

---

## 🗄️ Base de datos

MySQL 8 alojado en contenedor Docker. El schema se carga automáticamente al levantar el entorno.

**Tablas principales:**

| Tabla | Descripción |
|-------|-------------|
| `categorias` | Categorías de productos (frenos, motor, transmisión…) |
| `productos` | Catálogo de productos con stock, precio y referencia |
| `movimientos` | Historial de entradas y salidas de inventario |

---

## 🐳 Instalación con Docker

### Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/es21plus.git
cd es21plus/sistema-inventario-motos

# 2. Configurar variables de entorno
cp .env.example .env

# 3. Levantar los contenedores
docker-compose up -d --build

# 4. Acceder a la aplicación
# http://localhost:8080
```

> ⚠️ Si el puerto 3306 está ocupado (MySQL local instalado), el proyecto usa el **3307** para la base de datos. La aplicación sigue funcionando con normalidad.

### Comandos útiles

```bash
# Ver logs en tiempo real
docker-compose logs -f web

# Parar los contenedores
docker-compose down

# Reiniciar y resetear la base de datos
docker-compose down -v && docker-compose up -d --build
```

---

## 📁 Estructura del proyecto

```
sistema-inventario-motos/
├── docker-compose.yml
├── Dockerfile
├── apache.conf             # Config de Apache
├── php.ini                 # Config de PHP
├── .env.example            # Variables de entorno de ejemplo
├── database/
│   └── schema.sql          # DDL completo (se carga automáticamente)
└── src/
    ├── index.php            # Dashboard principal
    ├── nuevo_producto.php
    ├── editar_producto.php
    ├── eliminar_producto.php
    ├── movimientos.php
    ├── categorias.php
    ├── buscar.php
    ├── api/                 # Endpoints REST JSON
    ├── includes/
    │   ├── Database.php     # Conexión PDO singleton
    │   ├── Producto.php     # Modelo productos
    │   ├── Categoria.php    # Modelo categorías
    │   ├── Movimiento.php   # Modelo movimientos
    │   └── AppException.php # Manejo centralizado de errores
    ├── css/
    └── js/
```

---

## ⚙️ Variables de entorno

Copia `.env.example` a `.env` y ajusta los valores:

```env
DB_HOST=db
DB_PORT=3306
DB_NAME=inventario_motos
DB_USER=admin
DB_PASS=tu_password
```

> 🔒 El archivo `.env` está en `.gitignore`. Nunca lo incluyas en el repositorio.

---

## 👤 Autor

**Carlos Vico**
Proyecto *es21plus* · 2026
