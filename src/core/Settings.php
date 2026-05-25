<?php
/**
 * Acceso centralizado a la configuración del sistema almacenada en BD.
 *
 * Usa caché estática para evitar consultas repetidas en la misma petición.
 * Los valores de la tabla system_settings tienen prioridad sobre los .env
 * cuando están rellenados.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';

class Settings
{
    /** @var array<string,string>|null Caché estática; null = sin cargar */
    private static ?array $cache = null;

    /**
     * Obtiene el valor de una clave de configuración.
     *
     * @param string $key
     * @param mixed  $default Valor por defecto si la clave no existe o está vacía.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadAll();
        $val = self::$cache[$key] ?? null;
        return ($val !== null && $val !== '') ? $val : $default;
    }

    /**
     * Actualiza el valor de una clave e invalida la caché estática.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public static function set(string $key, string $value): void
    {
        try {
            $pdo = Database::getInstance();
            $pdo->prepare(
                'UPDATE system_settings SET value = ? WHERE setting_key = ?'
            )->execute([$value, $key]);
            self::$cache = null;
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }

    /**
     * Devuelve todas las configuraciones como array asociativo clave → valor.
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        self::loadAll();
        return self::$cache ?? [];
    }

    /**
     * Invalida la caché; fuerza recarga en el próximo get/all.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function loadAll(): void
    {
        if (self::$cache !== null) {
            return;
        }
        try {
            $pdo        = Database::getInstance();
            $rows       = $pdo->query('SELECT setting_key, value FROM system_settings')->fetchAll();
            self::$cache = array_column($rows, 'value', 'setting_key');
        } catch (\Throwable) {
            self::$cache = [];
        }
    }
}
