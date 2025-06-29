<?php
// notes.php - Lista y gestión de notas
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
    $where_conditions[] = "(title LIKE :search OR content LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener total de notas
$count_query = "SELECT COUNT(*) as total FROM literary_notes $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_count / $per_page);

// Obtener notas con paginación
$notes_query = "SELECT * FROM literary_notes 
                $where_clause
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
$notes_stmt = $db->prepare($notes_query);
foreach ($params as $key => $value) {
    $notes_stmt->bindValue($key, $value);
}
$notes_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$notes_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$notes_stmt->execute();
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas Literarias - Admin</title>
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
                    <span class="text-gray-300">Notas Literarias</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="note-form.php" class="bg-accent hover:bg-opacity-90 px-4 py-2 rounded text-sm">
                        + Nueva Nota
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
                            placeholder="Buscar notas..."
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
                        <a href="notes.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-2 text-sm text-gray-600">
                    <span>Total: <strong><?php echo $total_count; ?></strong> notas</span>
                </div>
            </form>
        </div>

        <!-- Lista de notas -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        <?php echo !empty($search) ? "Resultados para: \"$search\"" : "Todas las Notas"; ?>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="selectAll()" class="text-sm text-accent hover:underline">Seleccionar todo</button>
                        <span class="text-gray-300">|</span>
                        <button onclick="bulkDelete()" class="text-sm text-red-600 hover:underline">Eliminar seleccionados</button>
                    </div>
                </div>
            </div>

            <?php if (empty($notes)): ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">
                        <?php echo !empty($search) ? 'No se encontraron resultados' : 'No hay notas'; ?>
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo !empty($search) ? 'Intenta con otros términos de búsqueda' : 'Comienza creando tu primera nota literaria'; ?>
                    </p>
                    <div class="mt-6">
                        <a href="note-form.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-accent hover:bg-accent/90">
                            + Nueva Nota
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
                                    Nota
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Media
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
                            <?php foreach ($notes as $note): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="selected_notes[]" value="<?php echo $note['id']; ?>" class="rounded border-gray-300 text-accent focus:ring-accent">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <?php if ($note['media_url']): ?>
                                                <div class="flex-shrink-0 h-12 w-12 mr-4">
                                                    <?php if ($note['media_type'] === 'youtube'): ?>
                                                        <img src="https://img.youtube.com/vi/<?php echo extractYouTubeId($note['media_url']); ?>/mqdefault.jpg" alt="" class="h-12 w-12 rounded object-cover">
                                                    <?php elseif ($note['media_type'] === 'video'): ?>
                                                        <div class="h-12 w-12 bg-red-100 rounded flex items-center justify-center">
                                                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        </div>
                                                    <?php else: ?>
                                                        <img src="uploads/<?php echo escape($note['media_url']); ?>" alt="" class="h-12 w-12 rounded object-cover">
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex-shrink-0 h-12 w-12 mr-4 bg-gray-100 rounded flex items-center justify-center">
                                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo escape($note['title']); ?>
                                                    <?php if ($note['featured']): ?>
                                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            ★ Destacado
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo $note['excerpt'] ? escape(substr($note['excerpt'], 0, 100)) . '...' : 'Sin extracto'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            echo $note['status'] === 'published' ? 'bg-green-100 text-green-800' : ($note['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                            ?>">
                                            <?php echo ucfirst($note['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($note['media_type']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                                                <?php
                                                echo $note['media_type'] === 'image' ? 'bg-blue-100 text-blue-800' : ($note['media_type'] === 'video' ? 'bg-red-100 text-red-800' : 'bg-purple-100 text-purple-800');
                                                ?>">
                                                <?php echo ucfirst($note['media_type']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">Sin media</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <?php echo date('d/m/Y', strtotime($note['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            <?php echo date('H:i', strtotime($note['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="note-form.php?id=<?php echo $note['id']; ?>" class="text-accent hover:text-accent/80">
                                                Editar
                                            </a>
                                            <button onclick="deleteNote(<?php echo $note['id']; ?>)" class="text-red-600 hover:text-red-800">
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
            const checkboxes = document.querySelectorAll('input[name="selected_notes[]"]');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Eliminar nota individual
        function deleteNote(id) {
            if (confirm('¿Estás seguro de que quieres eliminar esta nota?')) {
                fetch('api/delete_note.php', {
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
                            alert('Error al eliminar la nota: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error de conexión');
                    });
            }
        }

        // Eliminar notas seleccionadas
        function bulkDelete() {
            const selected = document.querySelectorAll('input[name="selected_notes[]"]:checked');

            if (selected.length === 0) {
                alert('Selecciona al menos una nota para eliminar');
                return;
            }

            if (confirm(`¿Estás seguro de que quieres eliminar ${selected.length} nota(s)?`)) {
                const ids = Array.from(selected).map(cb => cb.value);

                Promise.all(ids.map(id =>
                        fetch('api/delete_note.php', {
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
                            alert(`${errors.length} nota(s) no pudieron ser eliminadas`);
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