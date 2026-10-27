<?php
require __DIR__ . '/../aplicacion/inicio.php';

$codigo = (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);
if (!in_array($codigo, [403, 404, 500], true)) {
    $codigo = 404;
}

mostrar_aviso_pagina(
    'La página solicitada no está disponible o no tienes acceso. Presiona Aceptar para iniciar sesión.',
    $codigo,
);
