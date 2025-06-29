<?php
// api/delete_user.php - Eliminar usuario
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';

requireAuth();

// Solo admin puede eliminar usuarios
if ($_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
    exit();
}

// No permitir que se elimine a sí mismo
if ($user_id == $_SESSION['admin_id']) {
    echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propio usuario']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el usuario existe
    $check_query = "SELECT username FROM admin_users WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $user_id);
    $check_stmt->execute();
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }

    // Contar cuántos admins quedan
    $admin_count_query = "SELECT COUNT(*) as count FROM admin_users WHERE role = 'admin' AND id != :id";
    $admin_count_stmt = $db->prepare($admin_count_query);
    $admin_count_stmt->bindParam(':id', $user_id);
    $admin_count_stmt->execute();
    $admin_count = $admin_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Verificar si se está eliminando el último admin
    $user_role_query = "SELECT role FROM admin_users WHERE id = :id";
    $user_role_stmt = $db->prepare($user_role_query);
    $user_role_stmt->bindParam(':id', $user_id);
    $user_role_stmt->execute();
    $user_role = $user_role_stmt->fetch(PDO::FETCH_ASSOC)['role'];

    if ($user_role === 'admin' && $admin_count == 0) {
        echo json_encode(['success' => false, 'message' => 'No puedes eliminar el último administrador']);
        exit();
    }

    // Eliminar usuario de la base de datos
    $delete_query = "DELETE FROM admin_users WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $user_id);

    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Usuario "' . $user['username'] . '" eliminado correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
