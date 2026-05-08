<?php
require 'config/db.php';

$message = "";
$status = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Buscar el token en la base de datos
        $stmt = $pdo->prepare("SELECT id_user FROM users WHERE token_activation = ? AND user_active = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Activar la cuenta y limpiar el token
            $stmt = $pdo->prepare("UPDATE users SET user_active = 1, token_activation = NULL WHERE id_user = ?");
            $stmt->execute([$user['id_user']]);
            $status = "success";
            $message = "¡Cuenta activada con éxito! Ahora puedes iniciar sesión.";
        } else {
            $status = "error";
            $message = "El enlace de activación no es válido o la cuenta ya ha sido activada.";
        }
    } catch (\PDOException $e) {
        $status = "error";
        $message = "Error en el servidor: " . $e->getMessage();
    }
} else {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activación de Cuenta</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #060910;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #151f35;
            padding: 40px;
            border-radius: 24px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            text-align: center;
            max-width: 400px;
            box-shadow: 0 0 60px rgba(59, 130, 246, 0.08);
        }
        h1 {
            background: linear-gradient(135deg, #60a5fa, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
        }
        .error { color: #f87171; }
        .success { color: #4ade80; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Activación</h1>
        <p class="<?php echo $status; ?>"><?php echo $message; ?></p>
        <a href="login.php" class="btn">Ir al inicio de sesión</a>
    </div>
</body>
</html>
