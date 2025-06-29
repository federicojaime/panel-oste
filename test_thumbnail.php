<?php
// test_thumbnail.php - Script simple para probar thumbnails
require_once 'config/config.php';

echo "<h1>Test de Thumbnails</h1>";

// 1. Verificar FFmpeg
echo "<h2>1. Verificar FFmpeg</h2>";
$ffmpegTest = shell_exec('ffmpeg -version 2>&1');
if (strpos($ffmpegTest, 'ffmpeg version') !== false) {
    echo "✅ FFmpeg disponible<br>";
    echo "<details><summary>Ver versión</summary><pre>" . htmlspecialchars(substr($ffmpegTest, 0, 500)) . "</pre></details>";
} else {
    echo "❌ FFmpeg NO disponible<br>";
    echo "Output: <pre>" . htmlspecialchars($ffmpegTest) . "</pre>";
}

// 2. Verificar directorios
echo "<h2>2. Verificar Directorios</h2>";
$uploadDir = UPLOAD_DIR;
$thumbnailDir = UPLOAD_DIR . 'thumbnails/';

echo "Upload dir: " . (is_dir($uploadDir) ? '✅' : '❌') . " $uploadDir<br>";
echo "Thumbnail dir: " . (is_dir($thumbnailDir) ? '✅' : '❌') . " $thumbnailDir<br>";

if (!is_dir($thumbnailDir)) {
    if (mkdir($thumbnailDir, 0755, true)) {
        echo "✅ Directorio thumbnails creado<br>";
    } else {
        echo "❌ No se pudo crear directorio thumbnails<br>";
    }
}

// 3. Probar generación SVG
echo "<h2>3. Probar SVG Placeholder</h2>";
$testId = 'test_' . time();
$svgThumb = generateSVGPlaceholder($testId);
if ($svgThumb) {
    echo "✅ SVG generado: $svgThumb<br>";
    $svgPath = $thumbnailDir . $svgThumb;
    if (file_exists($svgPath)) {
        echo "✅ Archivo SVG existe (" . filesize($svgPath) . " bytes)<br>";
        echo "<img src='uploads/thumbnails/$svgThumb' width='200' style='border:1px solid #ccc;'><br>";
    }
}

// 4. Buscar videos existentes
echo "<h2>4. Videos Existentes</h2>";
if (is_dir($uploadDir)) {
    $videos = glob($uploadDir . '*.{mp4,mov,avi,mkv,webm}', GLOB_BRACE);
    if (empty($videos)) {
        echo "❌ No hay videos en $uploadDir<br>";
        echo "<strong>Sube un video desde el admin para probar</strong><br>";
    } else {
        echo "✅ Videos encontrados:<br>";
        foreach ($videos as $video) {
            $basename = basename($video);
            $size = round(filesize($video) / 1024 / 1024, 2);
            echo "- $basename ({$size}MB)<br>";
            
            // Intentar generar thumbnail
            if (strpos($ffmpegTest, 'ffmpeg version') !== false) {
                echo "  🔄 Generando thumbnail...<br>";
                $thumb = generateThumbnailSimple($video, 'test_' . time());
                if ($thumb) {
                    echo "  ✅ Thumbnail: $thumb<br>";
                    if (strpos($thumb, '.svg') === false) {
                        echo "  <img src='uploads/thumbnails/$thumb' width='200' style='border:1px solid #ccc;'><br>";
                    }
                } else {
                    echo "  ❌ Error generando thumbnail<br>";
                }
            }
        }
    }
}

echo "<br><hr>";
echo "<a href='dashboard.php'>← Volver al admin</a>";
?>