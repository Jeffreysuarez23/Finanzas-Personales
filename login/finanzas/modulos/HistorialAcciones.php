<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];

// Filtros
$op_filtro = $_GET['op'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';
$fecha_filtro = $_GET['fecha'] ?? '';
$q_filtro = trim($_GET['q'] ?? '');

$tablesStmt = $pdo->prepare("SELECT DISTINCT table_name FROM user_logs WHERE id_user = ? ORDER BY table_name");
$tablesStmt->execute([$user_id]);
$table_names = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT * FROM user_logs WHERE id_user = ?";
$params = [$user_id];

if ($op_filtro) {
  $sql .= " AND operation = ?";
  $params[] = $op_filtro;
}
if ($tabla_filtro) {
  $sql .= " AND table_name = ?";
  $params[] = $tabla_filtro;
}
if ($fecha_filtro) {
  $sql .= " AND created_at::DATE = ?";
  $params[] = $fecha_filtro;
}
if ($q_filtro) {
  $sql .= " AND (operation ILIKE ? OR table_name ILIKE ? OR old_data ILIKE ? OR new_data ILIKE ?)";
  $queryValue = "%{$q_filtro}%";
  $params[] = $queryValue;
  $params[] = $queryValue;
  $params[] = $queryValue;
  $params[] = $queryValue;
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<body>
  <div class="dashboard-wrapper">

    <!-- Hero Banner -->
    <div class="dashboard-banner">
      <h1 class="banner-title">Historial de Acciones</h1>
      <p class="banner-subtitle">Registro automático de todas las operaciones realizadas en tu cuenta.</p>
    </div>

    <!-- Filtros -->
    <div class="filtros-card" id="filtrosCard">
      <h2 class="filtros-title">Filtros de Auditoría</h2>
      <div class="filtros-grid">
        <div class="campo-group">
          <label class="campo-label">Operación</label>
          <select class="campo-input" id="filtroOperacion">
            <option value="" <?php echo $op_filtro == '' ? 'selected' : ''; ?>>Todas</option>
            <option value="Inserción" <?php echo $op_filtro == 'Inserción' ? 'selected' : ''; ?>>Inserción</option>
            <option value="Actualización" <?php echo $op_filtro == 'Actualización' ? 'selected' : ''; ?>>Actualización</option>
            <option value="Eliminación" <?php echo $op_filtro == 'Eliminación' ? 'selected' : ''; ?>>Eliminación</option>
          </select>
        </div>
        <div class="campo-group">
          <label class="campo-label">Módulo</label>
          <select class="campo-input" id="filtroTabla">
            <option value="" <?php echo $tabla_filtro == '' ? 'selected' : ''; ?>>Todos</option>
            <?php foreach ($table_names as $table_name): ?>
              <option value="<?php echo htmlspecialchars($table_name); ?>" <?php echo $tabla_filtro == $table_name ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $table_name))); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo-group">
          <label class="campo-label">Buscar</label>
          <input class="campo-input" type="search" id="filtroQuery" placeholder="Operación, módulo o texto" value="<?php echo htmlspecialchars($q_filtro); ?>" />
        </div>
        <div class="campo-group">
          <label class="campo-label">Fecha</label>
          <input class="campo-input" type="date" id="filtroFecha" value="<?php echo $fecha_filtro; ?>" />
        </div>
        <div class="filtros-btns">
          <button class="btn-buscar" onclick="aplicarFiltros()">Buscar</button>
          <button class="btn-limpiar" onclick="limpiarFiltros()">Limpiar</button>
        </div>
      </div>
    </div>

    <!-- Tabla de historial -->
    <div class="tabla-card">
      <div class="table-container">
        <table id="historialTabla">
          <thead>
            <tr>
              <th>Fecha y Hora</th>
              <th>Operación</th>
              <th>Módulo</th>
              <th>ID</th>
              <th>Estado Anterior</th>
              <th>Estado Nuevo</th>
            </tr>
          </thead>
          <tbody id="tablaBody">
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                  No se han encontrado registros de acciones para estos filtros.
                </td>
              </tr>
              <?php else: foreach ($logs as $log):
                $badgeClass = '';
                switch ($log['operation']) {
                  case 'Inserción':
                    $badgeClass = 'badge-insertar';
                    break;
                  case 'Actualización':
                    $badgeClass = 'badge-actualizar';
                    break;
                  case 'Eliminación':
                    $badgeClass = 'badge-eliminar';
                    break;
                }
              ?>
                <tr class="fila-historial">
                  <td>
                    <div class="fecha-hora">
                      <span class="fecha"><?php echo $log['created_at']; ?></span>
                    </div>
                  </td>
                  <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $log['operation']; ?></span></td>
                  <td><span style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $log['table_name']); ?></span></td>
                  <td class="id-registro"><?php echo $log['record_id']; ?></td>
                  <td>
                    <?php if ($log['old_data']): ?>
                      <div class="datos-card datos-anterior">
                        <div class="dato-row"><?php echo htmlspecialchars($log['old_data']); ?></div>
                      </div>
                    <?php else: ?>
                      <div class="datos-card datos-na"><span class="na-text">N/A</span></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($log['new_data']): ?>
                      <div class="datos-card datos-nuevo">
                        <div class="dato-row"><?php echo htmlspecialchars($log['new_data']); ?></div>
                      </div>
                    <?php else: ?>
                      <div class="datos-card datos-na"><span class="na-text">N/A</span></div>
                    <?php endif; ?>
                  </td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    function aplicarFiltros() {
      const op = document.getElementById('filtroOperacion').value;
      const tabla = document.getElementById('filtroTabla').value;
      const q = document.getElementById('filtroQuery').value;
      const fecha = document.getElementById('filtroFecha').value;

      const params = new URLSearchParams();
      if (op) params.set('op', op);
      if (tabla) params.set('tabla', tabla);
      if (q) params.set('q', q);
      if (fecha) params.set('fecha', fecha);

      window.location.href = `index.php?mod=HistorialAcciones&${params.toString()}`;
    }

    function limpiarFiltros() {
      window.location.href = 'index.php?mod=HistorialAcciones';
    }
  </script>
</body>