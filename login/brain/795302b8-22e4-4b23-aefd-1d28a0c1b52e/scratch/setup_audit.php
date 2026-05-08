<?php
require_once "c:/xampp/htdocs/ProyectoPersonal/login/config/db.php";

try {
    // 1. Asegurar tabla de logs
    $sql = "CREATE TABLE IF NOT EXISTS user_logs (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        operation VARCHAR(50) NOT NULL,
        table_name VARCHAR(100) NOT NULL,
        record_id INT NOT NULL,
        old_data TEXT,
        new_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_user) REFERENCES users(id_user)
    )";
    $pdo->exec($sql);

    // Función para crear triggers detallados
    function updateTriggers($pdo, $tableName, $idColumn, $fields) {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_insert");
        $pdo->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_update");
        $pdo->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_delete");

        // Construir cadenas de concatenación
        $newConcat = "CONCAT(";
        $oldConcat = "CONCAT(";
        foreach ($fields as $f) {
            $newConcat .= "'$f: ', COALESCE(NEW.$f, 'N/A'), ' | ', ";
            $oldConcat .= "'$f: ', COALESCE(OLD.$f, 'N/A'), ' | ', ";
        }
        $newConcat = rtrim($newConcat, ", ' | ', ") . ")";
        $oldConcat = rtrim($oldConcat, ", ' | ', ") . ")";

        // INSERT
        $sqlInsert = "CREATE TRIGGER trg_{$tableName}_insert AFTER INSERT ON {$tableName}
            FOR EACH ROW
            BEGIN
                INSERT INTO user_logs (id_user, operation, table_name, record_id, new_data)
                VALUES (NEW.id_user, 'Inserción', '{$tableName}', NEW.{$idColumn}, $newConcat);
            END;";
        $pdo->exec($sqlInsert);

        // UPDATE
        $sqlUpdate = "CREATE TRIGGER trg_{$tableName}_update AFTER UPDATE ON {$tableName}
            FOR EACH ROW
            BEGIN
                INSERT INTO user_logs (id_user, operation, table_name, record_id, old_data, new_data)
                VALUES (NEW.id_user, 'Actualización', '{$tableName}', NEW.{$idColumn}, $oldConcat, $newConcat);
            END;";
        $pdo->exec($sqlUpdate);

        // DELETE
        $sqlDelete = "CREATE TRIGGER trg_{$tableName}_delete AFTER DELETE ON {$tableName}
            FOR EACH ROW
            BEGIN
                INSERT INTO user_logs (id_user, operation, table_name, record_id, old_data)
                VALUES (OLD.id_user, 'Eliminación', '{$tableName}', OLD.{$idColumn}, $oldConcat);
            END;";
        $pdo->exec($sqlDelete);
        
        echo "Triggers detallados para $tableName creados.\n";
    }

    updateTriggers($pdo, 'movements', 'id_movement', ['type', 'category', 'description', 'amount']);
    updateTriggers($pdo, 'necessary_expense', 'id_necessary', ['category', 'description', 'amount', 'state']);
    updateTriggers($pdo, 'save', 'id_save', ['amount']);
    updateTriggers($pdo, 'debts', 'id_debt', ['concept', 'description', 'amount', 'state']);

    // pay_debt (Abonos)
    $pdo->exec("DROP TRIGGER IF EXISTS trg_pay_debt_insert");
    $sqlPayInsert = "CREATE TRIGGER trg_pay_debt_insert AFTER INSERT ON pay_debt
        FOR EACH ROW
        BEGIN
            DECLARE user_id_val INT;
            SELECT id_user INTO user_id_val FROM debts WHERE id_debt = NEW.id_debt;
            INSERT INTO user_logs (id_user, operation, table_name, record_id, new_data)
            VALUES (user_id_val, 'Inserción', 'pay_debt', NEW.id_pay_debt, 
            CONCAT('Abono: ', NEW.amount, ' | Método: ', NEW.method, ' | Nota: ', COALESCE(NEW.description, '')));
        END;";
    $pdo->exec($sqlPayInsert);
    echo "Trigger detallado para pay_debt creado.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
