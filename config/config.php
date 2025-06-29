<?php
// config/config.php - CORREGIDO PARA VIDEOS

// Configuración de la aplicación
define('BASE_URL', 'http://localhost/panel-oste/');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB para videos

// Configuración PHP para archivos grandes
ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', 600);
ini_set('max_input_time', 600);
ini_set('memory_limit', '512M');

// Configuración de seguridad
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token validation - CORREGIDA
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Upload function - MEJORADA PARA VIDEOS
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'mkv', 'webm']) {
    // Verificar errores de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'message' => 'El archivo es demasiado grande. Máximo: 100MB'];
            case UPLOAD_ERR_PARTIAL:
                return ['success' => false, 'message' => 'El archivo se subió parcialmente'];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'message' => 'No se seleccionó archivo'];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['success' => false, 'message' => 'Falta directorio temporal'];
            case UPLOAD_ERR_CANT_WRITE:
                return ['success' => false, 'message' => 'Error de escritura'];
            default:
                return ['success' => false, 'message' => 'Error desconocido en upload'];
        }
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Archivo muy grande (máximo 100MB)'];
    }

    if ($file['size'] == 0) {
        return ['success' => false, 'message' => 'El archivo está vacío'];
    }

    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension'] ?? '');

    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Tipo no permitido. Permitidos: ' . implode(', ', $allowed_types)];
    }

    // Crear directorio si no existe
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'message' => 'No se pudo crear directorio de uploads'];
        }
    }

    // Generar nombre único
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $upload_path = UPLOAD_DIR . $filename;

    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename, 'path' => $upload_path];
    } else {
        return ['success' => false, 'message' => 'Error al guardar archivo'];
    }
}

function extractYouTubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return isset($matches[1]) ? $matches[1] : false;
}
?>