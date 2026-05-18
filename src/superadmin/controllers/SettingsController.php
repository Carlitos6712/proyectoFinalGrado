<?php
/**
 * Controlador de configuración global del sistema.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/Settings.php';
require_once __DIR__ . '/../../core/Mailer.php';

class SettingsController
{
    /**
     * Devuelve todas las configuraciones actuales.
     *
     * @return array<string,string>
     */
    public function index(): array
    {
        return Settings::all();
    }

    /**
     * Guarda la configuración general (app_name, registro, mantenimiento).
     *
     * @param array $data POST data.
     * @return void
     */
    public function saveGeneral(array $data): void
    {
        $appName = trim($data['app_name'] ?? '');
        if ($appName === '') {
            throw new AppException('El nombre de la aplicación no puede estar vacío.', 422);
        }

        Settings::set('app_name',            htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'));
        Settings::set('registration_open',   isset($data['registration_open']) ? '1' : '0');
        Settings::set('maintenance_mode',    isset($data['maintenance_mode'])  ? '1' : '0');
        Settings::set('maintenance_message', trim($data['maintenance_message'] ?? ''));
        Settings::set('default_plan',        in_array($data['default_plan'] ?? '', ['free','basic','pro']) ? $data['default_plan'] : 'free');
        Settings::set('trial_days',          (string)max(0, (int)($data['trial_days'] ?? 14)));
    }

    /**
     * Guarda la configuración de correo SMTP.
     * La contraseña se cifra con APP_KEY antes de almacenar.
     *
     * @param array $data POST data.
     * @return void
     */
    public function saveEmail(array $data): void
    {
        Settings::set('superadmin_email', trim($data['superadmin_email'] ?? ''));
        Settings::set('smtp_host',        trim($data['smtp_host']        ?? ''));
        Settings::set('smtp_port',        (string)(int)($data['smtp_port'] ?? 587));
        Settings::set('smtp_user',        trim($data['smtp_user']        ?? ''));
        Settings::set('smtp_from_name',   trim($data['smtp_from_name']   ?? ''));

        $newPassword = $data['smtp_password'] ?? '';
        if ($newPassword !== '') {
            Settings::set('smtp_password', Mailer::encryptSmtpPassword($newPassword));
        }
    }

    /**
     * Envía un email de prueba al superadmin_email configurado.
     *
     * @return bool
     */
    public function testEmail(): bool
    {
        $to = Settings::get('superadmin_email') ?: getenv('SUPERADMIN_EMAIL') ?: '';
        if (!$to) {
            throw new AppException('No hay email de superadmin configurado en Ajustes → Correo.', 422);
        }

        Mailer::send(
            $to,
            'Email de prueba – es21plus',
            '<h2>Email de prueba</h2>
             <p>Si recibes este correo, la configuración SMTP funciona correctamente.</p>
             <p><em>es21plus · ' . date('Y-m-d H:i:s') . '</em></p>'
        );

        return true;
    }
}
