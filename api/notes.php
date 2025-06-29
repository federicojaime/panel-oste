<?php
// api/notes.php - API para obtener notas (para el frontend React)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Obtener nota específica
                $id = $_GET['id'];
                $query = "SELECT * FROM literary_notes WHERE id = :id AND status = 'published'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $note = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($note) {
                    // Formatear media_url para el frontend
                    if ($note['media_url']) {
                        if ($note['media_type'] === 'youtube') {
                            $note['media'] = $note['media_url'];
                        } else {
                            $note['media'] = BASE_URL . 'uploads/' . $note['media_url'];
                        }
                    } else {
                        $note['media'] = null;
                    }
                    
                    // Formatear para compatibilidad con el JSON existente
                    $formatted_note = [
                        'title' => $note['title'],
                        'url' => $note['external_url'],
                        'content' => $note['content'] ?: 'Sin contenido',
                        'media' => $note['media'],
                        'video' => $note['media_type'] === 'video' ? $note['media'] : null
                    ];
                    
                    echo json_encode($formatted_note);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Nota no encontrada']);
                }
            } else {
                // Obtener todas las notas publicadas
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 20;
                $offset = ($page - 1) * $limit;
                
                $query = "SELECT * FROM literary_notes 
                         WHERE status = 'published' 
                         ORDER BY created_at DESC 
                         LIMIT :limit OFFSET :offset";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
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
                    
                    $formatted_notes[] = [
                        'title' => $note['title'],
                        'url' => $note['external_url'],
                        'content' => $note['content'] ?: 'Sin contenido',
                        'media' => $media,
                        'video' => $note['media_type'] === 'video' ? $media : null
                    ];
                }
                
                echo json_encode($formatted_notes);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

// api/delete_note.php - Eliminar nota
<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

session_start();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$note_id = $input['id'] ?? null;

if (!$note_id) {
    echo json_encode(['success' => false, 'message' => 'ID de nota requerido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar que la nota existe
    $check_query = "SELECT media_url FROM literary_notes WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $note_id);
    $check_stmt->execute();
    $note = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Nota no encontrada']);
        exit();
    }
    
    // Eliminar archivo de media si existe
    if ($note['media_url'] && file_exists('../uploads/' . $note['media_url'])) {
        unlink('../uploads/' . $note['media_url']);
    }
    
    // Eliminar nota de la base de datos
    $delete_query = "DELETE FROM literary_notes WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $note_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Nota eliminada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la nota']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

// logout.php - Cerrar sesión
<?php
require_once 'config/config.php';

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit();

// install.php - Script de instalación
<?php
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Instalación - Panel Admin Sergio Oste</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 min-h-screen flex items-center justify-center'>
    <div class='max-w-md w-full bg-white rounded-lg shadow-md p-6'>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $admin_username = $_POST['admin_username'];
    $admin_email = $_POST['admin_email'];
    $admin_password = $_POST['admin_password'];
    
    try {
        // Conectar a la base de datos
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Crear tablas
        $sql = file_get_contents('install.sql');
        $pdo->exec($sql);
        
        // Crear usuario admin
        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$admin_username, $admin_email, $password_hash]);
        
        // Crear archivo de configuración
        $config_content = "<?php
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('BASE_URL', 'http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
";
        file_put_contents('config/database_config.php', $config_content);
        
        echo "<div class='text-center'>
                <h1 class='text-2xl font-bold text-green-600 mb-4'>¡Instalación Completada!</h1>
                <p class='text-gray-600 mb-6'>El panel de administración ha sido instalado correctamente.</p>
                <div class='space-y-2'>
                    <a href='login.php' class='block w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700'>
                        Ir al Panel de Login
                    </a>
                    <p class='text-sm text-gray-500'>Usuario: $admin_username</p>
                </div>
              </div>";
        
        // Eliminar el archivo de instalación por seguridad
        unlink(__FILE__);
        
    } catch (Exception $e) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                Error: " . $e->getMessage() . "
              </div>";
    }
} else {
    echo "<form method='POST'>
            <h1 class='text-2xl font-bold text-gray-900 mb-6 text-center'>Instalación del Panel Admin</h1>
            
            <h3 class='text-lg font-medium text-gray-900 mb-4'>Configuración de Base de Datos</h3>
            <div class='space-y-4 mb-6'>
                <input type='text' name='db_host' placeholder='Host (localhost)' value='localhost' required class='w-full px-3 py-2 border rounded'>
                <input type='text' name='db_name' placeholder='Nombre de la base de datos' required class='w-full px-3 py-2 border rounded'>
                <input type='text' name='db_user' placeholder='Usuario de la base de datos' required class='w-full px-3 py-2 border rounded'>
                <input type='password' name='db_pass' placeholder='Contraseña de la base de datos' class='w-full px-3 py-2 border rounded'>
            </div>
            
            <h3 class='text-lg font-medium text-gray-900 mb-4'>Usuario Administrador</h3>
            <div class='space-y-4 mb-6'>
                <input type='text' name='admin_username' placeholder='Nombre de usuario' required class='w-full px-3 py-2 border rounded'>
                <input type='email' name='admin_email' placeholder='Email del administrador' required class='w-full px-3 py-2 border rounded'>
                <input type='password' name='admin_password' placeholder='Contraseña del administrador' required class='w-full px-3 py-2 border rounded'>
            </div>
            
            <button type='submit' class='w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700'>
                Instalar Panel Admin
            </button>
          </form>";
}

echo "</div></body></html>";
?>