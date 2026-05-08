<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validaciones básicas
    if (empty($name) || empty($lastname) || empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Correo electrónico no válido.']);
        exit;
    }

    try {
        // Verificar si el email o usuario ya existen
        $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email = ? OR user_name = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'El correo o nombre de usuario ya está registrado.']);
            exit;
        }

        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Generar token de activación
        $token = bin2hex(random_bytes(16));

        // Insertar usuario
        $stmt = $pdo->prepare("INSERT INTO users (name, lastname, user_name, email, password_hash, user_active, token_activation) VALUES (?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([$name, $lastname, $username, $email, $password_hash, $token]);

        // Enviar correo de activación
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor Gmail SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jeffrey232008suarez@gmail.com'; // REEMPLAZAR CON TU CORREO
            $mail->Password   = 'jcpi wgmj obsx zosl'; // REEMPLAZAR CON TU CONTRASEÑA DE APLICACIÓN
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Destinatarios
            $mail->setFrom('tu_correo@gmail.com', 'Finanzas Framework');
            $mail->addAddress($email, $name);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = 'Activa tu cuenta - Finanzas Framework';
            
            $activation_link = "http://" . $_SERVER['HTTP_HOST'] . "/ProyectoPersonal/login/activate.php?token=" . $token;
            
            $mail->Body    = "Hola $name,<br><br>Gracias por registrarte. Por favor, activa tu cuenta haciendo clic en el siguiente enlace:<br><br><a href='$activation_link'>$activation_link</a><br><br>Si no te registraste, puedes ignorar este correo.";
            $mail->AltBody = "Hola $name,\n\nGracias por registrarte. Por favor, activa tu cuenta haciendo clic en el siguiente enlace:\n\n$activation_link\n\nSi no te registraste, puedes ignorar este correo.";

            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'Registro exitoso. Por favor, revisa tu correo para activar tu cuenta.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'warning', 'message' => 'Usuario registrado, pero no se pudo enviar el correo de activación. Contacta al administrador. Error: ' . $mail->ErrorInfo]);
        }

    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
}
?>
