<?php
/**
 * Servicio de alertas de stock bajo por email.
 *
 * Verifica tras cada salida si el stock ha caído por debajo del mínimo
 * y envía un email de alerta al destinatario configurado en ALERT_EMAIL.
 * Usa la tabla alertas_stock para evitar duplicados dentro del mismo
 * episodio de stock bajo (entre una alerta y la siguiente entrada que
 * recupere el stock).
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */

require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class AlertaStock
{
    /** @var callable(string $to, string $subject, string $body): void */
    private $transport;

    /**
     * @param PDO           $pdo       Conexión PDO inyectada.
     * @param callable|null $transport Función de envío; si es null, usa PHPMailer SMTP.
     * @author Carlitos6712
     */
    public function __construct(private PDO $pdo, ?callable $transport = null)
    {
        $this->transport = $transport ?? $this->crearTransportPhpMailer();
    }

    /**
     * Verifica si el stock bajó del mínimo y envía la alerta si procede.
     *
     * No envía si: stock >= stock_minimo, alertas desactivadas para el
     * producto, ya hay una alerta activa en el mismo episodio, o ALERT_EMAIL
     * no está configurado.
     *
     * @param int $productoId  ID del producto afectado.
     * @param int $stockActual Stock tras el movimiento de salida.
     * @return void
     * @author Carlitos6712
     */
    public function verificarYEnviar(int $productoId, int $stockActual): void
    {
        $producto = $this->obtenerProducto($productoId);
        if ($producto === null) {
            return;
        }

        if ($stockActual > (int) $producto['stock_minimo']) {
            return;
        }

        if (!(bool) $producto['alertas_email']) {
            return;
        }

        if ($this->hayAlertaActivaParaEpisodio($productoId)) {
            return;
        }

        $alertEmail = $_ENV['ALERT_EMAIL'] ?? '';
        if ($alertEmail === '') {
            return;
        }

        $appUrl  = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $subject = "Alerta de stock bajo: {$producto['nombre']}";
        $body    = $this->construirCuerpoEmail($producto['nombre'], $stockActual, (int) $producto['stock_minimo'], $appUrl);

        ($this->transport)($alertEmail, $subject, $body);
        $this->registrarAlerta($productoId, $stockActual);
    }

    /**
     * Obtiene los datos básicos del producto necesarios para la alerta.
     *
     * @param int $productoId
     * @return array<string,mixed>|null
     * @author Carlitos6712
     */
    public function obtenerProducto(int $productoId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT nombre, stock_minimo, alertas_email
             FROM productos
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $productoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row !== false) ? $row : null;
    }

    /**
     * Determina si hay una alerta activa para el episodio actual de stock bajo.
     *
     * Un episodio es activo si existe una alerta previa Y no ha habido
     * ningún movimiento de entrada desde esa alerta (la entrada indica
     * posible recuperación de stock).
     *
     * @param int $productoId
     * @return bool
     * @author Carlitos6712
     */
    public function hayAlertaActivaParaEpisodio(int $productoId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM alertas_stock a
             WHERE a.producto_id = :id
             AND NOT EXISTS (
                 SELECT 1 FROM movimientos m
                 WHERE m.producto_id = :id2
                   AND m.tipo = 'entrada'
                   AND m.fecha > a.enviada_at
             )"
        );
        $stmt->execute([':id' => $productoId, ':id2' => $productoId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Registra que se ha enviado una alerta para el producto.
     *
     * @param int $productoId
     * @param int $stockActual
     * @return void
     * @author Carlitos6712
     */
    public function registrarAlerta(int $productoId, int $stockActual): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO alertas_stock (producto_id, stock_al_enviar)
             VALUES (:id, :stock)"
        );
        $stmt->execute([':id' => $productoId, ':stock' => $stockActual]);
    }

    /**
     * Construye el cuerpo de texto plano del email de alerta.
     *
     * @param string $nombre      Nombre del producto.
     * @param int    $stock       Stock actual tras el movimiento.
     * @param int    $stockMinimo Umbral mínimo configurado.
     * @param string $appUrl      URL base del panel de administración.
     * @return string
     * @author Carlitos6712
     */
    private function construirCuerpoEmail(string $nombre, int $stock, int $stockMinimo, string $appUrl): string
    {
        return "ALERTA: Stock bajo en el inventario de es21plus.\n\n"
            . "Producto  : {$nombre}\n"
            . "Stock actual : {$stock} unidades\n"
            . "Stock mínimo : {$stockMinimo} unidades\n\n"
            . "Accede al panel para gestionar el inventario:\n"
            . "{$appUrl}/productos.php\n";
    }

    /**
     * Crea el transport PHPMailer usando las variables de entorno SMTP.
     *
     * @return callable
     * @author Carlitos6712
     */
    private function crearTransportPhpMailer(): callable
    {
        return function (string $to, string $subject, string $body): void {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $_ENV['SMTP_HOST'] ?? 'localhost';
                $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 587);
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['SMTP_USER'] ?? '';
                $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                $from     = $_ENV['SMTP_USER']      ?? 'noreply@es21plus.local';
                $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'es21plus Inventario';
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);

                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
            } catch (MailException $e) {
                error_log("AlertaStock: fallo al enviar email – {$mail->ErrorInfo}");
            }
        };
    }
}
