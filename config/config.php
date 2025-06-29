<?php
// config/config.php - CORREGIDO SIN DUPLICADOS

// Configuración de la aplicación
define('BASE_URL', 'https://sergiotomasoste.com/panel-oste/');
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

// FUNCIONES HELPER PARA THUMBNAILS (sin duplicar las funciones principales)

/**
 * Verifica si FFmpeg está disponible en el sistema
 */
function checkFFmpegAvailability() {
    $output = shell_exec('ffmpeg -version 2>&1');
    return strpos($output, 'ffmpeg version') !== false;
}

/**
 * Crea el directorio de thumbnails si no existe
 */
function ensureThumbnailDirectory() {
    $thumbnailDir = UPLOAD_DIR . 'thumbnails/';
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    return $thumbnailDir;
}

/**
 * Obtiene información de un video usando FFprobe (si está disponible)
 */
function getVideoInfo($videoPath) {
    if (!checkFFmpegAvailability()) {
        return null;
    }
    
    $command = sprintf(
        'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
        escapeshellarg($videoPath)
    );
    
    $output = shell_exec($command);
    $info = json_decode($output, true);
    
    return $info;
}

/**
 * Limpia thumbnails antiguos (útil para mantenimiento)
 */
function cleanupOldThumbnails($days = 30) {
    $thumbnailDir = UPLOAD_DIR . 'thumbnails/';
    if (!is_dir($thumbnailDir)) {
        return false;
    }
    
    $files = glob($thumbnailDir . '*');
    $now = time();
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= (60 * 60 * 24 * $days)) {
                unlink($file);
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

/**
 * Obtiene el tamaño de un archivo de video de forma segura
 */
function getVideoFileSize($videoPath) {
    if (!file_exists($videoPath)) {
        return false;
    }
    
    return filesize($videoPath);
}

/**
 * Valida si un archivo es un video válido
 */
function isValidVideoFile($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv'];
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        return false;
    }
    
    // Verificar MIME type si la función está disponible
    if (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($filePath);
        return strpos($mimeType, 'video/') === 0;
    }
    
    return true;
}

/**
 * Genera un nombre único para el thumbnail
 */
function generateThumbnailFilename($noteId, $extension = 'jpg') {
    return 'thumb_' . $noteId . '_' . time() . '.' . $extension;
}

/**
 * Obtiene estadísticas de thumbnails
 */
function getThumbnailStats() {
    $thumbnailDir = UPLOAD_DIR . 'thumbnails/';
    
    if (!is_dir($thumbnailDir)) {
        return [
            'total_files' => 0,
            'total_size' => 0,
            'ffmpeg_available' => checkFFmpegAvailability()
        ];
    }
    
    $files = glob($thumbnailDir . '*');
    $totalSize = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $totalSize += filesize($file);
        }
    }
    
    return [
        'total_files' => count($files),
        'total_size' => $totalSize,
        'total_size_mb' => round($totalSize / 1024 / 1024, 2),
        'ffmpeg_available' => checkFFmpegAvailability()
    ];
}

// FUNCIÓN SIMPLE PARA GENERAR THUMBNAILS
function generateThumbnailSimple($videoPath, $noteId) {
    // Verificar FFmpeg
    $ffmpegTest = shell_exec('ffmpeg -version 2>&1');
    if (strpos($ffmpegTest, 'ffmpeg version') === false) {
        return generateSVGPlaceholder($noteId);
    }
    
    // Crear directorio
    $thumbnailDir = UPLOAD_DIR . 'thumbnails/';
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    $thumbnailName = 'thumb_' . $noteId . '_' . time() . '.jpg';
    $thumbnailPath = $thumbnailDir . $thumbnailName;
    
    // Comando simple de FFmpeg
    $command = "ffmpeg -i \"$videoPath\" -ss 00:00:01 -vframes 1 -q:v 2 -y \"$thumbnailPath\" 2>&1";
    $output = shell_exec($command);
    
    if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
        return $thumbnailName;
    }
    
    // Si falla, crear SVG
    return generateSVGPlaceholder($noteId);
}

function generateSVGPlaceholder($noteId) {
    $thumbnailDir = UPLOAD_DIR . 'thumbnails/';
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    $thumbnailName = 'placeholder_' . $noteId . '.svg';
    $thumbnailPath = $thumbnailDir . $thumbnailName;
    
    $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="640" height="360" viewBox="0 0 640 360" xmlns="http://www.w3.org/2000/svg">
    <rect width="640" height="360" fill="#374151"/>
    <circle cx="320" cy="150" r="40" fill="#EF4444"/>
    <polygon points="305,135 305,165 340,150" fill="white"/>
    <text x="320" y="220" font-family="Arial" font-size="18" fill="white" text-anchor="middle">VIDEO</text>
    <text x="320" y="250" font-family="Arial" font-size="14" fill="#9CA3AF" text-anchor="middle">Nota ' . $noteId . '</text>
</svg>';
    
    file_put_contents($thumbnailPath, $svgContent);
    return $thumbnailName;
}
?>