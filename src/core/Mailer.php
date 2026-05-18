<?php
/**
 * Servicio de envío de email centralizado via PHPMailer.
 *
 * Prioridad de configuración SMTP:
 *   1. Tabla system_settings (Settings::get)
 *   2. Variables de entorno MAIL_* / SMTP_*
 *
 * La contraseña SMTP se guarda cifrada en BD (AES-256-CBC con APP_KEY).
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.1.0
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    /**
     * Envía un correo HTML al destinatario indicado.
     *
     * @param  string $to       Dirección de destino.
     * @param  string $subject  Asunto del correo.
     * @param  string $bodyHtml Cuerpo en HTML.
     * @throws \RuntimeException Si el envío falla.
     * @return bool True si se envió correctamente.
     */
    public static function send(string $to, string $subject, string $bodyHtml): bool
    {
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($autoload)) {
            self::logError("vendor/autoload.php no encontrado. Ejecuta: composer install");
            throw new \RuntimeException('PHPMailer no disponible: vendor no instalado.');
        }
        require_once $autoload;

        // Cargar Settings solo si está disponible (evitar error si DB no levantó aún)
        $settingsHost = $settingsUser = $settingsPass = $settingsPort = $settingsFromName = '';
        if (class_exists('Settings')) {
            $settingsHost     = Settings::get('smtp_host',      '');
            $settingsUser     = Settings::get('smtp_user',      '');
            $settingsPass     = Settings::get('smtp_password',  '');
            $settingsPort     = Settings::get('smtp_port',      '');
            $settingsFromName = Settings::get('smtp_from_name', '');
        }

        $host     = $settingsHost     ?: (getenv('MAIL_HOST')      ?: getenv('SMTP_HOST')      ?: '');
        $port     = (int)(($settingsPort ?: (getenv('MAIL_PORT')   ?: getenv('SMTP_PORT')      ?: 587)));
        $user     = $settingsUser     ?: (getenv('MAIL_USERNAME')  ?: getenv('SMTP_USER')      ?: '');
        $pass     = $settingsPass
            ? self::decryptSmtpPassword($settingsPass)
            : (getenv('MAIL_PASSWORD') ?: getenv('SMTP_PASS') ?: '');
        $fromName = $settingsFromName ?: (getenv('MAIL_FROM_NAME') ?: getenv('SMTP_FROM_NAME') ?: 'es21plus');

        if (!$host || !$user) {
            self::logError("SMTP no configurado (host o usuario vacío).");
            throw new \RuntimeException('SMTP no configurado.');
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($user, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = strip_tags($bodyHtml);

            $mail->send();
            return true;
        } catch (MailException $e) {
            $msg = "Error al enviar a {$to}: {$mail->ErrorInfo}";
            self::logError($msg);
            throw new \RuntimeException($msg);
        }
    }

    /**
     * Cifra una contraseña SMTP con AES-256-CBC usando APP_KEY del entorno.
     *
     * @param string $password Contraseña en texto plano.
     * @return string Contraseña cifrada en base64, o el valor original si no hay APP_KEY.
     */
    public static function encryptSmtpPassword(string $password): string
    {
        $appKey = getenv('APP_KEY');
        if (!$appKey || $password === '') {
            return $password;
        }
        try {
            $iv  = random_bytes(16);
            $enc = openssl_encrypt($password, 'AES-256-CBC', $appKey, OPENSSL_RAW_DATA, $iv);
            return base64_encode($iv . $enc);
        } catch (\Throwable) {
            return $password;
        }
    }

    /**
     * Descifra una contraseña SMTP cifrada con encryptSmtpPassword.
     *
     * @param string $encrypted
     * @return string Contraseña descifrada, o el valor original si no se puede descifrar.
     */
    private static function decryptSmtpPassword(string $encrypted): string
    {
        $appKey = getenv('APP_KEY');
        if (!$appKey || $encrypted === '') {
            return $encrypted;
        }
        try {
            $data = base64_decode($encrypted, true);
            if ($data === false || strlen($data) <= 16) {
                return $encrypted;
            }
            $iv  = substr($data, 0, 16);
            $enc = substr($data, 16);
            $dec = openssl_decrypt($enc, 'AES-256-CBC', $appKey, OPENSSL_RAW_DATA, $iv);
            return ($dec !== false) ? $dec : $encrypted;
        } catch (\Throwable) {
            return $encrypted;
        }
    }

    private static function logError(string $message): void
    {
        $logDir  = __DIR__ . '/../../logs';
        $logFile = $logDir . '/mail_errors.log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
