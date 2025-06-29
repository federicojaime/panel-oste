<?php
// api/delete_press.php - Eliminar nota de prensa
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
$article_id = $input['id'] ?? null;

if (!$article_id) {
    echo json_encode(['success' => false, 'message' => 'ID de artículo requerido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el artículo existe
    $check_query = "SELECT title FROM press_articles WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $article_id);
    $check_stmt->execute();
    $article = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        echo json_encode(['success' => false, 'message' => 'Artículo no encontrado']);
        exit();
    }

    // Eliminar artículo de la base de datos
    $delete_query = "DELETE FROM press_articles WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $article_id);

    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Nota de prensa "' . $article['title'] . '" eliminada correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la nota']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>