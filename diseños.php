
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexion.php";

/* ==========================================================
   FUNCIONES
========================================================== */

function limpiar($dato)
{
    return htmlspecialchars($dato ?? "", ENT_QUOTES, "UTF-8");
}

function normalizarTexto($texto)
{
    $texto = mb_strtolower(trim($texto ?? ""), "UTF-8");

    $normalizado = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $texto);

    return strtolower($normalizado !== false ? $normalizado : $texto);
}

/* ==========================================================
   VERIFICAR USUARIO Y ROL
========================================================== */

$sesion_iniciada = isset($_SESSION["id_usuario"]);
$usuario = null;
$tipo_usuario = null;
$id_usuario = 0;

if ($sesion_iniciada) {

    $id_usuario = (int) $_SESSION["id_usuario"];

    $sql = "SELECT id_usuario, nombre_usuario, nombre_completo,
                   email, tipo_usuario
            FROM usuarios
            WHERE id_usuario = ?
            AND estado = 1
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);

    if ($stmt) {

        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);
    }

    if (!$usuario) {

        $_SESSION = [];
        session_destroy();

        $sesion_iniciada = false;
        $id_usuario = 0;

    } else {

        $tipo_usuario = $usuario["tipo_usuario"];
    }
}

$es_artista = $sesion_iniciada && $tipo_usuario === "Artista";
$es_cliente = $sesion_iniciada && $tipo_usuario === "Cliente";

/* ==========================================================
   TOKEN DE SEGURIDAD
========================================================== */

if (empty($_SESSION["token_disenos"])) {
    $_SESSION["token_disenos"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["token_disenos"];

/* ==========================================================
   PROCESAR PUBLICACIÓN DE DISEÑOS
   SOLAMENTE LOS ARTISTAS PUEDEN PUBLICAR
========================================================== */

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if (!$es_artista) {

        $mensaje = "No tienes permisos para publicar diseños.";
        $tipo_mensaje = "error";

    } elseif (
        !isset($_POST["token"]) ||
        !hash_equals($_SESSION["token_disenos"], $_POST["token"])
    ) {

        $mensaje = "La solicitud no es válida. Intenta nuevamente.";
        $tipo_mensaje = "error";

    } elseif ($_POST["accion"] === "crear_diseno") {

        $titulo = trim($_POST["titulo"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");

        $categorias_permitidas = [
            "Realista",
            "Blackwork",
            "Japonés",
            "Minimalista",
            "Tribal",
            "Old School",
            "Lettering",
            "Otro"
        ];

        if ($titulo === "" || $categoria === "") {

            $mensaje = "El título y la categoría son obligatorios.";
            $tipo_mensaje = "error";

        } elseif (!in_array($categoria, $categorias_permitidas, true)) {

            $mensaje = "Selecciona una categoría válida.";
            $tipo_mensaje = "error";

        } elseif (
            !isset($_FILES["imagen"]) ||
            $_FILES["imagen"]["error"] !== UPLOAD_ERR_OK
        ) {

            $mensaje = "Debes seleccionar una imagen para el diseño.";
            $tipo_mensaje = "error";

        } elseif ($_FILES["imagen"]["size"] > 8 * 1024 * 1024) {

            $mensaje = "La imagen no puede superar los 8 MB.";
            $tipo_mensaje = "error";

        } else {

            $archivo = $_FILES["imagen"];

            $tipos_permitidos = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp",
                "image/gif" => "gif"
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $tipo_archivo = finfo_file($finfo, $archivo["tmp_name"]);
            finfo_close($finfo);

            if (
                !isset($tipos_permitidos[$tipo_archivo]) ||
                getimagesize($archivo["tmp_name"]) === false
            ) {

                $mensaje = "El archivo seleccionado no es una imagen válida.";
                $tipo_mensaje = "error";

            } else {

                $carpeta = __DIR__ . "/img/disenos/";

                if (
                    !is_dir($carpeta) &&
                    !mkdir($carpeta, 0755, true)
                ) {
                    $mensaje = "No se pudo crear la carpeta de imágenes.";
                    $tipo_mensaje = "error";

                } else {

                    $extension = $tipos_permitidos[$tipo_archivo];

                    $nombre_archivo = "diseno_" .
                        $id_usuario . "_" .
                        bin2hex(random_bytes(8)) .
                        "." . $extension;

                    $ruta_fisica = $carpeta . $nombre_archivo;
                    $ruta_bd = "img/disenos/" . $nombre_archivo;

                    if (move_uploaded_file(
                        $archivo["tmp_name"],
                        $ruta_fisica
                    )) {

                        $sql = "INSERT INTO disenos
                                (
                                    id_usuario,
                                    titulo,
                                    descripcion,
                                    categoria,
                                    imagen,
                                    fecha_publicacion
                                )
                                VALUES (?, ?, ?, ?, ?, NOW())";

                        $stmt = mysqli_prepare($conexion, $sql);

                        if ($stmt) {

                            mysqli_stmt_bind_param(
                                $stmt,
                                "issss",
                                $id_usuario,
                                $titulo,
                                $descripcion,
                                $categoria,
                                $ruta_bd
                            );

                            if (mysqli_stmt_execute($stmt)) {

                                mysqli_stmt_close($stmt);

                                header("Location: diseños.php?publicado=1");
                                exit;

                            } else {

                                if (file_exists($ruta_fisica)) {
                                    unlink($ruta_fisica);
                                }

                                $mensaje = "No se pudo guardar el diseño.";
                                $tipo_mensaje = "error";

                                mysqli_stmt_close($stmt);
                            }

                        } else {

                            if (file_exists($ruta_fisica)) {
                                unlink($ruta_fisica);
                            }

                            $mensaje = "No se pudo preparar la publicación.";
                            $tipo_mensaje = "error";
                        }

                    } else {

                        $mensaje = "No se pudo subir la imagen.";
                        $tipo_mensaje = "error";
                    }
                }
            }
        }
    }
}

/* ==========================================================
   MENSAJE DE PUBLICACIÓN
========================================================== */

if (isset($_GET["publicado"]) && $_GET["publicado"] === "1") {
    $mensaje = "¡Tu diseño se publicó correctamente!";
    $tipo_mensaje = "exito";
}

/* ==========================================================
   CONSULTAR DISEÑOS DE TODOS LOS ARTISTAS
========================================================== */

$sql = "SELECT
            d.id_diseno,
            d.id_usuario,
            d.titulo,
            d.descripcion,
            d.categoria,
            d.imagen,
            d.fecha_publicacion,
            u.nombre_usuario,
            u.nombre_completo,
            u.tipo_usuario
        FROM disenos d
        INNER JOIN usuarios u
            ON d.id_usuario = u.id_usuario
        WHERE u.estado = 1
        ORDER BY d.fecha_publicacion DESC";

$resultado = mysqli_query($conexion, $sql);

$disenos = [];

if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $disenos[] = $fila;
    }
}

$total_disenos = count($disenos);

$categorias = [];

foreach ($disenos as $diseno) {
    if (!empty($diseno["categoria"])) {
        $categorias[$diseno["categoria"]] = $diseno["categoria"];
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Diseños | Parche del Tattoo</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
        /* ==================================================
           GALERÍA DE DISEÑOS
        ================================================== */

        .galeria-page {
            max-width: 1200px;
            margin: 35px auto;
            padding: 35px;
            background: #151515;
            color: #eee;
            border: 2px solid #444;
            border-radius: 12px;
        }

        .galeria-encabezado {
            text-align: center;
            margin-bottom: 30px;
        }

        .galeria-encabezado h1 {
            color: #ff6a00;
            font-size: 38px;
            margin-bottom: 10px;
        }

        .galeria-encabezado p {
            color: #bbb;
        }

        .contador-disenos {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            border-radius: 20px;
            background: #292929;
            color: #ff8a35;
            font-size: 14px;
        }

        /* ==================================================
           MENSAJES
        ================================================== */

        .mensaje-disenos {
            max-width: 1100px;
            margin: 20px auto;
            padding: 14px 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }

        .mensaje-disenos.exito {
            background: #d4edda;
            color: #155724;
        }

        .mensaje-disenos.error {
            background: #f8d7da;
            color: #721c24;
        }

        /* ==================================================
           BOTÓN PUBLICAR
        ================================================== */

        .zona-publicar {
            display: flex;
            justify-content: center;
            margin: 25px 0;
        }

        .btn-publicar-diseno {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 13px 24px;
            border: none;
            border-radius: 7px;
            background: #ff6a00;
            color: #fff;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            transition: .3s;
        }

        .btn-publicar-diseno:hover {
            background: #d95700;
            color: #fff;
            transform: translateY(-2px);
        }

        /* ==================================================
           FILTROS
        ================================================== */

        .filtros-disenos {
            display: flex;
            gap: 12px;
            margin: 25px 0 30px;
        }

        .filtros-disenos input,
        .filtros-disenos select {
            min-width: 0;
            padding: 12px 15px;
            border: 1px solid #555;
            border-radius: 7px;
            background: #242424;
            color: #fff;
            font-size: 14px;
        }

        .filtros-disenos input {
            flex: 1;
        }

        .filtros-disenos select {
            width: 220px;
        }

        /* ==================================================
           TARJETAS
        ================================================== */

        .galeria-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .item-diseno {
            min-width: 0;
            overflow: hidden;
            background: #222;
            border: 1px solid #444;
            border-radius: 10px;
            transition: .3s;
        }

        .item-diseno:hover {
            transform: translateY(-5px);
            border-color: #ff6a00;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .35);
        }

        .imagen-diseno {
            width: 100%;
            height: 260px;
            overflow: hidden;
            background: #111;
        }

        .imagen-diseno img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s;
        }

        .item-diseno:hover .imagen-diseno img {
            transform: scale(1.05);
        }

        .sin-imagen {
            height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            background: #191919;
            color: #888;
        }

        .sin-imagen i {
            font-size: 45px;
        }

        .informacion-diseno {
            padding: 18px;
        }

        .informacion-diseno h3 {
            color: #ff8a35;
            font-size: 21px;
            margin: 0 0 10px;
        }

        .informacion-diseno p {
            color: #ccc;
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .categoria-diseno {
            display: inline-block;
            padding: 5px 10px;
            margin-bottom: 10px;
            border-radius: 15px;
            background: #491c12;
            color: #ff9a58;
            font-size: 12px;
        }

        .autor-diseno {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #444;
            color: #aaa;
            font-size: 13px;
        }

        .autor-diseno i {
            color: #ff6a00;
        }

        .fecha-diseno {
            display: block;
            margin-top: 8px;
            color: #888;
            font-size: 12px;
        }

        .btn-ver-diseno {
            width: 100%;
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #ff6a00;
            border-radius: 6px;
            background: transparent;
            color: #ff8a35;
            cursor: pointer;
            transition: .3s;
        }

        .btn-ver-diseno:hover {
            background: #ff6a00;
            color: #fff;
        }

        .sin-resultados {
            grid-column: 1 / -1;
            padding: 45px 20px;
            text-align: center;
            color: #aaa;
        }

        .sin-resultados i {
            display: block;
            margin-bottom: 15px;
            color: #ff6a00;
            font-size: 45px;
        }

        /* ==================================================
           MODAL PUBLICAR
        ================================================== */

        .modal-formulario-diseno,
        .modal-diseno {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, .88);
        }

        .modal-formulario-diseno.activo,
        .modal-diseno.activo {
            display: flex;
        }

        .contenido-modal {
            position: relative;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            border: 1px solid #555;
            border-radius: 12px;
            background: #1b1b1b;
            color: #fff;
        }

        .contenido-modal h2 {
            margin: 0 0 25px;
            color: #ff8a35;
        }

        .cerrar-modal {
            position: absolute;
            top: 12px;
            right: 17px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 30px;
            cursor: pointer;
        }

        .campo-diseno {
            margin-bottom: 18px;
        }

        .campo-diseno label {
            display: block;
            margin-bottom: 7px;
            color: #eee;
            font-weight: bold;
        }

        .campo-diseno input,
        .campo-diseno textarea,
        .campo-diseno select {
            width: 100%;
            padding: 12px;
            border: 1px solid #555;
            border-radius: 6px;
            background: #292929;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        .campo-diseno textarea {
            min-height: 110px;
            resize: vertical;
        }

        .campo-diseno small {
            display: block;
            margin-top: 6px;
            color: #999;
        }

        .btn-guardar-diseno {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #ff6a00;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-guardar-diseno:hover {
            background: #d95700;
        }

        /* ==================================================
           MODAL IMAGEN
        ================================================== */

        .modal-diseno-contenido {
            position: relative;
            width: 100%;
            max-width: 850px;
            max-height: 90vh;
            padding: 25px;
            overflow: auto;
            border-radius: 12px;
            background: #171717;
            color: #fff;
            text-align: center;
        }

        .modal-diseno-contenido h2 {
            color: #ff8a35;
            margin-bottom: 20px;
        }

        .modal-diseno-contenido img {
            display: block;
            max-width: 100%;
            max-height: 70vh;
            margin: auto;
            object-fit: contain;
        }

        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 900px) {
            .galeria-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .galeria-page {
                margin: 15px;
                padding: 18px;
            }

            .galeria-encabezado h1 {
                font-size: 28px;
            }

            .filtros-disenos {
                flex-direction: column;
            }

            .filtros-disenos select {
                width: 100%;
            }

            .galeria-grid {
                grid-template-columns: 1fr;
            }

            .imagen-diseno {
                height: 240px;
            }

            .contenido-modal {
                padding: 22px 17px;
            }
        }
    </style>

</head>

<body>

<!-- ======================================================
     HEADER
====================================================== -->

<header>

    <div class="logo">

        <img
            src="img/image-removebg-preview.png"
            alt="Logo Parche del Tattoo"
            class="logo-img">

        <h2>Parche del <span>Tattoo</span></h2>

    </div>

    <nav>

        <a href="index.php">
            <i class="fa-solid fa-house"></i> Inicio
        </a>

        <?php if ($sesion_iniciada): ?>

            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i> Comunidad
            </a>

        <?php endif; ?>

        <a href="foro.php">
            <i class="fa-solid fa-comment"></i> Foro
        </a>

        <a href="blog.php">
            <i class="fa-solid fa-blog"></i> Blog
        </a>

        <a href="piercings.php">
            <i class="fa-solid fa-gem"></i> Piercings
        </a>

        <?php if ($sesion_iniciada): ?>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i> Mi perfil
            </a>

            <?php if ($es_artista): ?>

                <a href="mensajes.php">
                    <i class="fa-solid fa-envelope"></i> Mensajes
                </a>

            <?php endif; ?>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>

        <?php else: ?>

            <a href="contacto.php">
                <i class="fa-solid fa-envelope"></i> Contacto
            </a>

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

</header>

<!-- ======================================================
     MENSAJES
====================================================== -->

<?php if ($mensaje !== ""): ?>

    <div class="mensaje-disenos <?= limpiar($tipo_mensaje) ?>">
        <?= limpiar($mensaje) ?>
    </div>

<?php endif; ?>

<main>

    <section class="galeria-page">

        <div class="galeria-encabezado">

            <h1>
                <i class="fa-solid fa-palette"></i>
                Galería de Diseños
            </h1>

            <p>
                Explora los diseños creados por los artistas
                de nuestra comunidad.
            </p>

            <span class="contador-disenos">
                <i class="fa-solid fa-images"></i>
                <?= $total_disenos ?> diseños publicados
            </span>

        </div>

        <!-- SOLO LOS ARTISTAS PUEDEN PUBLICAR -->

        <?php if ($es_artista): ?>

            <div class="zona-publicar">

                <button
                    type="button"
                    class="btn-publicar-diseno"
                    onclick="abrirModalPublicar()">

                    <i class="fa-solid fa-plus"></i>
                    Publicar nuevo diseño

                </button>

            </div>

        <?php elseif ($es_cliente): ?>

            <p style="text-align:center;color:#aaa;">
                <i class="fa-solid fa-eye"></i>
                Estás viendo la galería. Puedes explorar los diseños
                publicados por nuestros artistas.
            </p>

        <?php elseif (!$sesion_iniciada): ?>

            <p style="text-align:center;color:#aaa;">
                <a href="login.php" style="color:#ff8a35;">
                    Inicia sesión
                </a>
                para acceder a las opciones de tu cuenta.
            </p>

        <?php endif; ?>

        <!-- FILTROS -->

        <div class="filtros-disenos">

            <input
                type="search"
                id="buscar"
                placeholder="Buscar por diseño o artista..."
                aria-label="Buscar diseños">

            <select id="categoria" aria-label="Filtrar por categoría">

                <option value="todos">
                    Todas las categorías
                </option>

                <?php foreach ($categorias as $categoria): ?>

                    <option value="<?= limpiar(normalizarTexto($categoria)) ?>">
                        <?= limpiar($categoria) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <!-- GALERÍA -->

        <div class="galeria-grid" id="galeriaGrid">

            <?php if ($total_disenos > 0): ?>

                <?php foreach ($disenos as $diseno): ?>

                    <?php
                    $nombre_autor = !empty($diseno["nombre_completo"])
                        ? $diseno["nombre_completo"]
                        : $diseno["nombre_usuario"];

                    $categoria_normalizada = normalizarTexto($diseno["categoria"]);

                    $texto_busqueda = normalizarTexto(
                        $diseno["titulo"] . " " .
                        $diseno["descripcion"] . " " .
                        $nombre_autor
                    );
                    ?>

                    <article
                        class="item-diseno"
                        data-categoria="<?= limpiar($categoria_normalizada) ?>"
                        data-busqueda="<?= limpiar($texto_busqueda) ?>">

                        <div class="imagen-diseno">

                            <?php if (!empty($diseno["imagen"])): ?>

                                <img
                                    src="<?= limpiar($diseno["imagen"]) ?>"
                                    alt="<?= limpiar($diseno["titulo"]) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none';">

                            <?php else: ?>

                                <div class="sin-imagen">
                                    <i class="fa-solid fa-image"></i>
                                    <span>Sin imagen</span>
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="informacion-diseno">

                            <span class="categoria-diseno">
                                <?= limpiar($diseno["categoria"]) ?>
                            </span>

                            <h3>
                                <?= limpiar($diseno["titulo"]) ?>
                            </h3>

                            <p>
                                <?= nl2br(limpiar($diseno["descripcion"])) ?>
                            </p>

                            <div class="autor-diseno">

                                <i class="fa-solid fa-user-pen"></i>

                                <span>
                                    Publicado por:
                                    <strong><?= limpiar($nombre_autor) ?></strong>
                                </span>

                            </div>

                            <small class="fecha-diseno">

                                <i class="fa-regular fa-calendar"></i>

                                <?php
                                echo !empty($diseno["fecha_publicacion"])
                                    ? date(
                                        "d/m/Y",
                                        strtotime($diseno["fecha_publicacion"])
                                    )
                                    : "";
                                ?>

                            </small>

                            <?php if (!empty($diseno["imagen"])): ?>

                                <button
                                    type="button"
                                    class="btn-ver-diseno"
                                    data-titulo="<?= limpiar($diseno["titulo"]) ?>"
                                    data-imagen="<?= limpiar($diseno["imagen"]) ?>">

                                    <i class="fa-solid fa-expand"></i>
                                    Ver diseño

                                </button>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="sin-resultados">

                    <i class="fa-solid fa-palette"></i>

                    <h3>Todavía no hay diseños publicados</h3>

                    <p>
                        Cuando los artistas publiquen sus trabajos,
                        aparecerán aquí.
                    </p>

                </div>

            <?php endif; ?>

            <div class="sin-resultados" id="sinResultados" hidden>

                <i class="fa-solid fa-magnifying-glass"></i>

                <h3>No encontramos diseños</h3>

                <p>Prueba con otra búsqueda o categoría.</p>

            </div>

        </div>

    </section>

</main>

<!-- ======================================================
     MODAL PARA PUBLICAR
     SOLAMENTE VISIBLE PARA ARTISTAS
====================================================== -->

<?php if ($es_artista): ?>

    <div class="modal-formulario-diseno" id="modalPublicar">

        <div class="contenido-modal">

            <button
                type="button"
                class="cerrar-modal"
                onclick="cerrarModalPublicar()">

                &times;

            </button>

            <h2>
                <i class="fa-solid fa-palette"></i>
                Publicar nuevo diseño
            </h2>

            <form method="POST" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="accion"
                    value="crear_diseno">

                <input
                    type="hidden"
                    name="token"
                    value="<?= limpiar($token) ?>">

                <div class="campo-diseno">

                    <label for="titulo">
                        Título del diseño *
                    </label>

                    <input
                        type="text"
                        id="titulo"
                        name="titulo"
                        maxlength="150"
                        placeholder="Ejemplo: Dragón japonés"
                        required>

                </div>

                <div class="campo-diseno">

                    <label for="descripcion">
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        maxlength="2000"
                        placeholder="Describe tu diseño..."></textarea>

                </div>

                <div class="campo-diseno">

                    <label for="categoria">
                        Categoría *
                    </label>

                    <select id="categoria" name="categoria" required>

                        <option value="">
                            Seleccionar categoría
                        </option>

                        <option value="Realista">Realista</option>
                        <option value="Blackwork">Blackwork</option>
                        <option value="Japonés">Japonés</option>
                        <option value="Minimalista">Minimalista</option>
                        <option value="Tribal">Tribal</option>
                        <option value="Old School">Old School</option>
                        <option value="Lettering">Lettering</option>
                        <option value="Otro">Otro</option>

                    </select>

                </div>

                <div class="campo-diseno">

                    <label for="imagen">
                        Imagen del diseño *
                    </label>

                    <input
                        type="file"
                        id="imagen"
                        name="imagen"
                        accept=".jpg,.jpeg,.png,.webp,.gif"
                        required>

                    <small>
                        Formatos permitidos: JPG, PNG, WEBP y GIF.
                        Tamaño máximo: 8 MB.
                    </small>

                </div>

                <button type="submit" class="btn-guardar-diseno">

                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Publicar diseño

                </button>

            </form>

        </div>

    </div>

<?php endif; ?>

<!-- ======================================================
     MODAL PARA VER IMAGEN
====================================================== -->

<div class="modal-diseno" id="modalDiseno">

    <div class="modal-diseno-contenido">

        <button
            type="button"
            class="cerrar-modal"
            id="cerrarModalDiseno">

            &times;

        </button>

        <h2 id="tituloModalDiseno"></h2>

        <img id="imagenModalDiseno" src="" alt="">

    </div>

</div>

<footer>

    <p>
        © 2026 Parche del Tattoo | Todos los derechos reservados.
    </p>

</footer>

<script>
/* ==========================================================
   BUSCADOR Y FILTROS
========================================================== */

const buscador = document.getElementById("buscar");
const filtroCategoria = document.getElementById("categoria");
const tarjetas = document.querySelectorAll(".item-diseno");
const sinResultados = document.getElementById("sinResultados");

function normalizar(texto) {
    return texto
        .toLocaleLowerCase("es")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();
}

function filtrarDisenos() {

    const texto = normalizar(buscador.value);
    const categoria = filtroCategoria.value;

    let visibles = 0;

    tarjetas.forEach(tarjeta => {

        const contenido = normalizar(
            tarjeta.dataset.busqueda || ""
        );

        const categoriaTarjeta = tarjeta.dataset.categoria || "";

        const coincideTexto = contenido.includes(texto);

        const coincideCategoria =
            categoria === "todos" ||
            categoriaTarjeta === categoria;

        const mostrar = coincideTexto && coincideCategoria;

        tarjeta.style.display = mostrar ? "" : "none";

        if (mostrar) {
            visibles++;
        }
    });

    sinResultados.hidden = visibles > 0;
}

buscador.addEventListener("input", filtrarDisenos);
filtroCategoria.addEventListener("change", filtrarDisenos);

/* ==========================================================
   MODAL PUBLICAR
========================================================== */

function abrirModalPublicar() {

    const modal = document.getElementById("modalPublicar");

    if (modal) {
        modal.classList.add("activo");
    }
}

function cerrarModalPublicar() {

    const modal = document.getElementById("modalPublicar");

    if (modal) {
        modal.classList.remove("activo");
    }
}

/* ==========================================================
   MODAL VER DISEÑO
========================================================== */

const modalImagen = document.getElementById("modalDiseno");

document.querySelectorAll(".btn-ver-diseno").forEach(boton => {

    boton.addEventListener("click", () => {

        document.getElementById("tituloModalDiseno").textContent =
            boton.dataset.titulo;

        document.getElementById("imagenModalDiseno").src =
            boton.dataset.imagen;

        document.getElementById("imagenModalDiseno").alt =
            boton.dataset.titulo;

        modalImagen.classList.add("activo");
    });

});

document.getElementById("cerrarModalDiseno").addEventListener("click", () => {

    modalImagen.classList.remove("activo");
});

modalImagen.addEventListener("click", event => {

    if (event.target === modalImagen) {
        modalImagen.classList.remove("activo");
    }
});

/* ==========================================================
   CERRAR MODALES
========================================================== */

document.addEventListener("keydown", event => {

    if (event.key === "Escape") {

        cerrarModalPublicar();

        modalImagen.classList.remove("activo");
    }
});

document.querySelectorAll(".modal-formulario-diseno").forEach(modal => {

    modal.addEventListener("click", event => {

        if (event.target === modal) {
            modal.classList.remove("activo");
        }
    });
});

/* ==========================================================
   OCULTAR MENSAJES
========================================================== */

const mensaje = document.querySelector(".mensaje-disenos");

if (mensaje) {

    setTimeout(() => {
        mensaje.style.display = "none";
    }, 4500);
}
</script>

</body>
</html>
