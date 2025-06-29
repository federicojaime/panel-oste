<?php
// api/notes.php - API ACTUALIZADA CON THUMBNAILS

// Headers CORS y de contenido
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Obtener nota específica por ID
                $id = intval($_GET['id']);
                $query = "SELECT * FROM literary_notes WHERE id = :id AND status = 'published'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $note = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($note) {
                    // Formatear media_url para el frontend
                    $media = null;
                    if ($note['media_url']) {
                        if ($note['media_type'] === 'youtube') {
                            $media = $note['media_url'];
                        } else {
                            $media = BASE_URL . 'uploads/' . $note['media_url'];
                        }
                    }

                    // Formatear thumbnail_url
                    $thumbnail = null;
                    if ($note['thumbnail_url']) {
                        $thumbnail = BASE_URL . 'uploads/thumbnails/' . $note['thumbnail_url'];
                    }

                    // Formatear para compatibilidad con el JSON existente
                    $formatted_note = [
                        'id' => intval($note['id']),
                        'title' => $note['title'],
                        'url' => $note['external_url'] ?: null,
                        'content' => $note['content'] ?: 'Sin contenido',
                        'excerpt' => $note['excerpt'] ?: null,
                        'media' => $media,
                        'media_type' => $note['media_type'],
                        'video' => $note['media_type'] === 'video' ? $media : null,
                        'thumbnail' => $thumbnail, // THUMBNAIL COMPLETO
                        'thumbnail_url' => $thumbnail, // ALIAS
                        'featured' => (bool)$note['featured'],
                        'created_at' => $note['created_at'],
                        'updated_at' => $note['updated_at']
                    ];

                    echo json_encode($formatted_note, JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Nota no encontrada'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Obtener todas las notas publicadas
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 20;
                $offset = ($page - 1) * $limit;

                // Filtros opcionales
                $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                $featured_only = isset($_GET['featured']) && $_GET['featured'] === 'true';

                // Construir query con filtros
                $where_conditions = ["status = 'published'"];
                $params = [];

                if (!empty($search)) {
                    $where_conditions[] = "(title LIKE :search OR content LIKE :search)";
                    $params[':search'] = '%' . $search . '%';
                }

                if ($featured_only) {
                    $where_conditions[] = "featured = 1";
                }

                $where_clause = implode(' AND ', $where_conditions);

                // Query principal
                $query = "SELECT * FROM literary_notes 
                         WHERE $where_clause 
                         ORDER BY created_at DESC 
                         LIMIT :limit OFFSET :offset";

                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Query para contar total
                $count_query = "SELECT COUNT(*) as total FROM literary_notes WHERE $where_clause";
                $count_stmt = $db->prepare($count_query);
                foreach ($params as $key => $value) {
                    $count_stmt->bindValue($key, $value);
                }
                $count_stmt->execute();
                $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

                // Formatear notas para compatibilidad con el JSON existente
                $formatted_notes = [];
                foreach ($notes as $note) {
                    $media = null;
                    if ($note['media_url']) {
                        if ($note['media_type'] === 'youtube') {
                            $media = $note['media_url'];
                        } else {
                            $media = BASE_URL . 'uploads/' . $note['media_url'];
                        }
                    }

                    // Formatear thumbnail
                    $thumbnail = null;
                    if ($note['thumbnail_url']) {
                        $thumbnail = BASE_URL . 'uploads/thumbnails/' . $note['thumbnail_url'];
                    }

                    $formatted_notes[] = [
                        'id' => intval($note['id']),
                        'title' => $note['title'],
                        'url' => $note['external_url'] ?: null,
                        'content' => $note['content'] ?: 'Sin contenido',
                        'excerpt' => $note['excerpt'] ?: null,
                        'media' => $media,
                        'media_type' => $note['media_type'],
                        'video' => $note['media_type'] === 'video' ? $media : null,
                        'thumbnail' => $thumbnail, // THUMBNAIL COMPLETO
                        'thumbnail_url' => $thumbnail, // ALIAS
                        'featured' => (bool)$note['featured'],
                        'created_at' => $note['created_at'],
                        'updated_at' => $note['updated_at']
                    ];
                }

                // Respuesta con metadatos de paginación
                $response = [
                    'success' => true,
                    'notes' => $formatted_notes, // CAMBIO: usar 'notes' en lugar de 'data'
                    'data' => $formatted_notes,   // Mantener para compatibilidad
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => intval($total),
                        'total_pages' => ceil($total / $limit),
                        'has_next' => $page < ceil($total / $limit),
                        'has_prev' => $page > 1
                    ]
                ];

                echo json_encode($response, JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>