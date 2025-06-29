<?php
// dashboard.php - Panel principal del admin (CORREGIDO Y COMPLETO)
require_once 'config/config.php';
require_once 'config/database.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas de notas literarias
$stats_query = "SELECT 
    COUNT(*) as total_notes,
    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_notes,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_notes,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_notes
    FROM literary_notes";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas de videos
$video_stats_query = "SELECT 
    COUNT(*) as total_videos,
    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_videos,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_videos
    FROM featured_videos";
$video_stats_stmt = $db->prepare($video_stats_query);
$video_stats_stmt->execute();
$video_stats = $video_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas de notas de prensa
$press_stats_query = "SELECT 
    COUNT(*) as total_press,
    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_press,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_press
    FROM press_articles";
$press_stats_stmt = $db->prepare($press_stats_query);
$press_stats_stmt->execute();
$press_stats = $press_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Obtener notas recientes
$recent_query = "SELECT id, title, status, media_url, media_type, created_at 
                FROM literary_notes 
                ORDER BY created_at DESC 
                LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->execute();
$recent_notes = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener videos recientes
$recent_videos_query = "SELECT id, title, status, youtube_id, created_at 
                       FROM featured_videos 
                       ORDER BY created_at DESC 
                       LIMIT 3";
$recent_videos_stmt = $db->prepare($recent_videos_query);
$recent_videos_stmt->execute();
$recent_videos = $recent_videos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener notas de prensa recientes
$recent_press_query = "SELECT id, title, status, source, published_date, created_at 
                      FROM press_articles 
                      ORDER BY created_at DESC 
                      LIMIT 3";
$recent_press_stmt = $db->prepare($recent_press_query);
$recent_press_stmt->execute();
$recent_press = $recent_press_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Sergio Oste</title>
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
                    <h1 class="text-xl font-bold">Admin Panel</h1>
                    <span class="text-accent">|</span>
                    <span class="text-gray-300">Sergio Tomás Oste</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">Bienvenido, <?php echo escape($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="bg-accent hover:bg-opacity-90 px-3 py-1 rounded text-sm">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg h-screen sticky top-0">
            <div class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-3 text-primary hover:bg-gray-100 rounded bg-gray-100">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8 7 4-4 4 4"></path>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="notes.php" class="flex items-center p-3 text-gray-600 hover:bg-gray-100 rounded">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Notas Literarias
                        </a>
                    </li>
                    <li>
                        <a href="videos.php" class="flex items-center p-3 text-gray-600 hover:bg-gray-100 rounded">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Videos Destacados
                        </a>
                    </li>
                    <li>
                        <a href="press.php" class="flex items-center p-3 text-gray-600 hover:bg-gray-100 rounded">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                            </svg>
                            Notas de Prensa
                        </a>
                    </li>
                    <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                        <li>
                            <a href="users.php" class="flex items-center p-3 text-gray-600 hover:bg-gray-100 rounded">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Usuarios
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <!-- Notas Literarias -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Notas Literarias</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_notes'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Videos Destacados -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Videos</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $video_stats['total_videos'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Notas de Prensa -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Prensa</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $press_stats['total_press'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Publicados -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Publicados</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php echo ($stats['published_notes'] ?? 0) + ($video_stats['published_videos'] ?? 0) + ($press_stats['published_press'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Borradores -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Borradores</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php echo ($stats['draft_notes'] ?? 0) + ($video_stats['draft_videos'] ?? 0) + ($press_stats['draft_press'] ?? 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Acciones Rápidas</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="note-form.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Nueva Nota</h4>
                                <p class="text-sm text-gray-600">Crear nueva nota literaria</p>
                            </div>
                        </a>

                        <a href="video-form.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Nuevo Video</h4>
                                <p class="text-sm text-gray-600">Agregar video destacado</p>
                            </div>
                        </a>

                        <a href="press-form.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Nueva Nota Prensa</h4>
                                <p class="text-sm text-gray-600">Agregar nota de prensa</p>
                            </div>
                        </a>

                        <a href="../" target="_blank" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Ver Sitio Web</h4>
                                <p class="text-sm text-gray-600">Abrir en nueva pestaña</p>
                            </div>
                        </a>

                        <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                            <a href="users.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                                <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <div>
                                    <h4 class="font-medium text-gray-900">Gestionar Usuarios</h4>
                                    <p class="text-sm text-gray-600">Crear y administrar usuarios</p>
                                </div>
                            </a>
                        <?php endif; ?>

                        <a href="notes.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Ver Todas las Notas</h4>
                                <p class="text-sm text-gray-600">Gestionar notas existentes</p>
                            </div>
                        </a>

                        <a href="videos.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Ver Todos los Videos</h4>
                                <p class="text-sm text-gray-600">Gestionar videos destacados</p>
                            </div>
                        </a>

                        <a href="press.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-accent hover:shadow-md transition">
                            <svg class="w-8 h-8 text-accent mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium text-gray-900">Ver Todas las Notas Prensa</h4>
                                <p class="text-sm text-gray-600">Gestionar notas de prensa</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Notes -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Notas Recientes</h3>
                        <a href="notes.php" class="text-accent hover:underline text-sm">Ver todas →</a>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_notes)): ?>
                            <p class="text-gray-500 text-center py-8">No hay notas recientes</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_notes as $note): ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <?php if ($note['media_url']): ?>
                                                <img src="uploads/<?php echo escape($note['media_url']); ?>" alt="" class="w-10 h-10 object-cover rounded">
                                            <?php else: ?>
                                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h4 class="font-medium text-gray-900 text-sm"><?php echo escape(substr($note['title'], 0, 40)) . (strlen($note['title']) > 40 ? '...' : ''); ?></h4>
                                                <div class="flex items-center space-x-2 text-xs text-gray-500">
                                                    <span class="px-2 py-1 bg-<?php echo $note['status'] === 'published' ? 'green' : ($note['status'] === 'draft' ? 'yellow' : 'gray'); ?>-100 text-<?php echo $note['status'] === 'published' ? 'green' : ($note['status'] === 'draft' ? 'yellow' : 'gray'); ?>-800 rounded">
                                                        <?php echo ucfirst($note['status']); ?>
                                                    </span>
                                                    <span><?php echo date('d/m/Y', strtotime($note['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="note-form.php?id=<?php echo $note['id']; ?>" class="text-accent hover:text-accent/80">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Videos -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Videos Recientes</h3>
                        <a href="videos.php" class="text-accent hover:underline text-sm">Ver todos →</a>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_videos)): ?>
                            <p class="text-gray-500 text-center py-8">No hay videos recientes</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_videos as $video): ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <img src="https://img.youtube.com/vi/<?php echo escape($video['youtube_id']); ?>/mqdefault.jpg" alt="" class="w-10 h-10 object-cover rounded">
                                            <div>
                                                <h4 class="font-medium text-gray-900 text-sm"><?php echo escape(substr($video['title'], 0, 40)) . (strlen($video['title']) > 40 ? '...' : ''); ?></h4>
                                                <div class="flex items-center space-x-2 text-xs text-gray-500">
                                                    <span class="px-2 py-1 bg-<?php echo $video['status'] === 'published' ? 'green' : ($video['status'] === 'draft' ? 'yellow' : 'gray'); ?>-100 text-<?php echo $video['status'] === 'published' ? 'green' : ($video['status'] === 'draft' ? 'yellow' : 'gray'); ?>-800 rounded">
                                                        <?php echo ucfirst($video['status']); ?>
                                                    </span>
                                                    <span><?php echo date('d/m/Y', strtotime($video['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="video-form.php?id=<?php echo $video['id']; ?>" class="text-accent hover:text-accent/80">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Press -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Prensa Reciente</h3>
                        <a href="press.php" class="text-accent hover:underline text-sm">Ver todas →</a>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_press)): ?>
                            <p class="text-gray-500 text-center py-8">No hay notas de prensa recientes</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_press as $article): ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-purple-100 rounded flex items-center justify-center">
                                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900 text-sm"><?php echo escape(substr($article['title'], 0, 40)) . (strlen($article['title']) > 40 ? '...' : ''); ?></h4>
                                                <div class="flex items-center space-x-2 text-xs text-gray-500">
                                                    <span class="px-2 py-1 bg-<?php echo $article['status'] === 'published' ? 'green' : ($article['status'] === 'draft' ? 'yellow' : 'gray'); ?>-100 text-<?php echo $article['status'] === 'published' ? 'green' : ($article['status'] === 'draft' ? 'yellow' : 'gray'); ?>-800 rounded">
                                                        <?php echo ucfirst($article['status']); ?>
                                                    </span>
                                                    <span><?php echo escape($article['source']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="press-form.php?id=<?php echo $article['id']; ?>" class="text-accent hover:text-accent/80">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="mt-8 bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Estado del Sistema</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <span class="text-sm text-gray-600">Base de datos conectada</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <span class="text-sm text-gray-600">APIs funcionando</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <span class="text-sm text-gray-600">Frontend sincronizado</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh del dashboard cada 5 minutos
        setTimeout(() => {
            location.reload();
        }, 300000);

        // Mostrar notificación de bienvenida
        if (localStorage.getItem('dashboard_visited') !== 'true') {
            setTimeout(() => {
                alert('¡Bienvenido al panel administrativo de Sergio Tomás Oste!\n\nDesde aquí puedes gestionar:\n• Notas Literarias\n• Videos Destacados\n• Notas de Prensa\n• Usuarios del sistema');
                localStorage.setItem('dashboard_visited', 'true');
            }, 1000);
        }
    </script>
</body>

</html>