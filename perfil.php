<?php
session_start();
require_once "conexion.php";

/* ==========================================================
   VERIFICAR SESIÓN
========================================================== */

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

$id_usuario = (int) $_SESSION["id_usuario"];


/* ==========================================================
   FUNCIONES
========================================================== */

function limpiar($dato)
{
    return htmlspecialchars($dato ?? "", ENT_QUOTES, "UTF-8");
}


/* ==========================================================
   PROCESAR CRUD
   TODO SE HACE EN ESTE MISMO ARCHIVO
========================================================== */

$mensaje = "";
$tipo_mensaje = "";


/* ==========================================================
   EDITAR PERFIL
========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    $accion = $_POST["accion"];


    /* ------------------------------------------------------
       ACTUALIZAR DATOS DEL PERFIL
    ------------------------------------------------------ */

    if ($accion === "editar_perfil") {

        $biografia = trim($_POST["biografia"] ?? "");
        $telefono = trim($_POST["telefono"] ?? "");
        $ciudad = trim($_POST["ciudad"] ?? "");
        $instagram = trim($_POST["instagram"] ?? "");
        $especialidad = trim($_POST["especialidad"] ?? "");


        $sql = "UPDATE perfiles
                SET biografia = ?,
                    telefono = ?,
                    ciudad = ?,
                    instagram = ?,
                    especialidad = ?
                WHERE id_usuario = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "sssssi",
            $biografia,
            $telefono,
            $ciudad,
            $instagram,
            $especialidad,
            $id_usuario
        );

        if (mysqli_stmt_execute($stmt)) {

            $mensaje = "Perfil actualizado correctamente.";
            $tipo_mensaje = "exito";

        } else {

            $mensaje = "No se pudo actualizar el perfil.";
            $tipo_mensaje = "error";
        }
    }


    /* ------------------------------------------------------
       CAMBIAR FOTO DE PERFIL
    ------------------------------------------------------ */

    if ($accion === "cambiar_foto") {

        if (
            isset($_FILES["foto_perfil"]) &&
            $_FILES["foto_perfil"]["error"] === UPLOAD_ERR_OK
        ) {

            $archivo = $_FILES["foto_perfil"];

            $tipos_permitidos = [
                "image/jpeg",
                "image/png",
                "image/webp",
                "image/gif"
            ];


            if (!in_array($archivo["type"], $tipos_permitidos)) {

                $mensaje = "Solo se permiten imágenes JPG, PNG, WEBP o GIF.";
                $tipo_mensaje = "error";

            } elseif ($archivo["size"] > 5 * 1024 * 1024) {

                $mensaje = "La imagen no puede superar los 5 MB.";
                $tipo_mensaje = "error";

            } else {

                $carpeta = "img/perfiles/";

                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0777, true);
                }


                $extension = strtolower(
                    pathinfo(
                        $archivo["name"],
                        PATHINFO_EXTENSION
                    )
                );


                $nombre_archivo =
                    "perfil_" .
                    $id_usuario .
                    "_" .
                    time() .
                    "." .
                    $extension;


                $ruta = $carpeta . $nombre_archivo;


                if (move_uploaded_file(
                    $archivo["tmp_name"],
                    $ruta
                )) {

                    $sql = "UPDATE perfiles
                            SET foto_perfil = ?
                            WHERE id_usuario = ?";

                    $stmt = mysqli_prepare(
                        $conexion,
                        $sql
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "si",
                        $ruta,
                        $id_usuario
                    );


                    if (mysqli_stmt_execute($stmt)) {

                        $mensaje = "Foto de perfil actualizada.";
                        $tipo_mensaje = "exito";

                    } else {

                        $mensaje = "Error al guardar la foto.";
                        $tipo_mensaje = "error";
                    }

                } else {

                    $mensaje = "No se pudo subir la imagen.";
                    $tipo_mensaje = "error";
                }
            }

        } else {

            $mensaje = "Selecciona una imagen.";
            $tipo_mensaje = "error";
        }
    }


    /* ======================================================
       CREAR DISEÑO
    ====================================================== */

    if ($accion === "crear_diseno") {

        $titulo = trim($_POST["titulo"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");


        if ($titulo === "") {

            $mensaje = "El título del diseño es obligatorio.";
            $tipo_mensaje = "error";

        } else {

            $imagen = "";


            /* Subir imagen del diseño */

            if (
                isset($_FILES["imagen"]) &&
                $_FILES["imagen"]["error"] === UPLOAD_ERR_OK
            ) {

                $archivo = $_FILES["imagen"];

                $tipos_permitidos = [
                    "image/jpeg",
                    "image/png",
                    "image/webp",
                    "image/gif"
                ];


                if (!in_array($archivo["type"], $tipos_permitidos)) {

                    $mensaje = "Formato de imagen no permitido.";
                    $tipo_mensaje = "error";

                } elseif ($archivo["size"] > 8 * 1024 * 1024) {

                    $mensaje = "La imagen no puede superar los 8 MB.";
                    $tipo_mensaje = "error";

                } else {

                    $carpeta = "img/disenos/";

                    if (!is_dir($carpeta)) {
                        mkdir($carpeta, 0777, true);
                    }


                    $extension = strtolower(
                        pathinfo(
                            $archivo["name"],
                            PATHINFO_EXTENSION
                        )
                    );


                    $nombre_archivo =
                        "diseno_" .
                        $id_usuario .
                        "_" .
                        time() .
                        "." .
                        $extension;


                    $imagen = $carpeta . $nombre_archivo;


                    move_uploaded_file(
                        $archivo["tmp_name"],
                        $imagen
                    );
                }
            }


            if ($tipo_mensaje !== "error") {

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


                $stmt = mysqli_prepare(
                    $conexion,
                    $sql
                );


                mysqli_stmt_bind_param(
                    $stmt,
                    "issss",
                    $id_usuario,
                    $titulo,
                    $descripcion,
                    $categoria,
                    $imagen
                );


                if (mysqli_stmt_execute($stmt)) {

                    $mensaje = "Diseño publicado correctamente.";
                    $tipo_mensaje = "exito";

                } else {

                    $mensaje = "No se pudo publicar el diseño.";
                    $tipo_mensaje = "error";
                }
            }
        }
    }


    /* ======================================================
       EDITAR DISEÑO
    ====================================================== */

    if ($accion === "editar_diseno") {

        $id_diseno = (int) ($_POST["id_diseno"] ?? 0);

        $titulo = trim($_POST["titulo"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");


        $sql = "UPDATE disenos
                SET titulo = ?,
                    descripcion = ?,
                    categoria = ?
                WHERE id_diseno = ?
                AND id_usuario = ?";


        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );


        mysqli_stmt_bind_param(
            $stmt,
            "sssii",
            $titulo,
            $descripcion,
            $categoria,
            $id_diseno,
            $id_usuario
        );


        if (mysqli_stmt_execute($stmt)) {

            $mensaje = "Diseño actualizado correctamente.";
            $tipo_mensaje = "exito";

        } else {

            $mensaje = "No se pudo actualizar el diseño.";
            $tipo_mensaje = "error";
        }
    }


    /* ======================================================
       ELIMINAR DISEÑO
    ====================================================== */

    if ($accion === "eliminar_diseno") {

        $id_diseno = (int) ($_POST["id_diseno"] ?? 0);


        $sql = "DELETE FROM disenos
                WHERE id_diseno = ?
                AND id_usuario = ?";


        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );


        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $id_diseno,
            $id_usuario
        );


        if (mysqli_stmt_execute($stmt)) {

            $mensaje = "Diseño eliminado correctamente.";
            $tipo_mensaje = "exito";

        } else {

            $mensaje = "No se pudo eliminar el diseño.";
            $tipo_mensaje = "error";
        }
    }


    /* ======================================================
       CREAR PUBLICACIÓN DEL FORO
    ====================================================== */

    if ($accion === "crear_publicacion") {

        $titulo = trim($_POST["titulo"] ?? "");
        $contenido = trim($_POST["contenido"] ?? "");


        if ($titulo === "" || $contenido === "") {

            $mensaje = "Completa el título y el contenido.";
            $tipo_mensaje = "error";

        } else {

            $sql = "INSERT INTO publicaciones_foro
                    (
                        id_usuario,
                        titulo,
                        contenido,
                        fecha_publicacion
                    )
                    VALUES (?, ?, ?, NOW())";


            $stmt = mysqli_prepare(
                $conexion,
                $sql
            );


            mysqli_stmt_bind_param(
                $stmt,
                "iss",
                $id_usuario,
                $titulo,
                $contenido
            );


            if (mysqli_stmt_execute($stmt)) {

                $mensaje = "Publicación creada correctamente.";
                $tipo_mensaje = "exito";

            } else {

                $mensaje = "No se pudo crear la publicación.";
                $tipo_mensaje = "error";
            }
        }
    }


    /* ======================================================
       EDITAR PUBLICACIÓN
    ====================================================== */

    if ($accion === "editar_publicacion") {

        $id_publicacion =
            (int) ($_POST["id_publicacion"] ?? 0);

        $titulo =
            trim($_POST["titulo"] ?? "");

        $contenido =
            trim($_POST["contenido"] ?? "");


        $sql = "UPDATE publicaciones_foro
                SET titulo = ?,
                    contenido = ?
                WHERE id_publicacion = ?
                AND id_usuario = ?";


        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );


        mysqli_stmt_bind_param(
            $stmt,
            "ssii",
            $titulo,
            $contenido,
            $id_publicacion,
            $id_usuario
        );


        if (mysqli_stmt_execute($stmt)) {

            $mensaje = "Publicación actualizada.";
            $tipo_mensaje = "exito";

        } else {

            $mensaje = "No se pudo actualizar.";
            $tipo_mensaje = "error";
        }
    }


    /* ======================================================
       ELIMINAR PUBLICACIÓN
    ====================================================== */

    if ($accion === "eliminar_publicacion") {

        $id_publicacion =
            (int) ($_POST["id_publicacion"] ?? 0);


        $sql = "DELETE FROM publicaciones_foro
                WHERE id_publicacion = ?
                AND id_usuario = ?";


        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );


        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $id_publicacion,
            $id_usuario
        );


        if (mysqli_stmt_execute($stmt)) {

            $mensaje = "Publicación eliminada.";
            $tipo_mensaje = "exito";

        } else {

            $mensaje = "No se pudo eliminar.";
            $tipo_mensaje = "error";
        }
    }
}


/* ==========================================================
   CONSULTAR USUARIO
========================================================== */

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


$stmt = mysqli_prepare(
    $conexion,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_usuario
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$usuario = mysqli_fetch_assoc($resultado);


if (!$usuario) {

    session_destroy();

    header("Location: login.php");

    exit;
}


/* ==========================================================
   CONSULTAR PERFIL
========================================================== */

$sql = "SELECT
            id_perfil,
            id_usuario,
            biografia,
            telefono,
            ciudad,
            instagram,
            especialidad,
            foto_perfil
        FROM perfiles
        WHERE id_usuario = ?
        LIMIT 1";


$stmt = mysqli_prepare(
    $conexion,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_usuario
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$perfil = mysqli_fetch_assoc($resultado);


/* Si no existe perfil */

if (!$perfil) {

    $sql = "INSERT INTO perfiles
            (
                id_usuario,
                biografia,
                telefono,
                ciudad,
                instagram,
                especialidad,
                foto_perfil
            )
            VALUES (?, '', '', '', '', '', '')";


    $stmt = mysqli_prepare(
        $conexion,
        $sql
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_usuario
    );

    mysqli_stmt_execute($stmt);


    /* Consultarlo nuevamente */

    $sql = "SELECT *
            FROM perfiles
            WHERE id_usuario = ?
            LIMIT 1";


    $stmt = mysqli_prepare(
        $conexion,
        $sql
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_usuario
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    $perfil = mysqli_fetch_assoc($resultado);
}


/* ==========================================================
   VARIABLES
========================================================== */

$nombre =
    !empty($usuario["nombre_completo"])
    ? $usuario["nombre_completo"]
    : $usuario["nombre_usuario"];


$foto =
    !empty($perfil["foto_perfil"])
    ? $perfil["foto_perfil"]
    : "img/artista1.jpg";


/* ==========================================================
   CONSULTAR DISEÑOS
========================================================== */

$sql = "SELECT
            id_diseno,
            id_usuario,
            titulo,
            descripcion,
            categoria,
            imagen,
            fecha_publicacion
        FROM disenos
        WHERE id_usuario = ?
        ORDER BY fecha_publicacion DESC";


$stmt = mysqli_prepare(
    $conexion,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_usuario
);

mysqli_stmt_execute($stmt);

$disenos = mysqli_stmt_get_result($stmt);


/* ==========================================================
   CONSULTAR PUBLICACIONES
========================================================== */

$sql = "SELECT
            id_publicacion,
            id_usuario,
            titulo,
            contenido,
            fecha_publicacion
        FROM publicaciones_foro
        WHERE id_usuario = ?
        ORDER BY fecha_publicacion DESC";


$stmt = mysqli_prepare(
    $conexion,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_usuario
);

mysqli_stmt_execute($stmt);

$publicaciones = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Perfil | Parche del Tattoo
    </title>


    <link
        rel="stylesheet"
        href="css/style.css">


    <link
        rel="stylesheet"
        href="css/responsive.css">


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">


    <style>

        /* ==================================================
           BOTONES
        ================================================== */

        .btn-perfil {
            display: inline-block;
            padding: 10px 18px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            background: #111;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-perfil:hover {
            background: #333;
        }


        .btn-eliminar {
            background: #a30000;
        }

        .btn-eliminar:hover {
            background: #d00000;
        }


        /* ==================================================
           BOTÓN FOTO
        ================================================== */

        .foto {
            position: relative;
        }


        .foto img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
        }


        .boton-camara {
            position: absolute;
            bottom: 10px;
            right: 10px;

            width: 45px;
            height: 45px;

            border-radius: 50%;

            border: 3px solid white;

            background: #111;
            color: white;

            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;
        }


        /* ==================================================
           MENSAJES
        ================================================== */

        .mensaje-perfil {
            width: 90%;
            max-width: 900px;
            margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }


        .mensaje-exito {
            background: #d4edda;
            color: #155724;
        }


        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
        }


        /* ==================================================
           MODALES
        ================================================== */

        .modal-perfil {
            display: none;

            position: fixed;

            z-index: 9999;

            left: 0;
            top: 0;

            width: 100%;
            height: 100%;

            background: rgba(0,0,0,.75);

            align-items: center;
            justify-content: center;

            padding: 20px;
        }


        .modal-contenido {
            background: white;
            color: #111;

            width: 100%;
            max-width: 600px;

            max-height: 90vh;

            overflow-y: auto;

            border-radius: 10px;

            padding: 25px;

            position: relative;
        }


        .modal-contenido h2 {
            margin-top: 0;
        }


        .cerrar-modal {
            position: absolute;

            top: 12px;
            right: 18px;

            font-size: 28px;

            cursor: pointer;

            color: #111;
        }


        .form-grupo {
            margin-bottom: 15px;
        }


        .form-grupo label {
            display: block;

            margin-bottom: 6px;

            font-weight: bold;
        }


        .form-grupo input,
        .form-grupo textarea,
        .form-grupo select {

            width: 100%;

            padding: 10px;

            border: 1px solid #bbb;

            border-radius: 5px;

            font-family: Arial;
        }


        .form-grupo textarea {
            min-height: 120px;

            resize: vertical;
        }


        /* ==================================================
           TARJETAS DE DISEÑOS
        ================================================== */

        .item {
            position: relative;
        }


        .item img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }


        .botones-item {
            margin-top: 10px;
        }


        /* ==================================================
           PUBLICACIONES
        ================================================== */

        .acciones-publicacion {
            margin-top: 15px;
        }


        /* ==================================================
           SIN CONTENIDO
        ================================================== */

        .sin-contenido {
            text-align: center;
            padding: 30px;
            width: 100%;
        }

    </style>

</head>


<body>


<!-- ======================================================
     HEADER
======================================================= -->

<header>

    <div class="logo">

        <img
            src="./img/image-removebg-preview.png"
            alt="Logo Parche del Tattoo"
            class="logo-img">

        <h2>
            Parche del <span>Tattoo</span>
        </h2>

    </div>


    <div class="buscador">

        <input
            type="text"
            placeholder="Buscar personas...">

        <i class="fa-solid fa-magnifying-glass"></i>

    </div>


    <nav>

        <a href="index.php">
            <i class="fa-solid fa-house"></i>
            Inicio
        </a>


        <a href="comunidad.php">
            <i class="fa-solid fa-users"></i>
            Comunidad
        </a>


        <a href="diseños.php">
            <i class="fa-solid fa-palette"></i>
            Diseños
        </a>


        <a href="foro.php">
            <i class="fa-solid fa-comment"></i>
            Foro
        </a>


        <a href="blog.php">
            <i class="fa-solid fa-blog"></i>
            Blog
        </a>


        <a
            href="logout.php"
            class="btn-cerrar-sesion">

            <i class="fa-solid fa-right-from-bracket"></i>
            Cerrar sesión
        </a>

    </nav>

</header>



<!-- ======================================================
     MENSAJE
======================================================= -->

<?php if (!empty($mensaje)): ?>

    <div
        class="mensaje-perfil
        <?php
        echo $tipo_mensaje === "exito"
            ? "mensaje-exito"
            : "mensaje-error";
        ?>">

        <?php echo limpiar($mensaje); ?>

    </div>

<?php endif; ?>



<!-- ======================================================
     PERFIL
======================================================= -->

<section class="perfil">


    <div class="portada">

        <img
            src="./img/banner.jpg"
            alt="Portada">

    </div>



    <div class="perfil-info">


        <!-- FOTO -->

        <div class="foto">

            <img
                src="<?php echo limpiar($foto); ?>"
                alt="Foto de perfil">


            <button
                type="button"
                class="boton-camara"
                onclick="abrirModal('modalFoto')">

                <i class="fa-solid fa-camera"></i>

            </button>

        </div>



        <!-- DATOS -->

        <div class="datos">


            <h1>

                <?php echo limpiar($nombre); ?>

            </h1>


            <p>

                <i class="fa-solid fa-at"></i>

                <?php
                echo limpiar(
                    $usuario["nombre_usuario"]
                );
                ?>

            </p>


            <?php if (!empty($perfil["ciudad"])): ?>

                <p>

                    <i class="fa-solid fa-location-dot"></i>

                    <?php
                    echo limpiar(
                        $perfil["ciudad"]
                    );
                    ?>

                </p>

            <?php endif; ?>


            <?php if (!empty($perfil["especialidad"])): ?>

                <p>

                    <i class="fa-solid fa-pen-nib"></i>

                    <?php
                    echo limpiar(
                        $perfil["especialidad"]
                    );
                    ?>

                </p>

            <?php endif; ?>


            <?php if (!empty($perfil["biografia"])): ?>

                <p>

                    <?php
                    echo nl2br(
                        limpiar(
                            $perfil["biografia"]
                        )
                    );
                    ?>

                </p>

            <?php endif; ?>


            <!-- ESTADÍSTICAS -->

            <div class="estadisticas">


                <div>

                    <h2>

                        <?php
                        echo mysqli_num_rows($disenos);
                        ?>

                    </h2>

                    <span>
                        Diseños
                    </span>

                </div>


                <div>

                    <h2>0</h2>

                    <span>
                        Seguidores
                    </span>

                </div>


                <div>

                    <h2>0</h2>

                    <span>
                        Siguiendo
                    </span>

                </div>


            </div>


            <button
                type="button"
                class="btn-perfil"
                onclick="abrirModal('modalEditarPerfil')">

                <i class="fa-solid fa-pen"></i>

                Editar perfil

            </button>


        </div>

    </div>

</section>



<!-- ======================================================
     DISEÑOS
======================================================= -->

<section class="publicaciones">


    <h2>

        Últimos Diseños

    </h2>


    <?php if ($usuario["tipo_usuario"] === "Artista"): ?>

        <button
            type="button"
            class="btn-perfil"
            onclick="abrirModal('modalCrearDiseno')">

            <i class="fa-solid fa-plus"></i>

            Nuevo diseño

        </button>

    <?php endif; ?>


    <div class="galeria-grid">


        <?php if (mysqli_num_rows($disenos) > 0): ?>


            <?php while ($diseno = mysqli_fetch_assoc($disenos)): ?>


                <div class="item">


                    <?php if (!empty($diseno["imagen"])): ?>

                        <img
                            src="<?php echo limpiar($diseno["imagen"]); ?>"
                            alt="<?php echo limpiar($diseno["titulo"]); ?>">

                    <?php else: ?>

                        <div class="sin-contenido">

                            <i class="fa-solid fa-image"></i>

                            Sin imagen

                        </div>

                    <?php endif; ?>


                    <h3>

                        <?php
                        echo limpiar(
                            $diseno["titulo"]
                        );
                        ?>

                    </h3>


                    <?php if (!empty($diseno["categoria"])): ?>

                        <p>

                            <i class="fa-solid fa-tag"></i>

                            <?php
                            echo limpiar(
                                $diseno["categoria"]
                            );
                            ?>

                        </p>

                    <?php endif; ?>


                    <p>

                        <?php
                        echo limpiar(
                            $diseno["descripcion"]
                        );
                        ?>

                    </p>


                    <?php if ($usuario["tipo_usuario"] === "Artista"): ?>

                        <div class="botones-item">


                            <!-- EDITAR -->

                            <button
                                type="button"
                                class="btn-perfil"
                                onclick="editarDiseno(
                                    <?php echo $diseno['id_diseno']; ?>,
                                    '<?php echo limpiar($diseno['titulo']); ?>',
                                    '<?php echo limpiar($diseno['descripcion']); ?>',
                                    '<?php echo limpiar($diseno['categoria']); ?>'
                                )">

                                <i class="fa-solid fa-pen"></i>

                                Editar

                            </button>


                            <!-- ELIMINAR -->

                            <form
                                method="POST"
                                style="display:inline;"
                                onsubmit="return confirm('¿Quieres eliminar este diseño?');">

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="eliminar_diseno">

                                <input
                                    type="hidden"
                                    name="id_diseno"
                                    value="<?php echo $diseno['id_diseno']; ?>">


                                <button
                                    type="submit"
                                    class="btn-perfil btn-eliminar">

                                    <i class="fa-solid fa-trash"></i>

                                    Eliminar

                                </button>

                            </form>


                        </div>

                    <?php endif; ?>


                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="sin-contenido">

                <i class="fa-solid fa-palette"></i>

                <h3>
                    No hay diseños todavía
                </h3>


                <?php if ($usuario["tipo_usuario"] === "Artista"): ?>

                    <p>
                        Publica tu primer diseño.
                    </p>

                <?php endif; ?>

            </div>


        <?php endif; ?>


    </div>

</section>



<!-- ======================================================
     PUBLICACIONES DEL FORO
======================================================= -->

<section class="timeline">


    <h2>

        Mis publicaciones

    </h2>


    <button
        type="button"
        class="btn-perfil"
        onclick="abrirModal('modalCrearPublicacion')">

        <i class="fa-solid fa-plus"></i>

        Nueva publicación

    </button>



    <?php if (mysqli_num_rows($publicaciones) > 0): ?>


        <?php while ($publicacion = mysqli_fetch_assoc($publicaciones)): ?>


            <div class="post">


                <h3>

                    <?php
                    echo limpiar(
                        $publicacion["titulo"]
                    );
                    ?>

                </h3>


                <p>

                    <?php
                    echo nl2br(
                        limpiar(
                            $publicacion["contenido"]
                        )
                    );
                    ?>

                </p>


                <small>

                    <i class="fa-regular fa-calendar"></i>

                    <?php
                    echo date(
                        "d/m/Y H:i",
                        strtotime(
                            $publicacion["fecha_publicacion"]
                        )
                    );
                    ?>

                </small>



                <div class="acciones-publicacion">


                    <button
                        type="button"
                        class="btn-perfil">

                        ❤️ Me gusta

                    </button>


                    <button
                        type="button"
                        class="btn-perfil">

                        💬 Comentar

                    </button>


                    <button
                        type="button"
                        class="btn-perfil">

                        📤 Compartir

                    </button>


                    <button
                        type="button"
                        class="btn-perfil"
                        onclick="editarPublicacion(
                            <?php echo $publicacion['id_publicacion']; ?>,
                            '<?php echo limpiar($publicacion['titulo']); ?>',
                            '<?php echo limpiar($publicacion['contenido']); ?>'
                        )">

                        <i class="fa-solid fa-pen"></i>

                        Editar

                    </button>


                    <form
                        method="POST"
                        style="display:inline;"
                        onsubmit="return confirm('¿Quieres eliminar esta publicación?');">


                        <input
                            type="hidden"
                            name="accion"
                            value="eliminar_publicacion">


                        <input
                            type="hidden"
                            name="id_publicacion"
                            value="<?php echo $publicacion['id_publicacion']; ?>">


                        <button
                            type="submit"
                            class="btn-perfil btn-eliminar">

                            <i class="fa-solid fa-trash"></i>

                            Eliminar

                        </button>


                    </form>


                </div>


            </div>


        <?php endwhile; ?>


    <?php else: ?>


        <div class="post">

            <h3>

                No tienes publicaciones todavía.

            </h3>


            <p>

                Cuando publiques algo aparecerá aquí.

            </p>

        </div>


    <?php endif; ?>


</section>



<!-- ======================================================
     MODAL CAMBIAR FOTO
======================================================= -->

<div
    id="modalFoto"
    class="modal-perfil">


    <div class="modal-contenido">


        <span
            class="cerrar-modal"
            onclick="cerrarModal('modalFoto')">

            &times;

        </span>


        <h2>

            Cambiar foto de perfil

        </h2>


        <form
            method="POST"
            enctype="multipart/form-data">


            <input
                type="hidden"
                name="accion"
                value="cambiar_foto">


            <div class="form-grupo">

                <label>
                    Selecciona una imagen
                </label>

                <input
                    type="file"
                    name="foto_perfil"
                    accept="image/*"
                    required>

            </div>


            <button
                type="submit"
                class="btn-perfil">

                <i class="fa-solid fa-upload"></i>

                Guardar foto

            </button>


        </form>

    </div>

</div>



<!-- ======================================================
     MODAL EDITAR PERFIL
======================================================= -->

<div
    id="modalEditarPerfil"
    class="modal-perfil">


    <div class="modal-contenido">


        <span
            class="cerrar-modal"
            onclick="cerrarModal('modalEditarPerfil')">

            &times;

        </span>


        <h2>

            Editar mi perfil

        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="accion"
                value="editar_perfil">


            <div class="form-grupo">

                <label>
                    Biografía
                </label>

                <textarea
                    name="biografia"><?php echo limpiar($perfil["biografia"]); ?></textarea>

            </div>


            <div class="form-grupo">

                <label>
                    Teléfono
                </label>

                <input
                    type="text"
                    name="telefono"
                    value="<?php echo limpiar($perfil["telefono"]); ?>">

            </div>


            <div class="form-grupo">

                <label>
                    Ciudad
                </label>

                <input
                    type="text"
                    name="ciudad"
                    value="<?php echo limpiar($perfil["ciudad"]); ?>">

            </div>


            <div class="form-grupo">

                <label>
                    Instagram
                </label>

                <input
                    type="text"
                    name="instagram"
                    value="<?php echo limpiar($perfil["instagram"]); ?>">

            </div>


            <div class="form-grupo">

                <label>
                    Especialidad
                </label>

                <input
                    type="text"
                    name="especialidad"
                    value="<?php echo limpiar($perfil["especialidad"]); ?>">

            </div>


            <button
                type="submit"
                class="btn-perfil">

                <i class="fa-solid fa-floppy-disk"></i>

                Guardar cambios

            </button>


        </form>

    </div>

</div>



<!-- ======================================================
     MODAL CREAR DISEÑO
======================================================= -->

<div
    id="modalCrearDiseno"
    class="modal-perfil">


    <div class="modal-contenido">


        <span
            class="cerrar-modal"
            onclick="cerrarModal('modalCrearDiseno')">

            &times;

        </span>


        <h2>

            Publicar nuevo diseño

        </h2>


        <form
            method="POST"
            enctype="multipart/form-data">


            <input
                type="hidden"
                name="accion"
                value="crear_diseno">


            <div class="form-grupo">

                <label>
                    Título
                </label>

                <input
                    type="text"
                    name="titulo"
                    required>

            </div>


            <div class="form-grupo">

                <label>
                    Descripción
                </label>

                <textarea
                    name="descripcion"></textarea>

            </div>


            <div class="form-grupo">

                <label>
                    Categoría
                </label>

                <select name="categoria">

                    <option value="">
                        Seleccionar
                    </option>

                    <option value="Realista">
                        Realista
                    </option>

                    <option value="Blackwork">
                        Blackwork
                    </option>

                    <option value="Japonés">
                        Japonés
                    </option>

                    <option value="Minimalista">
                        Minimalista
                    </option>

                    <option value="Old School">
                        Old School
                    </option>

                    <option value="Lettering">
                        Lettering
                    </option>

                    <option value="Otro">
                        Otro
                    </option>

                </select>

            </div>


            <div class="form-grupo">

                <label>
                    Imagen
                </label>

                <input
                    type="file"
                    name="imagen"
                    accept="image/*">

            </div>


            <button
                type="submit"
                class="btn-perfil">

                <i class="fa-solid fa-upload"></i>

                Publicar diseño

            </button>


        </form>

    </div>

</div>



<!-- ======================================================
     MODAL EDITAR DISEÑO
======================================================= -->

<div
    id="modalEditarDiseno"
    class="modal-perfil">


    <div class="modal-contenido">


        <span
            class="cerrar-modal"
            onclick="cerrarModal('modalEditarDiseno')">

            &times;

        </span>


        <h2>

            Editar diseño

        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="accion"
                value="editar_diseno">


            <input
                type="hidden"
                name="id_diseno"
                id="editar_id_diseno">


            <div class="form-grupo">

                <label>
                    Título
                </label>

                <input
                    type="text"
                    name="titulo"
                    id="editar_titulo_diseno"
                    required>

            </div>


            <div class="form-grupo">

                <label>
                    Descripción
                </label>

                <textarea
                    name="descripcion"
                    id="editar_descripcion_diseno"></textarea>

            </div>


            <div class="form-grupo">

                <label>
                    Categoría
                </label>

                <input
                    type="text"
                    name="categoria"
                    id="editar_categoria_diseno">

            </div>


            <button
                type="submit"
                class="btn-perfil">

                <i class="fa-solid fa-floppy-disk"></i>

                Guardar cambios

            </button>


        </form>

    </div>

</div>



<!-- ======================================================
     MODAL CREAR PUBLICACIÓN
======================================================= -->

<div
    id="modalCrearPublicacion"
    class="modal-perfil">


    <div class="modal-contenido">


        <span
            class="cerrar-modal"
            onclick="cerrarModal('modalCrearPublicacion')">

            &times;

        </span>


        <h2>

            Nueva publicación

        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="accion"
                value="crear_publicacion">


            <div class="form-grupo">

                <label>
                    Título
                </label>

                <input
                    type="text"
                    name="titulo"
                    required>

            </div>


            <div class="form-grupo">

                <label>
                    Contenido
                </label>

                <textarea
                    name="contenido"
                    required></textarea>

            </div>


            <button
                type="submit"
                class="btn-perfil">

                <i class="fa-solid fa-paper-plane"></i>

                Publicar

            </button>


        </form>

    </div>

</div>



<!-- ======================================================
     MODAL EDITAR PUBLICACIÓN
======================================================= -->

<div
    id="modalEditarPublicacion"
    class="modal-perfil">


    <div class="modal-contenido">


        <span
            class="cerrar-modal"
            onclick="cerrarModal('modalEditarPublicacion')">

            &times;

        </span>


        <h2>

            Editar publicación

        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="accion"
                value="editar_publicacion">


            <input
                type="hidden"
                name="id_publicacion"
                id="editar_id_publicacion">


            <div class="form-grupo">

                <label>
                    Título
                </label>

                <input
                    type="text"
                    name="titulo"
                    id="editar_titulo_publicacion"
                    required>

            </div>


            <div class="form-grupo">

                <label>
                    Contenido
                </label>

                <textarea
                    name="contenido"
                    id="editar_contenido_publicacion"
                    required></textarea>

            </div>


            <button
                type="submit"
                class="btn-perfil">

                <i class="fa-solid fa-floppy-disk"></i>

                Guardar cambios

            </button>


        </form>

    </div>

</div>



<!-- ======================================================
     JAVASCRIPT DE LOS MODALES
======================================================= -->

<script>


function abrirModal(id)
{
    document.getElementById(id).style.display = "flex";
}


function cerrarModal(id)
{
    document.getElementById(id).style.display = "none";
}


/* Cerrar al hacer clic fuera del modal */

window.onclick = function(event)
{

    if (event.target.classList.contains("modal-perfil"))
    {
        event.target.style.display = "none";
    }

};


/* ======================================================
   EDITAR DISEÑO
====================================================== */

function editarDiseno(
    id,
    titulo,
    descripcion,
    categoria
)
{

    document.getElementById(
        "editar_id_diseno"
    ).value = id;


    document.getElementById(
        "editar_titulo_diseno"
    ).value = titulo;


    document.getElementById(
        "editar_descripcion_diseno"
    ).value = descripcion;


    document.getElementById(
        "editar_categoria_diseno"
    ).value = categoria;


    abrirModal(
        "modalEditarDiseno"
    );
}


/* ======================================================
   EDITAR PUBLICACIÓN
====================================================== */

function editarPublicacion(
    id,
    titulo,
    contenido
)
{

    document.getElementById(
        "editar_id_publicacion"
    ).value = id;


    document.getElementById(
        "editar_titulo_publicacion"
    ).value = titulo;


    document.getElementById(
        "editar_contenido_publicacion"
    ).value = contenido;


    abrirModal(
        "modalEditarPublicacion"
    );
}


/* ======================================================
   OCULTAR MENSAJE AUTOMÁTICAMENTE
====================================================== */

setTimeout(function()
{

    const mensaje =
        document.querySelector(
            ".mensaje-perfil"
        );


    if (mensaje)
    {
        mensaje.style.display = "none";
    }

}, 4000);


</script>


<footer>

    <p>
        © 2026 Parche del Tattoo
    </p>

</footer>


</body>

</html>
