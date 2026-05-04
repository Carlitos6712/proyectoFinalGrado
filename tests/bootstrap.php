<?php
/**
 * Bootstrap for PHPUnit — sets env vars, includes from the mounted src/.
 *
 * Run from container:
 *   docker exec inventario_motos_web php /tmp/phpunit.phar --bootstrap /var/www/html/tests/bootstrap.php /var/www/html/tests/
 *
 * @author Carlitos6712
 */

// Env vars are already set by docker-compose; re-declare for CLI runs
$_ENV['DB_HOST']  = getenv('DB_HOST')  ?: 'db';
$_ENV['DB_PORT']  = getenv('DB_PORT')  ?: '3306';
$_ENV['DB_NAME']  = getenv('DB_NAME')  ?: 'inventario_motos';
$_ENV['DB_USER']  = getenv('DB_USER')  ?: 'admin';
$_ENV['DB_PASS']  = getenv('DB_PASS')  ?: 'luigi21plus';

require_once __DIR__ . '/../src/includes/AppException.php';
require_once __DIR__ . '/../src/includes/Database.php';
require_once __DIR__ . '/../src/includes/Producto.php';
require_once __DIR__ . '/../src/includes/Movimiento.php';
require_once __DIR__ . '/../src/includes/Categoria.php';
