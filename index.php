<?php
// index.php - Página de entrada del panel administrativo
require_once 'config/config.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Si no está logueado, redirigir al login
header('Location: login.php');
exit();
?>