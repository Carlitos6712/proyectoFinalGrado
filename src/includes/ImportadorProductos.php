<?php
/**
 * Importador masivo de productos desde CSV.
 *
 * Procesa un archivo CSV fila a fila, insertando o actualizando productos.
 * Implementa rollback completo si más del 50% de las filas fallan.
 * Registra la importación en auditoría.
 *
 * Columnas esperadas en el CSV (insensible a mayúsculas, con o sin BOM):
 *   Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */

require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../core/Session.php';

class ImportadorProductos
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const COLUMNAS = ['ref','nombre','categoria','marca','precio','stock','stock minimo','descripcion','proveedor','ubicacion'];

    /** Mapeo de variantes de nombre de columna → clave interna */
    private const ALIAS_COLS = [
        // ref / código de referencia
        'ref'                     => 'ref',
        'codigo_ref'              => 'ref',
        'código ref.'             => 'ref',
        'código ref'              => 'ref',
        'codigo ref'              => 'ref',
        'referencia'              => 'ref',
        'código de referencia'    => 'ref',
        'codigo de referencia'    => 'ref',
        'sku'                     => 'ref',
        // nombre
        'nombre'                  => 'nombre',
        'producto'                => 'nombre',
        'name'                    => 'nombre',
        // categoría
        'categoria'               => 'categoria',
        'categoría'               => 'categoria',
        'categoria_nombre'        => 'categoria',
        'category'                => 'categoria',
        // marca
        'marca'                   => 'marca',
        'marca_nombre'            => 'marca',
        'brand'                   => 'marca',
        // precio
        'precio'                  => 'precio',
        'precio (€)'              => 'precio',
        'price'                   => 'precio',
        // stock
        'stock'                   => 'stock',
        // stock mínimo / minimo
        'stock minimo'            => 'stock minimo',
        'stock mínimo'            => 'stock minimo',
        'stock_minimo'            => 'stock minimo',
        // descripción
        'descripcion'             => 'descripcion',
        'descripción'             => 'descripcion',
        // proveedor
        'proveedor'               => 'proveedor',
        // ubicación
        'ubicacion'               => 'ubicacion',
        'ubicación'               => 'ubicacion',
    ];

    private PDO       $pdo;
    private Auditoria $auditoria;
    private ?int      $businessId;

    /**
     * @param PDO|null      $pdo       Conexión PDO (usa singleton si no se pasa).
     * @param Auditoria|null $auditoria Instancia de auditoría.
     * @author Carlitos6712
     */
    public function __construct(?PDO $pdo = null, ?Auditoria $auditoria = null)
    {
        $this->pdo        = $pdo       ?? Database::getInstance();
        $this->auditoria  = $auditoria ?? new Auditoria($this->pdo);
        $this->businessId = Session::getBusinessId();
    }

    /**
     * Valida el archivo subido antes de procesarlo.
     *
     * @param array $file Entrada de $_FILES['campo'].
     * @return void
     * @throws AppException Si el archivo no es válido.
     * @author Carlitos6712
     */
    public function validarArchivo(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('Error al subir el archivo.', 400);
        }

        if (($file['size'] ?? 0) > self::MAX_FILE_SIZE) {
            throw new AppException('Archivo demasiado grande. Máximo 5 MB.', 400);
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new AppException('Solo se permiten archivos CSV.', 400);
        }
    }

    /**
     * Procesa el archivo CSV e inserta o actualiza productos.
     *
     * @param string $rutaTemporal    Ruta al archivo temporal.
     * @param bool   $modoActualizacion Si true, actualiza productos por Ref si existe.
     * @return array{insertados: int, actualizados: int, errores: array}
     * @throws AppException Si más del 50% de las filas fallan (con rollback).
     * @author Carlitos6712
     */
    public function procesar(string $rutaTemporal, bool $modoActualizacion = false): array
    {
        $insertados  = 0;
        $actualizados = 0;
        $errores     = [];

        $handle = fopen($rutaTemporal, 'r');
        if ($handle === false) {
            throw new AppException('No se pudo abrir el archivo CSV.', 500);
        }

        // Leer cabecera
        $cabeceraRaw = fgetcsv($handle, 0, ',', '"', '\\');
        if ($cabeceraRaw === false || $cabeceraRaw === null) {
            fclose($handle);
            throw new AppException('El archivo CSV está vacío o mal formado.', 400);
        }

        // Limpiar BOM UTF-8 de la primera columna
        $cabeceraRaw[0] = ltrim($cabeceraRaw[0], "\xEF\xBB\xBF");

        // Normalizar cabeceras aplicando alias
        $cabeceras = array_map(function ($c) {
            $key = strtolower(trim($c));
            return self::ALIAS_COLS[$key] ?? $key;
        }, $cabeceraRaw);

        // Validar columna mínima obligatoria
        if (!in_array('nombre', $cabeceras, true)) {
            fclose($handle);
            throw new AppException("El CSV debe tener al menos la columna 'nombre'.", 400);
        }
        if ($modoActualizacion && !in_array('ref', $cabeceras, true)) {
            fclose($handle);
            throw new AppException("El modo actualización requiere la columna 'ref'.", 400);
        }

        // Leer todas las filas de datos
        $filas = [];
        while (($fila = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($fila === [null]) {
                continue; // fila vacía
            }
            $filas[] = $fila;
        }
        fclose($handle);

        if (empty($filas)) {
            return ['insertados' => 0, 'actualizados' => 0, 'errores' => []];
        }

        $this->pdo->beginTransaction();

        try {
            $numLinea = 1; // 1 = primera fila de datos (después de cabecera)
            foreach ($filas as $fila) {
                $numLinea++;

                // Mapear por nombre de columna
                $dato = [];
                foreach ($cabeceras as $idx => $col) {
                    $dato[$col] = isset($fila[$idx]) ? trim($fila[$idx]) : '';
                }

                // Validar nombre
                if (empty($dato['nombre'] ?? '')) {
                    $errores[] = ['linea' => $numLinea, 'motivo' => 'El nombre es obligatorio.'];
                    continue;
                }

                // Validar precio
                $precioStr = $dato['precio'] ?? '0';
                if (!is_numeric($precioStr) || (float)$precioStr < 0) {
                    $errores[] = ['linea' => $numLinea, 'motivo' => 'El precio debe ser un número >= 0.'];
                    continue;
                }

                // Validar stock
                $stockStr = $dato['stock'] ?? '0';
                if (!is_numeric($stockStr) || (int)$stockStr < 0) {
                    $errores[] = ['linea' => $numLinea, 'motivo' => 'El stock debe ser un entero >= 0.'];
                    continue;
                }

                $nombre      = $dato['nombre'];
                $descripcion = $dato['descripcion'] ?? '';
                $precio      = (float)$precioStr;
                $stock       = (int)$stockStr;
                $stockMinimo = isset($dato['stock minimo']) && is_numeric($dato['stock minimo'])
                    ? (int)$dato['stock minimo']
                    : 5;
                $ref         = $dato['ref']       ?? '';
                $proveedor   = $dato['proveedor'] ?? '';
                $ubicacion   = $dato['ubicacion'] ?? '';
                $categoriaId = isset($dato['categoria']) ? $this->resolverCategoria($dato['categoria']) : null;
                $codigoRef   = $ref !== '' ? $ref : null;

                // Modo actualización: si ref existe, UPDATE
                if ($modoActualizacion && $codigoRef !== null) {
                    $idExistente = $this->buscarPorRef($codigoRef);
                    if ($idExistente !== null) {
                        $stmt = $this->pdo->prepare(
                            "UPDATE productos SET nombre=:nombre, descripcion=:descripcion, precio=:precio,
                             stock_minimo=:stock_minimo, categoria_id=:categoria_id, proveedor=:proveedor,
                             ubicacion=:ubicacion, updated_at=CURRENT_TIMESTAMP
                             WHERE id=:id AND deleted_at IS NULL"
                        );
                        $stmt->execute([
                            ':nombre'       => $nombre,
                            ':descripcion'  => $descripcion,
                            ':precio'       => $precio,
                            ':stock_minimo' => $stockMinimo,
                            ':categoria_id' => $categoriaId,
                            ':proveedor'    => $proveedor,
                            ':ubicacion'    => $ubicacion,
                            ':id'           => $idExistente,
                        ]);
                        $actualizados++;
                        continue;
                    }
                }

                // Modo inserción: si ref ya existe, es error
                if (!$modoActualizacion && $codigoRef !== null) {
                    $idExistente = $this->buscarPorRef($codigoRef);
                    if ($idExistente !== null) {
                        $errores[] = [
                            'linea'  => $numLinea,
                            'motivo' => "Ref '{$codigoRef}' ya existe, usa modo actualización.",
                        ];
                        continue;
                    }
                }

                // INSERT
                $bizCol = $this->businessId !== null ? ', business_id' : '';
                $bizVal = $this->businessId !== null ? ', :biz_id'     : '';
                $stmt = $this->pdo->prepare(
                    "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id, proveedor, ubicacion{$bizCol})
                     VALUES (:nombre, :descripcion, :precio, :stock, :stock_minimo, :codigo_ref, :categoria_id, :proveedor, :ubicacion{$bizVal})"
                );
                $params = [
                    ':nombre'       => $nombre,
                    ':descripcion'  => $descripcion,
                    ':precio'       => $precio,
                    ':stock'        => $stock,
                    ':stock_minimo' => $stockMinimo,
                    ':codigo_ref'   => $codigoRef,
                    ':categoria_id' => $categoriaId,
                    ':proveedor'    => $proveedor,
                    ':ubicacion'    => $ubicacion,
                ];
                if ($this->businessId !== null) {
                    $params[':biz_id'] = $this->businessId;
                }
                $stmt->execute($params);
                $insertados++;
            }

            // Verificar umbral del 50 %
            $totalFilas = count($filas);
            if ($totalFilas > 0 && (count($errores) / $totalFilas) > 0.5) {
                $this->pdo->rollBack();
                throw new AppException(
                    sprintf(
                        'Importación cancelada: %d de %d filas fallaron (>50%%). Se ha hecho rollback.',
                        count($errores),
                        $totalFilas
                    ),
                    422
                );
            }

            $this->pdo->commit();

        } catch (AppException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new AppException('Error interno durante la importación: ' . $e->getMessage(), 500);
        }

        // Registrar en auditoría
        $this->auditoria->registrar(
            'productos',
            0,
            'importacion_csv',
            null,
            ['insertados' => $insertados, 'actualizados' => $actualizados, 'errores' => count($errores)]
        );

        return ['insertados' => $insertados, 'actualizados' => $actualizados, 'errores' => $errores];
    }

    /**
     * Busca un producto por código de referencia.
     *
     * @param string $ref Código de referencia.
     * @return int|null ID del producto o null si no existe.
     * @author Carlitos6712
     */
    private function buscarPorRef(string $ref): ?int
    {
        $bizWhere = $this->businessId !== null ? " AND business_id = :biz_id" : "";
        $stmt = $this->pdo->prepare(
            "SELECT id FROM productos WHERE codigo_ref = :ref AND deleted_at IS NULL{$bizWhere} LIMIT 1"
        );
        $params = [':ref' => $ref];
        if ($this->businessId !== null) {
            $params[':biz_id'] = $this->businessId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Resuelve el ID de una categoría por su nombre.
     *
     * @param string $nombre Nombre de la categoría.
     * @return int|null ID de la categoría o null si no existe.
     * @author Carlitos6712
     */
    private function resolverCategoria(string $nombre): ?int
    {
        if (empty(trim($nombre))) {
            return null;
        }
        $bizWhere = $this->businessId !== null ? " AND business_id = :biz_id" : "";
        $stmt = $this->pdo->prepare(
            "SELECT id FROM categorias WHERE nombre = :nombre{$bizWhere} LIMIT 1"
        );
        $params = [':nombre' => trim($nombre)];
        if ($this->businessId !== null) {
            $params[':biz_id'] = $this->businessId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
