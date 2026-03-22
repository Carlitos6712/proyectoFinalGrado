<?php
/**
 * Excepción personalizada de la aplicación.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */
class AppException extends RuntimeException
{
    /**
     * @param string         $message  Mensaje descriptivo del error.
     * @param int            $code     Código HTTP o interno (default 0).
     * @param \Throwable|null $previous Excepción previa para encadenamiento.
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Retorna la representación del error como array JSON-friendly.
     *
     * @return array{success: bool, message: string, code: int}
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'code'    => $this->getCode(),
        ];
    }
}
