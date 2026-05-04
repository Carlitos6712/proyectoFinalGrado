<?php
/**
 * Bootstrap for PHPUnit — sets env vars, includes from the mounted src/.
 *
 * Run from container:
 *   docker exec inventario_motos_web php /tmp/phpunit.phar --bootstrap /var/www/html/tests/bootstrap.php /var/www/html/tests/
 *
 * @author Carlitos6712
 * @author   miguelrechefdez
 */

// Env vars are already set by docker-compose; re-declare for CLI runs.
// Use putenv() so Database::getInstance() can read via getenv().
// Local fallbacks use localhost:3307 (Docker exposes DB on host port 3307).
$defaults = [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3307',
    'DB_NAME' => 'inventario_motos',
    'DB_USER' => 'admin',
    'DB_PASS' => 'luigi21plus',
];
foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Producto.php';
require_once __DIR__ . '/../includes/Movimiento.php';
require_once __DIR__ . '/../includes/Categoria.php';
require_once __DIR__ . '/../includes/csrf.php';
