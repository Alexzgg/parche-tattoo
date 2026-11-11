
<?php
session_start();

require_once __DIR__ . '/conexion.php';

/* =====================================================
   CONFIGURACIÓN Y CONEXIÓN
===================================================== */

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    exit('Error: no se pudo establecer la conexión con la base de datos.');
}

$conexion->set_charset('utf8mb4');

/* Evitar que el navegador muestre información almacenada */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* =====================================================
   FUNCIONES
===================================================== */

function limpiar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function volver($mensaje, $id_publicacion = 0)
{
    $url = 'foro.php?mensaje=' . urlencode($mensaje);

    if ($id_publicacion > 0) {
        $url .= '#publicacion-' . (int) $id_publicacion;
    }

    header('Location: ' . $url);
    exit;
}

/* =====================================================
   SESIÓN Y USUARIO
===================================================== */

$logueado = isset($_SESSION['id_usuario']);
$id_usuario = $logueado ? (int) $_SESSION['id_usuario'] : 0;

$usuario = null;
$tipo_usuario = null;

if ($logueado) {

    $sql = "SELECT
                id_usuario,
                nombre_usuario,
                nombre_completo,
                email,
                tipo_usuario
            FROM usuarios
            WHERE id_usuario = ?
              AND estado = 1
            LIMIT 1";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        exit('Error al consultar el usuario.');
    }

    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();

    $resultado_usuario = $stmt->get_result();

    if ($resultado_usuario->num_rows === 1) {

        $usuario = $resultado_usuario->fetch_assoc();
        $tipo_usuario = $usuario['tipo_usuario'];

    } else {

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

        $logueado = false;
        $id_usuario = 0;
        $usuario = null;
        $tipo_usuario = null;
    }

    $stmt->close();
}

/* =====================================================
   ADMINISTRADOR
===================================================== */

$es_admin = (
    $tipo_usuario === 'Administrador' ||
    (
        isset($usuario['email']) &&
        $usuario['email'] === 'adminparche@example.com'
    )
);

/* =====================================================
   TOKEN DE SEGURIDAD
===================================================== */

if (empty($_SESSION['token_foro'])) {
    $_SESSION['token_foro'] = bin2hex(random_bytes(32));
}

$token_foro = $_SESSION['token_foro'];

/* =====================================================
   MENSAJES
===================================================== */

$mensajes = [
    'publicado' => '¡Tu publicación se ha creado correctamente!',
    'editado' => 'La publicación se actualizó correctamente.',
    'eliminado' => 'La publicación se eliminó correctamente.',
    'like' => 'Se ha actualizado tu Me gusta.',
    'respuesta' => '¡Tu respuesta se ha publicado!',
    'vacio' => 'Debes completar todos los campos obligatorios.',
    'login' => 'Debes iniciar sesión para realizar esta acción.',
    'permiso' => 'No tienes permiso para modificar esta publicación.',
    'error' => 'No fue posible completar la operación.'
];

$mensaje_estado = '';

if (isset($_GET['mensaje'])) {
    $mensaje_estado = $mensajes[$_GET['mensaje']] ?? '';
}

/* =====================================================
   PROCESAMIENTO DE FORMULARIOS
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Verificar sesión */

    if (!$logueado) {
        volver('login');
    }

    /* Verificar token */

    if (
        !isset($_POST['token']) ||
        !hash_equals($token_foro, $_POST['token'])
    ) {
        volver('error');
    }

    $accion = $_POST['accion'] ?? '';

    /* =================================================
       CREAR PUBLICACIÓN
    ================================================= */

    if ($accion === 'crear_publicacion') {

        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');

        if ($titulo === '' || $contenido === '') {
            volver('vacio');
        }

        if (
            mb_strlen($titulo) > 150 ||
            mb_strlen($contenido) > 5000
        ) {
            volver('error');
        }

        $sql = "INSERT INTO publicaciones_foro
                (id_usuario, titulo, contenido)
                VALUES (?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            volver('error');
        }

        $stmt->bind_param(
            'iss',
            $id_usuario,
            $titulo,
            $contenido
        );

        $ok = $stmt->execute();

        $stmt->close();

        volver($ok ? 'publicado' : 'error');
    }

    /* =================================================
       EDITAR PUBLICACIÓN
    ================================================= */

    elseif ($accion === 'editar_publicacion') {

        $id_publicacion = (int) ($_POST['id_publicacion'] ?? 0);

        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');

        if (
            $id_publicacion <= 0 ||
            $titulo === '' ||
            $contenido === ''
        ) {
            volver('vacio');
        }

        if (
            mb_strlen($titulo) > 150 ||
            mb_strlen($contenido) > 5000
        ) {
            volver('error');
        }

        /* Consultar propietario */

        $sql = "SELECT id_usuario
                FROM publicaciones_foro
                WHERE id_publicacion = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('i', $id_publicacion);
        $stmt->execute();

        $publicacion = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$publicacion) {
            volver('error');
        }

        $es_propietario = (
            (int) $publicacion['id_usuario'] === $id_usuario
        );

        if (!$es_propietario && !$es_admin) {
            volver('permiso', $id_publicacion);
        }

        /* Actualizar publicación */

        $sql = "UPDATE publicaciones_foro
                SET titulo = ?, contenido = ?
                WHERE id_publicacion = ?";

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param(
            'ssi',
            $titulo,
            $contenido,
            $id_publicacion
        );

        $ok = $stmt->execute();

        $stmt->close();

        volver(
            $ok ? 'editado' : 'error',
            $id_publicacion
        );
    }

    /* =================================================
       ELIMINAR PUBLICACIÓN
    ================================================= */

    elseif ($accion === 'eliminar_publicacion') {

        $id_publicacion = (int) ($_POST['id_publicacion'] ?? 0);

        if ($id_publicacion <= 0) {
            volver('error');
        }

        /* Consultar propietario */

        $sql = "SELECT id_usuario
                FROM publicaciones_foro
                WHERE id_publicacion = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('i', $id_publicacion);
        $stmt->execute();

        $publicacion = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$publicacion) {
            volver('error');
        }

        $es_propietario = (
            (int) $publicacion['id_usuario'] === $id_usuario
        );

        if (!$es_propietario && !$es_admin) {
            volver('permiso', $id_publicacion);
        }

        /* Eliminar publicación */

        $sql = "DELETE FROM publicaciones_foro
                WHERE id_publicacion = ?";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('i', $id_publicacion);

        $ok = $stmt->execute();
        $eliminadas = $stmt->affected_rows;

        $stmt->close();

        volver(
            ($ok && $eliminadas > 0) ? 'eliminado' : 'error'
        );
    }

    /* =================================================
       ME GUSTA
    ================================================= */

    elseif ($accion === 'like') {

        $id_publicacion = (int) ($_POST['id_publicacion'] ?? 0);

        if ($id_publicacion <= 0) {
            volver('error');
        }

        /* Verificar que exista */

        $sql = "SELECT id_publicacion
                FROM publicaciones_foro
                WHERE id_publicacion = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('i', $id_publicacion);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$existe) {
            volver('error');
        }

        /* Verificar si ya existe el Me gusta */

        $sql = "SELECT id_like
                FROM likes_foro
                WHERE id_publicacion = ?
                  AND id_usuario = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param(
            'ii',
            $id_publicacion,
            $id_usuario
        );

        $stmt->execute();

        $ya_gusta = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        /* Agregar o quitar Me gusta */

        if ($ya_gusta) {

            $sql = "DELETE FROM likes_foro
                    WHERE id_publicacion = ?
                      AND id_usuario = ?";

        } else {

            $sql = "INSERT INTO likes_foro
                    (id_publicacion, id_usuario)
                    VALUES (?, ?)";
        }

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param(
            'ii',
            $id_publicacion,
            $id_usuario
        );

        $ok = $stmt->execute();

        $stmt->close();

        volver(
            $ok ? 'like' : 'error',
            $id_publicacion
        );
    }

    /* =================================================
       RESPONDER PUBLICACIÓN
    ================================================= */

    elseif ($accion === 'responder') {

        $id_publicacion = (int) ($_POST['id_publicacion'] ?? 0);

        $contenido = trim(
            $_POST['contenido_respuesta'] ?? ''
        );

        if ($id_publicacion <= 0 || $contenido === '') {
            volver('vacio');
        }

        if (mb_strlen($contenido) > 2000) {
            volver('error', $id_publicacion);
        }

        /* Verificar publicación */

        $sql = "SELECT id_publicacion
                FROM publicaciones_foro
                WHERE id_publicacion = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('i', $id_publicacion);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$existe) {
            volver('error');
        }

        /* Guardar respuesta */

        $sql = "INSERT INTO respuestas_foro
                (id_publicacion, id_usuario, contenido)
                VALUES (?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param(
            'iis',
            $id_publicacion,
            $id_usuario,
            $contenido
        );

        $ok = $stmt->execute();

        $stmt->close();

        volver(
            $ok ? 'respuesta' : 'error',
            $id_publicacion
        );
    }

    else {
        volver('error');
    }
}

/* =====================================================
   CONSULTAR PUBLICACIONES
===================================================== */

$sql = "SELECT
            pf.id_publicacion,
            pf.id_usuario,
            pf.titulo,
            pf.contenido,
            pf.fecha_publicacion,

            u.nombre_usuario,

            p.foto_perfil,

            (
                SELECT COUNT(*)
                FROM likes_foro l
                WHERE l.id_publicacion = pf.id_publicacion
            ) AS total_likes,

            (
                SELECT COUNT(*)
                FROM likes_foro l
                WHERE l.id_publicacion = pf.id_publicacion
                  AND l.id_usuario = ?
            ) AS me_gusta,

            (
                SELECT COUNT(*)
                FROM respuestas_foro r
                WHERE r.id_publicacion = pf.id_publicacion
            ) AS total_respuestas

        FROM publicaciones_foro pf

        INNER JOIN usuarios u
            ON pf.id_usuario = u.id_usuario

        LEFT JOIN perfiles p
            ON u.id_usuario = p.id_usuario

        ORDER BY pf.fecha_publicacion DESC";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    exit('Error al consultar las publicaciones.');
}

$stmt->bind_param('i', $id_usuario);
$stmt->execute();

$resultado = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Foro | Parche del Tattoo</title>

    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <!-- CSS PRINCIPAL -->

    <link rel="stylesheet" href="css/style.css">

    <link rel="stylesheet" href="css/responsive.css">

    <style>

/* =====================================================
   SESIÓN
===================================================== */

.usuario-sesion {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
    margin-left: 15px;
}

.usuario-sesion i,
.tipo-usuario {
    color: #ff6a00;
}

.tipo-usuario {
    font-size: 12px;
    font-weight: bold;
}

.btn-cerrar-sesion {
    background: #dc3545 !important;
    color: #fff !important;
    border-radius: 8px;
    padding: 8px 14px !important;
}

/* =====================================================
   CONTENEDOR
===================================================== */

.foro-container {
    max-width: 900px;
    width: calc(100% - 30px);
    margin: 35px auto;
    padding: 30px;
    background: #d9d9d9;
    color: #111;
    border: 2px solid #080808;
    border-radius: 8px;
    box-shadow: 5px 5px 0 #000;
}

.foro-container h1,
.foro-container h2,
.foro-container h3,
.foro-container h4 {
    color: #111;
}

.foro-encabezado {
    text-align: center;
    margin-bottom: 25px;
}

.foro-encabezado p {
    color: #333;
}

/* =====================================================
   CREAR PUBLICACIÓN
===================================================== */

.nueva-publicacion {
    margin-bottom: 25px;
    padding: 22px;
    background: #ccc;
    border: 2px solid #111;
    border-radius: 8px;
    box-shadow: 4px 4px 0 #000;
}

.nueva-publicacion input,
.nueva-publicacion textarea {
    width: 100%;
    padding: 12px;
    background: #eee;
    color: #111;
    border: 2px solid #222;
    border-radius: 5px;
    box-sizing: border-box;
}

.nueva-publicacion textarea {
    min-height: 110px;
    resize: vertical;
}

/* =====================================================
   PUBLICACIONES
===================================================== */

.feed-publicaciones {
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.publicacion {
    background: #fff;
    color: #111;
    border: 3px solid #111;
    border-radius: 8px;
    padding: 22px;
    box-shadow: 4px 4px 0 #000;
    overflow-wrap: anywhere;
}

.publicacion .usuario {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.publicacion .usuario img {
    width: 55px;
    height: 55px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid #111;
}

.publicacion .usuario h3 {
    margin: 0;
    font-size: 1.4rem;
}

.publicacion .usuario span {
    color: #555;
    font-size: .8rem;
}

.publicacion .contenido {
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.publicacion .contenido h3 {
    color: #641520;
    margin-bottom: 10px;
}

/* =====================================================
   BOTONES
===================================================== */

.botones-foro {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.botones-foro form {
    margin: 0;
}

.botones-foro button,
.botones-foro a {
    display: inline-block;
    padding: 8px 13px;
    font-size: .85rem;
    border: 2px solid #111;
    background: #ddd;
    color: #111;
    border-radius: 4px;
    box-shadow: 2px 2px 0 #000;
    cursor: pointer;
    text-decoration: none;
    transition: .2s;
}

.botones-foro button:hover,
.botones-foro a:hover {
    transform: translateY(-2px);
}

.botones-foro .me-gusta-activo {
    background: #641520;
    color: #fff;
}

.botones-foro .btn-eliminar-foro {
    background: #641520;
    color: #fff;
}

.botones-foro .btn-editar-foro {
    background: #222;
    color: #fff;
}

/* =====================================================
   FORMULARIOS
===================================================== */

.formulario-respuesta {
    margin-top: 18px;
    padding: 15px;
    background: #eee;
    border: 2px solid #111;
    border-radius: 6px;
}

.formulario-respuesta input,
.formulario-respuesta textarea {
    width: 100%;
    padding: 10px;
    background: #fff;
    border: 2px solid #333;
    color: #111;
    border-radius: 4px;
    box-sizing: border-box;
}

.formulario-respuesta textarea {
    min-height: 85px;
    resize: vertical;
}

.formulario-respuesta button {
    margin-top: 8px;
}

/* =====================================================
   RESPUESTAS
===================================================== */

.lista-respuestas {
    margin-top: 22px;
    padding-top: 15px;
    border-top: 2px solid #bbb;
}

.respuesta {
    padding: 12px;
    margin-top: 10px;
    background: #e6e6e6;
    border: 1px solid #999;
    border-radius: 5px;
}

.respuesta strong {
    color: #641520;
}

.respuesta p {
    margin: 5px 0;
    overflow-wrap: anywhere;
}

.respuesta small {
    color: #666;
}

/* =====================================================
   MENSAJES
===================================================== */

.foro-mensaje {
    border: 2px solid #111;
    border-radius: 5px;
    color: #111;
    background: #eee;
}

/* =====================================================
   FOOTER
===================================================== */

footer {
    margin-top: 50px;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 768px) {

    .foro-container {
        padding: 18px;
    }

    .foro-container h1 {
        font-size: 2rem;
    }

    .nueva-publicacion,
    .publicacion {
        padding: 15px;
    }

    .botones-foro {
        gap: 8px;
    }

    .botones-foro button,
    .botones-foro a {
        font-size: .8rem;
    }
}

@media (max-width: 480px) {

    .foro-container {
        width: calc(100% - 20px);
        padding: 12px;
        margin: 20px auto;
    }

    .publicacion .usuario h3 {
        font-size: 1.1rem;
    }

    .publicacion .usuario img {
        width: 45px;
        height: 45px;
    }

}

    </style>

</head>

<body>

<!-- =====================================================
     ENCABEZADO
===================================================== -->

<header>

    <div class="logo">

        <img
            src="img/image-removebg-preview.png"
            alt="Logo Parche del Tattoo"
            class="logo-img">

        <h2>
            Parche del <span>Tattoo</span>
        </h2>

    </div>

    <nav>

        <a href="index.php">
            <i class="fa-solid fa-house"></i>
            Inicio
        </a>

        <?php if ($es_admin): ?>

            <a href="admin.php">
                <i class="fa-solid fa-user-shield"></i>
                Administrador
            </a>

        <?php endif; ?>

        <a href="comunidad.php">
            <i class="fa-solid fa-users"></i>
            Comunidad
        </a>

        <a href="diseños.php">
            <i class="fa-solid fa-palette"></i>
            Diseños
        </a>

        <?php if ($logueado): ?>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i>
                Mi perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>

        <?php else: ?>

            <a href="login.php">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar sesión
            </a>

            <a href="registro.php">
                <i class="fa-solid fa-user-plus"></i>
                Crear cuenta
            </a>

        <?php endif; ?>

    </nav>

    <?php if ($logueado && $usuario): ?>

        <div class="usuario-sesion">

            <i class="fa-solid fa-circle-user"></i>

            <div>

                <strong>
                    <?= limpiar($usuario['nombre_usuario']) ?>
                </strong>

                <br>

                <span class="tipo-usuario">
                    <?= limpiar($tipo_usuario) ?>
                </span>

            </div>

        </div>

    <?php endif; ?>

</header>

<!-- =====================================================
     CONTENIDO DEL FORO
===================================================== -->

<main class="foro-container">

    <div class="foro-encabezado">

        <h1>
            <i class="fa-solid fa-comments"></i>
            Foro de la Comunidad
        </h1>

        <p>
            Pregunta, responde y comparte conocimientos
            con otros artistas y miembros de la comunidad.
        </p>

        <hr>

    </div>

    <!-- MENSAJES DEL SISTEMA -->

    <?php if ($mensaje_estado !== ''): ?>

        <div class="alert foro-mensaje text-center" role="status">
            <?= limpiar($mensaje_estado) ?>
        </div>

    <?php endif; ?>

    <!-- =================================================
         CREAR PUBLICACIÓN
    ================================================== -->

    <section class="nueva-publicacion">

        <h2>¿Qué quieres compartir?</h2>

        <p>
            Crea una publicación para la comunidad.
        </p>

        <?php if ($logueado): ?>

            <form action="foro.php" method="POST">

                <input
                    type="hidden"
                    name="token"
                    value="<?= limpiar($token_foro) ?>">

                <input
                    type="hidden"
                    name="accion"
                    value="crear_publicacion">

                <div class="mb-3">

                    <input
                        type="text"
                        name="titulo"
                        maxlength="150"
                        placeholder="Título de tu publicación..."
                        required>

                </div>

                <div class="mb-3">

                    <textarea
                        name="contenido"
                        maxlength="5000"
                        placeholder="Escribe tu mensaje aquí..."
                        required></textarea>

                </div>

                <div class="text-end">

                    <button
                        type="submit"
                        class="btn-tattoo btn-principal">

                        <i class="fa-solid fa-plus"></i>
                        Nueva publicación

                    </button>

                </div>

            </form>

        <?php else: ?>

            <a
                href="login.php"
                class="btn-tattoo btn-principal">

                <i class="fa-solid fa-right-to-bracket"></i>
                Inicia sesión para publicar

            </a>

        <?php endif; ?>

    </section>

    <!-- =================================================
         LISTADO DE PUBLICACIONES
    ================================================== -->

    <div class="feed-publicaciones">

        <?php if ($resultado->num_rows > 0): ?>

            <?php while ($post = $resultado->fetch_assoc()): ?>

                <?php

                $id_post = (int) $post['id_publicacion'];

                $foto = !empty($post['foto_perfil'])
                    ? $post['foto_perfil']
                    : 'img/default-user.png';

                $nombre = $post['nombre_usuario'];

                $username = '@' . strtolower(
                    str_replace(' ', '', $nombre)
                );

                $fecha = !empty($post['fecha_publicacion'])
                    ? date(
                        'd/m/Y H:i',
                        strtotime($post['fecha_publicacion'])
                    )
                    : '';

                $le_gusta = (int) $post['me_gusta'] > 0;

                $es_autor = (
                    $logueado &&
                    (int) $post['id_usuario'] === $id_usuario
                );

                $puede_administrar = (
                    $es_autor ||
                    ($logueado && $es_admin)
                );

                ?>

                <article
                    class="publicacion"
                    id="publicacion-<?= $id_post ?>">

                    <!-- USUARIO -->

                    <div class="usuario">

                        <img
                            src="<?= limpiar($foto) ?>"
                            alt="Foto de perfil"
                            onerror="this.onerror=null;this.src='img/default-user.png';">

                        <div>

                            <h3>
                                <?= limpiar($nombre) ?>
                            </h3>

                            <span>
                                <?= limpiar($username) ?>
                                ·
                                <?= limpiar($fecha) ?>
                            </span>

                        </div>

                    </div>

                    <!-- CONTENIDO -->

                    <div class="contenido">

                        <h3>
                            <?= limpiar($post['titulo']) ?>
                        </h3>

                        <p>
                            <?= nl2br(limpiar($post['contenido'])) ?>
                        </p>

                    </div>

                    <!-- BOTONES -->

                    <div class="botones-foro">

                        <?php if ($logueado): ?>

                            <!-- ME GUSTA -->

                            <form method="POST" action="foro.php">

                                <input
                                    type="hidden"
                                    name="token"
                                    value="<?= limpiar($token_foro) ?>">

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="like">

                                <input
                                    type="hidden"
                                    name="id_publicacion"
                                    value="<?= $id_post ?>">

                                <button
                                    type="submit"
                                    class="<?= $le_gusta ? 'me-gusta-activo' : '' ?>">

                                    <i class="fa-solid fa-heart"></i>

                                    <?= $le_gusta ? 'Ya no me gusta' : 'Me gusta' ?>

                                    (<?= (int) $post['total_likes'] ?>)

                                </button>

                            </form>

                            <!-- RESPONDER -->

                            <button
                                type="button"
                                onclick="mostrarRespuesta(<?= $id_post ?>)">

                                <i class="fa-solid fa-comment-dots"></i>

                                Responder
                                (<?= (int) $post['total_respuestas'] ?>)

                            </button>

                        <?php else: ?>

                            <a href="login.php">

                                <i class="fa-solid fa-heart"></i>

                                Me gusta
                                (<?= (int) $post['total_likes'] ?>)

                            </a>

                            <a href="login.php">

                                <i class="fa-solid fa-comment-dots"></i>

                                Responder

                            </a>

                        <?php endif; ?>

                        <!-- EDITAR Y ELIMINAR -->

                        <?php if ($puede_administrar): ?>

                            <button
                                type="button"
                                class="btn-editar-foro"
                                onclick="mostrarEdicion(<?= $id_post ?>)">

                                <i class="fa-solid fa-pen"></i>
                                Editar

                            </button>

                            <form
                                method="POST"
                                action="foro.php"
                                onsubmit="return confirm('¿Seguro que deseas eliminar esta publicación? Esta acción no se puede deshacer.');">

                                <input
                                    type="hidden"
                                    name="token"
                                    value="<?= limpiar($token_foro) ?>">

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="eliminar_publicacion">

                                <input
                                    type="hidden"
                                    name="id_publicacion"
                                    value="<?= $id_post ?>">

                                <button
                                    type="submit"
                                    class="btn-eliminar-foro">

                                    <i class="fa-solid fa-trash"></i>
                                    Eliminar

                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                    <!-- =================================================
                         FORMULARIO DE EDICIÓN
                    ================================================== -->

                    <?php if ($puede_administrar): ?>

                        <div
                            id="edicion-<?= $id_post ?>"
                            class="formulario-respuesta"
                            hidden>

                            <h4>
                                <i class="fa-solid fa-pen"></i>
                                Editar publicación
                            </h4>

                            <form method="POST" action="foro.php">

                                <input
                                    type="hidden"
                                    name="token"
                                    value="<?= limpiar($token_foro) ?>">

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="editar_publicacion">

                                <input
                                    type="hidden"
                                    name="id_publicacion"
                                    value="<?= $id_post ?>">

                                <div class="mb-3">

                                    <input
                                        type="text"
                                        name="titulo"
                                        maxlength="150"
                                        value="<?= limpiar($post['titulo']) ?>"
                                        placeholder="Título de la publicación"
                                        required>

                                </div>

                                <div class="mb-3">

                                    <textarea
                                        name="contenido"
                                        maxlength="5000"
                                        required><?= limpiar($post['contenido']) ?></textarea>

                                </div>

                                <button
                                    type="submit"
                                    class="btn-tattoo btn-principal">

                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Guardar cambios

                                </button>

                                <button
                                    type="button"
                                    onclick="mostrarEdicion(<?= $id_post ?>)">

                                    <i class="fa-solid fa-xmark"></i>
                                    Cancelar

                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                    <!-- =================================================
                         FORMULARIO DE RESPUESTA
                    ================================================== -->

                    <?php if ($logueado): ?>

                        <div
                            id="respuesta-<?= $id_post ?>"
                            class="formulario-respuesta"
                            hidden>

                            <form method="POST" action="foro.php">

                                <input
                                    type="hidden"
                                    name="token"
                                    value="<?= limpiar($token_foro) ?>">

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="responder">

                                <input
                                    type="hidden"
                                    name="id_publicacion"
                                    value="<?= $id_post ?>">

                                <textarea
                                    name="contenido_respuesta"
                                    maxlength="2000"
                                    placeholder="Escribe tu respuesta..."
                                    required></textarea>

                                <div class="text-end mt-2">

                                    <button
                                        type="submit"
                                        class="btn-tattoo btn-principal">

                                        <i class="fa-solid fa-paper-plane"></i>
                                        Publicar respuesta

                                    </button>

                                </div>

                            </form>

                        </div>

                    <?php endif; ?>

                    <!-- =================================================
                         CONSULTAR RESPUESTAS
                    ================================================== -->

                    <?php

                    $sql_respuestas = "SELECT
                                            r.contenido,
                                            r.fecha_respuesta,
                                            u.nombre_usuario

                                       FROM respuestas_foro r

                                       INNER JOIN usuarios u
                                           ON r.id_usuario = u.id_usuario

                                       WHERE r.id_publicacion = ?

                                       ORDER BY r.fecha_respuesta ASC";

                    $stmt_respuestas = $conexion->prepare(
                        $sql_respuestas
                    );

                    $stmt_respuestas->bind_param(
                        'i',
                        $id_post
                    );

                    $stmt_respuestas->execute();

                    $respuestas = $stmt_respuestas->get_result();

                    ?>

                    <?php if ($respuestas->num_rows > 0): ?>

                        <div class="lista-respuestas">

                            <h3>
                                <i class="fa-solid fa-comments"></i>
                                Respuestas
                                (<?= $respuestas->num_rows ?>)
                            </h3>

                            <?php while ($respuesta = $respuestas->fetch_assoc()): ?>

                                <div class="respuesta">

                                    <strong>
                                        <?= limpiar($respuesta['nombre_usuario']) ?>
                                    </strong>

                                    <p>
                                        <?= nl2br(limpiar($respuesta['contenido'])) ?>
                                    </p>

                                    <small>
                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime($respuesta['fecha_respuesta'])
                                        ) ?>
                                    </small>

                                </div>

                            <?php endwhile; ?>

                        </div>

                    <?php endif; ?>

                    <?php $stmt_respuestas->close(); ?>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="text-center my-5">

                <h2>
                    <i class="fa-solid fa-comments"></i>
                    Aún no hay publicaciones
                </h2>

                <p>
                    ¡Sé el primero en compartir algo con la comunidad!
                </p>

            </div>

        <?php endif; ?>

    </div>

</main>

<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <div class="footer-contenedor">

        <div class="footer-info">

            <h3>Parche del Tattoo</h3>

            <p>
                Plataforma web para conectar artistas,
                estudios de tatuajes y amantes del arte corporal.
            </p>

            <p>
                Proyecto académico desarrollado para la
                Institución Universitaria Pascual Bravo.
            </p>

        </div>

        <div class="footer-links">

            <h3>Enlaces</h3>

            <a href="index.php">Inicio</a>
            <a href="comunidad.php">Comunidad</a>
            <a href="diseños.php">Diseños</a>
            <a href="foro.php">Foro</a>
            <a href="contacto.php">Contacto</a>

        </div>

        <div class="footer-logos">

            <h3>Instituciones</h3>

            <img
                src="img/pascual.jpg"
                alt="Pascual Bravo">

            <img
                src="img/felix.png"
                alt="Félix de Bedout Moreno">

        </div>

    </div>

    <div class="footer-bottom">

        <p>
            © 2026 Parche del Tattoo |
            Todos los derechos reservados.
        </p>

        <div class="redes">

            <a href="#" aria-label="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>

            <a href="#" aria-label="Instagram">
                <i class="fab fa-instagram"></i>
            </a>

            <a href="#" aria-label="TikTok">
                <i class="fab fa-tiktok"></i>
            </a>

            <a href="#" aria-label="WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>

        </div>

    </div>

</footer>

<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

function mostrarRespuesta(id) {

    const formulario = document.getElementById(
        'respuesta-' + id
    );

    if (!formulario) {
        return;
    }

    formulario.hidden = !formulario.hidden;

    if (!formulario.hidden) {

        const textarea = formulario.querySelector('textarea');

        if (textarea) {
            textarea.focus();
        }
    }
}

function mostrarEdicion(id) {

    const formulario = document.getElementById(
        'edicion-' + id
    );

    if (!formulario) {
        return;
    }

    formulario.hidden = !formulario.hidden;

    if (!formulario.hidden) {

        const input = formulario.querySelector(
            'input[name="titulo"]'
        );

        if (input) {
            input.focus();
        }
    }
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
$stmt->close();
$conexion->close();
?>
