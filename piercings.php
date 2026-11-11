
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "conexion.php";

$sesion_iniciada = isset($_SESSION["id_usuario"]);
$usuario = null;
$tipo_usuario = null;
$id_usuario = 0;

$mensaje = "";
$tipo_mensaje = "success";

$carpetaImagenes = __DIR__ . "/img/piercings/";
$rutaImagenes = "img/piercings/";

if (!is_dir($carpetaImagenes)) {
    mkdir($carpetaImagenes, 0755, true);
}

if (!isset($_SESSION["csrf_piercings"])) {
    $_SESSION["csrf_piercings"] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION["csrf_piercings"];

/* =========================================
   VERIFICAR USUARIO
========================================= */

if ($sesion_iniciada) {
    $id_usuario = (int) $_SESSION["id_usuario"];

    $sqlUsuario = "SELECT id_usuario, nombre_usuario, nombre_completo,
                          email, tipo_usuario
                   FROM usuarios
                   WHERE id_usuario = ? AND estado = 1
                   LIMIT 1";

    $stmtUsuario = $conexion->prepare($sqlUsuario);

    if ($stmtUsuario) {
        $stmtUsuario->bind_param("i", $id_usuario);
        $stmtUsuario->execute();

        $resultadoUsuario = $stmtUsuario->get_result();

        if ($resultadoUsuario->num_rows === 1) {
            $usuario = $resultadoUsuario->fetch_assoc();
            $tipo_usuario = $usuario["tipo_usuario"];
        } else {
            $_SESSION = [];
            session_destroy();
            $sesion_iniciada = false;
            $id_usuario = 0;
        }

        $stmtUsuario->close();
    }
}

$esArtista = $sesion_iniciada && $tipo_usuario === "Artista";

/* =========================================
   FUNCIONES
========================================= */

function limpiar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

function eliminarImagenPiercing($imagen)
{
    $nombreArchivo = basename($imagen);

    if ($nombreArchivo === "" || $nombreArchivo === "." || $nombreArchivo === "..") {
        return;
    }

    $ruta = __DIR__ . "/img/piercings/" . $nombreArchivo;

    if (is_file($ruta)) {
        unlink($ruta);
    }
}

function subirImagenPiercing($archivo, $carpeta, $rutaRelativa)
{
    if (!isset($archivo) || $archivo["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($archivo["error"] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($archivo["size"] > 5 * 1024 * 1024) {
        return false;
    }

    $informacion = @getimagesize($archivo["tmp_name"]);

    if (!$informacion) {
        return false;
    }

    $mime = $informacion["mime"];

    $extensiones = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($extensiones[$mime])) {
        return false;
    }

    $nombreArchivo = bin2hex(random_bytes(16))
                   . "." . $extensiones[$mime];

    $destino = $carpeta . $nombreArchivo;

    if (!move_uploaded_file($archivo["tmp_name"], $destino)) {
        return false;
    }

    return $rutaRelativa . $nombreArchivo;
}

/* =========================================
   PROCESAR PUBLICACIONES
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$esArtista) {
        header("Location: piercings.php");
        exit;
    }

    if (
        !isset($_POST["csrf"]) ||
        !hash_equals($csrf, $_POST["csrf"])
    ) {
        $_SESSION["mensaje_piercing"] = "La solicitud no es válida.";
        $_SESSION["tipo_mensaje_piercing"] = "danger";

        header("Location: piercings.php");
        exit;
    }

    $accion = $_POST["accion"] ?? "";

    /* =====================================
       CREAR
    ===================================== */

    if ($accion === "crear") {

        $nombre = trim($_POST["nombre"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");

        if ($nombre === "" || $descripcion === "") {
            $mensaje = "Completa todos los campos.";
            $tipo_mensaje = "danger";
        } else {

            $imagen = subirImagenPiercing(
                $_FILES["imagen"] ?? null,
                $carpetaImagenes,
                $rutaImagenes
            );

            if ($imagen === null || $imagen === false) {
                $mensaje = "Debes seleccionar una imagen válida. Formatos permitidos: JPG, PNG y WEBP. Máximo 5 MB.";
                $tipo_mensaje = "danger";
            } else {

                $sql = "INSERT INTO piercings
                        (id_usuario, nombre, descripcion, imagen)
                        VALUES (?, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param(
                        "isss",
                        $id_usuario,
                        $nombre,
                        $descripcion,
                        $imagen
                    );

                    if ($stmt->execute()) {
                        $stmt->close();

                        $_SESSION["mensaje_piercing"] =
                            "El piercing fue publicado correctamente.";

                        $_SESSION["tipo_mensaje_piercing"] = "success";

                        header("Location: piercings.php");
                        exit;
                    }

                    eliminarImagenPiercing($imagen);
                    $mensaje = "No fue posible publicar el piercing.";
                    $tipo_mensaje = "danger";

                    $stmt->close();
                }
            }
        }
    }

    /* =====================================
       ACTUALIZAR
    ===================================== */

    if ($accion === "actualizar") {

        $id_piercing = (int) ($_POST["id_piercing"] ?? 0);
        $nombre = trim($_POST["nombre"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");

        if ($id_piercing <= 0 || $nombre === "" || $descripcion === "") {
            $mensaje = "Verifica la información del formulario.";
            $tipo_mensaje = "danger";
        } else {

            $sqlActual = "SELECT imagen
                          FROM piercings
                          WHERE id_piercing = ?
                          AND id_usuario = ?
                          LIMIT 1";

            $stmtActual = $conexion->prepare($sqlActual);
            $stmtActual->bind_param("ii", $id_piercing, $id_usuario);
            $stmtActual->execute();

            $resultadoActual = $stmtActual->get_result();
            $piercingActual = $resultadoActual->fetch_assoc();

            $stmtActual->close();

            if (!$piercingActual) {
                $mensaje = "No tienes permiso para modificar este piercing.";
                $tipo_mensaje = "danger";
            } else {

                $imagenAnterior = $piercingActual["imagen"];

                $nuevaImagen = subirImagenPiercing(
                    $_FILES["imagen"] ?? null,
                    $carpetaImagenes,
                    $rutaImagenes
                );

                if ($nuevaImagen === false) {
                    $mensaje = "La imagen no es válida o supera los 5 MB.";
                    $tipo_mensaje = "danger";
                } else {

                    $imagenFinal = $nuevaImagen ?? $imagenAnterior;

                    $sql = "UPDATE piercings
                            SET nombre = ?,
                                descripcion = ?,
                                imagen = ?
                            WHERE id_piercing = ?
                            AND id_usuario = ?";

                    $stmt = $conexion->prepare($sql);

                    if ($stmt) {
                        $stmt->bind_param(
                            "sssii",
                            $nombre,
                            $descripcion,
                            $imagenFinal,
                            $id_piercing,
                            $id_usuario
                        );

                        if ($stmt->execute()) {

                            if ($nuevaImagen !== null) {
                                eliminarImagenPiercing($imagenAnterior);
                            }

                            $stmt->close();

                            $_SESSION["mensaje_piercing"] =
                                "El piercing fue actualizado correctamente.";

                            $_SESSION["tipo_mensaje_piercing"] = "success";

                            header("Location: piercings.php");
                            exit;
                        }

                        if ($nuevaImagen !== null) {
                            eliminarImagenPiercing($nuevaImagen);
                        }

                        $mensaje = "No fue posible actualizar el piercing.";
                        $tipo_mensaje = "danger";

                        $stmt->close();
                    }
                }
            }
        }
    }

    /* =====================================
       ELIMINAR
    ===================================== */

    if ($accion === "eliminar") {

        $id_piercing = (int) ($_POST["id_piercing"] ?? 0);

        $sqlBuscar = "SELECT imagen
                      FROM piercings
                      WHERE id_piercing = ?
                      AND id_usuario = ?
                      LIMIT 1";

        $stmtBuscar = $conexion->prepare($sqlBuscar);
        $stmtBuscar->bind_param("ii", $id_piercing, $id_usuario);
        $stmtBuscar->execute();

        $resultadoBuscar = $stmtBuscar->get_result();
        $piercing = $resultadoBuscar->fetch_assoc();

        $stmtBuscar->close();

        if ($piercing) {

            $sqlEliminar = "DELETE FROM piercings
                            WHERE id_piercing = ?
                            AND id_usuario = ?";

            $stmtEliminar = $conexion->prepare($sqlEliminar);
            $stmtEliminar->bind_param("ii", $id_piercing, $id_usuario);

            if ($stmtEliminar->execute()) {
                eliminarImagenPiercing($piercing["imagen"]);

                $stmtEliminar->close();

                $_SESSION["mensaje_piercing"] =
                    "El piercing fue eliminado correctamente.";

                $_SESSION["tipo_mensaje_piercing"] = "success";

                header("Location: piercings.php");
                exit;
            }

            $mensaje = "No fue posible eliminar el piercing.";
            $tipo_mensaje = "danger";

            $stmtEliminar->close();

        } else {
            $mensaje = "No tienes permiso para eliminar este piercing.";
            $tipo_mensaje = "danger";
        }
    }
}

/* =========================================
   MENSAJES
========================================= */

if (isset($_SESSION["mensaje_piercing"])) {
    $mensaje = $_SESSION["mensaje_piercing"];
    $tipo_mensaje = $_SESSION["tipo_mensaje_piercing"] ?? "success";

    unset(
        $_SESSION["mensaje_piercing"],
        $_SESSION["tipo_mensaje_piercing"]
    );
}

/* =========================================
   CARGAR PIERCING PARA EDITAR
========================================= */

$editar = null;

if ($esArtista && isset($_GET["editar"])) {

    $id_editar = (int) $_GET["editar"];

    $sqlEditar = "SELECT *
                  FROM piercings
                  WHERE id_piercing = ?
                  AND id_usuario = ?
                  LIMIT 1";

    $stmtEditar = $conexion->prepare($sqlEditar);
    $stmtEditar->bind_param("ii", $id_editar, $id_usuario);
    $stmtEditar->execute();

    $resultadoEditar = $stmtEditar->get_result();
    $editar = $resultadoEditar->fetch_assoc();

    $stmtEditar->close();
}

/* =========================================
   CONSULTAR CATÁLOGO
========================================= */

$sqlPiercings = "
    SELECT
        p.id_piercing,
        p.id_usuario,
        p.nombre,
        p.descripcion,
        p.imagen,
        p.fecha_publicacion,
        u.nombre_usuario,
        u.nombre_completo
    FROM piercings p
    INNER JOIN usuarios u
        ON p.id_usuario = u.id_usuario
    WHERE u.estado = 1
    AND u.tipo_usuario = 'Artista'
    ORDER BY p.fecha_publicacion DESC
";

$resultadoPiercings = $conexion->query($sqlPiercings);

$totalPiercings = $resultadoPiercings
    ? $resultadoPiercings->num_rows
    : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Piercings | Parche del Tattoo</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
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
            font-weight: bold;
            font-size: 13px;
        }

        .btn-cerrar-sesion {
            background: #dc3545 !important;
            color: #fff !important;
            border-radius: 8px;
            padding: 8px 14px !important;
        }

        .catalogo-piercing {
            padding: 60px 5%;
            background: #111;
            color: #fff;
        }

        .catalogo-encabezado {
            text-align: center;
            margin-bottom: 35px;
        }

        .catalogo-encabezado h1 {
            color: #ff6a00;
            font-size: 38px;
            margin-bottom: 12px;
        }

        .catalogo-encabezado p {
            color: #ccc;
        }

        .contador-piercings {
            color: #ff6a00;
            font-weight: bold;
            margin-top: 12px;
        }

        .panel-artista {
            max-width: 850px;
            margin: 0 auto 45px;
            padding: 28px;
            background: #1b1b1b;
            border: 1px solid #393939;
            border-radius: 15px;
        }

        .panel-artista h2 {
            color: #ff6a00;
            margin-bottom: 20px;
        }

        .form-piercing {
            display: grid;
            gap: 15px;
        }

        .form-piercing label {
            color: #fff;
            font-weight: bold;
        }

        .form-piercing input,
        .form-piercing textarea {
            width: 100%;
            padding: 13px;
            border-radius: 8px;
            border: 1px solid #444;
            background: #101010;
            color: #fff;
        }

        .form-piercing textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-piercing input[type="file"] {
            cursor: pointer;
        }

        .btn-publicar-piercing,
        .btn-cancelar-piercing {
            display: inline-block;
            padding: 12px 20px;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-publicar-piercing {
            background: #ff6a00;
            color: #fff;
        }

        .btn-publicar-piercing:hover {
            background: #d95600;
        }

        .btn-cancelar-piercing {
            background: #444;
            color: #fff;
            text-align: center;
        }

        .imagen-editar {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #ff6a00;
        }

        .alerta-piercing {
            max-width: 850px;
            margin: 0 auto 25px;
            padding: 14px 18px;
            border-radius: 8px;
        }

        .alerta-piercing.success {
            background: #183c25;
            color: #a5e6b7;
        }

        .alerta-piercing.danger {
            background: #4b2020;
            color: #ffc1c1;
        }

        .piercing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            max-width: 1250px;
            margin: auto;
        }

        .piercing-card {
            background: #1c1c1c;
            border: 1px solid #393939;
            border-radius: 14px;
            overflow: hidden;
            transition: .3s;
        }

        .piercing-card:hover {
            transform: translateY(-5px);
            border-color: #ff6a00;
        }

        .piercing-card img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            display: block;
        }

        .piercing-card-contenido {
            padding: 20px;
        }

        .piercing-card h3 {
            color: #ff6a00;
            margin-bottom: 10px;
        }

        .piercing-card p {
            color: #ccc;
            line-height: 1.6;
        }

        .autor-piercing {
            font-size: 13px;
            color: #aaa !important;
            margin-top: 12px;
        }

        .fecha-piercing {
            font-size: 12px;
            color: #888 !important;
        }

        .acciones-artista {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }

        .btn-editar,
        .btn-eliminar {
            flex: 1;
            padding: 10px;
            border: 0;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-editar {
            background: #d97706;
        }

        .btn-eliminar {
            background: #b02a37;
        }

        .form-eliminar {
            flex: 1;
        }

        .form-eliminar button {
            width: 100%;
        }

        .sin-piercings {
            grid-column: 1 / -1;
            text-align: center;
            padding: 45px 20px;
            background: #1b1b1b;
            border-radius: 12px;
            color: #ccc;
        }

        .sin-piercings i {
            font-size: 45px;
            color: #ff6a00;
            margin-bottom: 15px;
        }

        .cuidados {
            padding: 55px 5%;
            background: #171717;
            color: #fff;
        }

        .cuidados h2 {
            text-align: center;
            color: #ff6a00;
            margin-bottom: 30px;
        }

        .cuidados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: auto;
        }

        .cuidados-grid > div {
            text-align: center;
            padding: 20px;
            background: #222;
            border-radius: 12px;
        }

        .cuidados-grid i {
            font-size: 32px;
            color: #ff6a00;
            margin-bottom: 15px;
        }

        .cuidados-grid h3 {
            margin-bottom: 10px;
        }

        .cuidados-grid p {
            color: #ccc;
        }

        @media(max-width: 600px) {
            .catalogo-piercing {
                padding: 40px 15px;
            }

            .catalogo-encabezado h1 {
                font-size: 29px;
            }

            .panel-artista {
                padding: 18px;
            }

            .piercing-card img {
                height: 230px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">
        <img src="img/image-removebg-preview.png"
             alt="Logo Parche del Tattoo"
             class="logo-img">

        <h2>Parche del <span>Tattoo</span></h2>
    </div>

    <div class="buscador">
        <input type="text"
               placeholder="Buscar personas..."
               aria-label="Buscar personas">

        <i class="fa-solid fa-magnifying-glass"></i>
    </div>

    <nav>
        <a href="index.php">
            <i class="fa-solid fa-house"></i> Inicio
        </a>

        <?php if ($sesion_iniciada && $tipo_usuario === "Cliente"): ?>

            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i> Comunidad
            </a>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i> Foro
            </a>

            <a href="blog.php">
                <i class="fa-solid fa-blog"></i> Blog
            </a>

            <a href="piercings.php">
                <i class="fa-solid fa-gem"></i> Piercings
            </a>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i> Mi Perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>

        <?php elseif ($esArtista): ?>

            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i> Comunidad
            </a>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i> Foro
            </a>

            <a href="blog.php">
                <i class="fa-solid fa-blog"></i> Blog
            </a>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i> Mi Perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>

        <?php else: ?>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i> Foro
            </a>

            <a href="blog.php">
                <i class="fa-solid fa-blog"></i> Blog
            </a>

            <a href="piercings.php">
                <i class="fa-solid fa-gem"></i> Piercings
            </a>

            <a href="contacto.php">
                <i class="fa-solid fa-envelope"></i> Contacto
            </a>

            <a href="login.php">
                <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
            </a>

            <a href="registro.php">
                <i class="fa-solid fa-user-plus"></i> Crear cuenta
            </a>

        <?php endif; ?>
    </nav>

    <?php if ($sesion_iniciada && $usuario): ?>
        <div class="usuario-sesion">
            <i class="fa-solid fa-circle-user"></i>

            <div>
                <strong><?= limpiar($usuario["nombre_usuario"]) ?></strong>
                <br>
                <span class="tipo-usuario"><?= limpiar($tipo_usuario) ?></span>
            </div>
        </div>
    <?php endif; ?>
</header>

<main>

    <section class="catalogo-piercing">

        <div class="catalogo-encabezado">
            <h1>
                <i class="fa-solid fa-gem"></i>
                Catálogo de Piercings
            </h1>

            <p>
                Explora diferentes estilos de perforaciones,
                descubre nuevas ideas y encuentra inspiración.
            </p>

            <div class="contador-piercings">
                <?= $totalPiercings ?> piercings publicados
            </div>
        </div>

        <?php if ($mensaje !== ""): ?>
            <div class="alerta-piercing <?= limpiar($tipo_mensaje) ?>">
                <?= limpiar($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($esArtista): ?>

            <section class="panel-artista" id="formularioPiercing">

                <h2>
                    <i class="fa-solid fa-cloud-arrow-up"></i>

                    <?= $editar ? "Editar piercing" : "Publicar nuevo piercing" ?>
                </h2>

                <form class="form-piercing"
                      method="POST"
                      enctype="multipart/form-data">

                    <input type="hidden" name="csrf"
                           value="<?= limpiar($csrf) ?>">

                    <input type="hidden"
                           name="accion"
                           value="<?= $editar ? "actualizar" : "crear" ?>">

                    <?php if ($editar): ?>
                        <input type="hidden"
                               name="id_piercing"
                               value="<?= (int) $editar["id_piercing"] ?>">
                    <?php endif; ?>

                    <div>
                        <label for="nombre">Nombre del piercing</label>

                        <input type="text"
                               id="nombre"
                               name="nombre"
                               maxlength="150"
                               placeholder="Ejemplo: Septum, Helix, Labret..."
                               value="<?= limpiar($editar["nombre"] ?? "") ?>"
                               required>
                    </div>

                    <div>
                        <label for="descripcion">Descripción</label>

                        <textarea id="descripcion"
                                  name="descripcion"
                                  placeholder="Describe el piercing, su ubicación y características..."
                                  required><?= limpiar($editar["descripcion"] ?? "") ?></textarea>
                    </div>

                    <?php if ($editar): ?>
                        <div>
                            <label>Imagen actual</label>
                            <br><br>

                            <img src="<?= limpiar($editar["imagen"]) ?>"
                                 class="imagen-editar"
                                 alt="Imagen actual del piercing">
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="imagen">
                            <?= $editar ? "Cambiar fotografía (opcional)" : "Seleccionar fotografía" ?>
                        </label>

                        <input type="file"
                               id="imagen"
                               name="imagen"
                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                               <?= $editar ? "" : "required" ?>>
                    </div>

                    <div>
                        <button type="submit" class="btn-publicar-piercing">
                            <i class="fa-solid fa-floppy-disk"></i>

                            <?= $editar ? "Guardar cambios" : "Publicar piercing" ?>
                        </button>

                        <?php if ($editar): ?>
                            <a href="piercings.php"
                               class="btn-cancelar-piercing">
                                Cancelar
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </section>

        <?php endif; ?>

        <div class="piercing-grid">

            <?php if ($resultadoPiercings && $totalPiercings > 0): ?>

                <?php while ($piercing = $resultadoPiercings->fetch_assoc()): ?>

                    <article class="piercing-card">

                        <img src="<?= limpiar($piercing["imagen"]) ?>"
                             alt="<?= limpiar($piercing["nombre"]) ?>"
                             loading="lazy">

                        <div class="piercing-card-contenido">

                            <h3><?= limpiar($piercing["nombre"]) ?></h3>

                            <p>
                                <?= nl2br(limpiar($piercing["descripcion"])) ?>
                            </p>

                            <p class="autor-piercing">
                                <i class="fa-solid fa-user"></i>

                                Publicado por:
                                <?= limpiar(
                                    $piercing["nombre_completo"]
                                    ?: $piercing["nombre_usuario"]
                                ) ?>
                            </p>

                            <p class="fecha-piercing">
                                <i class="fa-regular fa-calendar"></i>

                                <?= date(
                                    "d/m/Y",
                                    strtotime($piercing["fecha_publicacion"])
                                ) ?>
                            </p>

                            <?php if (
                                $esArtista &&
                                (int) $piercing["id_usuario"] === $id_usuario
                            ): ?>

                                <div class="acciones-artista">

                                    <a href="piercings.php?editar=<?= (int) $piercing["id_piercing"] ?>"
                                       class="btn-editar">
                                        <i class="fa-solid fa-pen"></i>
                                        Editar
                                    </a>

                                    <form method="POST"
                                          class="form-eliminar"
                                          onsubmit="return confirm('¿Estás seguro de eliminar este piercing?');">

                                        <input type="hidden"
                                               name="csrf"
                                               value="<?= limpiar($csrf) ?>">

                                        <input type="hidden"
                                               name="accion"
                                               value="eliminar">

                                        <input type="hidden"
                                               name="id_piercing"
                                               value="<?= (int) $piercing["id_piercing"] ?>">

                                        <button type="submit" class="btn-eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                            Eliminar
                                        </button>

                                    </form>

                                </div>

                            <?php endif; ?>

                        </div>
                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="sin-piercings">
                    <i class="fa-solid fa-gem"></i>

                    <h3>Aún no hay piercings publicados</h3>

                    <p>
                        <?php if ($esArtista): ?>
                            Sé el primero en compartir tus trabajos con la comunidad.
                        <?php else: ?>
                            Pronto encontrarás nuevos estilos de piercings en este catálogo.
                        <?php endif; ?>
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </section>

    <section class="cuidados">

        <h2>Cuidados recomendados</h2>

        <div class="cuidados-grid">

            <div>
                <i class="fa-solid fa-hand-sparkles"></i>
                <h3>Higiene</h3>
                <p>
                    Limpia la zona siguiendo las indicaciones del perforador
                    y usando productos adecuados.
                </p>
            </div>

            <div>
                <i class="fa-solid fa-ban"></i>
                <h3>No manipular</h3>
                <p>
                    Evita tocar o girar la joya innecesariamente,
                    especialmente con las manos sucias.
                </p>
            </div>

            <div>
                <i class="fa-solid fa-heart"></i>
                <h3>Cicatrización</h3>
                <p>
                    Respeta el tiempo de cicatrización indicado
                    por el profesional.
                </p>
            </div>

            <div>
                <i class="fa-solid fa-user-doctor"></i>
                <h3>Profesionales</h3>
                <p>
                    Acude a estudios que utilicen material estéril
                    y joyería apropiada para la perforación.
                </p>
            </div>

        </div>

    </section>

</main>

<footer>
    <p>© 2026 Parche del Tattoo | Todos los derechos reservados.</p>
</footer>

</body>
</html>