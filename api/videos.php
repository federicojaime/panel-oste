<?php
// api/videos.php - API para obtener videos destacados

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
                // Obtener video específico por ID
                $id = intval($_GET['id']);
                $query = "SELECT * FROM featured_videos WHERE id = :id AND status = 'published'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $video = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($video) {
                    $formatted_video = [
                        'id' => intval($video['id']),
                        'title' => $video['title'],
                        'url' => $video['url'],
                        'youtube_id' => $video['youtube_id'],
                        'thumbnail' => $video['thumbnail_url'] ?: "https://img.youtube.com/vi/{$video['youtube_id']}/mqdefault.jpg",
                        'description' => $video['description'],
                        'featured' => (bool)$video['featured'],
                        'created_at' => $video['created_at'],
                        'updated_at' => $video['updated_at']
                    ];
                    
                    echo json_encode($formatted_video, JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Video no encontrado'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Obtener todos los videos publicados
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
                    $where_conditions[] = "(title LIKE :search OR description LIKE :search)";
                    $params[':search'] = '%' . $search . '%';
                }
                
                if ($featured_only) {
                    $where_conditions[] = "featured = 1";
                }
                
                $where_clause = implode(' AND ', $where_conditions);
                
                // Query principal
                $query = "SELECT * FROM featured_videos 
                         WHERE $where_clause 
                         ORDER BY order_position ASC, created_at DESC 
                         LIMIT :limit OFFSET :offset";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Query para contar total
                $count_query = "SELECT COUNT(*) as total FROM featured_videos WHERE $where_clause";
                $count_stmt = $db->prepare($count_query);
                foreach ($params as $key => $value) {
                    $count_stmt->bindValue($key, $value);
                }
                $count_stmt->execute();
                $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Formatear videos para compatibilidad
                $formatted_videos = [];
                foreach ($videos as $video) {
                    $formatted_videos[] = [
                        'id' => intval($video['id']),
                        'title' => $video['title'],
                        'url' => $video['url'],
                        'youtube_id' => $video['youtube_id'],
                        'thumbnail' => $video['thumbnail_url'] ?: "https://img.youtube.com/vi/{$video['youtube_id']}/mqdefault.jpg",
                        'description' => $video['description'],
                        'featured' => (bool)$video['featured'],
                        'created_at' => $video['created_at'],
                        'updated_at' => $video['updated_at']
                    ];
                }
                
                // Respuesta con metadatos de paginación
                $response = [
                    'data' => $formatted_videos,
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