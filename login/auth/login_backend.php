<?php
require '../config/db.php';

header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
        exit;
    }

    try {
        // Buscar el usuario por nombre de usuario o email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_name = ? OR email = ?");
        $stmt->execute([$username_or_email, $username_or_email]);
        $user = $stmt->fetch();

        if ($user) {
            // Verificar contraseña
            if (password_verify($password, $user['password_hash'])) {
                
                // VERIFICAR SI LA CUENTA ESTÁ ACTIVA
                if ($user['user_active'] == 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Tu cuenta aún no ha sido activada. Por favor, revisa tu correo electrónico.']);
                    exit;
                }

                // Iniciar sesión
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['name'] = $user['name'];
                
                echo json_encode(['status' => 'success', 'message' => 'Inicio de sesión exitoso.', 'redirect' => 'finanzas/index.php']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'El usuario no existe.']);
        }
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
}
?>
