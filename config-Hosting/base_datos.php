<?php
declare(strict_types=1);
// Las variables de entorno permiten configurar el despliegue sin modificar el código.
return [
    'servidor' => getenv('INTRANET_BD_SERVIDOR') ?: '127.0.0.1',
    'puerto' => getenv('INTRANET_BD_PUERTO') ?: '3306',
    'nombre' => getenv('INTRANET_BD_NOMBRE') ?: 'muniniquen_intranet_municipal',
    'usuario' => getenv('INTRANET_BD_USUARIO') ?: 'muniniquen_intranet_municipal',
    'clave' => getenv('INTRANET_BD_CLAVE') ?: 'h.zOApM%P~4xJ-{)',
];
