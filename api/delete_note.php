<?php
// api/delete_note.php - Eliminar nota literaria
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
$note_id = $input['id'] ?? null;

if (!$note_id) {
    echo json_encode(['success' => false, 'message' => 'ID de nota requerido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que la nota existe y obtener info del archivo
    $check_query = "SELECT title, media_url, media_type, thumbnail_url FROM literary_notes WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $note_id);
    $check_stmt->execute();
    $note = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Nota no encontrada']);
        exit();
    }

    // Eliminar archivos asociados
    if ($note['media_url'] && $note['media_type'] !== 'youtube') {
        $media_path = UPLOAD_DIR . $note['media_url'];
        if (file_exists($media_path)) {
            unlink($media_path);
        }
    }

    // Eliminar thumbnail si existe
    if ($note['thumbnail_url']) {
        $thumbnail_path = UPLOAD_DIR . 'thumbnails/' . $note['thumbnail_url'];
        if (file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
    }

    // Eliminar nota de la base de datos
    $delete_query = "DELETE FROM literary_notes WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $note_id);

    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Nota "' . $note['title'] . '" eliminada correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la nota']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>