<?php
// video-form.php - Formulario para crear y editar videos destacados
require_once 'config/config.php';
require_once 'config/database.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();

$video_id = $_GET['id'] ?? null;
$video = null;
$success_message = '';
$error_message = '';

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Token de seguridad inválido';
    } else {
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $order_position = intval($_POST['order_position']);
        
        // Extraer YouTube ID de la URL
        $youtube_id = extractYouTubeId($url);
        
        if (empty($title)) {
            $error_message = 'El título es requerido';
        } elseif (empty($url)) {
            $error_message = 'La URL es requerida';
        } elseif (!$youtube_id) {
            $error_message = 'URL de YouTube inválida';
        } else {
            try {
                if ($video_id) {
                    // Actualizar video existente
                    $query = "UPDATE featured_videos SET 
                             title = :title, 
                             url = :url, 
                             youtube_id = :youtube_id,
                             description = :description, 
                             status = :status, 
                             featured = :featured, 
                             order_position = :order_position,
                             updated_at = NOW()
                             WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':url', $url);
                    $stmt->bindParam(':youtube_id', $youtube_id);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':featured', $featured);
                    $stmt->bindParam(':order_position', $order_position);
                    $stmt->bindParam(':id', $video_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Video actualizado correctamente';
                    }
                } else {
                    // Crear nuevo video
                    $query = "INSERT INTO featured_videos 
                             (title, url, youtube_id, description, status, featured, order_position, author_id, created_at) 
                             VALUES 
                             (:title, :url, :youtube_id, :description, :status, :featured, :order_position, :author_id, NOW())";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':url', $url);
                    $stmt->bindParam(':youtube_id', $youtube_id);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':featured', $featured);
                    $stmt->bindParam(':order_position', $order_position);
                    $stmt->bindParam(':author_id', $_SESSION['admin_id']);
                    
                    if ($stmt->execute()) {
                        $video_id = $db->lastInsertId();
                        $success_message = 'Video creado correctamente';
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Error al guardar el video: ' . $e->getMessage();
            }
        }
    }
}

// Cargar video para edición
if ($video_id) {
    $query = "SELECT * FROM featured_videos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $video_id);
    $stmt->execute();
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$video) {
        header('Location: videos.php');
        exit();
    }
}

// Obtener el siguiente número de orden
$next_order_query = "SELECT COALESCE(MAX(order_position), 0) + 1 as next_order FROM featured_videos";
$next_order_stmt = $db->prepare($next_order_query);
$next_order_stmt->execute();
$next_order = $next_order_stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $video ? 'Editar' : 'Nuevo'; ?> Video - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1a2639",
                        secondary: "#3f51b5",
                        accent: "#ff5722"
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-primary text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-xl font-bold hover:text-accent">Admin Panel</a>
                    <span class="text-accent">|</span>
                    <span class="text-gray-300"><?php echo $video ? 'Editar' : 'Nuevo'; ?> Video</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="videos.php" class="hover:text-accent">← Volver a videos</a>
                    <a href="logout.php" class="bg-accent hover:bg-opacity-90 px-3 py-1 rounded text-sm">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-8 px-4">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo escape($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo escape($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Contenido principal -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Título -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Título del Video *
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            required
                            value="<?php echo $video ? escape($video['title']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Ingresa el título del video"
                        >
                    </div>

                    <!-- URL de YouTube -->
                    <div>
                        <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                            URL de YouTube *
                        </label>
                        <input 
                            type="url" 
                            id="url" 
                            name="url" 
                            required
                            value="<?php echo $video ? escape($video['url']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="https://www.youtube.com/watch?v=..."
                            onchange="updatePreview()"
                        >
                        <p class="text-xs text-gray-500 mt-1">
                            Ingresa la URL completa del video de YouTube
                        </p>
                    </div>

                    <!-- Vista previa -->
                    <div id="video-preview" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Vista Previa
                        </label>
                        <div class="border border-gray-300 rounded-md p-4">
                            <iframe id="preview-iframe" width="100%" height="300" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Descripción
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Descripción opcional del video"
                        ><?php echo $video ? escape($video['description']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Publicar -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Publicar</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Estado
                                </label>
                                <select 
                                    id="status" 
                                    name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                                >
                                    <option value="draft" <?php echo ($video && $video['status'] === 'draft') ? 'selected' : ''; ?>>Borrador</option>
                                    <option value="published" <?php echo ($video && $video['status'] === 'published') ? 'selected' : ''; ?>>Publicado</option>
                                    <option value="archived" <?php echo ($video && $video['status'] === 'archived') ? 'selected' : ''; ?>>Archivado</option>
                                </select>
                            </div>

                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="featured" 
                                    name="featured" 
                                    value="1"
                                    <?php echo ($video && $video['featured']) ? 'checked' : ''; ?>
                                    class="rounded border-gray-300 text-accent focus:ring-accent"
                                >
                                <label for="featured" class="ml-2 text-sm text-gray-700">
                                    Video destacado
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 space-y-2">
                            <button 
                                type="submit" 
                                class="w-full bg-accent text-white py-2 px-4 rounded-md hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-accent"
                            >
                                <?php echo $video ? 'Actualizar' : 'Crear'; ?> Video
                            </button>
                        </div>
                    </div>

                    <!-- Configuración de orden -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Configuración</h3>
                        
                        <div>
                            <label for="order_position" class="block text-sm font-medium text-gray-700 mb-2">
                                Posición de Orden
                            </label>
                            <input 
                                type="number" 
                                id="order_position" 
                                name="order_position"
                                min="1"
                                value="<?php echo $video ? $video['order_position'] : $next_order; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                Los videos se ordenan por este número (menor = primero)
                            </p>
                        </div>
                    </div>

                    <!-- Información del video si es edición -->
                    <?php if ($video): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información</h3>
                            
                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-medium">YouTube ID:</span>
                                    <span class="text-gray-600"><?php echo $video['youtube_id']; ?></span>
                                </div>
                                <div>
                                    <span class="font-medium">Creado:</span>
                                    <span class="text-gray-600"><?php echo date('d/m/Y H:i', strtotime($video['created_at'])); ?></span>
                                </div>
                                <div>
                                    <span class="font-medium">Actualizado:</span>
                                    <span class="text-gray-600"><?php echo date('d/m/Y H:i', strtotime($video['updated_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script>
        function updatePreview() {
            const url = document.getElementById('url').value;
            const preview = document.getElementById('video-preview');
            const iframe = document.getElementById('preview-iframe');
            
            if (url) {
                const videoId = extractYouTubeId(url);
                if (videoId) {
                    iframe.src = `https://www.youtube.com/embed/${videoId}`;
                    preview.classList.remove('hidden');
                } else {
                    preview.classList.add('hidden');
                }
            } else {
                preview.classList.add('hidden');
            }
        }

        function extractYouTubeId(url) {
            const regex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
            const match = url.match(regex);
            return match ? match[1] : null;
        }

        // Mostrar vista previa si hay una URL al cargar la página
        window.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });

        // Auto-focus en el primer campo
        document.getElementById('title').focus();
    </script>
</body>
</html>