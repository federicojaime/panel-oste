<?php
// logout.php - Cerrar sesión del panel administrativo
require_once 'config/config.php';

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se usan cookies de sesión, eliminar también la cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destruir la sesión completamente
session_destroy();

// Limpiar cualquier cookie adicional que puedas haber establecido
setcookie('remember_token', '', time() - 3600, '/');

// Redirigir al login con mensaje de logout exitoso
header('Location: login.php?logged_out=1');
exit();
?>