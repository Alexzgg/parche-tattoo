
<?php

include("conexion.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ==============================
    // RECIBIR DATOS
    // ==============================

    $usuario = trim($_POST["usuario"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmar = $_POST["confirmar"] ?? "";
    $tipo = $_POST["tipo"] ?? "";

    // ==============================
    // VALIDACIONES
    // ==============================

    if (
        $usuario === "" ||
        $correo === "" ||
        $password === "" ||
        $confirmar === "" ||
        $tipo === ""
    ) {

        $error = "Complete todos los campos obligatorios.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = "Ingrese un correo electrónico válido.";

    } elseif ($password !== $confirmar) {

        $error = "Las contraseñas no coinciden.";

    } elseif (strlen($password) < 6) {

        $error = "La contraseña debe tener mínimo 6 caracteres.";

    } else {

        // ==============================
        // COMPROBAR SI YA EXISTE
        // ==============================

        $sql = "SELECT id_usuario
                FROM usuarios
                WHERE email = ? OR nombre_usuario = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param("ss", $correo, $usuario);

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $error = "El correo o nombre de usuario ya está registrado.";

        } else {

            // ==============================
            // ENCRIPTAR CONTRASEÑA
            // ==============================

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // ==============================
            // INSERTAR USUARIO
            // ==============================

            $sql = "INSERT INTO usuarios
                    (
                        nombre_usuario,
                        email,
                        password,
                        telefono,
                        tipo_usuario
                    )
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $conexion->prepare($sql);

            $stmt->bind_param(
                "sssss",
                $usuario,
                $correo,
                $password_hash,
                $telefono,
                $tipo
            );

            if ($stmt->execute()) {

                // ==============================
                // OBTENER ID DEL USUARIO
                // ==============================

                $id_usuario = $conexion->insert_id;

                // ==============================
                // CREAR PERFIL
                // ==============================

                $sqlPerfil = "INSERT INTO perfiles
                              (id_usuario)
                              VALUES (?)";

                $stmtPerfil = $conexion->prepare($sqlPerfil);

                $stmtPerfil->bind_param(
                    "i",
                    $id_usuario
                );

                if ($stmtPerfil->execute()) {

                    $stmtPerfil->close();
                    $stmt->close();

                    // ==============================
                    // REDIRIGIR AL LOGIN
                    // ==============================

                    header("Location: login.php?registro=exitoso");
                    exit();

                } else {

                    $error = "Usuario creado, pero no se pudo crear su perfil.";

                    $stmtPerfil->close();
                }

            } else {

                $error = "No se pudo registrar el usuario.";

            }
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Registro | Parche del Tattoo</title>

    <link rel="stylesheet"
          href="css/style.css">

    <link rel="stylesheet"
          href="css/responsive.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

</head>

<body class="login-body">

<div class="login-container">

    <!-- ==============================
         PARTE IZQUIERDA
    =============================== -->

    <div class="login-left">

        <h1>
            Únete a
            <span>Parche del Tattoo</span>
        </h1>

        <p>
            Crea tu perfil, publica tus diseños,
            consigue clientes y comparte con la comunidad.
        </p>

        <img
            src="./img/image-removebg-preview.png"
            alt="Logo">

    </div>


    <!-- ==============================
         PARTE DERECHA
    =============================== -->

    <div class="login-right">

        <form
            id="registroForm"
            method="POST"
            action="registro.php">

            <h2>Crear Cuenta</h2>


            <!-- MENSAJE DE ERROR -->

            <?php if ($error !== ""): ?>

                <div class="mensaje-error">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!-- USUARIO -->

            <div class="input-box">

                <i class="fa fa-user-tag"></i>

                <input
                    type="text"
                    name="usuario"
                    id="usuario"
                    placeholder="Nombre de Usuario"
                    required>

            </div>


            <!-- CORREO -->

            <div class="input-box">

                <i class="fa fa-envelope"></i>

                <input
                    type="email"
                    name="correo"
                    id="correo"
                    placeholder="Correo Electrónico"
                    required>

            </div>


            <!-- TELÉFONO -->

            <div class="input-box">

                <i class="fa fa-phone"></i>

                <input
                    type="tel"
                    name="telefono"
                    id="telefono"
                    placeholder="Teléfono">

            </div>


            <!-- CONTRASEÑA -->

            <div class="input-box">

                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Contraseña"
                    required>

            </div>


            <!-- CONFIRMAR CONTRASEÑA -->

            <div class="input-box">

                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    name="confirmar"
                    id="confirmar"
                    placeholder="Confirmar Contraseña"
                    required>

            </div>


            <!-- TIPO DE USUARIO -->

            <div class="input-box">

                <i class="fa fa-palette"></i>

                <select
                    name="tipo"
                    id="tipo"
                    required>

                    <option value="">
                        Tipo de Usuario
                    </option>

                    <option value="Artista">
                        Artista
                    </option>

                    <option value="Cliente">
                        Cliente
                    </option>

                </select>

            </div>


            <!-- TÉRMINOS Y CONDICIONES -->

            <label style="display:block;margin-bottom:20px;">

                <input
                    type="checkbox"
                    required>

                Acepto los términos y condiciones

            </label>


            <!-- BOTÓN REGISTRAR -->

            <button type="submit">

                <i class="fa-solid fa-user-plus"></i>

                Registrarme

            </button>


            <!-- ENLACE AL LOGIN -->

            <p class="registro">

                ¿Ya tienes una cuenta?

                <a href="login.php">
                    Iniciar Sesión
                </a>

            </p>

        </form>

    </div>

</div>

</body>

</html>
