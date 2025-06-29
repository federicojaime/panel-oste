<?php
// press.php - Lista y gestión de notas de prensa
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
    $where_conditions[] = "(title LIKE :search OR source LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener total de artículos
$count_query = "SELECT COUNT(*) as total FROM press_articles $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_count / $per_page);

// Obtener artículos con paginación
$articles_query = "SELECT * FROM press_articles 
                  $where_clause
                  ORDER BY order_position ASC, published_date DESC 
                  LIMIT :limit OFFSET :offset";
$articles_stmt = $db->prepare($articles_query);
foreach ($params as $key => $value) {
    $articles_stmt->bindValue($key, $value);
}
$articles_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$articles_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$articles_stmt->execute();
$articles = $articles_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas de Prensa - Admin</title>
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
                    <span class="text-gray-300">Notas de Prensa</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="press-form.php" class="bg-accent hover:bg-opacity-90 px-4 py-2 rounded text-sm">
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
                            placeholder="Buscar notas de prensa..."
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
                        <a href="press.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-2 text-sm text-gray-600">
                    <span>Total: <strong><?php echo $total_count; ?></strong> notas</span>
                </div>
            </form>
        </div>

        <!-- Lista de artículos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        <?php echo !empty($search) ? "Resultados para: \"$search\"" : "Todas las Notas de Prensa"; ?>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="selectAll()" class="text-sm text-accent hover:underline">Seleccionar todo</button>
                        <span class="text-gray-300">|</span>
                        <button onclick="bulkDelete()" class="text-sm text-red-600 hover:underline">Eliminar seleccionados</button>
                    </div>
                </div>
            </div>

            <?php if (empty($articles)): ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">
                        <?php echo !empty($search) ? 'No se encontraron resultados' : 'No hay notas de prensa'; ?>
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo !empty($search) ? 'Intenta con otros términos de búsqueda' : 'Comienza creando tu primera nota de prensa'; ?>
                    </p>
                    <div class="mt-6">
                        <a href="press-form.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-accent hover:bg-accent/90">
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
                                    Artículo
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fuente
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
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
                            <?php foreach ($articles as $article): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="selected_articles[]" value="<?php echo $article['id']; ?>" class="rounded border-gray-300 text-accent focus:ring-accent">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 mr-4">
                                                <div class="h-10 w-10 bg-blue-100 rounded flex items-center justify-center">
                                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo escape($article['title']); ?>
                                                    <?php if ($article['featured']): ?>
                                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            ★ Destacado
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($article['description']): ?>
                                                    <div class="text-sm text-gray-500 mt-1">
                                                        <?php echo escape(substr($article['description'], 0, 100)) . '...'; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo escape($article['source']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            echo $article['status'] === 'published' ? 'bg-green-100 text-green-800' : ($article['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                            ?>">
                                            <?php echo ucfirst($article['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <?php echo date('d/m/Y', strtotime($article['published_date'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Orden: #<?php echo $article['order_position']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="<?php echo $article['url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                Ver
                                            </a>
                                            <a href="press-form.php?id=<?php echo $article['id']; ?>" class="text-accent hover:text-accent/80">
                                                Editar
                                            </a>
                                            <button onclick="deleteArticle(<?php echo $article['id']; ?>)" class="text-red-600 hover:text-red-800">
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
            const checkboxes = document.querySelectorAll('input[name="selected_articles[]"]');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Eliminar artículo individual
        function deleteArticle(id) {
            if (confirm('¿Estás seguro de que quieres eliminar esta nota de prensa?')) {
                fetch('api/delete_press.php', {
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

        // Eliminar artículos seleccionados
        function bulkDelete() {
            const selected = document.querySelectorAll('input[name="selected_articles[]"]:checked');

            if (selected.length === 0) {
                alert('Selecciona al menos una nota para eliminar');
                return;
            }

            if (confirm(`¿Estás seguro de que quieres eliminar ${selected.length} nota(s) de prensa?`)) {
                const ids = Array.from(selected).map(cb => cb.value);

                Promise.all(ids.map(id =>
                        fetch('api/delete_press.php', {
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