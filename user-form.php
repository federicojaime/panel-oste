<?php
// user-form.php - Formulario para crear y editar usuarios
require_once 'config/config.php';
require_once 'config/database.php';

requireAuth();

// Solo admin puede gestionar usuarios
if ($_SESSION['admin_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_GET['id'] ?? null;
$user = null;
$success_message = '';
$error_message = '';

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Token de seguridad inválido';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validaciones básicas
        if (empty($username) || empty($email) || empty($role)) {
            $error_message = 'Todos los campos obligatorios deben completarse';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'El email no tiene un formato válido';
        } elseif (!$user_id && empty($password)) {
            $error_message = 'La contraseña es requerida para nuevos usuarios';
        } elseif (!empty($password) && $password !== $confirm_password) {
            $error_message = 'Las contraseñas no coinciden';
        } elseif (!empty($password) && strlen($password) < 6) {
            $error_message = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            try {
                if ($user_id) {
                    // Verificar que el username/email no estén en uso por otro usuario
                    $check_query = "SELECT id FROM admin_users WHERE (username = :username OR email = :email) AND id != :user_id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':username', $username);
                    $check_stmt->bindParam(':email', $email);
                    $check_stmt->bindParam(':user_id', $user_id);
                    $check_stmt->execute();

                    if ($check_stmt->rowCount() > 0) {
                        $error_message = 'El usuario o email ya está en uso';
                    } else {
                        // Actualizar usuario existente
                        if (!empty($password)) {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $query = "UPDATE admin_users SET 
                                     username = :username, 
                                     email = :email, 
                                     role = :role,
                                     password_hash = :password_hash
                                     WHERE id = :id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':password_hash', $password_hash);
                        } else {
                            $query = "UPDATE admin_users SET 
                                     username = :username, 
                                     email = :email, 
                                     role = :role
                                     WHERE id = :id";
                            $stmt = $db->prepare($query);
                        }

                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':role', $role);
                        $stmt->bindParam(':id', $user_id);

                        if ($stmt->execute()) {
                            $success_message = 'Usuario actualizado correctamente';
                        }
                    }
                } else {
                    // Verificar que el username/email no estén en uso
                    $check_query = "SELECT id FROM admin_users WHERE username = :username OR email = :email";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':username', $username);
                    $check_stmt->bindParam(':email', $email);
                    $check_stmt->execute();

                    if ($check_stmt->rowCount() > 0) {
                        $error_message = 'El usuario o email ya está en uso';
                    } else {
                        // Crear nuevo usuario
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $query = "INSERT INTO admin_users (username, email, role, password_hash, created_at) 
                                 VALUES (:username, :email, :role, :password_hash, NOW())";

                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':role', $role);
                        $stmt->bindParam(':password_hash', $password_hash);

                        if ($stmt->execute()) {
                            $user_id = $db->lastInsertId();
                            $success_message = 'Usuario creado correctamente';
                        }
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Error al guardar el usuario: ' . $e->getMessage();
            }
        }
    }
}

// Cargar usuario para edición
if ($user_id) {
    $query = "SELECT * FROM admin_users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: users.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user ? 'Editar' : 'Nuevo'; ?> Usuario - Admin</title>
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
                    <span class="text-gray-300"><?php echo $user ? 'Editar' : 'Nuevo'; ?> Usuario</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="users.php" class="hover:text-accent">← Volver a usuarios</a>
                    <a href="logout.php" class="bg-accent hover:bg-opacity-90 px-3 py-1 rounded text-sm">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto py-8 px-4">
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

        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    <?php echo $user ? 'Editar Usuario' : 'Crear Nuevo Usuario'; ?>
                </h2>
                <p class="text-gray-600 mt-2">
                    <?php echo $user ? 'Modifica la información del usuario' : 'Completa los datos para crear un nuevo usuario'; ?>
                </p>
            </div>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Información básica -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de Usuario *
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            value="<?php echo $user ? escape($user['username']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="Ingresa el nombre de usuario">
                        <p class="text-xs text-gray-500 mt-1">Solo letras, números y guiones bajos</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email *
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            value="<?php echo $user ? escape($user['email']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                            placeholder="usuario@ejemplo.com">
                    </div>
                </div>

                <!-- Rol -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        Rol *
                    </label>
                    <select
                        id="role"
                        name="role"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent">
                        <option value="">Selecciona un rol</option>
                        <option value="admin" <?php echo ($user && $user['role'] === 'admin') ? 'selected' : ''; ?>>
                            Administrador - Acceso completo
                        </option>
                        <option value="editor" <?php echo ($user && $user['role'] === 'editor') ? 'selected' : ''; ?>>
                            Editor - Solo gestión de contenido
                        </option>
                    </select>
                    <div class="mt-2 text-sm text-gray-600">
                        <p><strong>Administrador:</strong> Puede gestionar usuarios, notas y configuración</p>
                        <p><strong>Editor:</strong> Solo puede crear y editar notas literarias</p>
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <?php echo $user ? 'Cambiar Contraseña (opcional)' : 'Contraseña'; ?>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $user ? 'Nueva Contraseña' : 'Contraseña *'; ?>
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                <?php echo !$user ? 'required' : ''; ?>
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                                placeholder="<?php echo $user ? 'Dejar vacío para mantener actual' : 'Mínimo 6 caracteres'; ?>">
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirmar Contraseña <?php echo !$user ? '*' : ''; ?>
                            </label>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                <?php echo !$user ? 'required' : ''; ?>
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                                placeholder="Repite la contraseña">
                        </div>
                    </div>

                    <?php if ($user): ?>
                        <p class="text-sm text-gray-500 mt-2">
                            ⚠️ Deja los campos de contraseña vacíos si no quieres cambiarla
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Información adicional si es edición -->
                <?php if ($user): ?>
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Usuario</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div>
                                <label class="block text-gray-600 font-medium">Creado:</label>
                                <p class="text-gray-900"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                            </div>
                            <div>
                                <label class="block text-gray-600 font-medium">Último acceso:</label>
                                <p class="text-gray-900">
                                    <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="border-t border-gray-200 pt-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            * Campos obligatorios
                        </div>
                        <div class="flex space-x-3">
                            <a
                                href="users.php"
                                class="bg-gray-500 text-white py-2 px-4 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Cancelar
                            </a>
                            <button
                                type="submit"
                                class="bg-accent text-white py-2 px-6 rounded-md hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-accent">
                                <?php echo $user ? 'Actualizar Usuario' : 'Crear Usuario'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Validación de contraseñas en tiempo real
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (password !== confirmPassword && confirmPassword !== '') {
                this.setCustomValidity('Las contraseñas no coinciden');
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
        });

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }

            <?php if (!$user): ?>
                if (password.length < 6) {
                    e.preventDefault();
                    alert('La contraseña debe tener al menos 6 caracteres');
                    return false;
                }
            <?php endif; ?>
        });

        // Auto-focus en el primer campo
        document.getElementById('username').focus();
    </script>
</body>

</html>