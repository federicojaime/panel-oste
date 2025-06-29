<?php
// api/generate_thumbnail.php - Generar thumbnail desde video

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$video_path = $input['video_path'] ?? null;
$note_id = $input['note_id'] ?? null;

if (!$video_path || !$note_id) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit();
}

// Función para generar thumbnail
function generateVideoThumbnail($videoPath, $noteId) {
    // Verificar que FFmpeg está instalado
    $ffmpeg = 'ffmpeg'; // o la ruta completa: '/usr/bin/ffmpeg'
    
    // Verificar que el archivo de video existe
    if (!file_exists($videoPath)) {
        throw new Exception('Archivo de video no encontrado: ' . $videoPath);
    }
    
    // Crear directorio de thumbnails si no existe
    $thumbnailDir = UPLOAD_DIR . 'thumbnails/';
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    // Nombre del thumbnail
    $thumbnailName = 'thumb_' . $noteId . '_' . time() . '.jpg';
    $thumbnailPath = $thumbnailDir . $thumbnailName;
    
    // Comando FFmpeg para extraer frame del segundo 1
    $command = sprintf(
        '%s -i %s -ss 00:00:01 -vframes 1 -q:v 2 -y %s 2>&1',
        escapeshellcmd($ffmpeg),
        escapeshellarg($videoPath),
        escapeshellarg($thumbnailPath)
    );
    
    // Ejecutar comando
    $output = shell_exec($command);
    
    // Verificar si se generó el thumbnail
    if (!file_exists($thumbnailPath)) {
        // Intentar con segundo 0 si falla
        $command = sprintf(
            '%s -i %s -ss 00:00:00 -vframes 1 -q:v 2 -y %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($videoPath),
            escapeshellarg($thumbnailPath)
        );
        
        $output = shell_exec($command);
        
        if (!file_exists($thumbnailPath)) {
            throw new Exception('Error generando thumbnail: ' . $output);
        }
    }
    
    return [
        'filename' => $thumbnailName,
        'path' => $thumbnailPath,
        'url' => BASE_URL . 'uploads/thumbnails/' . $thumbnailName
    ];
}

try {
    // Convertir ruta relativa a absoluta
    $fullVideoPath = str_replace(BASE_URL . 'uploads/', UPLOAD_DIR, $video_path);
    
    // Generar thumbnail
    $thumbnail = generateVideoThumbnail($fullVideoPath, $note_id);
    
    // Actualizar base de datos con el thumbnail
    $database = new Database();
    $db = $database->getConnection();
    
    $update_query = "UPDATE literary_notes SET thumbnail_url = :thumbnail WHERE id = :id";
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':thumbnail', $thumbnail['filename']);
    $stmt->bindParam(':id', $note_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'thumbnail_url' => $thumbnail['url'],
        'message' => 'Thumbnail generado correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>