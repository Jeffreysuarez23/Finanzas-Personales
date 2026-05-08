<?php
require_once "c:/xampp/htdocs/ProyectoPersonal/login/config/db.php";

try {
    // 1. Eliminar la restricción actual
    $pdo->exec("ALTER TABLE pay_debt DROP FOREIGN KEY pay_debt_ibfk_1");
    
    // 2. Añadir la nueva restricción con ON DELETE CASCADE
    $pdo->exec("ALTER TABLE pay_debt ADD CONSTRAINT pay_debt_ibfk_1 FOREIGN KEY (id_debt) REFERENCES debts(id_debt) ON DELETE CASCADE");
    
    echo "Base de datos actualizada: Ahora puedes eliminar deudas y sus abonos se borrarán automáticamente.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
