<?php
declare(strict_types=1);
require_once __DIR__ . '/librerias/autoload.php';

function encabezado_importacion(string $valor): string
{
    $valor = mb_strtolower(trim($valor));
    $valor = strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    $valor = trim(preg_replace('/[^a-z0-9]+/', ' ', $valor) ?? '');
    return match ($valor) {
        'nombre' => 'nombres',
        'apellido' => 'apellidos',
        'r u t' => 'rut',
        default => $valor,
    };
}

function dividir_fila_pdf(string $linea): array
{
    return array_map('trim', preg_split('/(?:\t+| {2,}|\s*[;|]\s*)/u', trim($linea)) ?: []);
}

function extraer_filas_personas(string $ruta, string $extension): array
{
    if ($extension === 'pdf') {
        $pdf = (new \Smalot\PdfParser\Parser())->parseFile($ruta);
        if (count($pdf->getPages()) > 30) {
            throw new RuntimeException('El PDF supera las 30 páginas por carga.');
        }
        $texto = $pdf->getText();
        if (trim($texto) === '') {
            throw new RuntimeException(
                'El PDF no contiene texto seleccionable. Exporta el listado desde Excel o usa un PDF con texto.',
            );
        }
        $filas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            if (trim($linea) !== '') {
                $filas[] = dividir_fila_pdf($linea);
            }
        }
        return ['PDF' => $filas];
    }
    if ($extension === 'xlsx') {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('El archivo Excel no es válido.');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $dato = $zip->statIndex($i);
            $total += $dato['size'];
        }
        $cantidad = $zip->numFiles;
        $zip->close();
        if ($total > 50 * 1024 * 1024 || $cantidad > 5000) {
            throw new RuntimeException('El archivo Excel contiene demasiados datos. Divide el listado.');
        }
    }
    $lector = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($extension === 'xls' ? 'Xls' : 'Xlsx');
    $lector->setReadDataOnly(true);
    $total = 0;
    foreach ($lector->listWorksheetInfo($ruta) as $hoja) {
        $total += $hoja['totalRows'];
        if ($total > 5000 || $hoja['totalColumns'] > 30) {
            throw new RuntimeException('El Excel admite hasta 5000 filas y 30 columnas por carga.');
        }
    }
    $libro = $lector->load($ruta);
    $salida = [];
    try {
        foreach ($libro->getWorksheetIterator() as $hoja) {
            $filas = [];
            foreach ($hoja->getRowIterator() as $fila) {
                $valores = [];
                foreach ($fila->getCellIterator() as $celda) {
                    // No calcula ni ejecuta fórmulas de archivos importados.
                    $valores[] =
                        $celda->getDataType() === 'f'
                            ? '[Fórmula no permitida]'
                            : (string) $celda->getValue();
                }
                $filas[] = $valores;
            }
            if ($filas) {
                $salida[$hoja->getTitle()] = $filas;
            }
        }
    } finally {
        $libro->disconnectWorksheets();
    }
    return $salida;
}

function registrar_filas_personas(array $hojas): array
{
    global $bd;
    exigir('administracion');
    $roles = [];
    foreach (consulta('SELECT id,nombre FROM roles')->fetchAll() as $rol) {
        $roles[encabezado_importacion($rol['nombre'])] = $rol['id'];
    }
    $resultado = ['registrados' => 0, 'omitidos' => 0, 'invalidos' => 0, 'errores' => []];
    $bd->beginTransaction();
    try {
        $vistas = 0;
        $cabeceras = 0;
        foreach ($hojas as $hoja => $filas) {
            $mapa = null;
            foreach ($filas as $numero => $fila) {
                if (++$vistas > 5000) {
                    throw new RuntimeException('El archivo supera las 5000 filas por carga.');
                }
                if (trim(implode('', $fila)) === '') {
                    continue;
                }
                $normal = array_map(fn($v) => encabezado_importacion((string) $v), $fila);
                $posiciones = array_flip($normal);
                if (
                    isset(
                        $posiciones['nombres'],
                        $posiciones['apellidos'],
                        $posiciones['rut'],
                        $posiciones['rol'],
                    )
                ) {
                    $mapa = $posiciones;
                    $cabeceras++;
                    continue;
                }
                if ($mapa === null) {
                    continue;
                }
                try {
                    $rut = normalizar_rut((string) ($fila[$mapa['rut']] ?? ''));
                    if (consulta('SELECT id FROM usuarios WHERE rut=?', [$rut])->fetch()) {
                        $resultado['omitidos']++;
                        continue;
                    }
                    $nombres = validar_nombre((string) ($fila[$mapa['nombres']] ?? ''), 'nombres');
                    $apellidos = validar_nombre((string) ($fila[$mapa['apellidos']] ?? ''), 'apellidos');
                    $rol = encabezado_importacion((string) ($fila[$mapa['rol']] ?? ''));
                    if (!isset($roles[$rol])) {
                        throw new RuntimeException(
                            'Rol no reconocido. Usa Administrador, Informática, Editor o Funcionario.',
                        );
                    }
                    try {
                        consulta(
                            'INSERT INTO usuarios(nombre,nombres,apellidos,rut,correo,unidad,cargo,rol_id,activo) VALUES(?,?,?,?,NULL,?,?,?,1)',
                            [
                                $nombres . ' ' . $apellidos,
                                $nombres,
                                $apellidos,
                                $rut,
                                'Sin unidad asignada',
                                'Sin cargo asignado',
                                $roles[$rol],
                            ],
                        );
                    } catch (PDOException $ex) {
                        if (($ex->errorInfo[1] ?? 0) == 1062) {
                            $resultado['omitidos']++;
                            continue;
                        }
                        throw $ex;
                    }
                    $resultado['registrados']++;
                    auditar(
                        'Registró por importación: ' .
                            $nombres .
                            ' ' .
                            $apellidos .
                            ' · RUT ' .
                            formatear_rut($rut) .
                            ' · Rol ' .
                            $rol,
                    );
                } catch (RuntimeException $ex) {
                    if ($ex instanceof PDOException) {
                        throw $ex;
                    }
                    $resultado['invalidos']++;
                    if (count($resultado['errores']) < 50) {
                        $resultado['errores'][] =
                            $hoja . ' · fila ' . ($numero + 1) . ': ' . $ex->getMessage();
                    }
                }
            }
        }
        if (!$cabeceras) {
            throw new RuntimeException(
                'No se encontraron las columnas Nombres, Apellidos, RUT y Rol. En PDF, usa una tabla con columnas separadas o líneas con punto y coma.',
            );
        }
        auditar(
            'Importación de personas: ' .
                $resultado['registrados'] .
                ' registradas, ' .
                $resultado['omitidos'] .
                ' RUT repetidos, ' .
                $resultado['invalidos'] .
                ' inválidas.',
        );
        $bd->commit();
        return $resultado;
    } catch (Throwable $ex) {
        if ($bd->inTransaction()) {
            $bd->rollBack();
        }
        throw $ex;
    }
}

function importar_archivo_personas(): array
{
    exigir('administracion');
    $archivo = $_FILES['archivo_personas'] ?? null;
    if (
        !$archivo ||
        !is_string($archivo['name']) ||
        $archivo['error'] !== UPLOAD_ERR_OK ||
        !is_uploaded_file($archivo['tmp_name'])
    ) {
        throw new RuntimeException('Selecciona un solo archivo Excel o PDF válido por carga.');
    }
    if ($archivo['size'] < 1 || $archivo['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('El archivo debe pesar entre 1 byte y 5 MB.');
    }
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $tipos = [
        'pdf' => ['application/pdf'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'xls' => [
            'application/vnd.ms-excel',
            'application/x-ole-storage',
            'application/CDFV2',
            'application/octet-stream',
        ],
    ];
    if (!isset($tipos[$extension]) || !in_array($mime, $tipos[$extension], true)) {
        throw new RuntimeException('Solo se admiten Excel XLSX, 
    XLS o PDF con texto seleccionable.');
    }
    try {
        $hojas = extraer_filas_personas($archivo['tmp_name'], $extension);
    } catch (Throwable $ex) {
        error_log((string) $ex);
        throw new RuntimeException(
            $ex instanceof RuntimeException && !($ex instanceof PDOException)
                ? $ex->getMessage()
                : 'No se pudo leer el archivo. Verifica su formato y que no esté protegido ni escaneado.',
        );
    }
    return registrar_filas_personas($hojas);
}
