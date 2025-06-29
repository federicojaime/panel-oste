<?php
// press-form.php - Formulario para crear y editar notas de prensa
require_once 'config/config.php';
require_once 'config/database.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();

$article_id = $_GET['id'] ?? null;
$article = null;
$success_message = '';
$error_message = '';

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Token de seguridad inválido';
    } else {
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $source = trim($_POST['source']);
        $published_date = $_POST['published_date'];
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $order_position = intval($_POST['order_position']);
        
        if (empty($title)) {
            $error_message = 'El título es requerido';
        } elseif (empty($url)) {
            $error_message = 'La URL es requerida';
        } elseif (empty($source)) {
            $error_message = 'La fuente es requerida';
        } elseif (empty($published_date)) {
            $error_message = 'La fecha de publicación es requerida';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error_message = 'La URL no tiene un formato válido';
        } else {
            try {
                if ($article_id) {
                    // Actualizar artículo existente
                    $query = "UPDATE press_articles SET 
                             title = :title, 
                             url = :url, 
                             source = :source,
                             published_date = :published_date,
                             description = :description, 
                             status = :status, 
                             featured = :featured, 
                             order_position = :order_position,
                             updated_at = NOW()
                             WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':url', $url);
                    $stmt->bindParam(':source', $source);
                    $stmt->bindParam(':published_date', $published_date);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':featured', $featured);
                    $stmt->bindParam(':order_position', $order_position);
                    $stmt->bindParam(':id', $article_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Nota de prensa actualizada correctamente';
                    }
                } else {
                    // Crear nuevo artículo
                    $query = "INSERT INTO press_articles 
                             (title, url, source, published_date, description, status, featured, order_position, author_id, created_at) 
                             VALUES 
                             (:title, :url, :source, :published_date, :description, :status, :featured, :order_position, :author_id, NOW())";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':url', $url);
                    $stmt->bindParam(':source', $source);
                    $stmt->bindParam(':published_date', $published_date);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':featured', $featured);
                    $stmt->bindParam(':order_position', $order_position);
                    $stmt->bindParam(':author_id', $_SESSION['admin_id']);
                    
                    if ($stmt->execute()) {
                        $article_id = $db->lastInsertId();
                        $success_message = 'Nota de prensa creada correctamente';
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Error al guardar la nota: ' . $e->getMessage();
            }
        }
    }
}

// Cargar artículo para edición
if ($article_id) {
    $query = "SELECT * FROM press_articles WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $article_id);
    $stmt->execute();
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        header('Location: press.php');
        exit();
    }
}

// Obtener el siguiente número de orden
$next_order_query = "SELECT COALESCE(MAX(order_position), 0) + 1 as next_order FROM press_articles";
$next_order_stmt = $db->prepare($next_order_query);
$next_order_stmt->execute();
$next_order = $next_order_stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article ? 'Editar' : 'Nueva'; ?> Nota de Prensa - Admin</title>
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
                    <span class="text-gray-300"><?php echo $article ? 'Editar' : 'Nueva'; ?> Nota de Prensa</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="press.php" class="hover:text-accent">← Volver a notas</a>
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
                            Título del Artículo *
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            required
                            value="<?php echo $article ? escape($article['title']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Ingresa el título del artículo"
                        >
                    </div>

                    <!-- URL del artículo -->
                    <div>
                        <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                            URL del Artículo *
                        </label>
                        <input 
                            type="url" 
                            id="url" 
                            name="url" 
                            required
                            value="<?php echo $article ? escape($article['url']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="https://ejemplo.com/articulo"
                        >
                        <p class="text-xs text-gray-500 mt-1">
                            URL completa donde se puede leer el artículo
                        </p>
                    </div>

                    <!-- Fuente y fecha -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="source" class="block text-sm font-medium text-gray-700 mb-2">
                                Fuente *
                            </label>
                            <input 
                                type="text" 
                                id="source" 
                                name="source" 
                                required
                                value="<?php echo $article ? escape($article['source']) : ''; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                                placeholder="Nombre del medio/publicación"
                            >
                        </div>

                        <div>
                            <label for="published_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Fecha de Publicación *
                            </label>
                            <input 
                                type="date" 
                                id="published_date" 
                                name="published_date" 
                                required
                                value="<?php echo $article ? $article['published_date'] : ''; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            >
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
                            placeholder="Descripción opcional del artículo"
                        ><?php echo $article ? escape($article['description']) : ''; ?></textarea>
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
                                    <option value="draft" <?php echo ($article && $article['status'] === 'draft') ? 'selected' : ''; ?>>Borrador</option>
                                    <option value="published" <?php echo ($article && $article['status'] === 'published') ? 'selected' : ''; ?>>Publicado</option>
                                    <option value="archived" <?php echo ($article && $article['status'] === 'archived') ? 'selected' : ''; ?>>Archivado</option>
                                </select>
                            </div>

                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="featured" 
                                    name="featured" 
                                    value="1"
                                    <?php echo ($article && $article['featured']) ? 'checked' : ''; ?>
                                    class="rounded border-gray-300 text-accent focus:ring-accent"
                                >
                                <label for="featured" class="ml-2 text-sm text-gray-700">
                                    Nota destacada
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 space-y-2">
                            <button 
                                type="submit" 
                                class="w-full bg-accent text-white py-2 px-4 rounded-md hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-accent"
                            >
                                <?php echo $article ? 'Actualizar' : 'Crear'; ?> Nota
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
                                value="<?php echo $article ? $article['order_position'] : $next_order; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                Las notas se ordenan por este número (menor = primero)
                            </p>
                        </div>
                    </div>

                    <!-- Vista previa del enlace -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Vista Previa</h3>
                        
                        <div id="link-preview" class="text-sm">
                            <div class="border border-gray-200 rounded p-3 bg-white">
                                <div class="font-medium text-gray-900" id="preview-title">
                                    <?php echo $article ? escape($article['title']) : 'Título del artículo'; ?>
                                </div>
                                <div class="text-gray-600 mt-1" id="preview-source">
                                    <?php echo $article ? escape($article['source']) : 'Fuente'; ?>
                                </div>
                                <div class="text-gray-500 text-xs mt-2" id="preview-date">
                                    <?php echo $article ? date('d M Y', strtotime($article['published_date'])) : 'Fecha'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información del artículo si es edición -->
                    <?php if ($article): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información</h3>
                            
                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-medium">Creado:</span>
                                    <span class="text-gray-600"><?php echo date('d/m/Y H:i', strtotime($article['created_at'])); ?></span>
                                </div>
                                <div>
                                    <span class="font-medium">Actualizado:</span>
                                    <span class="text-gray-600"><?php echo date('d/m/Y H:i', strtotime($article['updated_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Actualizar vista previa en tiempo real
        function updatePreview() {
            const title = document.getElementById('title').value || 'Título del artículo';
            const source = document.getElementById('source').value || 'Fuente';
            const date = document.getElementById('published_date').value;
            
            document.getElementById('preview-title').textContent = title;
            document.getElementById('preview-source').textContent = source;
            
            if (date) {
                const formattedDate = new Date(date).toLocaleDateString('es-ES', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
                document.getElementById('preview-date').textContent = formattedDate;
            } else {
                document.getElementById('preview-date').textContent = 'Fecha';
            }
        }

        // Escuchar cambios en los campos
        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('source').addEventListener('input', updatePreview);
        document.getElementById('published_date').addEventListener('change', updatePreview);

        // Auto-focus en el primer campo
        document.getElementById('title').focus();

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const url = document.getElementById('url').value;
            
            if (url && !isValidUrl(url)) {
                e.preventDefault();
                alert('Por favor, ingresa una URL válida');
                return false;
            }
        });

        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    </script>
</body>
</html>