<?php
function campo(
    string $nombre,
    string $etiqueta,
    array $datos = [],
    string $tipo = 'text',
    int $max = 180,
    bool $obligatorio = true,
): void {
    $valor = $_SESSION['formulario'][$nombre] ?? ($datos[$nombre] ?? '');
    echo '<label>' . e($etiqueta) . ($obligatorio ? ' <span class="requerido">*</span>' : '');
    if ($tipo === 'textarea') {
        echo '<textarea name="' .
            e($nombre) .
            '" rows="6" maxlength="' .
            $max .
            '" ' .
            ($obligatorio ? 'required' : '') .
            '>' .
            e($valor) .
            '</textarea>';
    } else {
        echo '<input type="' .
            e($tipo) .
            '" name="' .
            e($nombre) .
            '" value="' .
            ($tipo === 'password' ? '' : e($valor)) .
            '" maxlength="' .
            $max .
            '" ' .
            ($obligatorio ? 'required' : '') .
            '>';
    }
    echo '</label>';
}
function selector(string $nombre, string $etiqueta, array $opciones, array $datos = []): void
{
    $valor = $_SESSION['formulario'][$nombre] ?? ($datos[$nombre] ?? '');
    echo '<label>' . e($etiqueta) . '<select name="' . e($nombre) . '">';
    foreach ($opciones as $k => $v) {
        echo '<option value="' .
            e($k) .
            '" ' .
            ((string) $valor === (string) $k ? 'selected' : '') .
            '>' .
            e($v) .
            '</option>';
    }
    echo '</select></label>';
}
function opciones(array $valores): array
{
    return array_combine($valores, $valores);
}
function comenzar_formulario(string $accion, int $id = 0, bool $archivo = false): void
{
    echo '<form method="post" ' .
        ($archivo ? 'enctype="multipart/form-data"' : '') .
        '>' .
        csrf() .
        '<input type="hidden" name="accion" value="' .
        e($accion) .
        '"><input type="hidden" name="id" value="' .
        $id .
        '">';
}
function terminar_formulario(string $modulo): void
{
    echo '<div class="acciones"><button>Guardar cambios</button><a class="boton secundario" href="index.php?modulo=' .
        e($modulo) .
        '">Cancelar</a></div></form>';
}
function encabezado(string $titulo, string $descripcion, string $boton = '', string $enlace = ''): void
{
    echo '<div class="encabezado"><div><span class="etiqueta">INTRANET MUNICIPAL</span><h1>' .
        e($titulo) .
        '</h1><p>' .
        e($descripcion) .
        '</p></div>';
    if ($boton) {
        echo '<a class="boton" href="' . e($enlace) . '">＋ ' . e($boton) . '</a>';
    }
    echo '</div>';
}
function filtros(
    string $modulo,
    string $placeholder,
    array $categorias = [],
    string $campo = 'categoria',
): void {
    echo '<form class="filtros" method="get"><input type="hidden" name="modulo" value="' .
        e($modulo) .
        '"><label class="busqueda"><span class="sr">Buscar</span><input type="search" name="q" value="' .
        e($_GET['q'] ?? '') .
        '" placeholder="' .
        e($placeholder) .
        '" maxlength="180"></label>';
    if ($categorias) {
        selector($campo, ucfirst($campo), ['' => 'Todas las opciones'] + opciones($categorias), $_GET);
    }
    echo '<button>Buscar</button><a class="boton-texto" href="index.php?modulo=' .
        e($modulo) .
        '">Limpiar</a></form>';
}
function pagina_datos(
    string $tabla,
    string $condicion,
    array $parametros,
    string $orden = 'id DESC',
    int $por_pagina = 12,
): array
{
    $por_pagina = max(1, $por_pagina);
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $total = (int) consulta(
        'SELECT COUNT(*) FROM ' . $tabla . ' WHERE ' . $condicion,
        $parametros,
    )->fetchColumn();
    $paginas = max(1, (int) ceil($total / $por_pagina));
    $pagina = min($pagina, $paginas);
    $filas = consulta(
        'SELECT * FROM ' .
            $tabla .
            ' WHERE ' .
            $condicion .
            ' ORDER BY ' .
            $orden .
            ' LIMIT ' . $por_pagina . ' OFFSET ' .
            ($pagina - 1) * $por_pagina,
        $parametros,
    )->fetchAll();
    return [$filas, $total, $pagina, $paginas];
}
function paginacion(
    int $total,
    int $pagina,
    int $paginas,
    bool $numerada = false,
    string $parametro = 'pagina',
    string $ancla = '',
): void
{
    if ($numerada) {
        echo '<nav class="paginacion paginacion-centrada" aria-label="Paginación del listado">';
        echo '<span>' . $total . ' registros · Página ' . $pagina . ' de ' . $paginas . '</span>';
        echo '<div class="paginacion-controles">';
        $enlace = static function (int $destino, string $texto, string $etiqueta) use ($pagina, $paginas, $parametro, $ancla): void {
            if ($destino < 1 || $destino > $paginas) {
                echo '<span class="boton secundario deshabilitado" aria-disabled="true">' . e($texto) . '</span>';
                return;
            }
            if ($destino === $pagina) {
                echo '<span class="boton pagina-actual" aria-current="page" aria-label="' . e($etiqueta) . '">' . e($texto) . '</span>';
                return;
            }
            $consulta = $_GET;
            $consulta[$parametro] = $destino;
            $fragmento = $ancla !== '' ? '#' . rawurlencode($ancla) : '';
            echo '<a class="boton secundario" aria-label="' . e($etiqueta) . '" href="?' . e(http_build_query($consulta) . $fragmento) . '">' . e($texto) . '</a>';
        };
        $enlace($pagina - 1, '← Anterior', 'Página anterior');
        $numeros = array_unique(array_merge([1], range(max(1, $pagina - 2), min($paginas, $pagina + 2)), [$paginas]));
        sort($numeros);
        $anterior = 0;
        foreach ($numeros as $numero) {
            if ($anterior > 0 && $numero - $anterior > 1) {
                echo '<span class="paginacion-separador" aria-hidden="true">…</span>';
            }
            $enlace($numero, (string) $numero, 'Página ' . $numero);
            $anterior = $numero;
        }
        $enlace($pagina + 1, 'Siguiente →', 'Página siguiente');
        echo '</div></nav>';
        return;
    }
    echo '<div class="paginacion"><span>' .
        $total .
        ' registros · Página ' .
        $pagina .
        ' de ' .
        $paginas .
        '</span><div>';
    foreach (['← Anterior' => $pagina - 1, 'Siguiente →' => $pagina + 1] as $texto => $p) {
        if ($p >= 1 && $p <= $paginas) {
            $q = $_GET;
            $q[$parametro] = $p;
            echo '<a class="boton secundario" href="?' . e(http_build_query($q)) . '">' . e($texto) . '</a> ';
        }
    }
    echo '</div></div>';
}
function vacio(string $titulo, string $texto): void
{
    echo '<div class="vacio"><span>◇</span><h3>' . e($titulo) . '</h3><p>' . e($texto) . '</p></div>';
}
