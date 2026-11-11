
<?php
session_start();

require_once __DIR__ . '/conexion.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    exit('Error de conexión con la base de datos.');
}

$conexion->set_charset('utf8mb4');

/* =====================================================
   FUNCIONES GENERALES
===================================================== */

function h($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function tablasPermitidas(): array
{
    return [
        'usuarios',
        'perfiles',
        'disenos',
        'comentarios',
        'likes',
        'publicaciones_foro',
        'respuestas_foro',
        'likes_foro',
        'piercings',
        'mensajes',
        'notificaciones',
        'contactos',
        'reportes',
        'moderacion'
    ];
}

function nombresTablas(): array
{
    return [
        'usuarios' => 'Usuarios',
        'perfiles' => 'Perfiles',
        'disenos' => 'Diseños',
        'comentarios' => 'Comentarios',
        'likes' => 'Me gusta de diseños',
        'publicaciones_foro' => 'Publicaciones del foro',
        'respuestas_foro' => 'Respuestas del foro',
        'likes_foro' => 'Me gusta del foro',
        'piercings' => 'Piercings',
        'mensajes' => 'Mensajes',
        'notificaciones' => 'Notificaciones',
        'contactos' => 'Contactos',
        'reportes' => 'Reportes',
        'moderacion' => 'Moderación'
    ];
}

function ejecutarPreparado(
    mysqli $conexion,
    string $sql,
    string $tipos = '',
    array $valores = []
): mysqli_stmt {

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        throw new Exception('No fue posible preparar la consulta.');
    }

    if ($tipos !== '') {
        $referencias = [$tipos];

        foreach ($valores as $key => $valor) {
            $referencias[] = &$valores[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $referencias);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception($error);
    }

    return $stmt;
}

function obtenerColumnas(mysqli $conexion, string $tabla): array
{
    if (!in_array($tabla, tablasPermitidas(), true)) {
        throw new Exception('Tabla no permitida.');
    }

    $resultado = $conexion->query("SHOW COLUMNS FROM `$tabla`");

    if (!$resultado) {
        throw new Exception('No fue posible consultar la estructura.');
    }

    $columnas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $columnas[] = $fila;
    }

    return $columnas;
}

function obtenerClavePrimaria(array $columnas): string
{
    foreach ($columnas as $columna) {
        if ($columna['Key'] === 'PRI') {
            return $columna['Field'];
        }
    }

    throw new Exception('La tabla no tiene clave primaria.');
}

function obtenerTipoCampo(string $tipo): string
{
    $tipo = strtolower($tipo);

    if (preg_match('/^enum\(/', $tipo)) {
        return 'enum';
    }

    if (
        str_contains($tipo, 'int') ||
        str_contains($tipo, 'decimal') ||
        str_contains($tipo, 'float') ||
        str_contains($tipo, 'double')
    ) {
        return 'number';
    }

    if (str_contains($tipo, 'text')) {
        return 'textarea';
    }

    if (str_contains($tipo, 'datetime') || str_contains($tipo, 'timestamp')) {
        return 'datetime';
    }

    if (str_contains($tipo, 'date')) {
        return 'date';
    }

    return 'text';
}

function opcionesEnum(string $tipo): array
{
    if (!preg_match('/^enum\((.*)\)$/i', $tipo, $coincidencias)) {
        return [];
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $coincidencias[1],
        $opciones
    );

    return $opciones[1] ?? [];
}

/* =====================================================
   CARGA DE IMÁGENES
===================================================== */

function esCampoImagen(string $campo): bool
{
    return in_array($campo, ['imagen', 'foto_perfil'], true);
}

function cargarImagen(string $tabla, string $campo): ?string
{
    if (!esCampoImagen($campo)) {
        return null;
    }

    if (
        !isset($_FILES['imagenes'][$campo]) ||
        $_FILES['imagenes'][$campo]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $archivo = $_FILES['imagenes'][$campo];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No fue posible cargar la imagen.');
    }

    if ($archivo['size'] > 5 * 1024 * 1024) {
        throw new Exception('La imagen no puede superar los 5 MB.');
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);

    if (!isset($permitidos[$mime])) {
        throw new Exception('Formato no permitido. Usa JPG, PNG o WEBP.');
    }

    $carpetas = [
        'disenos' => 'img/disenos',
        'piercings' => 'img/piercings',
        'perfiles' => 'img/perfiles'
    ];

    $carpeta = $carpetas[$tabla] ?? 'img/administracion';
    $rutaFisica = __DIR__ . '/' . $carpeta;

    if (!is_dir($rutaFisica)) {
        if (!mkdir($rutaFisica, 0755, true) && !is_dir($rutaFisica)) {
            throw new Exception('No fue posible crear la carpeta de imágenes.');
        }
    }

    $nombreArchivo = $campo . '_' . bin2hex(random_bytes(12)) . '.' . $permitidos[$mime];
    $destino = $rutaFisica . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new Exception('No fue posible guardar la imagen.');
    }

    return $carpeta . '/' . $nombreArchivo;
}

function valorParaBaseDatos($valor, array $columna)
{
    $valor = trim((string)$valor);

    if ($columna['Field'] === 'password') {
        if ($valor === '') {
            return null;
        }

        return password_hash($valor, PASSWORD_DEFAULT);
    }

    if ($valor === '' && $columna['Null'] === 'YES') {
        return null;
    }

    if ($valor === '' && $columna['Null'] === 'NO') {
        return '';
    }

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/i', $columna['Type'])) {
        return (int)$valor;
    }

    if (preg_match('/^(decimal|float|double)/i', $columna['Type'])) {
        return (float)$valor;
    }

    return $valor;
}

/* =====================================================
   VERIFICAR ADMINISTRADOR
===================================================== */

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

$idAdmin = (int)$_SESSION['id_usuario'];

$stmtAdmin = ejecutarPreparado(
    $conexion,
    "SELECT * FROM usuarios WHERE id_usuario = ? LIMIT 1",
    'i',
    [$idAdmin]
);

$admin = $stmtAdmin->get_result()->fetch_assoc();
$stmtAdmin->close();

if (
    !$admin ||
    $admin['tipo_usuario'] !== 'Administrador' ||
    (int)$admin['estado'] !== 1
) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (empty($_SESSION['token_admin'])) {
    $_SESSION['token_admin'] = bin2hex(random_bytes(32));
}

$tokenAdmin = $_SESSION['token_admin'];

$mensaje = '';
$tipoMensaje = 'success';

$tabla = $_GET['tabla'] ?? '';

if ($tabla !== '' && !in_array($tabla, tablasPermitidas(), true)) {
    $tabla = '';
}

/* =====================================================
   PROCESAR FORMULARIOS
===================================================== */

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (
            !isset($_POST['token']) ||
            !hash_equals($tokenAdmin, $_POST['token'])
        ) {
            throw new Exception('La sesión del formulario no es válida. Recarga la página.');
        }

        /* CERRAR SESIÓN */

        if (isset($_POST['cerrar_sesion'])) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $parametros = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $parametros['path'],
                    $parametros['domain'],
                    $parametros['secure'],
                    $parametros['httponly']
                );
            }

            session_destroy();

            header('Location: login.php');
            exit;
        }

        $tablaPost = $_POST['tabla'] ?? '';

        if (!in_array($tablaPost, tablasPermitidas(), true)) {
            throw new Exception('Tabla no permitida.');
        }

        $tabla = $tablaPost;
        $columnas = obtenerColumnas($conexion, $tabla);
        $clave = obtenerClavePrimaria($columnas);

        /* ELIMINAR */

        if (isset($_POST['eliminar'])) {
            $idRegistro = (int)($_POST['id_registro'] ?? 0);

            if ($idRegistro <= 0) {
                throw new Exception('Registro no válido.');
            }

            if ($tabla === 'usuarios' && $idRegistro === $idAdmin) {
                throw new Exception('No puedes eliminar tu propia cuenta.');
            }

            $stmt = ejecutarPreparado(
                $conexion,
                "DELETE FROM `$tabla` WHERE `$clave` = ?",
                'i',
                [$idRegistro]
            );

            $stmt->close();

            $mensaje = 'Registro eliminado correctamente.';
        }

        /* CREAR O EDITAR */

        if (isset($_POST['guardar'])) {

            $modo = $_POST['modo'] ?? 'crear';
            $idRegistro = (int)($_POST['id_registro'] ?? 0);

            if (!in_array($modo, ['crear', 'editar'], true)) {
                throw new Exception('Operación no válida.');
            }

            if ($modo === 'editar' && $idRegistro <= 0) {
                throw new Exception('Registro no válido.');
            }

            if ($tabla === 'usuarios' && $modo === 'editar' && $idRegistro === $idAdmin) {

                if (
                    isset($_POST['datos']['tipo_usuario']) &&
                    $_POST['datos']['tipo_usuario'] !== 'Administrador'
                ) {
                    throw new Exception('No puedes cambiar el rol de tu propia cuenta.');
                }

                if (
                    isset($_POST['datos']['estado']) &&
                    (int)$_POST['datos']['estado'] !== 1
                ) {
                    throw new Exception('No puedes desactivar tu propia cuenta.');
                }
            }

            $campos = [];
            $valores = [];
            $tipos = '';

            foreach ($columnas as $columna) {

                $nombre = $columna['Field'];
                $extra = strtolower($columna['Extra']);

                if (str_contains($extra, 'auto_increment')) {
                    continue;
                }

                if ($modo === 'editar' && $nombre === $clave) {
                    continue;
                }

                if (esCampoImagen($nombre)) {
                    $imagenNueva = cargarImagen($tabla, $nombre);

                    if ($imagenNueva !== null) {
                        $valor = $imagenNueva;
                    } else {
                        $valor = trim((string)($_POST['datos'][$nombre] ?? ''));

                        if ($modo === 'editar') {
                            $valor = $_POST['imagen_actual'][$nombre] ?? $valor;
                        }
                    }
                } else {
                    $valorRecibido = $_POST['datos'][$nombre] ?? '';

                    if (
                        $nombre === 'password' &&
                        $modo === 'editar' &&
                        trim((string)$valorRecibido) === ''
                    ) {
                        continue;
                    }

                    if (
                        $modo === 'crear' &&
                        !isset($_POST['datos'][$nombre]) &&
                        $columna['Default'] !== null
                    ) {
                        continue;
                    }

                    if (
                        $modo === 'crear' &&
                        !isset($_POST['datos'][$nombre]) &&
                        $columna['Null'] === 'YES'
                    ) {
                        continue;
                    }

                    $valor = valorParaBaseDatos($valorRecibido, $columna);
                }

                if ($valor === '' && $columna['Null'] !== 'YES') {
                    throw new Exception('El campo "' . $nombre . '" es obligatorio.');
                }

                $campos[] = $nombre;
                $valores[] = $valor;

                if (is_int($valor)) {
                    $tipos .= 'i';
                } elseif (is_float($valor)) {
                    $tipos .= 'd';
                } else {
                    $tipos .= 's';
                }
            }

            if (!$campos) {
                throw new Exception('No hay información para guardar.');
            }

            if ($modo === 'editar') {

                $asignaciones = [];

                foreach ($campos as $campo) {
                    $asignaciones[] = "`$campo` = ?";
                }

                $tipos .= 'i';
                $valores[] = $idRegistro;

                $sql = "UPDATE `$tabla`
                        SET " . implode(', ', $asignaciones) . "
                        WHERE `$clave` = ?";

                $stmt = ejecutarPreparado($conexion, $sql, $tipos, $valores);
                $stmt->close();

                $mensaje = 'Información actualizada correctamente.';

            } else {

                $camposSQL = array_map(
                    fn($campo) => "`$campo`",
                    $campos
                );

                $marcadores = array_fill(0, count($campos), '?');

                $sql = "INSERT INTO `$tabla` (" . implode(', ', $camposSQL) . ")
                        VALUES (" . implode(', ', $marcadores) . ")";

                $stmt = ejecutarPreparado($conexion, $sql, $tipos, $valores);
                $stmt->close();

                $mensaje = 'Registro creado correctamente.';
            }
        }
    }

} catch (Throwable $e) {
    $mensaje = 'No fue posible completar la operación: ' . $e->getMessage();
    $tipoMensaje = 'danger';
}

/* =====================================================
   ESTADÍSTICAS
===================================================== */

$estadisticas = [];

$consultas = [
    'usuarios' => 'SELECT COUNT(*) total FROM usuarios',
    'artistas' => "SELECT COUNT(*) total FROM usuarios WHERE tipo_usuario = 'Artista'",
    'clientes' => "SELECT COUNT(*) total FROM usuarios WHERE tipo_usuario = 'Cliente'",
    'disenos' => 'SELECT COUNT(*) total FROM disenos',
    'piercings' => 'SELECT COUNT(*) total FROM piercings',
    'publicaciones' => 'SELECT COUNT(*) total FROM publicaciones_foro',
    'comentarios' => 'SELECT COUNT(*) total FROM comentarios',
    'reportes' => "SELECT COUNT(*) total FROM reportes WHERE estado = 'Pendiente'",
    'contactos' => 'SELECT COUNT(*) total FROM contactos'
];

foreach ($consultas as $nombre => $sql) {
    $resultado = $conexion->query($sql);

    $estadisticas[$nombre] = $resultado
        ? (int)$resultado->fetch_assoc()['total']
        : 0;
}

/* =====================================================
   CARGAR TABLA SELECCIONADA
===================================================== */

$registroEditar = null;
$columnas = [];
$registros = [];
$clave = '';

if ($tabla !== '') {

    $columnas = obtenerColumnas($conexion, $tabla);
    $clave = obtenerClavePrimaria($columnas);

    if (isset($_GET['editar'])) {

        $idEditar = (int)$_GET['editar'];

        $stmtEditar = ejecutarPreparado(
            $conexion,
            "SELECT * FROM `$tabla` WHERE `$clave` = ? LIMIT 1",
            'i',
            [$idEditar]
        );

        $registroEditar = $stmtEditar->get_result()->fetch_assoc();
        $stmtEditar->close();
    }

    $resultado = $conexion->query(
        "SELECT * FROM `$tabla` ORDER BY `$clave` DESC LIMIT 100"
    );

    if (!$resultado) {
        exit('No fue posible cargar los registros.');
    }

    while ($fila = $resultado->fetch_assoc()) {
        $registros[] = $fila;
    }
}

$tablas = nombresTablas();

$iconos = [
    'usuarios' => 'fa-users',
    'perfiles' => 'fa-id-card',
    'disenos' => 'fa-palette',
    'comentarios' => 'fa-comment',
    'likes' => 'fa-heart',
    'publicaciones_foro' => 'fa-comments',
    'respuestas_foro' => 'fa-reply',
    'likes_foro' => 'fa-thumbs-up',
    'piercings' => 'fa-gem',
    'mensajes' => 'fa-envelope',
    'notificaciones' => 'fa-bell',
    'contactos' => 'fa-address-book',
    'reportes' => 'fa-flag',
    'moderacion' => 'fa-shield-halved'
];

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administración | Parche del Tattoo</title>

    <!-- ICONOS -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    

    <!-- CSS DEL PANEL DE ADMINISTRACIÓN -->
    <link rel="stylesheet" href="css/admin.css?v=1">

    <!-- CSS RESPONSIVE -->
    <link rel="stylesheet" href="css/responsive.css?v=1">
</head>

<body>

<div class="admin-layout">

    <!-- MENÚ LATERAL -->

    <aside class="admin-sidebar">

        <div class="admin-brand">
            <img src="img/image-removebg-preview.png" alt="Parche del Tattoo">

            <div>
                <h2>Parche del <span>Tattoo</span></h2>
                <small>Panel de administración</small>
            </div>
        </div>

        <div class="admin-user">
            <div class="admin-user-avatar">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div class="admin-user-info">
                <strong><?= h($admin['nombre_usuario']) ?></strong>
                <small><?= h($admin['email']) ?></small>
            </div>
        </div>

        <nav class="admin-menu">

            <div class="admin-menu-title">Principal</div>

            <a href="admin.php" class="<?= $tabla === '' ? 'activo' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                Resumen general
            </a>

            <div class="admin-menu-title">Gestión de usuarios</div>

            <?php foreach (['usuarios', 'perfiles'] as $nombre): ?>
                <a href="admin.php?tabla=<?= h($nombre) ?>"
                   class="<?= $tabla === $nombre ? 'activo' : '' ?>">
                    <i class="fa-solid <?= h($iconos[$nombre]) ?>"></i>
                    <?= h($tablas[$nombre]) ?>
                </a>
            <?php endforeach; ?>

            <div class="admin-menu-title">Contenido</div>

            <?php foreach (['disenos', 'piercings', 'publicaciones_foro', 'respuestas_foro'] as $nombre): ?>
                <a href="admin.php?tabla=<?= h($nombre) ?>"
                   class="<?= $tabla === $nombre ? 'activo' : '' ?>">
                    <i class="fa-solid <?= h($iconos[$nombre]) ?>"></i>
                    <?= h($tablas[$nombre]) ?>
                </a>
            <?php endforeach; ?>

            <div class="admin-menu-title">Interacción</div>

            <?php foreach (['comentarios', 'likes', 'likes_foro', 'mensajes', 'notificaciones'] as $nombre): ?>
                <a href="admin.php?tabla=<?= h($nombre) ?>"
                   class="<?= $tabla === $nombre ? 'activo' : '' ?>">
                    <i class="fa-solid <?= h($iconos[$nombre]) ?>"></i>
                    <?= h($tablas[$nombre]) ?>
                </a>
            <?php endforeach; ?>

            <div class="admin-menu-title">Control y seguimiento</div>

            <?php foreach (['contactos', 'reportes', 'moderacion'] as $nombre): ?>
                <a href="admin.php?tabla=<?= h($nombre) ?>"
                   class="<?= $tabla === $nombre ? 'activo' : '' ?>">
                    <i class="fa-solid <?= h($iconos[$nombre]) ?>"></i>
                    <?= h($tablas[$nombre]) ?>
                </a>
            <?php endforeach; ?>

            <div class="admin-menu-title">Navegación</div>

            <a href="index.php" target="_blank">
                <i class="fa-solid fa-globe"></i>
                Ver sitio web
            </a>

            <a href="comunidad.php" target="_blank">
                <i class="fa-solid fa-people-group"></i>
                Comunidad
            </a>

            <a href="foro.php" target="_blank">
                <i class="fa-solid fa-comments"></i>
                Foro
            </a>

        </nav>

    </aside>

    <!-- CONTENIDO PRINCIPAL -->

    <main class="admin-main">

        <header class="admin-topbar">

            <div>
                <h1>
                    <?= $tabla === ''
                        ? 'Resumen general'
                        : h($tablas[$tabla]) ?>
                </h1>

                <p>
                    Bienvenido al centro de administración de Parche del Tattoo.
                </p>
            </div>

            <div class="admin-top-actions">

                <form method="POST">
                    <input type="hidden" name="token" value="<?= h($tokenAdmin) ?>">

                    <button type="submit"
                            name="cerrar_sesion"
                            value="1"
                            class="admin-btn admin-btn-peligro">

                        <i class="fa-solid fa-right-from-bracket"></i>
                        Cerrar sesión

                    </button>
                </form>

            </div>

        </header>

        <?php if ($mensaje !== ''): ?>
            <div class="admin-alerta <?= h($tipoMensaje) ?>">
                <i class="fa-solid <?= $tipoMensaje === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= h($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- RESUMEN -->

        <?php if ($tabla === ''): ?>

            <section class="admin-estadisticas">

                <?php
                $tarjetas = [
                    ['Usuarios registrados', 'usuarios', 'fa-users'],
                    ['Artistas', 'artistas', 'fa-palette'],
                    ['Clientes', 'clientes', 'fa-user'],
                    ['Diseños publicados', 'disenos', 'fa-image'],
                    ['Piercings', 'piercings', 'fa-gem'],
                    ['Publicaciones del foro', 'publicaciones', 'fa-comments'],
                    ['Comentarios', 'comentarios', 'fa-comment-dots'],
                    ['Reportes pendientes', 'reportes', 'fa-flag'],
                    ['Contactos recibidos', 'contactos', 'fa-envelope']
                ];
                ?>

                <?php foreach ($tarjetas as $tarjeta): ?>
                    <article class="admin-stat">

                        <div class="admin-stat-icon">
                            <i class="fa-solid <?= h($tarjeta[2]) ?>"></i>
                        </div>

                        <div class="admin-stat-info">
                            <span><?= h($tarjeta[0]) ?></span>
                            <strong><?= (int)$estadisticas[$tarjeta[1]] ?></strong>
                        </div>

                    </article>
                <?php endforeach; ?>

            </section>

            <section class="admin-card">

                <div class="admin-card-header">
                    <div>
                        <h2>
                            <i class="fa-solid fa-layer-group"></i>
                            Módulos de administración
                        </h2>

                        <p class="admin-description">
                            Selecciona una sección para consultar y gestionar su información.
                        </p>
                    </div>
                </div>

                <div class="admin-modulos">

                    <?php foreach ($tablas as $nombre => $etiqueta): ?>

                        <a href="admin.php?tabla=<?= h($nombre) ?>" class="admin-modulo">
                            <i class="fa-solid <?= h($iconos[$nombre]) ?>"></i>
                            <span><?= h($etiqueta) ?></span>
                        </a>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php else: ?>

            <!-- GESTIÓN DE TABLAS -->

            <section class="admin-card">

                <div class="admin-card-header">

                    <div>
                        <h2>
                            <i class="fa-solid <?= h($iconos[$tabla]) ?>"></i>
                            Gestión de <?= h($tablas[$tabla]) ?>
                        </h2>

                        <p class="admin-description">
                            Crea, consulta, modifica o elimina los registros de este módulo.
                            Se muestran hasta 100 registros.
                        </p>
                    </div>

                    <a href="admin.php?tabla=<?= h($tabla) ?>&nuevo=1"
                       class="admin-btn">
                        <i class="fa-solid fa-plus"></i>
                        Nuevo registro
                    </a>

                </div>

                <?php if (isset($_GET['nuevo']) || $registroEditar): ?>

                    <div class="admin-formulario-seccion">

                        <div class="admin-card-header">
                            <div>
                                <h2>
                                    <i class="fa-solid <?= $registroEditar ? 'fa-pen-to-square' : 'fa-circle-plus' ?>"></i>
                                    <?= $registroEditar ? 'Editar información' : 'Crear nuevo registro' ?>
                                </h2>

                                <p class="admin-description">
                                    Completa los campos correspondientes.
                                    Los campos marcados con * son obligatorios.
                                </p>
                            </div>
                        </div>

                        <form method="POST"
                              enctype="multipart/form-data"
                              class="admin-formulario">

                            <input type="hidden" name="token" value="<?= h($tokenAdmin) ?>">
                            <input type="hidden" name="tabla" value="<?= h($tabla) ?>">
                            <input type="hidden" name="modo" value="<?= $registroEditar ? 'editar' : 'crear' ?>">
                            <input type="hidden" name="id_registro"
                                   value="<?= $registroEditar ? (int)$registroEditar[$clave] : 0 ?>">

                            <?php foreach ($columnas as $columna): ?>

                                <?php
                                $campo = $columna['Field'];
                                $tipo = strtolower($columna['Type']);
                                $extra = strtolower($columna['Extra']);

                                if (str_contains($extra, 'auto_increment')) {
                                    continue;
                                }

                                if ($registroEditar && $campo === $clave) {
                                    continue;
                                }

                                $valor = $registroEditar[$campo] ?? '';

                                if ($campo === 'password') {
                                    $valor = '';
                                }

                                $tipoHTML = obtenerTipoCampo($tipo);

                                $esCompleto = (
                                    $tipoHTML === 'textarea' ||
                                    in_array($campo, [
                                        'contenido',
                                        'descripcion',
                                        'biografia',
                                        'mensaje',
                                        'comentario'
                                    ], true)
                                );

                                $obligatorio = (
                                    $columna['Null'] === 'NO' &&
                                    $columna['Default'] === null &&
                                    $campo !== 'password'
                                );
                                ?>

                                <div class="admin-campo <?= $esCompleto || esCampoImagen($campo) ? 'completo' : '' ?>">

                                    <label for="campo_<?= h($campo) ?>">
                                        <?= h(ucfirst(str_replace('_', ' ', $campo))) ?>
                                        <?= $obligatorio ? '*' : '' ?>
                                    </label>

                                    <?php if (esCampoImagen($campo)): ?>

                                        <?php
                                        $imagenActual = (string)$valor;
                                        $imagenPreview = $imagenActual !== '' ? $imagenActual : '';
                                        ?>

                                        <input type="hidden"
                                               name="imagen_actual[<?= h($campo) ?>]"
                                               value="<?= h($imagenActual) ?>">

                                        <div class="admin-imagen-contenedor">

                                            <img
                                                src="<?= h($imagenPreview) ?>"
                                                alt="Vista previa"
                                                class="admin-imagen-preview"
                                                id="preview_<?= h($campo) ?>"
                                                <?= $imagenPreview === '' ? 'hidden' : '' ?>
                                            >

                                            <div class="admin-imagen-info">

                                                <p>
                                                    Adjunta una imagen en formato JPG, PNG o WEBP.
                                                    Tamaño máximo: 5 MB.
                                                    <?= $registroEditar ? 'Si no seleccionas otra imagen, se conservará la actual.' : '' ?>
                                                </p>

                                                <label class="admin-file-label"
                                                       for="archivo_<?= h($campo) ?>">
                                                    <i class="fa-solid fa-paperclip"></i>
                                                    Adjuntar imagen
                                                </label>

                                                <input
                                                    class="admin-file-input"
                                                    type="file"
                                                    id="archivo_<?= h($campo) ?>"
                                                    name="imagenes[<?= h($campo) ?>]"
                                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                                    data-preview="preview_<?= h($campo) ?>"
                                                >

                                                <span class="admin-nombre-archivo"
                                                      id="nombre_<?= h($campo) ?>">
                                                    <?= $imagenActual !== '' ? h(basename($imagenActual)) : 'No se ha seleccionado ninguna imagen.' ?>
                                                </span>

                                            </div>

                                        </div>

                                        <small class="admin-ayuda">
                                            <?php if ($imagenActual !== ''): ?>
                                                Imagen actual: <?= h($imagenActual) ?>
                                            <?php else: ?>
                                                La imagen se guardará automáticamente en la carpeta correspondiente.
                                            <?php endif; ?>
                                        </small>

                                    <?php elseif ($campo === 'password'): ?>

                                        <input
                                            type="password"
                                            id="campo_password"
                                            name="datos[password]"
                                            autocomplete="new-password"
                                            <?= !$registroEditar && $columna['Null'] === 'NO' && $columna['Default'] === null ? 'required' : '' ?>
                                            placeholder="<?= $registroEditar ? 'Dejar vacío para conservar la contraseña' : 'Escribe una contraseña' ?>"
                                        >

                                        <small class="admin-ayuda">
                                            La contraseña se almacenará de forma segura mediante password_hash().
                                        </small>

                                    <?php elseif ($tipoHTML === 'enum'): ?>

                                        <select
                                            id="campo_<?= h($campo) ?>"
                                            name="datos[<?= h($campo) ?>]"
                                            <?= $obligatorio ? 'required' : '' ?>
                                        >

                                            <?php if ($columna['Null'] === 'YES'): ?>
                                                <option value="">Sin seleccionar</option>
                                            <?php endif; ?>

                                            <?php foreach (opcionesEnum($tipo) as $opcion): ?>
                                                <option
                                                    value="<?= h($opcion) ?>"
                                                    <?= (string)$valor === $opcion ? 'selected' : '' ?>
                                                >
                                                    <?= h($opcion) ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>

                                    <?php elseif ($tipoHTML === 'textarea'): ?>

                                        <textarea
                                            id="campo_<?= h($campo) ?>"
                                            name="datos[<?= h($campo) ?>]"
                                            <?= $obligatorio ? 'required' : '' ?>
                                        ><?= h($valor) ?></textarea>

                                    <?php else: ?>

                                        <?php
                                        $tipoInput = 'text';

                                        if ($tipoHTML === 'number') {
                                            $tipoInput = 'number';
                                        } elseif ($tipoHTML === 'date') {
                                            $tipoInput = 'date';
                                        } elseif ($tipoHTML === 'datetime') {
                                            $tipoInput = 'datetime-local';

                                            if ($valor && strpos($valor, ' ') !== false) {
                                                $valor = str_replace(' ', 'T', substr($valor, 0, 16));
                                            }
                                        }

                                        if ($campo === 'email') {
                                            $tipoInput = 'email';
                                        }
                                        ?>

                                        <input
                                            type="<?= h($tipoInput) ?>"
                                            id="campo_<?= h($campo) ?>"
                                            name="datos[<?= h($campo) ?>]"
                                            value="<?= h($valor) ?>"
                                            <?= $obligatorio ? 'required' : '' ?>
                                            <?= $tipoInput === 'number' ? 'step="any"' : '' ?>
                                        >

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                            <div class="admin-campo completo">

                                <div class="admin-formulario-acciones">

                                    <button type="submit" name="guardar" value="1" class="admin-btn">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                        Guardar información
                                    </button>

                                    <a href="admin.php?tabla=<?= h($tabla) ?>"
                                       class="admin-btn admin-btn-secundario">
                                        <i class="fa-solid fa-xmark"></i>
                                        Cancelar
                                    </a>

                                </div>

                            </div>

                        </form>

                    </div>

                <?php endif; ?>

            </section>

            <!-- LISTADO -->

            <section class="admin-card">

                <div class="admin-card-header">

                    <div>
                        <h2>
                            <i class="fa-solid fa-list"></i>
                            Registros de <?= h($tablas[$tabla]) ?>
                        </h2>

                        <p class="admin-description">
                            Total de registros mostrados: <?= count($registros) ?>
                        </p>
                    </div>

                    <a href="admin.php?tabla=<?= h($tabla) ?>"
                       class="admin-btn admin-btn-secundario">
                        <i class="fa-solid fa-rotate"></i>
                        Actualizar
                    </a>

                </div>

                <div class="admin-tabla-contenedor">

                    <table class="admin-tabla">

                        <thead>
                            <tr>
                                <?php foreach ($columnas as $columna): ?>
                                    <th><?= h($columna['Field']) ?></th>
                                <?php endforeach; ?>

                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($registros): ?>

                                <?php foreach ($registros as $fila): ?>

                                    <tr>

                                        <?php foreach ($columnas as $columna): ?>

                                            <?php
                                            $campo = $columna['Field'];
                                            $valor = $fila[$campo] ?? '';
                                            ?>

                                            <td>

                                                <?php if ($campo === 'password'): ?>

                                                    <span class="admin-etiqueta">Protegida</span>

                                                <?php elseif (esCampoImagen($campo) && $valor !== ''): ?>

                                                    <img
                                                        src="<?= h($valor) ?>"
                                                        alt="Imagen"
                                                        class="admin-tabla-imagen"
                                                        loading="lazy"
                                                    >

                                                <?php elseif (in_array($campo, ['estado', 'leido', 'leida', 'atendido'], true)): ?>

                                                    <span class="admin-etiqueta <?= (int)$valor === 1 ? 'admin-activo' : 'admin-inactivo' ?>">
                                                        <?= (int)$valor === 1 ? 'Activo / Sí' : 'Inactivo / No' ?>
                                                    </span>

                                                <?php else: ?>

                                                    <?= h(mb_strimwidth((string)$valor, 0, 90, '...')) ?>

                                                <?php endif; ?>

                                            </td>

                                        <?php endforeach; ?>

                                        <td>

                                            <div class="admin-acciones">

                                                <a
                                                    href="admin.php?tabla=<?= h($tabla) ?>&editar=<?= (int)$fila[$clave] ?>"
                                                    class="admin-btn admin-btn-secundario admin-btn-icono"
                                                    title="Editar registro"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>

                                                <?php if (!($tabla === 'usuarios' && (int)$fila[$clave] === $idAdmin)): ?>

                                                    <form method="POST"
                                                          class="admin-form-accion"
                                                          onsubmit="return confirm('¿Deseas eliminar este registro? Esta acción no se puede deshacer.');">

                                                        <input type="hidden" name="token" value="<?= h($tokenAdmin) ?>">
                                                        <input type="hidden" name="tabla" value="<?= h($tabla) ?>">
                                                        <input type="hidden" name="id_registro" value="<?= (int)$fila[$clave] ?>">

                                                        <button
                                                            type="submit"
                                                            name="eliminar"
                                                            value="1"
                                                            class="admin-btn admin-btn-peligro admin-btn-icono"
                                                            title="Eliminar registro"
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <span class="admin-etiqueta">Tu cuenta</span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="<?= count($columnas) + 1 ?>" class="admin-sin-registros">

                                        <i class="fa-solid fa-folder-open"></i>

                                        No hay registros disponibles en esta sección.

                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        <?php endif; ?>

        <footer class="admin-pie">
            © <?= date('Y') ?> Parche del Tattoo · Panel de administración
        </footer>

    </main>

</div>

<script>
/* =====================================================
   VISTA PREVIA DE IMÁGENES
===================================================== */

document.querySelectorAll('.admin-file-input').forEach(function(input) {

    input.addEventListener('change', function() {

        const archivo = this.files[0];

        if (!archivo) {
            return;
        }

        const formatos = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!formatos.includes(archivo.type)) {
            alert('Selecciona una imagen JPG, PNG o WEBP.');
            this.value = '';
            return;
        }

        if (archivo.size > 5 * 1024 * 1024) {
            alert('La imagen no puede superar los 5 MB.');
            this.value = '';
            return;
        }

        const preview = document.getElementById(this.dataset.preview);
        const coincidencia = this.name.match(/\[(.*?)\]/);

        const nombre = coincidencia
            ? document.getElementById('nombre_' + coincidencia[1])
            : null;

        if (preview) {
            preview.src = URL.createObjectURL(archivo);
            preview.hidden = false;
        }

        if (nombre) {
            nombre.textContent = archivo.name;
        }

    });

});
</script>

</body>
</html>
