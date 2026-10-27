<?php
declare(strict_types=1);

function normalizar_rut(string $valor): string
{
    $valor = strtoupper(preg_replace('/[.\s]/u', '', trim($valor)) ?? '');
    if (!preg_match('/\A([0-9]{1,8})-?([0-9K])\z/', $valor, $partes)) {
        throw new RuntimeException('Ingresa un RUT chileno válido, con su dígito verificador.');
    }
    $cuerpo = ltrim($partes[1], '0');
    if ($cuerpo === '') {
        throw new RuntimeException('El RUT no es válido.');
    }
    $suma = 0;
    $factor = 2;
    foreach (str_split(strrev($cuerpo)) as $digito) {
        $suma += (int) $digito * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }
    $resto = 11 - ($suma % 11);
    $dv = match ($resto) {
        11 => '0',
        10 => 'K',
        default => (string) $resto,
    };
    if ($dv !== $partes[2]) {
        throw new RuntimeException('El dígito verificador del RUT no es correcto.');
    }
    return $cuerpo . '-' . $dv;
}

function formatear_rut(?string $valor): string
{
    $valor = $valor ?? '';
    try {
        $normalizado = normalizar_rut($valor);
    } catch (RuntimeException) {
        return $valor;
    }
    [$cuerpo, $verificador] = explode('-', $normalizado);
    return number_format((int) $cuerpo, 0, ',', '.') . '-' . $verificador;
}

function formatear_ruts_en_texto(string $texto): string
{
    return preg_replace_callback(
        '/(?<![0-9A-Za-z])(?:[0-9]{1,3}(?:\.[0-9]{3}){1,2}|[0-9]{1,8})-[0-9Kk](?![0-9A-Za-z])/',
        static fn(array $coincidencia): string => formatear_rut($coincidencia[0]),
        $texto,
    ) ?? $texto;
}

function validar_nombre(string $valor, string $campo): string
{
    $valor = preg_replace('/\s+/u', ' ', trim($valor)) ?? '';
    if (
        $valor === '' ||
        mb_strlen($valor) > 120 ||
        !preg_match("/\A[\p{L}\p{M}][\p{L}\p{M} '\x{2019}-]*\z/u", $valor)
    ) {
        throw new RuntimeException('Revisa el campo ' . $campo . ' (solo nombres, hasta 120 caracteres).');
    }
    return $valor;
}

function autenticar_persona(string $rut, string $pin): ?array
{
    try {
        $rut = normalizar_rut($rut);
    } catch (RuntimeException) {
        return null;
    }
    if (!pin_valido($pin)) {
        return null;
    }
    $usuario = consulta(
        'SELECT u.*,r.nombre rol,r.clave,r.version FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.rut=? AND u.activo=1',
        [$rut],
    )->fetch();
    // El PIN se verifica únicamente contra el rol asignado a este RUT.
    if (!$usuario || !password_verify($pin, $usuario['clave'])) {
        return null;
    }
    unset($usuario['clave']);
    return $usuario;
}

function existe_administrador_personal(): bool
{
    return (bool) consulta(
        'SELECT EXISTS(SELECT 1 FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre="Administrador" AND u.rut IS NOT NULL AND u.activo=1)',
    )->fetchColumn();
}

function registrar_administrador_inicial(string $nombres, string $apellidos, string $rut, string $pin): void
{
    global $bd;
    $nombres = validar_nombre($nombres, 'nombres');
    $apellidos = validar_nombre($apellidos, 'apellidos');
    $rut = normalizar_rut($rut);
    $bd->beginTransaction();
    try {
        $rol = consulta('SELECT * FROM roles WHERE nombre="Administrador" FOR UPDATE')->fetch();
        if (!$rol || !pin_valido($pin) || !password_verify($pin, $rol['clave'])) {
            throw new RuntimeException('El PIN de Administrador no es correcto.');
        }
        if (existe_administrador_personal()) {
            throw new RuntimeException(
                'El primer Administrador ya está registrado. Ingresa con tu RUT y PIN.',
            );
        }
        if (consulta('SELECT id FROM usuarios WHERE rut=?', [$rut])->fetch()) {
            throw new RuntimeException('Ese RUT ya está registrado.');
        }
        consulta(
            'INSERT INTO usuarios(nombre,nombres,apellidos,rut,correo,unidad,cargo,rol_id,activo) VALUES(?,?,?,?,NULL,?,?,?,1)',
            [
                $nombres . ' ' . $apellidos,
                $nombres,
                $apellidos,
                $rut,
                'Administración',
                'Administrador de intranet',
                $rol['id'],
            ],
        );
        consulta('INSERT INTO auditoria(usuario_id,autor_nombre,autor_rut,accion) VALUES(?,?,?,?)', [
            (int) $bd->lastInsertId(),
            $nombres . ' ' . $apellidos,
            $rut,
            'Registro del primer Administrador',
        ]);
        $bd->commit();
    } catch (Throwable $ex) {
        if ($bd->inTransaction()) {
            $bd->rollBack();
        }
        throw $ex;
    }
}
