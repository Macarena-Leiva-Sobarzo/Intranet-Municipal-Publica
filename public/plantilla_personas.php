<?php
require __DIR__ . '/../aplicacion/inicio.php';
$bd = conexion();
$persona = isset($_SESSION['usuario'])
    ? consulta(
        'SELECT u.*,r.nombre rol,r.version FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1',
        [$_SESSION['usuario']['id']],
    )->fetch()
    : false;
if (
    !$persona ||
    !$persona['rut'] ||
    $persona['rol'] !== 'Administrador' ||
    (int) $persona['version'] !== (int) $_SESSION['usuario']['version'] ||
    (int) $persona['rol_id'] !== (int) $_SESSION['usuario']['rol_id'] ||
    ($_SESSION['actividad'] ?? 0) < time() - 3600
) {
    mostrar_aviso_pagina('Solo el Administrador puede descargar la plantilla de personas.', 403);
}
$_SESSION['actividad'] = time();
require __DIR__ . '/../aplicacion/librerias/autoload.php';
$libro = new PhpOffice\PhpSpreadsheet\Spreadsheet();
$hoja = $libro->getActiveSheet();
$hoja->setTitle('Personas');
$hoja->fromArray([['Nombres', 'Apellidos', 'RUT', 'Rol']]);
$hoja->getStyle('A1:D1')->getFont()->setBold(true);
$hoja->getColumnDimension('A')->setWidth(28);
$hoja->getColumnDimension('B')->setWidth(28);
$hoja->getColumnDimension('C')->setWidth(18);
$hoja->getColumnDimension('D')->setWidth(22);
$hoja->getStyle('C2:C5000')->getNumberFormat()->setFormatCode('@');
auditar('Descargó plantilla de importación de personas');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="plantilla_personas.xlsx"');
(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
$libro->disconnectWorksheets();
