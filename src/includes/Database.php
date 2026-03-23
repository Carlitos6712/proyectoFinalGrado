<?php
/**
 * Gestión de la conexión PDO a la base de datos.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */
class Database
{
    /** @var PDO|null Instancia singleton */
    private static ?PDO $instance = null;

    /** Constructor privado para evitar instanciación directa. */
    private function __construct() {}

    /**
     * Retorna la instancia PDO singleton.
     *
     * @throws AppException Si falla la conexión.
     * @return PDO Instancia de conexión PDO.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    /**
     * Crea la conexión PDO usando variables de entorno.
     *
     * @throws AppException Si faltan variables de entorno o falla la conexión.
     * @return PDO
     */
    private static function connect(): PDO
    {
        // getenv() es más fiable que $_ENV en Apache+Docker
        $host   = getenv('DB_HOST')   ?: null;
        $port   = getenv('DB_PORT')   ?: '3306';
        $dbname = getenv('DB_NAME')   ?: null;
        $user   = getenv('DB_USER')   ?: null;
        $pass   = getenv('DB_PASS');
        // Permitir contraseña vacía (string vacío es válido)
        if ($pass === false) { $pass = null; }

        if (!$host || !$dbname || !$user || $pass === null) {
            throw new AppException('Faltan variables de entorno para la base de datos.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        return new PDO($dsn, $user, $pass, $options);
    }
}
