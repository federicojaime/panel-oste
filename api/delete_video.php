<?php
// api/delete_video.php - Eliminar video destacado
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$video_id = $input['id'] ?? null;

if (!$video_id) {
    echo json_encode(['success' => false, 'message' => 'ID de video requerido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el video existe
    $check_query = "SELECT title FROM featured_videos WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $video_id);
    $check_stmt->execute();
    $video = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        echo json_encode(['success' => false, 'message' => 'Video no encontrado']);
        exit();
    }

    // Eliminar video de la base de datos
    $delete_query = "DELETE FROM featured_videos WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $video_id);

    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Video "' . $video['title'] . '" eliminado correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el video']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>