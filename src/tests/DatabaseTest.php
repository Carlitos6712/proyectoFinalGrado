<?php
/**
 * Unit tests for Database PDO Singleton.
 *
 * Covers:
 *  - connects successfully with valid env vars
 *  - returns same PDO instance on multiple calls (Singleton)
 *  - throws AppException when connection fails (invalid host)
 *
 * @package  Es21Plus\Tests
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private ?PDO $savedInstance    = null;
    private string|false $savedHost = false;

    protected function setUp(): void
    {
        $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
        $prop->setAccessible(true);
        $this->savedInstance = $prop->getValue();
        $this->savedHost     = getenv('DB_HOST');
    }

    protected function tearDown(): void
    {
        $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->savedInstance);

        if ($this->savedHost === false) {
            putenv('DB_HOST');
        } else {
            putenv("DB_HOST={$this->savedHost}");
        }
    }

    #[Test]
    public function connects_successfully_with_valid_env_vars(): void
    {
        $pdo = Database::getInstance();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    #[Test]
    public function returns_same_instance_on_multiple_calls(): void
    {
        $pdo1 = Database::getInstance();
        $pdo2 = Database::getInstance();
        $this->assertSame($pdo1, $pdo2);
    }

    #[Test]
    public function throws_AppException_when_connection_fails(): void
    {
        $prop = (new \ReflectionClass(Database::class))->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        putenv('DB_HOST=255.255.255.255');

        $this->expectException(AppException::class);
        Database::getInstance();
    }
}
