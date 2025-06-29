<?php
// videos.php - Lista y gestión de videos destacados
require_once 'config/config.php';
require_once 'config/database.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();

// Parámetros de paginación y búsqueda
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener total de videos
$count_query = "SELECT COUNT(*) as total FROM featured_videos $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_count / $per_page);

// Obtener videos con paginación
$videos_query = "SELECT * FROM featured_videos 
                $where_clause
                ORDER BY order_position ASC, created_at DESC 
                LIMIT :limit OFFSET :offset";
$videos_stmt = $db->prepare($videos_query);
foreach ($params as $key => $value) {
    $videos_stmt->bindValue($key, $value);
}
$videos_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$videos_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$videos_stmt->execute();
$videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videos Destacados - Admin</title>
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
                    <span class="text-gray-300">Videos Destacados</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="video-form.php" class="bg-accent hover:bg-opacity-90 px-4 py-2 rounded text-sm">
                        + Nuevo Video
                    </a>
                    <a href="logout.php" class="hover:text-accent">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Filtros y búsqueda -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="GET" class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <div class="relative">
                        <input
                            type="text"
                            name="search"
                            value="<?php echo escape($search); ?>"
                            placeholder="Buscar videos..."
                            class="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent w-full sm:w-64">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent">
                        <option value="">Todos los estados</option>
                        <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Publicado</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                        <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archivado</option>
                    </select>

                    <button type="submit" class="bg-accent text-white px-4 py-2 rounded-md hover:bg-opacity-90">
                        Filtrar
                    </button>

                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <a href="videos.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-2 text-sm text-gray-600">
                    <span>Total: <strong><?php echo $total_count; ?></strong> videos</span>
                </div>
            </form>
        </div>

        <!-- Lista de videos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        <?php echo !empty($search) ? "Resultados para: \"$search\"" : "Todos los Videos"; ?>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="selectAll()" class="text-sm text-accent hover:underline">Seleccionar todo</button>
                        <span class="text-gray-300">|</span>
                        <button onclick="bulkDelete()" class="text-sm text-red-600 hover:underline">Eliminar seleccionados</button>
                    </div>
                </div>
            </div>

            <?php if (empty($videos)): ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">
                        <?php echo !empty($search) ? 'No se encontraron resultados' : 'No hay videos'; ?>
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo !empty($search) ? 'Intenta con otros términos de búsqueda' : 'Comienza creando tu primer video destacado'; ?>
                    </p>
                    <div class="mt-6">
                        <a href="video-form.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-accent hover:bg-accent/90">
                            + Nuevo Video
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-accent focus:ring-accent">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Video
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Orden
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($videos as $video): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="selected_videos[]" value="<?php echo $video['id']; ?>" class="rounded border-gray-300 text-accent focus:ring-accent">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-16 w-24 mr-4">
                                                <img src="https://img.youtube.com/vi/<?php echo $video['youtube_id']; ?>/mqdefault.jpg" alt="" class="h-16 w-24 rounded object-cover">
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo escape($video['title']); ?>
                                                    <?php if ($video['featured']): ?>
                                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            ★ Destacado
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    ID: <?php echo $video['youtube_id']; ?>
                                                </div>
                                                <?php if ($video['description']): ?>
                                                    <div class="text-sm text-gray-500 mt-1">
                                                        <?php echo escape(substr($video['description'], 0, 100)) . '...'; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            echo $video['status'] === 'published' ? 'bg-green-100 text-green-800' : ($video['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                            ?>">
                                            <?php echo ucfirst($video['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            #<?php echo $video['order_position']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <?php echo date('d/m/Y', strtotime($video['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            <?php echo date('H:i', strtotime($video['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="<?php echo $video['url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                Ver
                                            </a>
                                            <a href="video-form.php?id=<?php echo $video['id']; ?>" class="text-accent hover:text-accent/80">
                                                Editar
                                            </a>
                                            <button onclick="deleteVideo(<?php echo $video['id']; ?>)" class="text-red-600 hover:text-red-800">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-8 rounded-lg shadow">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? "&search=" . urlencode($search) : ''; ?><?php echo $status_filter ? "&status=" . urlencode($status_filter) : ''; ?>"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? "&search=" . urlencode($search) : ''; ?><?php echo $status_filter ? "&status=" . urlencode($status_filter) : ''; ?>"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Siguiente
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Mostrando <span class="font-medium"><?php echo (($page - 1) * $per_page) + 1; ?></span>
                            a <span class="font-medium"><?php echo min($page * $per_page, $total_count); ?></span>
                            de <span class="font-medium"><?php echo $total_count; ?></span> resultados
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? "&search=" . urlencode($search) : ''; ?><?php echo $status_filter ? "&status=" . urlencode($status_filter) : ''; ?>"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                    <?php echo $i === $page ? 'bg-accent text-white border-accent' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Seleccionar todo
        document.getElementById('select-all').addEventListener('change', function() {
            selectAll();
        });

        function selectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('input[name="selected_videos[]"]');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Eliminar video individual
        function deleteVideo(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este video?')) {
                fetch('api/delete_video.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error al eliminar el video: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error de conexión');
                    });
            }
        }

        // Eliminar videos seleccionados
        function bulkDelete() {
            const selected = document.querySelectorAll('input[name="selected_videos[]"]:checked');

            if (selected.length === 0) {
                alert('Selecciona al menos un video para eliminar');
                return;
            }

            if (confirm(`¿Estás seguro de que quieres eliminar ${selected.length} video(s)?`)) {
                const ids = Array.from(selected).map(cb => cb.value);

                Promise.all(ids.map(id =>
                        fetch('api/delete_video.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: id
                            })
                        })
                    ))
                    .then(responses => Promise.all(responses.map(r => r.json())))
                    .then(results => {
                        const errors = results.filter(r => !r.success);
                        if (errors.length === 0) {
                            location.reload();
                        } else {
                            alert(`${errors.length} video(s) no pudieron ser eliminados`);
                            location.reload();
                        }
                    })
                    .catch(error => {
                        alert('Error de conexión');
                    });
            }
        }
    </script>
</body>
</html>