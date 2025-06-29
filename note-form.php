<?php
// note-form.php - Formulario para crear y editar notas
require_once 'config/config.php';
require_once 'config/database.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();

$note_id = $_GET['id'] ?? null;
$note = null;
$success_message = '';
$error_message = '';

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Token de seguridad inválido';
    } else {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $excerpt = trim($_POST['excerpt']);
        $status = $_POST['status'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $external_url = trim($_POST['external_url']);
        $media_type = $_POST['media_type'];
        
        $media_url = '';

        // Procesar media
        if ($media_type === 'youtube' && !empty($_POST['youtube_url'])) {
            $youtube_id = extractYouTubeId($_POST['youtube_url']);
            if ($youtube_id) {
                $media_url = $_POST['youtube_url'];
            }
        } elseif (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $media_upload_result = uploadFile($_FILES['media_file']);
            if ($media_upload_result['success']) {
                $media_url = $media_upload_result['filename'];
            } else {
                $error_message = $media_upload_result['message'];
            }
        }

        if (empty($title)) {
            $error_message = 'El título es requerido';
        } elseif (empty($error_message)) {
            try {
                if ($note_id) {
                    // Actualizar nota existente
                    $query = "UPDATE literary_notes SET 
                             title = :title, 
                             content = :content, 
                             excerpt = :excerpt, 
                             status = :status, 
                             featured = :featured, 
                             external_url = :external_url,
                             media_type = :media_type,
                             updated_at = NOW()";
                    
                    if (!empty($media_url)) {
                        $query .= ", media_url = :media_url";
                    }
                    
                    $query .= " WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':content', $content);
                    $stmt->bindParam(':excerpt', $excerpt);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':featured', $featured);
                    $stmt->bindParam(':external_url', $external_url);
                    $stmt->bindParam(':media_type', $media_type);
                    $stmt->bindParam(':id', $note_id);
                    
                    if (!empty($media_url)) {
                        $stmt->bindParam(':media_url', $media_url);
                    }
                    
                    if ($stmt->execute()) {
                        $success_message = 'Nota actualizada correctamente';
                    }
                } else {
                    // Crear nueva nota
                    $query = "INSERT INTO literary_notes 
                             (title, content, excerpt, status, featured, external_url, media_url, media_type, author_id, created_at) 
                             VALUES 
                             (:title, :content, :excerpt, :status, :featured, :external_url, :media_url, :media_type, :author_id, NOW())";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':content', $content);
                    $stmt->bindParam(':excerpt', $excerpt);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':featured', $featured);
                    $stmt->bindParam(':external_url', $external_url);
                    $stmt->bindParam(':media_url', $media_url);
                    $stmt->bindParam(':media_type', $media_type);
                    $stmt->bindParam(':author_id', $_SESSION['admin_id']);
                    
                    if ($stmt->execute()) {
                        $note_id = $db->lastInsertId();
                        $success_message = 'Nota creada correctamente';
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Error al guardar la nota: ' . $e->getMessage();
            }
        }
    }
}

// Cargar nota para edición
if ($note_id) {
    $query = "SELECT * FROM literary_notes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $note_id);
    $stmt->execute();
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        header('Location: notes.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $note ? 'Editar' : 'Nueva'; ?> Nota - Admin</title>
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
    <script src="https://cdn.ckeditor.com/ckeditor5/35.4.0/classic/ckeditor.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-primary text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-xl font-bold hover:text-accent">Admin Panel</a>
                    <span class="text-accent">|</span>
                    <span class="text-gray-300"><?php echo $note ? 'Editar' : 'Nueva'; ?> Nota</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="notes.php" class="hover:text-accent">← Volver a notas</a>
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

        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Contenido principal -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Título -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Título *
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            required
                            value="<?php echo $note ? escape($note['title']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Ingresa el título de la nota"
                        >
                    </div>

                    <!-- Extracto -->
                    <div>
                        <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-2">
                            Extracto
                        </label>
                        <textarea 
                            id="excerpt" 
                            name="excerpt" 
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Breve descripción de la nota (opcional)"
                        ><?php echo $note ? escape($note['excerpt']) : ''; ?></textarea>
                    </div>

                    <!-- Contenido -->
                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                            Contenido
                        </label>
                        <textarea 
                            id="content" 
                            name="content" 
                            rows="12"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Escribe el contenido de la nota aquí..."
                        ><?php echo $note ? escape($note['content']) : ''; ?></textarea>
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
                                    <option value="draft" <?php echo ($note && $note['status'] === 'draft') ? 'selected' : ''; ?>>Borrador</option>
                                    <option value="published" <?php echo ($note && $note['status'] === 'published') ? 'selected' : ''; ?>>Publicado</option>
                                    <option value="archived" <?php echo ($note && $note['status'] === 'archived') ? 'selected' : ''; ?>>Archivado</option>
                                </select>
                            </div>

                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="featured" 
                                    name="featured" 
                                    value="1"
                                    <?php echo ($note && $note['featured']) ? 'checked' : ''; ?>
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
                                <?php echo $note ? 'Actualizar' : 'Crear'; ?> Nota
                            </button>
                        </div>
                    </div>

                    <!-- Media -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Medios</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Media
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="media_type" value="image" class="text-accent focus:ring-accent" 
                                               <?php echo (!$note || $note['media_type'] === 'image') ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Imagen</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="media_type" value="video" class="text-accent focus:ring-accent"
                                               <?php echo ($note && $note['media_type'] === 'video') ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">Video</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="media_type" value="youtube" class="text-accent focus:ring-accent"
                                               <?php echo ($note && $note['media_type'] === 'youtube') ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm">YouTube</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Upload de archivo -->
                            <div id="file-upload" class="media-option">
                                <label for="media_file" class="block text-sm font-medium text-gray-700 mb-2">
                                    Subir Archivo
                                </label>
                                <input 
                                    type="file" 
                                    id="media_file" 
                                    name="media_file"
                                    accept="image/*,video/*"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                                >
                                <p class="text-xs text-gray-500 mt-1">
                                    Máximo 10MB. Formatos: JPG, PNG, GIF, MP4, MOV
                                </p>
                            </div>

                            <!-- URL de YouTube -->
                            <div id="youtube-url" class="media-option hidden">
                                <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-2">
                                    URL de YouTube
                                </label>
                                <input 
                                    type="url" 
                                    id="youtube_url" 
                                    name="youtube_url"
                                    value="<?php echo ($note && $note['media_type'] === 'youtube') ? escape($note['media_url']) : ''; ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                >
                            </div>

                            <!-- Vista previa del media actual -->
                            <?php if ($note && $note['media_url']): ?>
                                <div class="border border-gray-200 rounded p-3">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Media actual:</p>
                                    <?php if ($note['media_type'] === 'youtube'): ?>
                                        <iframe 
                                            width="100%" 
                                            height="120" 
                                            src="https://www.youtube.com/embed/<?php echo extractYouTubeId($note['media_url']); ?>" 
                                            frameborder="0" 
                                            allowfullscreen
                                            class="rounded"
                                        ></iframe>
                                    <?php elseif ($note['media_type'] === 'video'): ?>
                                        <video controls class="w-full rounded max-h-32">
                                            <source src="uploads/<?php echo escape($note['media_url']); ?>" type="video/mp4">
                                        </video>
                                    <?php else: ?>
                                        <img src="uploads/<?php echo escape($note['media_url']); ?>" alt="Media" class="w-full rounded max-h-32 object-cover">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- URL Externa -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">URL Externa</h3>
                        <div>
                            <label for="external_url" class="block text-sm font-medium text-gray-700 mb-2">
                                Enlace a artículo original
                            </label>
                            <input 
                                type="url" 
                                id="external_url" 
                                name="external_url"
                                value="<?php echo $note ? escape($note['external_url']) : ''; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                                placeholder="https://ejemplo.com/articulo"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Inicializar CKEditor
        ClassicEditor
            .create(document.querySelector('#content'), {
                language: 'es',
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', '|', 'undo', 'redo']
            })
            .catch(error => {
                console.error(error);
            });

        // Cambiar tipo de media
        document.querySelectorAll('input[name="media_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.media-option').forEach(option => {
                    option.classList.add('hidden');
                });
                
                if (this.value === 'youtube') {
                    document.getElementById('youtube-url').classList.remove('hidden');
                } else {
                    document.getElementById('file-upload').classList.remove('hidden');
                }
            });
        });

        // Configurar visibilidad inicial según el tipo seleccionado
        document.addEventListener('DOMContentLoaded', function() {
            const selectedType = document.querySelector('input[name="media_type"]:checked').value;
            document.querySelectorAll('.media-option').forEach(option => {
                option.classList.add('hidden');
            });
            
            if (selectedType === 'youtube') {
                document.getElementById('youtube-url').classList.remove('hidden');
            } else {
                document.getElementById('file-upload').classList.remove('hidden');
            }
        });
    </script>
</body>
</html>