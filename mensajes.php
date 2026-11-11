<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    exit('Error de conexión con la base de datos.');
}
$conexion->set_charset('utf8mb4');

function h($valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// Este módulo es privado: requiere una sesión válida.
if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

$id_usuario = (int) $_SESSION['id_usuario'];
$sqlUsuario = "SELECT id_usuario, nombre_usuario, nombre_completo, email, tipo_usuario
               FROM usuarios WHERE id_usuario = ? AND estado = 1 LIMIT 1";
$stmtUsuario = $conexion->prepare($sqlUsuario);
$stmtUsuario->bind_param('i', $id_usuario);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();
$stmtUsuario->close();

if (!$usuario) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['token_mensajes'])) {
    $_SESSION['token_mensajes'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_mensajes'];

$alerta = '';
$tipoAlerta = '';
$accion = $_POST['accion'] ?? '';

// Procesar envío, respuesta, edición o eliminación.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['token']) || !hash_equals($token, $_POST['token'])) {
            throw new Exception('La solicitud no es válida. Recarga la página.');
        }

        if ($accion === 'enviar' || $accion === 'responder') {
            $destinatario = (int)($_POST['id_destinatario'] ?? 0);
            if ($accion === 'responder') {
                $idOriginalPost = (int)($_POST['id_original'] ?? 0);
                $buscarOriginal = $conexion->prepare("SELECT id_remitente FROM mensajes WHERE id_mensaje = ? AND id_destinatario = ? LIMIT 1");
                $buscarOriginal->bind_param('ii', $idOriginalPost, $id_usuario);
                $buscarOriginal->execute();
                $originalPost = $buscarOriginal->get_result()->fetch_assoc();
                $buscarOriginal->close();
                if (!$originalPost) {
                    throw new Exception('No puedes responder ese mensaje.');
                }
                $destinatario = (int)$originalPost['id_remitente'];
            }
            $asunto = trim($_POST['asunto'] ?? '');
            $contenido = trim($_POST['contenido'] ?? '');

            if ($destinatario <= 0 || $destinatario === $id_usuario) {
                throw new Exception('Selecciona un destinatario válido.');
            }
            if ($asunto === '' || $contenido === '') {
                throw new Exception('El asunto y el mensaje son obligatorios.');
            }
            if (mb_strlen($asunto) > 150) {
                throw new Exception('El asunto no puede superar los 150 caracteres.');
            }
            if (mb_strlen($contenido) > 10000) {
                throw new Exception('El mensaje no puede superar los 10.000 caracteres.');
            }

            $verificar = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND estado = 1 LIMIT 1");
            $verificar->bind_param('i', $destinatario);
            $verificar->execute();
            $existe = $verificar->get_result()->fetch_assoc();
            $verificar->close();
            if (!$existe) {
                throw new Exception('El destinatario no existe o está inactivo.');
            }

            $insertar = $conexion->prepare("INSERT INTO mensajes (id_remitente, id_destinatario, asunto, contenido) VALUES (?, ?, ?, ?)");
            $insertar->bind_param('iiss', $id_usuario, $destinatario, $asunto, $contenido);
            if (!$insertar->execute()) {
                throw new Exception('No fue posible enviar el mensaje.');
            }
            $insertar->close();
            $alerta = 'Mensaje enviado correctamente.';
            $tipoAlerta = 'success';
            $_SESSION['token_mensajes'] = bin2hex(random_bytes(32));
            $token = $_SESSION['token_mensajes'];
        }

        elseif ($accion === 'editar') {
            $id_mensaje = (int)($_POST['id_mensaje'] ?? 0);
            $asunto = trim($_POST['asunto'] ?? '');
            $contenido = trim($_POST['contenido'] ?? '');
            if ($id_mensaje <= 0 || $asunto === '' || $contenido === '') {
                throw new Exception('Completa todos los campos.');
            }
            if (mb_strlen($asunto) > 150 || mb_strlen($contenido) > 10000) {
                throw new Exception('El asunto o el mensaje supera el límite permitido.');
            }
            // Solo el remitente puede editar sus propios mensajes.
            $comprobarEdicion = $conexion->prepare("SELECT id_mensaje FROM mensajes WHERE id_mensaje = ? AND id_remitente = ? LIMIT 1");
            $comprobarEdicion->bind_param('ii', $id_mensaje, $id_usuario);
            $comprobarEdicion->execute();
            $existeEdicion = $comprobarEdicion->get_result()->fetch_assoc();
            $comprobarEdicion->close();
            if (!$existeEdicion) {
                throw new Exception('No puedes editar este mensaje.');
            }
            $editar = $conexion->prepare("UPDATE mensajes SET asunto = ?, contenido = ? WHERE id_mensaje = ? AND id_remitente = ?");
            $editar->bind_param('ssii', $asunto, $contenido, $id_mensaje, $id_usuario);
            $editar->execute();
            $actualizados = $editar->affected_rows;
            $editar->close();
            $alerta = 'Mensaje actualizado correctamente.';
            $tipoAlerta = 'success';
            $_GET['vista'] = 'salida';
        }

        elseif ($accion === 'eliminar') {
            $id_mensaje = (int)($_POST['id_mensaje'] ?? 0);
            $eliminar = $conexion->prepare("DELETE FROM mensajes WHERE id_mensaje = ? AND id_remitente = ?");
            $eliminar->bind_param('ii', $id_mensaje, $id_usuario);
            $eliminar->execute();
            $eliminar->close();
            $alerta = 'Mensaje eliminado de tu bandeja de salida.';
            $tipoAlerta = 'success';
            $_GET['vista'] = 'salida';
        }
    } catch (Throwable $e) {
        $alerta = $e->getMessage();
        $tipoAlerta = 'error';
    }
}

$vista = $_GET['vista'] ?? 'entrada';
if (!in_array($vista, ['entrada', 'salida', 'nuevo', 'leer', 'editar'], true)) {
    $vista = 'entrada';
}

// Marcar como leído únicamente si el mensaje pertenece a la bandeja recibida.
$mensajeVer = null;
$idVer = (int)($_GET['id'] ?? 0);
if ($vista === 'leer' && $idVer > 0) {
    $marcar = $conexion->prepare("UPDATE mensajes SET leido = 1 WHERE id_mensaje = ? AND id_destinatario = ?");
    $marcar->bind_param('ii', $idVer, $id_usuario);
    $marcar->execute();
    $marcar->close();

    $leer = $conexion->prepare("SELECT m.*, u.nombre_usuario AS nombre_remitente
                                FROM mensajes m
                                INNER JOIN usuarios u ON u.id_usuario = m.id_remitente
                                WHERE m.id_mensaje = ? AND m.id_destinatario = ? LIMIT 1");
    $leer->bind_param('ii', $idVer, $id_usuario);
    $leer->execute();
    $mensajeVer = $leer->get_result()->fetch_assoc();
    $leer->close();
    if (!$mensajeVer) {
        $alerta = 'El mensaje no existe o no tienes permiso para verlo.';
        $tipoAlerta = 'error';
        $vista = 'entrada';
    }
}

$mensajeEditar = null;
if ($vista === 'editar' && $idVer > 0) {
    $editarConsulta = $conexion->prepare("SELECT id_mensaje, asunto, contenido FROM mensajes WHERE id_mensaje = ? AND id_remitente = ? LIMIT 1");
    $editarConsulta->bind_param('ii', $idVer, $id_usuario);
    $editarConsulta->execute();
    $mensajeEditar = $editarConsulta->get_result()->fetch_assoc();
    $editarConsulta->close();
    if (!$mensajeEditar) {
        $alerta = 'No puedes editar este mensaje o ya no existe.';
        $tipoAlerta = 'error';
        $vista = 'salida';
    }
}

// Destinatarios disponibles para redactar mensajes.
$usuarios = [];
$resultadoUsuarios = $conexion->query("SELECT id_usuario, nombre_usuario, tipo_usuario FROM usuarios WHERE estado = 1 AND id_usuario <> {$id_usuario} ORDER BY nombre_usuario ASC");
if ($resultadoUsuarios) {
    while ($fila = $resultadoUsuarios->fetch_assoc()) $usuarios[] = $fila;
}

// Bandeja de entrada.
$entrada = [];
if ($vista === 'entrada') {
    $consulta = $conexion->prepare("SELECT m.id_mensaje, m.asunto, m.contenido, m.leido, m.fecha_envio,
                                          u.nombre_usuario AS persona
                                   FROM mensajes m INNER JOIN usuarios u ON u.id_usuario = m.id_remitente
                                   WHERE m.id_destinatario = ? ORDER BY m.fecha_envio DESC");
    $consulta->bind_param('i', $id_usuario);
    $consulta->execute();
    $res = $consulta->get_result();
    while ($fila = $res->fetch_assoc()) $entrada[] = $fila;
    $consulta->close();
}

// Bandeja de salida.
$salida = [];
if ($vista === 'salida') {
    $consulta = $conexion->prepare("SELECT m.id_mensaje, m.asunto, m.contenido, m.leido, m.fecha_envio,
                                          u.nombre_usuario AS persona
                                   FROM mensajes m INNER JOIN usuarios u ON u.id_usuario = m.id_destinatario
                                   WHERE m.id_remitente = ? ORDER BY m.fecha_envio DESC");
    $consulta->bind_param('i', $id_usuario);
    $consulta->execute();
    $res = $consulta->get_result();
    while ($fila = $res->fetch_assoc()) $salida[] = $fila;
    $consulta->close();
}

$noLeidos = 0;
$contador = $conexion->prepare("SELECT COUNT(*) AS total FROM mensajes WHERE id_destinatario = ? AND leido = 0");
$contador->bind_param('i', $id_usuario);
$contador->execute();
$noLeidos = (int)$contador->get_result()->fetch_assoc()['total'];
$contador->close();

// Para responder a un mensaje recibido, el destinatario original será el remitente.
$responderA = null;
if ($vista === 'nuevo' && isset($_GET['responder'])) {
    $idOriginal = (int)$_GET['responder'];
    $original = $conexion->prepare("SELECT m.id_remitente, m.asunto, u.nombre_usuario
                                    FROM mensajes m INNER JOIN usuarios u ON u.id_usuario = m.id_remitente
                                    WHERE m.id_mensaje = ? AND m.id_destinatario = ? LIMIT 1");
    $original->bind_param('ii', $idOriginal, $id_usuario);
    $original->execute();
    $responderA = $original->get_result()->fetch_assoc();
    $original->close();
    if (!$responderA) {
        $alerta = 'No puedes responder ese mensaje.';
        $tipoAlerta = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes | Parche del Tattoo</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    
</head>
<body>
<header>
    <div class="logo">
        <img src="img/image-removebg-preview.png" alt="Logo Parche del Tattoo" class="logo-img">
        <h2>Parche del <span>Tattoo</span></h2>
    </div>
    <nav>
        <a href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
        <?php if ($usuario['tipo_usuario'] === 'Administrador'): ?>
            <a href="admin.php"><i class="fa-solid fa-gauge-high"></i> Administración</a>
        <?php endif; ?>
        <a href="comunidad.php"><i class="fa-solid fa-users"></i> Comunidad</a>
        <a href="diseños.php"><i class="fa-solid fa-palette"></i> Diseños</a>
        <a href="foro.php"><i class="fa-solid fa-comments"></i> Foro</a>
        <a href="blog.php"><i class="fa-solid fa-blog"></i> Blog</a>
        <a href="piercings.php"><i class="fa-solid fa-gem"></i> Piercings</a>
        <a href="perfil.php"><i class="fa-solid fa-user"></i> Mi perfil</a>
        <a href="mensajes.php"><i class="fa-solid fa-envelope"></i> Mensajes</a>
        <a href="contacto.php"><i class="fa-solid fa-envelope"></i> Contacto</a>
        <a href="logout.php" class="btn-cerrar-sesion"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
    </nav>
    <div class="usuario-sesion"><i class="fa-solid fa-circle-user"></i><div><strong><?= h($usuario['nombre_usuario']) ?></strong><br><span class="tipo-usuario"><?= h($usuario['tipo_usuario']) ?></span></div></div>
</header>

<main class="mensajes-contenedor">
    <h1><i class="fa-solid fa-envelope"></i> Mis mensajes</h1>
    <p>Consulta tus conversaciones, responde y administra los mensajes que has enviado.</p>

    <?php if ($alerta !== ''): ?>
        <div class="mensaje-alerta <?= h($tipoAlerta) ?>" role="status"><?= h($alerta) ?></div>
    <?php endif; ?>

    <div class="mensajes-tabs">
        <a href="mensajes.php?vista=entrada" class="<?= $vista === 'entrada' || $vista === 'leer' ? 'activo' : '' ?>"><i class="fa-solid fa-inbox"></i> Recibidos <?php if ($noLeidos > 0): ?><span class="contador"><?= $noLeidos ?></span><?php endif; ?></a>
        <a href="mensajes.php?vista=salida" class="<?= $vista === 'salida' || $vista === 'editar' ? 'activo' : '' ?>"><i class="fa-solid fa-paper-plane"></i> Enviados</a>
        <a href="mensajes.php?vista=nuevo" class="<?= $vista === 'nuevo' ? 'activo' : '' ?>"><i class="fa-solid fa-pen"></i> Nuevo mensaje</a>
    </div>

    <?php if ($vista === 'entrada'): ?>
        <h2>Bandeja de entrada</h2>
        <?php if (!$entrada): ?>
            <div class="sin-mensajes"><i class="fa-regular fa-envelope fa-2x"></i><p>No tienes mensajes recibidos todavía.</p></div>
        <?php else: ?>
            <div class="mensaje-lista">
            <?php foreach ($entrada as $m): ?>
                <article class="mensaje-item <?= !$m['leido'] ? 'no-leido' : '' ?>">
                    <div>
                        <h3><?= h($m['asunto'] ?: '(Sin asunto)') ?> <?= !$m['leido'] ? '<small>• NUEVO</small>' : '' ?></h3>
                        <div class="mensaje-meta">De: <strong><?= h($m['persona']) ?></strong> · <?= h(date('d/m/Y H:i', strtotime($m['fecha_envio']))) ?></div>
                        <p class="mensaje-resumen"><?= h(mb_strimwidth($m['contenido'], 0, 150, '...')) ?></p>
                    </div>
                    <div class="mensaje-acciones">
                        <a href="mensajes.php?vista=leer&id=<?= (int)$m['id_mensaje'] ?>"><i class="fa-solid fa-eye"></i> Leer</a>
                        <a href="mensajes.php?vista=nuevo&responder=<?= (int)$m['id_mensaje'] ?>"><i class="fa-solid fa-reply"></i> Responder</a>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($vista === 'salida'): ?>
        <h2>Bandeja de salida</h2>
        <?php if (!$salida): ?>
            <div class="sin-mensajes"><i class="fa-regular fa-paper-plane fa-2x"></i><p>Aún no has enviado mensajes.</p></div>
        <?php else: ?>
            <div class="mensaje-lista">
            <?php foreach ($salida as $m): ?>
                <article class="mensaje-item">
                    <div>
                        <h3><?= h($m['asunto'] ?: '(Sin asunto)') ?></h3>
                        <div class="mensaje-meta">Para: <strong><?= h($m['persona']) ?></strong> · <?= h(date('d/m/Y H:i', strtotime($m['fecha_envio']))) ?></div>
                        <p class="mensaje-resumen"><?= h(mb_strimwidth($m['contenido'], 0, 150, '...')) ?></p>
                    </div>
                    <div class="mensaje-acciones">
                        <a href="mensajes.php?vista=editar&id=<?= (int)$m['id_mensaje'] ?>"><i class="fa-solid fa-pen"></i> Editar</a>
                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este mensaje?');">
                            <input type="hidden" name="token" value="<?= h($token) ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_mensaje" value="<?= (int)$m['id_mensaje'] ?>">
                            <button class="btn-mensaje" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($vista === 'nuevo'): ?>
        <h2><?= $responderA ? 'Responder mensaje' : 'Redactar mensaje' ?></h2>
        <form class="form-mensaje" method="POST" action="mensajes.php?vista=nuevo">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="accion" value="<?= $responderA ? 'responder' : 'enviar' ?>">
            <?php if ($responderA): ?><input type="hidden" name="id_original" value="<?= (int)($_GET['responder'] ?? 0) ?>"><?php endif; ?>
            <label for="id_destinatario">Destinatario</label>
            <select name="id_destinatario" id="id_destinatario" required>
                <option value="">Selecciona un usuario</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= (int)$u['id_usuario'] ?>" <?= ($responderA && (int)$responderA['id_remitente'] === (int)$u['id_usuario']) ? 'selected' : '' ?>><?= h($u['nombre_usuario']) ?> (<?= h($u['tipo_usuario']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label for="asunto">Asunto</label>
            <input type="text" id="asunto" name="asunto" maxlength="150" required value="<?= $responderA ? h('Re: ' . ($responderA['asunto'] ?? '')) : '' ?>">
            <label for="contenido">Mensaje</label>
            <textarea id="contenido" name="contenido" maxlength="10000" placeholder="Escribe tu mensaje..." required></textarea>
            <button type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar mensaje</button>
        </form>

    <?php elseif ($vista === 'leer' && $mensajeVer): ?>
        <div class="mensaje-lectura">
            <h2><?= h($mensajeVer['asunto'] ?: '(Sin asunto)') ?></h2>
            <p class="mensaje-meta">De: <strong><?= h($mensajeVer['nombre_remitente']) ?></strong> · <?= h(date('d/m/Y H:i', strtotime($mensajeVer['fecha_envio']))) ?></p>
            <div class="contenido"><?= h($mensajeVer['contenido']) ?></div>
            <div class="mensaje-acciones" style="margin-top:20px">
                <a href="mensajes.php?vista=nuevo&responder=<?= (int)$mensajeVer['id_mensaje'] ?>"><i class="fa-solid fa-reply"></i> Responder</a>
                <a href="mensajes.php?vista=entrada"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </div>
        </div>

    <?php elseif ($vista === 'editar' && $mensajeEditar): ?>
        <h2>Editar mensaje enviado</h2>
        <form class="form-mensaje" method="POST" action="mensajes.php?vista=salida">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_mensaje" value="<?= (int)$mensajeEditar['id_mensaje'] ?>">
            <label for="asunto">Asunto</label>
            <input type="text" id="asunto" name="asunto" maxlength="150" required value="<?= h($mensajeEditar['asunto']) ?>">
            <label for="contenido">Mensaje</label>
            <textarea id="contenido" name="contenido" maxlength="10000" required><?= h($mensajeEditar['contenido']) ?></textarea>
            <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
            <a class="btn-mensaje" href="mensajes.php?vista=salida">Cancelar</a>
        </form>
    <?php endif; ?>
</main>

<footer><p>© 2026 Parche del Tattoo | Todos los derechos reservados.</p></footer>
</body>
</html>
