<?php

// Iniciar la sesión
session_start();

// Eliminar todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Regresar al inicio
header("Location: index.php");
exit;

?>
