<?php
declare(strict_types=1);

function base_publica(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    return rtrim(preg_replace('~/(?:public/)?[^/]+\.php$~', '', $script) ?? '', '/');
}

function ruta_publica(string $direccion): string
{
    if (
        $direccion === '' ||
        str_starts_with($direccion, '#') ||
        preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $direccion)
    ) {
        return $direccion;
    }
    $partes = parse_url(html_entity_decode($direccion, ENT_QUOTES, 'UTF-8'));
    if ($partes === false) {
        return $direccion;
    }
    $base = base_publica();
    $ruta = $partes['path'] ?? 'index.php';
    if (str_starts_with($ruta, $base . '/public/')) {
        $ruta = substr($ruta, strlen($base . '/public/'));
    }
    parse_str($partes['query'] ?? '', $parametros);
    $destinos = [
        'ingresar.php' => 'ingresar',
        'registro_inicial.php' => 'primer-acceso',
        'plantilla_personas.php' => 'plantilla-personas',
        'descargar.php' => 'descargar',
        'respaldar.php' => 'respaldar',
        'aviso.php' => 'aviso',
    ];
    if ($ruta === 'index.php') {
        $modulo = $parametros['modulo'] ?? ($_GET['modulo'] ?? 'inicio');
        $permitidos = [
            'inicio',
            'noticias',
            'directorio',
            'documentos',
            'sistemas',
            'soporte',
            'administracion',
            'ayuda',
        ];
        if (!is_string($modulo) || !in_array($modulo, $permitidos, true)) {
            return $direccion;
        }
        $ruta = $modulo;
        unset($parametros['modulo']);
    } elseif (isset($destinos[$ruta])) {
        $ruta = $destinos[$ruta];
    } elseif (preg_match('~^(?:css/|imagenes/|interacciones\.js)~', $ruta)) {
        $ruta = 'recursos/' . $ruta;
    } else {
        return $direccion;
    }
    $consulta = $parametros ? '?' . http_build_query($parametros) : '';
    $fragmento = isset($partes['fragment']) ? '#' . $partes['fragment'] : '';
    return $base . '/' . $ruta . $consulta . $fragmento;
}

function presentar_rutas_limpias(string $contenido): string
{
    // Los documentos y respaldos se entregan sin transformar sus bytes.
    foreach (headers_list() as $cabecera) {
        if (stripos($cabecera, 'Content-Disposition: attachment') === 0) {
            return $contenido;
        }
    }
    return preg_replace_callback(
        '~\b(href|src|action)="([^"<>]*)"~i',
        fn(array $atributo): string => $atributo[1] .
            '="' .
            htmlspecialchars(ruta_publica($atributo[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '"',
        $contenido,
    ) ?? $contenido;
}
