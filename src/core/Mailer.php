<?php
/**
 * Servicio de envío de email centralizado via PHPMailer.
 *
 * Lee configuración SMTP desde variables de entorno.
 * Si el envío falla, registra en logs/mail_errors.log y lanza excepción
 * controlada — nunca interrumpe el flujo principal si el llamador captura.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
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
     * @throws \RuntimeException Si el envío falla (para que el llamador pueda manejarla).
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

        $host     = getenv('MAIL_HOST')      ?: getenv('SMTP_HOST')      ?: '';
        $port     = (int)(getenv('MAIL_PORT')     ?: getenv('SMTP_PORT')     ?: 587);
        $user     = getenv('MAIL_USERNAME')  ?: getenv('SMTP_USER')      ?: '';
        $pass     = getenv('MAIL_PASSWORD')  ?: getenv('SMTP_PASS')      ?: '';
        $fromName = getenv('MAIL_FROM_NAME') ?: getenv('SMTP_FROM_NAME') ?: 'es21plus';

        if (!$host || !$user) {
            self::logError("SMTP no configurado (MAIL_HOST o MAIL_USERNAME vacío).");
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
     * Escribe un mensaje en logs/mail_errors.log.
     *
     * @param string $message
     * @return void
     */
    private static function logError(string $message): void
    {
        $logDir  = __DIR__ . '/../../logs';
        $logFile = $logDir . '/mail_errors.log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
