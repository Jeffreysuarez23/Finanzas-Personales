<?php
require_once __DIR__ . "/../../config/db.php";

$user_id = $_SESSION['user_id'];

// Función para formatear moneda
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return '$' . number_format($amount, 2, ',', '.');
    }
}

// Obtener filtros
$origen = $_GET['origen'] ?? 'todo';
$tipo_mov = $_GET['tipo_mov'] ?? 'cualquiera';
$tipo_deuda = $_GET['tipo_deuda'] ?? 'cualquiera';
$mes = $_GET['mes'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$queries = [];
$params = [];

// 1. MOVIMIENTOS (Ingreso/Gasto)
if ($origen === 'todo' || $origen === 'movimiento') {
    if ($tipo_mov === 'cualquiera' || $tipo_mov === 'Ingreso' || $tipo_mov === 'Gasto') {
        $sql = "SELECT created_at as fecha, 'Movimiento' as origen, type as tipo, category as subtipo, description, amount, 'N/A' as pagado 
                FROM movements WHERE id_user = ?";
        $p = [$user_id];
        if ($tipo_mov !== 'cualquiera') { $sql .= " AND type = ?"; $p[] = $tipo_mov; }
        $queries[] = ["sql" => $sql, "params" => $p];
    }
}

// 2. AHORROS
if ($origen === 'todo' || $origen === 'movimiento') {
    if ($tipo_mov === 'cualquiera' || $tipo_mov === 'Ahorro') {
        $sql = "SELECT created_at as fecha, 'Movimiento' as origen, 'Ahorro' as tipo, 'Ahorro' as subtipo, 'Ahorro mensual' as description, amount, 'N/A' as pagado 
                FROM save WHERE id_user = ?";
        $queries[] = ["sql" => $sql, "params" => [$user_id]];
    }
}

// 3. GASTOS NECESARIOS
if ($origen === 'todo' || $origen === 'movimiento') {
    if ($tipo_mov === 'cualquiera' || $tipo_mov === 'Gasto necesario') {
        $sql = "SELECT created_at as fecha, 'Movimiento' as origen, 'Gasto necesario' as tipo, category as subtipo, description, amount, state as pagado 
                FROM necessary_expense WHERE id_user = ?";
        $queries[] = ["sql" => $sql, "params" => [$user_id]];
    }
}

// 4. DEUDAS
if ($origen === 'todo' || $origen === 'deuda') {
    $sql = "SELECT created_at as fecha, 'Deuda' as origen, type_debt as tipo, concept as subtipo, description, amount, state as pagado 
            FROM debts WHERE id_user = ?";
    $p = [$user_id];
    if ($tipo_deuda !== 'cualquiera') { $sql .= " AND type_debt = ?"; $p[] = $tipo_deuda; }
    $queries[] = ["sql" => $sql, "params" => $p];
}

// Combinar y filtrar por fecha
$finalRows = [];
foreach ($queries as $q) {
    $sql = $q['sql'];
    $p = $q['params'];
    
    if ($mes) { $sql .= " AND TO_CHAR(created_at, 'YYYY-MM') = ?"; $p[] = $mes; }
    if ($desde) { $sql .= " AND created_at::DATE >= ?"; $p[] = $desde; }
    if ($hasta) { $sql .= " AND created_at::DATE <= ?"; $p[] = $hasta; }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $finalRows = array_merge($finalRows, $stmt->fetchAll());
}

// Ordenar por fecha DESC
usort($finalRows, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

$totalRegistros = count($finalRows);
?>

<body>
  <div class="dashboard-wrapper">

    <!-- Hero Banner -->
    <div class="dashboard-banner">
      <h1 class="banner-title">Consultar datos</h1>
      <p class="banner-subtitle">Explora todo tu historial financiero con filtros avanzados.</p>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
      <h2 class="filtros-title">Filtros de búsqueda</h2>
      <div class="filtros-grid">
        <div class="filtro-group">
          <label class="filtro-label">Buscar en</label>
          <select class="filtro-select" id="filtroOrigen">
            <option value="todo" <?php echo $origen == 'todo' ? 'selected' : ''; ?>>Todo</option>
            <option value="movimiento" <?php echo $origen == 'movimiento' ? 'selected' : ''; ?>>Movimientos</option>
            <option value="deuda" <?php echo $origen == 'deuda' ? 'selected' : ''; ?>>Deudas</option>
          </select>
        </div>
        <div class="filtro-group">
          <label class="filtro-label">Tipo de movimiento</label>
          <select class="filtro-select" id="filtroTipoMovimiento" <?php echo $origen == 'deuda' ? 'disabled' : ''; ?>>
            <option value="cualquiera" <?php echo $tipo_mov == 'cualquiera' ? 'selected' : ''; ?>>Cualquiera</option>
            <option value="Ingreso" <?php echo $tipo_mov == 'Ingreso' ? 'selected' : ''; ?>>Ingreso</option>
            <option value="Gasto" <?php echo $tipo_mov == 'Gasto' ? 'selected' : ''; ?>>Gasto</option>
            <option value="Ahorro" <?php echo $tipo_mov == 'Ahorro' ? 'selected' : ''; ?>>Ahorro</option>
            <option value="Gasto necesario" <?php echo $tipo_mov == 'Gasto necesario' ? 'selected' : ''; ?>>Gasto necesario</option>
          </select>
        </div>
        <div class="filtro-group">
          <label class="filtro-label">Tipo de deuda</label>
          <select class="filtro-select" id="filtroTipoDeuda" <?php echo $origen == 'movimiento' ? 'disabled' : ''; ?>>
            <option value="cualquiera" <?php echo $tipo_deuda == 'cualquiera' ? 'selected' : ''; ?>>Cualquiera</option>
            <option value="Yo debo" <?php echo $tipo_deuda == 'Yo debo' ? 'selected' : ''; ?>>Yo debo</option>
            <option value="Me deben" <?php echo $tipo_deuda == 'Me deben' ? 'selected' : ''; ?>>Me deben</option>
          </select>
        </div>
        <div class="filtro-group">
          <label class="filtro-label">Mes específico</label>
          <input type="month" class="filtro-input" id="filtroMes" value="<?php echo $mes; ?>" />
        </div>
        <div class="filtro-group">
          <label class="filtro-label">Desde</label>
          <input type="date" class="filtro-input" id="filtroDesde" value="<?php echo $desde; ?>" />
        </div>
        <div class="filtro-group">
          <label class="filtro-label">Hasta</label>
          <input type="date" class="filtro-input" id="filtroHasta" value="<?php echo $hasta; ?>" />
        </div>
      </div>
      <div class="filtros-actions">
        <button class="btn-consultar" onclick="consultar()">Consultar ahora</button>
        <button class="btn-limpiar" onclick="limpiar()">Limpiar filtros</button>
      </div>
    </div>

    <!-- Resultados -->
    <div class="resultados-card">
      <div class="resultados-header">
        <span class="resultados-title">Resultados de búsqueda</span>
        <span class="resultados-count" id="resultadosCount"><?php echo $totalRegistros; ?> registros encontrados</span>
      </div>

      <div class="table-container">
        <table id="tablaResultados">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Origen</th>
              <th>Tipo</th>
              <th>Concepto</th>
              <th>Descripción</th>
              <th>Monto</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody id="tablaBody">
            <?php if (empty($finalRows)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:50px; color:#94a3b8;">
                        No se encontraron registros con los filtros seleccionados.
                    </td>
                </tr>
            <?php else: foreach ($finalRows as $row): 
                $origenClass = $row['origen'] == 'Movimiento' ? 'movimiento' : 'deuda';
                $tipoLower = mb_strtolower($row['tipo']);
                $tipoClass = '';
                if (strpos($tipoLower, 'ingreso') !== false) $tipoClass = 'ingreso';
                else if (strpos($tipoLower, 'gasto') !== false) $tipoClass = 'gasto';
                else if (strpos($tipoLower, 'ahorro') !== false) $tipoClass = 'ahorro';
                else if (strpos($tipoLower, 'yo debo') !== false) $tipoClass = 'deuda';
                else if (strpos($tipoLower, 'me deben') !== false) $tipoClass = 'me-deben';

                $montoClass = ($row['tipo'] == 'Ingreso' || $row['tipo'] == 'Me deben') ? 'monto-positivo' : 'monto-negativo';
                if ($row['tipo'] == 'Ahorro') $montoClass = 'monto-ahorro'; // Opcional, podrías añadir un estilo para ahorros
            ?>
                <tr class="fila-dato">
                  <td class="col-fecha"><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                  <td><span class="pill-origen pill-origen--<?php echo $origenClass; ?>"><?php echo $row['origen']; ?></span></td>
                  <td><span class="pill-tipo pill-tipo--<?php echo $tipoClass; ?>"><?php echo $row['tipo']; ?></span></td>
                  <td class="col-subtipo"><?php echo htmlspecialchars($row['subtipo']); ?></td>
                  <td class="col-desc"><?php echo htmlspecialchars($row['description']); ?></td>
                  <td class="col-monto <?php echo $montoClass; ?>"><?php echo formatMoney($row['amount']); ?></td>
                  <td>
                    <?php if ($row['pagado'] === 'N/A'): ?>
                        <span class="badge-pagado badge-na">N/A</span>
                    <?php else: ?>
                        <span class="badge-pagado <?php echo ($row['pagado'] == 'Pagado' || $row['pagado'] == 'Pagada') ? 'badge-si' : 'badge-no'; ?>">
                            <?php echo $row['pagado']; ?>
                        </span>
                    <?php endif; ?>
                  </td>
                </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    function consultar() {
        const origen = document.getElementById('filtroOrigen').value;
        const tipo_mov = document.getElementById('filtroTipoMovimiento').value;
        const tipo_deuda = document.getElementById('filtroTipoDeuda').value;
        const mes = document.getElementById('filtroMes').value;
        const desde = document.getElementById('filtroDesde').value;
        const hasta = document.getElementById('filtroHasta').value;

        let url = `index.php?mod=consultar&origen=${origen}&tipo_mov=${tipo_mov}&tipo_deuda=${tipo_deuda}`;
        if (mes) url += `&mes=${mes}`;
        if (desde) url += `&desde=${desde}`;
        if (hasta) url += `&hasta=${hasta}`;

        window.location.href = url;
    }

    function limpiar() {
        window.location.href = 'index.php?mod=consultar';
    }

    // Lógica de exclusión mutua de filtros
    const mesInput = document.getElementById('filtroMes');
    const desdeInput = document.getElementById('filtroDesde');
    const hastaInput = document.getElementById('filtroHasta');

    mesInput.addEventListener('input', function() {
        const hasValue = this.value !== '';
        desdeInput.disabled = hasValue;
        hastaInput.disabled = hasValue;
        if (hasValue) {
            desdeInput.value = '';
            hastaInput.value = '';
        }
    });

    const checkDates = () => {
        const hasValue = (desdeInput.value !== '' || hastaInput.value !== '');
        mesInput.disabled = hasValue;
        if (hasValue) {
            mesInput.value = '';
        }
    };

    desdeInput.addEventListener('input', checkDates);
    hastaInput.addEventListener('input', checkDates);

    // Lógica para deshabilitar selects según origen
    document.getElementById('filtroOrigen').addEventListener('change', function() {
        const val = this.value;
        document.getElementById('filtroTipoMovimiento').disabled = (val === 'deuda');
        document.getElementById('filtroTipoDeuda').disabled = (val === 'movimiento');
    });

    // Ejecutar al cargar para mantener estado si hay filtros activos
    window.addEventListener('load', () => {
        if (mesInput.value !== '') {
            desdeInput.disabled = true;
            hastaInput.disabled = true;
        } else if (desdeInput.value !== '' || hastaInput.value !== '') {
            mesInput.disabled = true;
        }
    });
  </script>
</body>