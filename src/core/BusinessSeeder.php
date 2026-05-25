<?php
/**
 * Siembra datos de demostración al crear un nuevo negocio.
 *
 * Genera categorías y productos de ejemplo para que el panel
 * no arranque vacío tras el alta de una empresa.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';

class BusinessSeeder
{
    /**
     * Inserta datos de demostración para el negocio recién creado.
     *
     * Crea 3 categorías y 5 productos con stock inicial.
     * Si ya existen datos para ese business_id no hace nada.
     *
     * @param int $businessId ID del negocio al que pertenecen los datos.
     * @return void
     */
    public static function seed(int $businessId): void
    {
        $pdo = Database::getInstance();

        $check = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE business_id = ?');
        $check->execute([$businessId]);
        if ((int)$check->fetchColumn() > 0) {
            return;
        }

        $pdo->beginTransaction();
        try {
            $catStmt = $pdo->prepare(
                'INSERT INTO categorias (nombre, descripcion, business_id) VALUES (?, ?, ?)'
            );

            $cats = [
                ['Repuestos',   'Piezas de recambio y repuestos mecánicos'],
                ['Aceites',     'Lubricantes y fluidos para mantenimiento'],
                ['Accesorios',  'Accesorios y equipamiento para moto'],
            ];
            $catIds = [];
            foreach ($cats as [$nombre, $desc]) {
                $catStmt->execute([$nombre, $desc, $businessId]);
                $catIds[] = (int)$pdo->lastInsertId();
            }

            $prodStmt = $pdo->prepare(
                'INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo,
                 categoria_id, business_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $productos = [
                ['Filtro de aceite',         'Filtro compatible con modelos estándar', 8.50,  20, 5, $catIds[0]],
                ['Pastillas de freno',        'Kit pastillas delantera + trasera',      18.00, 10, 3, $catIds[0]],
                ['Aceite Motor 10W-40 1L',    'Aceite sintético para 4T',               12.00, 30, 8, $catIds[1]],
                ['Líquido de frenos DOT 4',   'Frasco 250 ml',                           5.50, 15, 4, $catIds[1]],
                ['Guantes de protección XL',  'Guantes homologados talla XL',           35.00,  8, 2, $catIds[2]],
            ];

            foreach ($productos as [$nombre, $desc, $precio, $stock, $stockMin, $catId]) {
                $prodStmt->execute([$nombre, $desc, $precio, $stock, $stockMin, $catId, $businessId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // Seed failure is non-blocking — business creation must not fail
        }
    }
}
