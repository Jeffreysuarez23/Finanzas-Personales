<?php
require_once "config/db.php";

try {
    $stmt = $pdo->query("SELECT 1");
    echo "<h1>✅ ¡Conexión exitosa a Supabase!</h1>";
    echo "<p>El servidor PHP puede comunicarse correctamente con la base de datos PostgreSQL.</p>";
} catch (Exception $e) {
    echo "<h1>❌ Error de conexión</h1>";
    echo "<p>Detalles del error: " . $e->getMessage() . "</p>";
}
?>
