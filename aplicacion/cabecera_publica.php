<?php
function mostrar_cabecera_publica(string $titulo, string $clase_publica = ''): void
{
    ?>
<!doctype html>
<html lang="es">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= e($titulo) ?> · Intranet Ñiquén</title>
        <link rel="stylesheet" href="css/estilos.css">
        <script src="interacciones.js" defer></script>
    </head>

    <body class="publica <?= e($clase_publica) ?>">
        <header class="marca-publica">
            <a class="logo-publico" href="index.php">
                <img src="imagenes/logo_municipalidad.png" width="560" height="217"
                    alt="Ilustre Municipalidad de Ñiquén. ¡El progreso lo construimos juntos!">
            </a>
            <div class="identificador-intranet">
                <strong>Intranet municipal</strong>
                <small>REGIÓN DE ÑUBLE · ESPACIO INTERNO</small>
            </div>
        </header>
        <?php
}
