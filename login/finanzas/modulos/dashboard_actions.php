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
function formatMoney($amount)
{
    return '$' . number_format($amount, 2, ',', '.');
}

function formatLogData(array $data): string
{
    $parts = [];
    foreach ($data as $label => $value) {
        $parts[] = $label . ': ' . ($value !== null && $value !== '' ? $value : 'N/A');
    }
    return implode(' | ', $parts);
}

function logUserAction(PDO $pdo, int $user_id, string $operation, string $table_name, int $record_id, ?string $old_data = null, ?string $new_data = null): void
{
    $stmt = $pdo->prepare("INSERT INTO user_logs (id_user, operation, table_name, record_id, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $operation, $table_name, $record_id, $old_data, $new_data]);
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
            $stmt = $pdo->prepare("INSERT INTO movements (type, category, description, amount, created_at, id_user) VALUES (?, ?, ?, ?, ?, ?) RETURNING id_movement");
            $stmt->execute([$type, $category, $description, $amount, $date . ' ' . date('H:i:s'), $user_id]);
            $record_id = (int) $stmt->fetchColumn();
            logUserAction($pdo, $user_id, 'Inserción', 'movements', $record_id, null, formatLogData([
                'Tipo' => $type,
                'Categoría' => $category,
                'Descripción' => $description,
                'Monto' => $amount,
            ]));
            echo json_encode(['status' => 'success', 'message' => 'Movimiento agregado correctamente.']);
            break;

        case 'add_save':
            $amount = $_POST['amount'] ?? 0;
            $date = $_POST['date'] ?? date('Y-m-d');
            if (empty($amount)) {
                echo json_encode(['status' => 'error', 'message' => 'El monto es obligatorio.']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO save (amount, created_at, id_user) VALUES (?, ?, ?) RETURNING id_save");
            $stmt->execute([$amount, $date . ' ' . date('H:i:s'), $user_id]);
            $record_id = (int) $stmt->fetchColumn();
            logUserAction($pdo, $user_id, 'Inserción', 'save', $record_id, null, formatLogData([
                'Monto' => $amount,
            ]));
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
            $stmt = $pdo->prepare("INSERT INTO necessary_expense (type, category, description, amount, state, created_at, id_user) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id_necessary");
            $stmt->execute([$type, $category, $description, $amount, $state, $date . ' ' . date('H:i:s'), $user_id]);
            $record_id = (int) $stmt->fetchColumn();
            logUserAction($pdo, $user_id, 'Inserción', 'necessary_expense', $record_id, null, formatLogData([
                'Tipo' => $type,
                'Categoría' => $category,
                'Descripción' => $description,
                'Monto' => $amount,
                'Estado' => $state,
            ]));
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
            $stmt = $pdo->prepare("INSERT INTO debts (concept, type_debt, description, amount, state, created_at, id_user) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id_debt");
            $stmt->execute([$concept, $type_debt, $description, $amount, $state, $date . ' ' . date('H:i:s'), $user_id]);
            $record_id = (int) $stmt->fetchColumn();
            logUserAction($pdo, $user_id, 'Inserción', 'debts', $record_id, null, formatLogData([
                'Concepto' => $concept,
                'Tipo' => $type_debt,
                'Descripción' => $description,
                'Monto' => $amount,
                'Estado' => $state,
            ]));
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
            $stmt = $pdo->prepare("SELECT amount, state FROM debts WHERE id_debt = ? AND id_user = ?");
            $stmt->execute([$id_debt, $user_id]);
            $debt = $stmt->fetch();
            if (!$debt) {
                echo json_encode(['status' => 'error', 'message' => 'Deuda no encontrada.']);
                exit;
            }
            if ($amount > $debt['amount']) {
                echo json_encode(['status' => 'error', 'message' => 'El abono no puede ser mayor a la deuda pendiente (' . formatMoney($debt['amount']) . ').']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO pay_debt (amount, method, description, id_debt, created_at) VALUES (?, ?, ?, ?, ?) RETURNING id_pay_debt");
            $stmt->execute([$amount, $method, $description, $id_debt, $date . ' ' . date('H:i:s')]);
            $payment_id = (int) $stmt->fetchColumn();
            logUserAction($pdo, $user_id, 'Inserción', 'pay_debt', $payment_id, null, formatLogData([
                'Abono' => $amount,
                'Método' => $method,
                'Descripción' => $description,
            ]));

            $oldDebtData = formatLogData([
                'Monto anterior' => $debt['amount'],
                'Estado anterior' => $debt['state'],
            ]);

            $stmt = $pdo->prepare("UPDATE debts SET amount = amount - ? WHERE id_debt = ? RETURNING amount, state");
            $stmt->execute([$amount, $id_debt]);
            $newDebt = $stmt->fetch();
            if ($newDebt && $newDebt['amount'] <= 0) {
                $stmt = $pdo->prepare("UPDATE debts SET state = 'Pagada', amount = 0 WHERE id_debt = ? RETURNING amount, state");
                $stmt->execute([$id_debt]);
                $newDebt = $stmt->fetch();
            }

            if ($newDebt) {
                logUserAction($pdo, $user_id, 'Actualización', 'debts', (int) $id_debt, $oldDebtData, formatLogData([
                    'Monto nuevo' => $newDebt['amount'],
                    'Estado nuevo' => $newDebt['state'],
                ]));
            }

            echo json_encode(['status' => 'success', 'message' => 'Abono registrado y saldo actualizado.']);
            break;

        // --- ACCIONES DE ELIMINAR ---
        case 'delete_item':
            $type = $_POST['type'] ?? '';
            $id = $_POST['id'] ?? '';
            $table = '';
            $id_col = '';

            switch ($type) {
                case 'movement':
                    $table = 'movements';
                    $id_col = 'id_movement';
                    break;
                case 'save':
                    $table = 'save';
                    $id_col = 'id_save';
                    break;
                case 'necessary':
                    $table = 'necessary_expense';
                    $id_col = 'id_necessary';
                    break;
                case 'debt':
                    $table = 'debts';
                    $id_col = 'id_debt';
                    break;
            }

            if (!$table) {
                echo json_encode(['status' => 'error', 'message' => 'Tipo inválido.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM $table WHERE $id_col = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);
            $oldRow = $stmt->fetch();
            if (!$oldRow) {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el registro o no tienes permiso.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_col = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);

            if ($stmt->rowCount() > 0) {
                logUserAction($pdo, $user_id, 'Eliminación', $table, (int) $id, formatLogData($oldRow), null);
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
            $stmt = $pdo->prepare("SELECT * FROM movements WHERE id_movement = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);
            $oldRow = $stmt->fetch();
            if (!$oldRow) {
                echo json_encode(['status' => 'error', 'message' => 'Movimiento no encontrado.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE movements SET category = ?, description = ?, amount = ? WHERE id_movement = ? AND id_user = ? RETURNING *");
            $stmt->execute([$cat, $desc, $amt, $id, $user_id]);
            $newRow = $stmt->fetch();
            if ($newRow) {
                logUserAction($pdo, $user_id, 'Actualización', 'movements', (int) $id, formatLogData($oldRow), formatLogData($newRow));
            }
            echo json_encode(['status' => 'success', 'message' => 'Movimiento actualizado.']);
            break;

        case 'edit_save':
            $id = $_POST['id'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM save WHERE id_save = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);
            $oldRow = $stmt->fetch();
            if (!$oldRow) {
                echo json_encode(['status' => 'error', 'message' => 'Ahorro no encontrado.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE save SET amount = ? WHERE id_save = ? AND id_user = ? RETURNING *");
            $stmt->execute([$amt, $id, $user_id]);
            $newRow = $stmt->fetch();
            if ($newRow) {
                logUserAction($pdo, $user_id, 'Actualización', 'save', (int) $id, formatLogData($oldRow), formatLogData($newRow));
            }
            echo json_encode(['status' => 'success', 'message' => 'Ahorro actualizado.']);
            break;

        case 'edit_necessary':
            $id = $_POST['id'] ?? '';
            $cat = $_POST['category'] ?? '';
            $desc = $_POST['description'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $state = $_POST['state'] ?? 'Pendiente';
            $stmt = $pdo->prepare("SELECT * FROM necessary_expense WHERE id_necessary = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);
            $oldRow = $stmt->fetch();
            if (!$oldRow) {
                echo json_encode(['status' => 'error', 'message' => 'Gasto necesario no encontrado.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE necessary_expense SET category = ?, description = ?, amount = ?, state = ? WHERE id_necessary = ? AND id_user = ? RETURNING *");
            $stmt->execute([$cat, $desc, $amt, $state, $id, $user_id]);
            $newRow = $stmt->fetch();
            if ($newRow) {
                logUserAction($pdo, $user_id, 'Actualización', 'necessary_expense', (int) $id, formatLogData($oldRow), formatLogData($newRow));
            }
            echo json_encode(['status' => 'success', 'message' => 'Gasto necesario actualizado.']);
            break;

        case 'edit_debt':
            $id = $_POST['id'] ?? '';
            $type_debt = $_POST['type_debt'] ?? '';
            $desc = $_POST['description'] ?? '';
            $amt = $_POST['amount'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM debts WHERE id_debt = ? AND id_user = ?");
            $stmt->execute([$id, $user_id]);
            $oldRow = $stmt->fetch();
            if (!$oldRow) {
                echo json_encode(['status' => 'error', 'message' => 'Deuda no encontrada.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE debts SET type_debt = ?, description = ?, amount = ? WHERE id_debt = ? AND id_user = ? RETURNING *");
            $stmt->execute([$type_debt, $desc, $amt, $id, $user_id]);
            $newRow = $stmt->fetch();
            if ($newRow) {
                logUserAction($pdo, $user_id, 'Actualización', 'debts', (int) $id, formatLogData($oldRow), formatLogData($newRow));
            }
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
