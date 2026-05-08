<?php
session_start();
require_once "../../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Helper to format money in response messages
function formatMoney($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}

try {
    switch ($action) {
        case 'add_movement':
            $type = $_POST['type'] ?? '';
            $category = $_POST['category'] ?? '';
            $description = $_POST['description'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $date = $_POST['date'] ?? date('Y-m-d');
            if (empty($type) || empty($category) || empty($amount)) {
                echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO movements (type, category, description, amount, created_at, id_user) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $category, $description, $amount, $date . ' ' . date('H:i:s'), $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Movimiento agregado correctamente.']);
            break;

        case 'add_save':
            $amount = $_POST['amount'] ?? 0;
            $date = $_POST['date'] ?? date('Y-m-d');
            if (empty($amount)) {
                echo json_encode(['status' => 'error', 'message' => 'El monto es obligatorio.']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO save (amount, created_at, id_user) VALUES (?, ?, ?)");
            $stmt->execute([$amount, $date . ' ' . date('H:i:s'), $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Ahorro agregado correctamente.']);
            break;

        case 'add_necessary':
            $type = $_POST['type'] ?? 'Gasto';
            $category = $_POST['category'] ?? '';
            $description = $_POST['description'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $state = $_POST['state'] ?? 'Pendiente';
            $date = $_POST['date'] ?? date('Y-m-d');
            if (empty($category) || empty($amount)) {
                echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO necessary_expense (type, category, description, amount, state, created_at, id_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $category, $description, $amount, $state, $date . ' ' . date('H:i:s'), $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Gasto necesario agregado correctamente.']);
            break;

        case 'add_debt':
            $concept = $_POST['concept'] ?? '';
            $type_debt = $_POST['type_debt'] ?? '';
            $description = $_POST['description'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $state = $_POST['state'] ?? 'Pendiente';
            $date = $_POST['date'] ?? date('Y-m-d');
            if (empty($concept) || empty($type_debt) || empty($amount)) {
                echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO debts (concept, type_debt, description, amount, state, created_at, id_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$concept, $type_debt, $description, $amount, $state, $date . ' ' . date('H:i:s'), $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Deuda agregada correctamente.']);
            break;

        case 'add_payment':
            $id_debt = $_POST['id_debt'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $method = $_POST['method'] ?? '';
            $description = $_POST['description'] ?? '';
            $date = $_POST['date'] ?? date('Y-m-d');
            if (empty($id_debt) || empty($amount) || empty($method)) {
                echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT amount FROM debts WHERE id_debt = ?");
            $stmt->execute([$id_debt]);
            $current_debt_amount = $stmt->fetchColumn();
            if ($amount > $current_debt_amount) {
                echo json_encode(['status' => 'error', 'message' => 'El abono no puede ser mayor a la deuda pendiente (' . formatMoney($current_debt_amount) . ').']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO pay_debt (amount, method, description, id_debt, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$amount, $method, $description, $id_debt, $date . ' ' . date('H:i:s')]);
            $stmt = $pdo->prepare("UPDATE debts SET amount = amount - ? WHERE id_debt = ?");
            $stmt->execute([$amount, $id_debt]);
            $stmt = $pdo->prepare("SELECT amount FROM debts WHERE id_debt = ?");
            $stmt->execute([$id_debt]);
            $new_amount = $stmt->fetchColumn();
            if ($new_amount <= 0) {
                $stmt = $pdo->prepare("UPDATE debts SET state = 'Pagada', amount = 0 WHERE id_debt = ?");
                $stmt->execute([$id_debt]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Abono registrado y saldo actualizado.']);
            break;

        // --- ACCIONES DE ELIMINAR ---
        case 'delete_item':
            $type = $_POST['type'] ?? '';
            $id = $_POST['id'] ?? '';
            $table = '';
            $id_col = '';

            switch($type) {
                case 'movement': $table = 'movements'; $id_col = 'id_movement'; break;
                case 'save': $table = 'save'; $id_col = 'id_save'; break;
                case 'necessary': $table = 'necessary_expense'; $id_col = 'id_necessary'; break;
                case 'debt': $table = 'debts'; $id_col = 'id_debt'; break;
            }

            if (!$table) { echo json_encode(['status' => 'error', 'message' => 'Tipo inválido.']); exit; }

            // Verificar propiedad antes de borrar
            $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_col = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Registro eliminado correctamente.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el registro o no tienes permiso.']);
            }
            break;

        // --- ACCIONES DE EDITAR ---
        case 'edit_movement':
            $id = $_POST['id'] ?? '';
            $cat = $_POST['category'] ?? '';
            $desc = $_POST['description'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $stmt = $pdo->prepare("UPDATE movements SET category = ?, description = ?, amount = ? WHERE id_movement = ? AND id_user = ?");
            $stmt->execute([$cat, $desc, $amt, $id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Movimiento actualizado.']);
            break;

        case 'edit_save':
            $id = $_POST['id'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $stmt = $pdo->prepare("UPDATE save SET amount = ? WHERE id_save = ? AND id_user = ?");
            $stmt->execute([$amt, $id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Ahorro actualizado.']);
            break;

        case 'edit_necessary':
            $id = $_POST['id'] ?? '';
            $cat = $_POST['category'] ?? '';
            $desc = $_POST['description'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $state = $_POST['state'] ?? 'Pendiente';
            $stmt = $pdo->prepare("UPDATE necessary_expense SET category = ?, description = ?, amount = ?, state = ? WHERE id_necessary = ? AND id_user = ?");
            $stmt->execute([$cat, $desc, $amt, $state, $id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Gasto necesario actualizado.']);
            break;

        case 'edit_debt':
            $id = $_POST['id'] ?? '';
            $type_debt = $_POST['type_debt'] ?? '';
            $desc = $_POST['description'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $stmt = $pdo->prepare("UPDATE debts SET type_debt = ?, description = ?, amount = ? WHERE id_debt = ? AND id_user = ?");
            $stmt->execute([$type_debt, $desc, $amt, $id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Deuda actualizada.']);
            break;

        case 'update_profile':
            $name = $_POST['name'] ?? '';
            $lastname = $_POST['lastname'] ?? '';
            $user_name_val = $_POST['user_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';

            // 1. Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id_user = ?");
            $stmt->execute([$user_id]);
            $db_pass = $stmt->fetchColumn();

            if (!password_verify($current_pass, $db_pass)) {
                echo json_encode(['status' => 'error', 'message' => 'La contraseña actual es incorrecta.']);
                exit;
            }

            // 2. Actualizar datos básicos
            $stmt = $pdo->prepare("UPDATE users SET name = ?, lastname = ?, user_name = ?, email = ? WHERE id_user = ?");
            $stmt->execute([$name, $lastname, $user_name_val, $email, $user_id]);

            // 3. Si hay nueva contraseña, actualizarla
            if (!empty($new_pass)) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id_user = ?");
                $stmt->execute([$new_hash, $user_id]);
            }

            // Actualizar variables de sesión si es necesario
            $_SESSION['name'] = $name;

            echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado correctamente.']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>
