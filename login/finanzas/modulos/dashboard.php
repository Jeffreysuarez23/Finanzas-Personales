<?php
require_once __DIR__ . "/../../config/db.php";

$user_id = $_SESSION['user_id'];

// Obtener mes y año seleccionados (por defecto el actual)
$mes_sel = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio_sel = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Función para formatear moneda
function formatMoney($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}

// --- LÓGICA PARA OBTENER DATOS DE TODOS LOS MESES ---
$meses_nombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$resumen_mensual = [];

foreach ($meses_nombres as $num => $nombre) {
    // Ingresos y Gastos
    $stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM movements WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? GROUP BY type");
    $stmt->execute([$user_id, $num, $anio_sel]);
    $movs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $ingreso = $movs['Ingreso'] ?? 0;
    $gasto = $movs['Gasto'] ?? 0;

    // Ahorros
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM save WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ?");
    $stmt->execute([$user_id, $num, $anio_sel]);
    $ahorro = $stmt->fetchColumn() ?? 0;

    // Gastos Necesarios (Separar por estado)
    $stmt = $pdo->prepare("SELECT state, SUM(amount) as total FROM necessary_expense WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? GROUP BY state");
    $stmt->execute([$user_id, $num, $anio_sel]);
    $nec_sums = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $pagados = $nec_sums['Pagado'] ?? 0;
    $pendientes = $nec_sums['Pendiente'] ?? 0;

    $deberia_tener = $ingreso - $gasto - $ahorro - $pagados;
    $puedo_gastar = $deberia_tener - $pendientes;

    $resumen_mensual[$num] = [
        'ingreso' => $ingreso,
        'gasto' => $gasto,
        'ahorro' => $ahorro,
        'necesarios_pagados' => $pagados,
        'necesarios_pendientes' => $pendientes,
        'deberia_tener' => $deberia_tener,
        'puedo_gastar' => $puedo_gastar
    ];
}

// Datos del mes seleccionado para las tablas
$total_ingresos = $resumen_mensual[$mes_sel]['ingreso'];
$total_gastos = $resumen_mensual[$mes_sel]['gasto'];
$total_ahorro = $resumen_mensual[$mes_sel]['ahorro'];
$total_necesarios_pagados = $resumen_mensual[$mes_sel]['necesarios_pagados'];
$total_necesarios_pendientes = $resumen_mensual[$mes_sel]['necesarios_pendientes'];
$balance = $resumen_mensual[$mes_sel]['deberia_tener'];

// Listas detalladas para las tablas
$stmt = $pdo->prepare("SELECT * FROM movements WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $mes_sel, $anio_sel]);
$movements = $stmt->fetchAll();

$ingresos_list = array_filter($movements, fn($m) => $m['type'] === 'Ingreso');
$gastos_list = array_filter($movements, fn($m) => $m['type'] === 'Gasto');

$stmt = $pdo->prepare("SELECT * FROM save WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $mes_sel, $anio_sel]);
$savings = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM necessary_expense WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $mes_sel, $anio_sel]);
$necessary_expenses = $stmt->fetchAll();

// --- LÓGICA DE DEUDAS (INDEPENDIENTE DEL MES) ---
$stmt = $pdo->prepare("SELECT * FROM debts WHERE id_user = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$all_debts = $stmt->fetchAll();

$yo_debo_pendientes = [];
$yo_debo_pagadas = [];
$me_deben_pendientes = [];
$me_deben_pagadas = [];

$total_yo_debo_pend = 0;
$total_me_deben_pend = 0;

foreach ($all_debts as $d) {
    if ($d['concept'] === 'Yo debo') {
        if ($d['state'] === 'Pendiente') {
            $yo_debo_pendientes[] = $d;
            $total_yo_debo_pend += $d['amount'];
        } else {
            $yo_debo_pagadas[] = $d;
        }
    } else { // 'Me deben'
        if ($d['state'] === 'Pendiente') {
            $me_deben_pendientes[] = $d;
            $total_me_deben_pend += $d['amount'];
        } else {
            $me_deben_pagadas[] = $d;
        }
    }
}

// Consultar historial de abonos
$stmt = $pdo->prepare("SELECT p.*, d.description as debt_name FROM pay_debt p 
                       JOIN debts d ON p.id_debt = d.id_debt 
                       WHERE d.id_user = ? ORDER BY p.created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$payments_history = $stmt->fetchAll();
?>

<body>
  <div id="app" style="width:100%">
    <div class="dashboard-wrapper">

      <!-- Hero Banner -->
      <div class="dashboard-banner">
        <h1 class="banner-title">Dashboard Personal</h1>
        <p class="banner-subtitle">Controla tus ingresos y gastos en un solo lugar.</p>
      </div>

      <!-- Tarjetas principales -->
      <div class="cards-grid">
        <div class="card"><span class="card-label">TOTAL INGRESOS</span><span class="card-value"><?php echo formatMoney($total_ingresos); ?></span></div>
        <div class="card"><span class="card-label">TOTAL GASTOS</span><span class="card-value"><?php echo formatMoney($total_gastos); ?></span></div>
        <div class="card"><span class="card-label">TOTAL AHORRO</span><span class="card-value"><?php echo formatMoney($total_ahorro); ?></span></div>
        <div class="card"><span class="card-label">NECESARIOS PENDIENTES</span><span class="card-value"><?php echo formatMoney($total_necesarios_pendientes); ?></span></div>
      </div>

      <!-- Balance -->
      <div class="cards-grid">
        <div class="card">
          <span class="card-label">BALANCE (Disponible)</span>
          <span class="card-value <?php echo $balance >= 0 ? '' : 'monto-negativo'; ?>"><?php echo formatMoney($balance); ?></span>
        </div>
      </div>

      <!-- Carrusel de meses -->
      <div class="month-selector">
        <div class="carousel-wrapper">
          <h2 class="section-title">Selecciona un mes (<?php echo $anio_sel; ?>)</h2>
          <div class="carousel-outer">
            <button class="btn-nav btn-prev" id="prevMonth">‹</button>
            <div class="carousel-track" id="carouselTrack" style="overflow-x: auto; scroll-behavior: smooth;">
              <?php foreach ($meses_nombres as $num => $nombre): 
                $data = $resumen_mensual[$num];
              ?>
                <div class="month-card <?php echo ($num == $mes_sel) ? 'active' : ''; ?>" 
                     onclick="window.location.href='index.php?mod=dashboard&mes=<?php echo $num; ?>&anio=<?php echo $anio_sel; ?>'"
                     style="cursor: pointer;">
                  <div class="month-card-title"><?php echo $nombre . ' ' . $anio_sel; ?></div>
                  <div class="month-card-row"><span class="row-label">Ingreso:</span><span class="row-value"><?php echo formatMoney($data['ingreso']); ?></span></div>
                  <div class="month-card-row"><span class="row-label">Gastos necesarios pendientes:</span><span class="row-value"><?php echo formatMoney($data['necesarios_pendientes']); ?></span></div>
                  <div class="month-card-row"><span class="row-label">Gastos necesarios pagados:</span><span class="row-value"><?php echo formatMoney($data['necesarios_pagados']); ?></span></div>
                  <div class="month-card-row"><span class="row-label">Gasto:</span><span class="row-value"><?php echo formatMoney($data['gasto']); ?></span></div>
                  <div class="month-card-row"><span class="row-label">Ahorro:</span><span class="row-value"><?php echo formatMoney($data['ahorro']); ?></span></div>
                  <div class="month-card-row"><span class="row-label">Que debería tener (sin gastos pendientes):</span><span class="row-value"><?php echo formatMoney($data['deberia_tener']); ?></span></div>
                  <div class="month-card-row"><span class="row-label">Que puedo gastar?:</span><span class="row-value"><?php echo formatMoney($data['puedo_gastar']); ?></span></div>
                </div>
              <?php endforeach; ?>
            </div>
            <button class="btn-nav btn-next" id="nextMonth">›</button>
          </div>
        </div>
      </div>

      <!-- Movimientos -->
      <div class="movimientos-wrapper">
        <div class="mov-header">
          <span class="mov-title">Movimientos de <?php echo $meses_nombres[$mes_sel]; ?></span>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn-agregar btn-mov" onclick="openAddMovementModal()">+ Agregar movimiento</button>
            <button class="btn-agregar btn-save" onclick="openAddSaveModal()">+ Agregar ahorro</button>
            <button class="btn-agregar btn-nec" onclick="openAddNecessaryModal()">+ Agregar gasto necesario</button>
          </div>
        </div>

        <div class="summary-bar">
          <span>Ingresos: <b><?php echo formatMoney($total_ingresos); ?></b></span>
          <span>Gastos: <b><?php echo formatMoney($total_gastos); ?></b></span>
          <span>Ahorros: <b><?php echo formatMoney($total_ahorro); ?></b></span>
          <span>Nec. Pendientes: <b><?php echo formatMoney($total_necesarios_pendientes); ?></b></span>
          <span>Balance: <b><?php echo formatMoney($balance); ?></b></span>
        </div>

        <!-- Ingresos -->
        <div class="section-block">
          <div class="section-heading">Ingresos</div>
          <div class="table-container">
            <table>
              <thead><tr><th># ID</th><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Monto</th><th>Acción</th></tr></thead>
              <tbody>
                <?php if (empty($ingresos_list)): ?><tr><td colspan="6" style="text-align:center;">No hay ingresos.</td></tr>
                <?php else: foreach ($ingresos_list as $i): ?>
                  <tr>
                    <td><b><?php echo $i['id_movement']; ?></b></td>
                    <td><?php echo date('d/m/Y', strtotime($i['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($i['category']); ?></td>
                    <td><?php echo htmlspecialchars($i['description']); ?></td>
                    <td class="monto-positivo"><?php echo formatMoney($i['amount']); ?></td>
                    <td class="acciones">
                        <button class="btn-add" onclick='openEditMovementModal(<?php echo json_encode($i); ?>)'>Editar</button>
                        <button class="btn-del" onclick='confirmDelete("movement", <?php echo json_encode($i); ?>)'>Eliminar</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Ahorros -->
        <div class="section-block">
          <div class="section-heading">Ahorros</div>
          <div class="table-container">
            <table>
              <thead><tr><th># ID</th><th>Fecha</th><th>Monto</th><th>Acción</th></tr></thead>
              <tbody>
                <?php if (empty($savings)): ?><tr><td colspan="4" style="text-align:center;">No hay ahorros.</td></tr>
                <?php else: foreach ($savings as $s): ?>
                  <tr>
                    <td><b><?php echo $s['id_save']; ?></b></td>
                    <td><?php echo date('d/m/Y', strtotime($s['created_at'])); ?></td>
                    <td class="monto-ahorro"><?php echo formatMoney($s['amount']); ?></td>
                    <td class="acciones">
                        <button class="btn-add" onclick='openEditSaveModal(<?php echo json_encode($s); ?>)'>Editar</button>
                        <button class="btn-del" onclick='confirmDelete("save", <?php echo json_encode($s); ?>)'>Eliminar</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Gastos -->
        <div class="section-block">
          <div class="section-heading">Gastos</div>
          <div class="table-container">
            <table>
              <thead><tr><th># ID</th><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Monto</th><th>Acción</th></tr></thead>
              <tbody>
                <?php if (empty($gastos_list)): ?><tr><td colspan="6" style="text-align:center;">No hay gastos.</td></tr>
                <?php else: foreach ($gastos_list as $g): ?>
                  <tr>
                    <td><b><?php echo $g['id_movement']; ?></b></td>
                    <td><?php echo date('d/m/Y', strtotime($g['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($g['category']); ?></td>
                    <td><?php echo htmlspecialchars($g['description']); ?></td>
                    <td class="monto-negativo"><?php echo formatMoney($g['amount']); ?></td>
                    <td class="acciones">
                        <button class="btn-add" onclick='openEditMovementModal(<?php echo json_encode($g); ?>)'>Editar</button>
                        <button class="btn-del" onclick='confirmDelete("movement", <?php echo json_encode($g); ?>)'>Eliminar</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Gastos necesarios -->
        <div class="section-block">
          <div class="section-heading">Gastos necesarios</div>
          <div class="table-container">
            <table>
              <thead><tr><th># ID</th><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
              <tbody>
                <?php if (empty($necessary_expenses)): ?><tr><td colspan="7" style="text-align:center;">No hay gastos necesarios.</td></tr>
                <?php else: foreach ($necessary_expenses as $ne): ?>
                  <tr>
                    <td><b><?php echo $ne['id_necessary']; ?></b></td>
                    <td><?php echo date('d/m/Y', strtotime($ne['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($ne['category']); ?></td>
                    <td><?php echo htmlspecialchars($ne['description']); ?></td>
                    <td class="monto-info"><?php echo formatMoney($ne['amount']); ?></td>
                    <td><span class="badge <?php echo $ne['state'] === 'Pagado' ? 'badge-pagado' : 'badge-pendiente'; ?>">
                        <?php echo $ne['state']; ?>
                    </span></td>
                    <td class="acciones">
                        <button class="btn-add" onclick='openEditNecessaryModal(<?php echo json_encode($ne); ?>)'>Editar</button>
                        <button class="btn-del" onclick='confirmDelete("necessary", <?php echo json_encode($ne); ?>)'>Eliminar</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- GESTIÓN DE DEUDAS -->
        <div class="deudas-wrapper">
          <div class="deudas-header">
            <span class="mov-title">Gestión de Deudas (General)</span>
            <div class="deudas-btns">
              <button class="btn-agregar btn-save" onclick="openAddPaymentModal()">💰 Abonar a deuda</button>
              <button class="btn-agregar btn-nec" onclick="openAddDebtModal()">+ Agregar deuda</button>
            </div>
          </div>

          <div class="deudas-summary-cards">
            <div class="deuda-card deuda-card--rojo">
              <div class="deuda-card-label-rojo">🔴 Deudas Pendientes (Yo debo)</div>
              <div class="deuda-card-value deuda-card-value--rojo"><?php echo formatMoney($total_yo_debo_pend); ?></div>
              <div class="deuda-card-sub"><?php echo count($yo_debo_pendientes); ?> deuda(s) pendiente(s)</div>
            </div>
            <div class="deuda-card deuda-card--verde">
              <div class="deuda-card-label-verde">💛 Me Deben Pendientes</div>
              <div class="deuda-card-value deuda-card-value--verde"><?php echo formatMoney($total_me_deben_pend); ?></div>
              <div class="deuda-card-sub"><?php echo count($me_deben_pendientes); ?> deuda(s) pendiente(s)</div>
            </div>
          </div>

          <!-- Deudas Pendientes (Yo debo) -->
          <div class="section-block">
            <div class="section-heading">■ Deudas Pendientes (Yo debo)</div>
            <div class="table-container">
              <table>
                <thead><tr><th># ID</th><th>Fecha</th><th>Concepto</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                  <?php if (empty($yo_debo_pendientes)): ?><tr><td colspan="7" style="text-align:center;">No tienes deudas pendientes.</td></tr>
                  <?php else: foreach ($yo_debo_pendientes as $d): ?>
                    <tr>
                      <td><b><?php echo $d['id_debt']; ?></b></td>
                      <td><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($d['type_debt']); ?></td>
                      <td><?php echo htmlspecialchars($d['description']); ?></td>
                      <td class="monto-negativo"><?php echo formatMoney($d['amount']); ?></td>
                      <td><span class="badge badge-pendiente">Pendiente</span></td>
                      <td class="acciones">
                        <button class="btn-add" onclick='openEditDebtModal(<?php echo json_encode($d); ?>)'>Editar</button>
                        <button class="btn-del" onclick='confirmDelete("debt", <?php echo json_encode($d); ?>)'>Eliminar</button>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Me Deben Pendientes -->
          <div class="section-block">
            <div class="section-heading">■ Me Deben Pendientes</div>
            <div class="table-container">
              <table>
                <thead><tr><th># ID</th><th>Fecha</th><th>Concepto</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                  <?php if (empty($me_deben_pendientes)): ?><tr><td colspan="7" style="text-align:center;">No te deben nada pendiente.</td></tr>
                  <?php else: foreach ($me_deben_pendientes as $d): ?>
                    <tr>
                      <td><b><?php echo $d['id_debt']; ?></b></td>
                      <td><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($d['type_debt']); ?></td>
                      <td><?php echo htmlspecialchars($d['description']); ?></td>
                      <td class="monto-positivo"><?php echo formatMoney($d['amount']); ?></td>
                      <td><span class="badge badge-pendiente">Pendiente</span></td>
                      <td class="acciones">
                        <button class="btn-add" onclick='openEditDebtModal(<?php echo json_encode($d); ?>)'>Editar</button>
                        <button class="btn-del" onclick='confirmDelete("debt", <?php echo json_encode($d); ?>)'>Eliminar</button>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Deudas Pagadas (Yo debo) -->
          <div class="section-block">
            <div class="section-heading">■ Deudas Pagadas (Yo debo)</div>
            <div class="table-container">
              <table>
                <thead><tr><th># ID</th><th>Fecha</th><th>Concepto</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                  <?php if (empty($yo_debo_pagadas)): ?><tr><td colspan="7" style="text-align:center;">No tienes deudas pagadas.</td></tr>
                  <?php else: foreach ($yo_debo_pagadas as $d): ?>
                    <tr>
                      <td><b><?php echo $d['id_debt']; ?></b></td>
                      <td><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($d['type_debt']); ?></td>
                      <td><?php echo htmlspecialchars($d['description']); ?></td>
                      <td class="monto-negativo"><?php echo formatMoney($d['amount']); ?></td>
                      <td><span class="badge badge-pagado">Pagada</span></td>
                      <td class="acciones">
                        <button class="btn-del" onclick='confirmDelete("debt", <?php echo json_encode($d); ?>)'>Eliminar</button>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Me Deben Pagadas -->
          <div class="section-block">
            <div class="section-heading">■ Me Deben Pagadas</div>
            <div class="table-container">
              <table>
                <thead><tr><th># ID</th><th>Fecha</th><th>Concepto</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                  <?php if (empty($me_deben_pagadas)): ?><tr><td colspan="7" style="text-align:center;">No hay deudas pagadas de otros.</td></tr>
                  <?php else: foreach ($me_deben_pagadas as $d): ?>
                    <tr>
                      <td><b><?php echo $d['id_debt']; ?></b></td>
                      <td><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($d['type_debt']); ?></td>
                      <td><?php echo htmlspecialchars($d['description']); ?></td>
                      <td class="monto-positivo"><?php echo formatMoney($d['amount']); ?></td>
                      <td><span class="badge badge-pagado">Pagada</span></td>
                      <td class="acciones">
                        <button class="btn-del" onclick='confirmDelete("debt", <?php echo json_encode($d); ?>)'>Eliminar</button>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Historial de Abonos -->
          <div class="section-block">
            <div class="section-heading">■ Historial de Abonos Recientes</div>
            <div class="table-container">
              <table>
                <thead><tr><th># ID Deuda</th><th>Fecha</th><th>Deuda (Nombre)</th><th>Método</th><th>Descripción</th><th>Monto</th></tr></thead>
                <tbody>
                  <?php if (empty($payments_history)): ?><tr><td colspan="6" style="text-align:center;">No hay abonos registrados.</td></tr>
                  <?php else: foreach ($payments_history as $p): ?>
                    <tr>
                      <td><b><?php echo $p['id_debt']; ?></b></td>
                      <td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($p['debt_name']); ?></td>
                      <td><?php echo htmlspecialchars($p['method']); ?></td>
                      <td><?php echo htmlspecialchars($p['description']); ?></td>
                      <td class="monto-ahorro"><?php echo formatMoney($p['amount']); ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    const track = document.getElementById('carouselTrack');
    const activeCard = track.querySelector('.month-card.active');
    if (activeCard) activeCard.scrollIntoView({ behavior: 'auto', inline: 'start', block: 'nearest' });

    document.getElementById('prevMonth').addEventListener('click', () => track.scrollBy({ left: -200, behavior: 'smooth' }));
    document.getElementById('nextMonth').addEventListener('click', () => track.scrollBy({ left: 200, behavior: 'smooth' }));

    // Global SWAL Config
    const swalConfig = {
        customClass: {
            container: 'premium-swal-container',
            popup: 'premium-swal-popup',
            header: 'premium-swal-header',
            title: 'premium-swal-title',
            htmlContainer: 'premium-swal-html',
            actions: 'premium-swal-actions',
            confirmButton: 'premium-confirm-btn',
            cancelButton: 'premium-cancel-btn'
        },
        buttonsStyling: false,
        background: 'transparent',
        showCancelButton: true,
        cancelButtonText: 'Cancelar'
    };

    async function sendData(formData) {
        try {
            const response = await fetch('modulos/dashboard_actions.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.status === 'success') {
                Swal.fire({ 
                    ...swalConfig,
                    icon: 'success', 
                    title: '¡Éxito!', 
                    text: data.message, 
                    timer: 1500, 
                    showConfirmButton: false,
                    showCancelButton: false,
                    customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-success' }
                }).then(() => location.reload());
            } else {
                Swal.fire({ 
                    ...swalConfig, 
                    icon: 'error', 
                    title: 'Error', 
                    text: data.message, 
                    showCancelButton: false,
                    customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
                });
            }
        } catch (error) { 
            Swal.fire({ 
                ...swalConfig, 
                icon: 'error', 
                title: 'Error', 
                text: 'No se pudo conectar con el servidor.', 
                showCancelButton: false,
                customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
            }); 
        }
    }

    // --- HELPER: CONFIRMATION STEP ---
    async function confirmAction(title, iconHtml, rows, confirmText, btnClass, swalClass = '') {
        let html = `
            <div class="swal-icon-circle ${iconHtml}">
                ${iconHtml.includes('add') ? '+' : iconHtml.includes('edit') ? '✎' : '🗑'}
            </div>
            <div class="premium-summary">
                ${rows.map(r => `
                    <div class="premium-summary-row">
                        <span class="ps-label">${r.label}</span>
                        <span class="ps-value ${r.class || ''}">${r.value}</span>
                    </div>
                `).join('')}
            </div>
        `;

        return Swal.fire({
            ...swalConfig,
            title: title,
            html: html,
            confirmButtonText: confirmText,
            customClass: { 
                ...swalConfig.customClass, 
                confirmButton: `premium-confirm-btn ${btnClass}`,
                popup: `premium-swal-popup ${swalClass}`
            }
        });
    }

    // --- FUNCIONES DE ELIMINAR ---
    async function confirmDelete(type, data) {
        const desc = data.description || (type === 'save' ? `Ahorro de $${parseFloat(data.amount).toLocaleString()}` : 'Sin descripción');
        const rows = [
            { label: 'Tipo', value: type.charAt(0).toUpperCase() + type.slice(1), class: 'ps-badge' },
            { label: 'Descripción', value: desc },
            { label: 'Monto', value: `$${parseFloat(data.amount).toLocaleString()}`, class: 'monto-negativo' }
        ];

        const res = await confirmAction('¿Eliminar este registro?', 'swal-icon-del', rows, 'Sí, eliminar', 'btn-rose', 'swal-error');
        if (res.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('type', type);
            formData.append('id', data.id_movement || data.id_save || data.id_necessary || data.id_debt);
            sendData(formData);
        }
    }

    // --- FUNCIONES DE EDITAR ---
    function openEditMovementModal(data) {
        const incomeCats = ['Salario', 'Honorarios', 'Ventas', 'Inversiones', 'Subsidios', 'Otro'];
        const expenseCats = ['Alimentación / Mercado', 'Transporte', 'Servicios Públicos', 'Arriendo / Hipoteca', 'Salud', 'Educación', 'Ocio', 'Otro'];
        
        const cats = data.type === 'Ingreso' ? incomeCats : expenseCats;
        const isCustom = !cats.includes(data.category) && data.category !== 'Otro';
        
        let optionsHtml = '';
        cats.forEach(c => {
            const selected = (c === data.category || (c === 'Otro' && isCustom)) ? 'selected' : '';
            optionsHtml += `<option value="${c}" ${selected}>${c}</option>`;
        });

        Swal.fire({
            ...swalConfig,
            title: 'Editar ' + data.type,
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Categoría:</label>
                    <select id="ed_category" class="premium-swal-input">
                        ${optionsHtml}
                    </select>
                    <div id="ed_other_container" style="display:${isCustom || data.category === 'Otro' ? 'block' : 'none'};">
                        <input type="text" id="ed_other_category" class="premium-swal-input" value="${isCustom ? data.category : ''}" placeholder="¿Cuál categoría?">
                    </div>
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="ed_amount" class="premium-swal-input" value="${formatNumberCOP(data.amount.toString().split('.')[0])}">
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="ed_description" class="premium-swal-input" value="${data.description}">
                </div>
            `,
            didOpen: () => {
                const catSelect = document.getElementById('ed_category');
                const otherContainer = document.getElementById('ed_other_container');
                catSelect.addEventListener('change', () => {
                    otherContainer.style.display = (catSelect.value === 'Otro') ? 'block' : 'none';
                });
            },
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-indigo', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                let newCat = document.getElementById('ed_category').value;
                if (newCat === 'Otro') {
                    newCat = document.getElementById('ed_other_category').value || 'Otro';
                }
                const newAmt = unformatNumberCOP(document.getElementById('ed_amount').value);
                const newDesc = document.getElementById('ed_description').value;

                const rows = [
                    { label: 'De:', value: `${data.category} | $${parseFloat(data.amount).toLocaleString()}`, class: 'ps-label' },
                    { label: 'A:', value: `${newCat} | $${parseFloat(newAmt).toLocaleString()}`, class: 'monto-positivo' },
                    { label: 'Descripción:', value: newDesc }
                ];

                const finalRes = await confirmAction('¿Confirmar cambios?', 'swal-icon-edit', rows, 'Sí, actualizar', 'btn-indigo', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'edit_movement');
                    formData.append('id', data.id_movement);
                    formData.append('category', newCat);
                    formData.append('amount', newAmt);
                    formData.append('description', newDesc);
                    sendData(formData);
                }
            }
        });
    }

    function openEditSaveModal(data) {
        Swal.fire({
            ...swalConfig,
            title: 'Editar Ahorro',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="ed_amount" class="premium-swal-input" value="${formatNumberCOP(data.amount.toString().split('.')[0])}">
                </div>
            `,
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-indigo', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const newAmt = unformatNumberCOP(document.getElementById('ed_amount').value);
                const rows = [
                    { label: 'Original', value: `$${parseFloat(data.amount).toLocaleString()}` },
                    { label: 'Nuevo', value: `$${parseFloat(newAmt).toLocaleString()}`, class: 'monto-ahorro' }
                ];
                const finalRes = await confirmAction('¿Actualizar ahorro?', 'swal-icon-edit', rows, 'Confirmar', 'btn-indigo', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'edit_save');
                    formData.append('id', data.id_save);
                    formData.append('amount', newAmt);
                    sendData(formData);
                }
            }
        });
    }

    function openEditNecessaryModal(data) {
        const standardCats = ['Vivienda', 'Servicios', 'Salud', 'Educación'];
        const isCustom = !standardCats.includes(data.category) && data.category !== 'Otros';

        Swal.fire({
            ...swalConfig,
            title: 'Editar Gasto Necesario',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Categoría:</label>
                    <select id="ed_category" class="premium-swal-input">
                        <option value="Vivienda" ${data.category === 'Vivienda' ? 'selected' : ''}>Vivienda</option>
                        <option value="Servicios" ${data.category === 'Servicios' ? 'selected' : ''}>Servicios</option>
                        <option value="Salud" ${data.category === 'Salud' ? 'selected' : ''}>Salud</option>
                        <option value="Educación" ${data.category === 'Educación' ? 'selected' : ''}>Educación</option>
                        <option value="Otros" ${data.category === 'Otros' || isCustom ? 'selected' : ''}>Otros</option>
                    </select>
                    <div id="ed_other_container" style="display:${isCustom || data.category === 'Otros' ? 'block' : 'none'};">
                        <input type="text" id="ed_other_category" class="premium-swal-input" value="${isCustom ? data.category : ''}" placeholder="¿Cuál gasto necesario?">
                    </div>
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="ed_amount" class="premium-swal-input" value="${formatNumberCOP(data.amount.toString().split('.')[0])}">
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="ed_description" class="premium-swal-input" value="${data.description}">
                    <label class="ps-label">Estado:</label>
                    <select id="ed_state" class="premium-swal-input">
                        <option value="Pendiente" ${data.state === 'Pendiente' ? 'selected' : ''}>Pendiente</option>
                        <option value="Pagado" ${data.state === 'Pagado' ? 'selected' : ''}>Pagado</option>
                    </select>
                </div>
            `,
            didOpen: () => {
                const catSelect = document.getElementById('ed_category');
                const otherContainer = document.getElementById('ed_other_container');
                catSelect.addEventListener('change', () => {
                    otherContainer.style.display = (catSelect.value === 'Otros') ? 'block' : 'none';
                });
            },
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-indigo', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                let newCat = document.getElementById('ed_category').value;
                if (newCat === 'Otros') {
                    newCat = document.getElementById('ed_other_category').value || 'Otros';
                }
                const newAmt = unformatNumberCOP(document.getElementById('ed_amount').value);
                const newDesc = document.getElementById('ed_description').value;
                const newState = document.getElementById('ed_state').value;
                const rows = [
                    { label: 'Categoría', value: newCat },
                    { label: 'Monto', value: `$${parseFloat(newAmt).toLocaleString()}` },
                    { label: 'Descripción', value: newDesc },
                    { label: 'Estado', value: newState, class: newState === 'Pagado' ? 'monto-positivo' : 'monto-negativo' }
                ];
                const finalRes = await confirmAction('¿Actualizar gasto?', 'swal-icon-edit', rows, 'Confirmar', 'btn-indigo', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'edit_necessary');
                    formData.append('id', data.id_necessary);
                    formData.append('amount', newAmt);
                    formData.append('state', newState);
                    formData.append('category', newCat);
                    formData.append('description', newDesc);
                    sendData(formData);
                }
            }
        });
    }

    function openEditDebtModal(data) {
        Swal.fire({
            ...swalConfig,
            title: 'Editar Deuda',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="ed_amount" class="premium-swal-input" value="${formatNumberCOP(data.amount.toString().split('.')[0])}">
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="ed_description" class="premium-swal-input" value="${data.description}">
                </div>
            `,
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-indigo', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const newAmt = unformatNumberCOP(document.getElementById('ed_amount').value);
                const newDesc = document.getElementById('ed_description').value;
                const rows = [
                    { label: 'Deuda', value: newDesc },
                    { label: 'Nuevo Saldo', value: `$${parseFloat(newAmt).toLocaleString()}`, class: 'monto-negativo' }
                ];
                const finalRes = await confirmAction('¿Actualizar deuda?', 'swal-icon-edit', rows, 'Confirmar', 'btn-indigo', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'edit_debt');
                    formData.append('id', data.id_debt);
                    formData.append('amount', newAmt);
                    formData.append('description', newDesc);
                    formData.append('type_debt', data.type_debt);
                    sendData(formData);
                }
            }
        });
    }

    // --- FUNCIONES DE AGREGAR ---
    function openAddMovementModal() {
        const incomeCats = ['Salario', 'Honorarios', 'Ventas', 'Inversiones', 'Subsidios', 'Otro'];
        const expenseCats = ['Alimentación / Mercado', 'Transporte', 'Servicios Públicos', 'Arriendo / Hipoteca', 'Salud', 'Educación', 'Ocio', 'Otro'];

        Swal.fire({
            ...swalConfig,
            title: 'Agregar Movimiento',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Tipo:</label>
                    <select id="sw_type" class="premium-swal-input">
                        <option value="Ingreso">Ingreso</option>
                        <option value="Gasto">Gasto</option>
                    </select>
                    <label class="ps-label">Categoría:</label>
                    <select id="sw_category" class="premium-swal-input"></select>
                    <div id="other_category_container" style="display:none;">
                        <input type="text" id="sw_other_category" class="premium-swal-input" placeholder="¿Cuál categoría?">
                    </div>
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="sw_amount" class="premium-swal-input" placeholder="0">
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="sw_description" class="premium-swal-input" placeholder="Nota opcional">
                </div>
            `,
            didOpen: () => {
                const typeSelect = document.getElementById('sw_type');
                const catSelect = document.getElementById('sw_category');
                const otherContainer = document.getElementById('other_category_container');
                const updateCats = () => {
                    const cats = typeSelect.value === 'Ingreso' ? incomeCats : expenseCats;
                    catSelect.innerHTML = cats.map(c => `<option value="${c}">${c}</option>`).join('');
                    otherContainer.style.display = 'none';
                };
                typeSelect.addEventListener('change', updateCats);
                catSelect.addEventListener('change', () => otherContainer.style.display = catSelect.value === 'Otro' ? 'block' : 'none');
                updateCats();
            },
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-emerald', popup: 'premium-swal-popup swal-success' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                let cat = document.getElementById('sw_category').value;
                if (cat === 'Otro') cat = document.getElementById('sw_other_category').value;
                const type = document.getElementById('sw_type').value;
                const amt = unformatNumberCOP(document.getElementById('sw_amount').value);
                const desc = document.getElementById('sw_description').value;

                if (!amt) { 
                    Swal.fire({ 
                        ...swalConfig, 
                        title: 'Error', 
                        text: 'El monto es obligatorio', 
                        icon: 'error', 
                        showCancelButton: false,
                        customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
                    }); 
                    return; 
                }

                const rows = [
                    { label: 'Tipo', value: type, class: 'ps-badge' },
                    { label: 'Categoría', value: cat },
                    { label: 'Descripción', value: desc || 'Sin descripción' },
                    { label: 'Monto', value: `$${parseFloat(amt).toLocaleString()}`, class: type === 'Ingreso' ? 'monto-positivo' : 'monto-negativo' }
                ];

                const finalRes = await confirmAction('¿Agregar este movimiento?', 'swal-icon-add', rows, 'Sí, agregar', 'btn-emerald', 'swal-success');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'add_movement');
                    formData.append('type', type);
                    formData.append('category', cat);
                    formData.append('amount', amt);
                    formData.append('description', desc);
                    sendData(formData);
                }
            }
        });
    }

    function openAddSaveModal() {
        Swal.fire({
            ...swalConfig,
            title: 'Agregar Ahorro',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Monto a ahorrar:</label>
                    <input type="text" id="sw_amount" class="premium-swal-input" placeholder="0">
                </div>
            `,
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-amber', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const amt = unformatNumberCOP(document.getElementById('sw_amount').value);
                if (!amt) {
                    Swal.fire({ ...swalConfig, title: 'Error', text: 'El monto es obligatorio', icon: 'error', showCancelButton: false, customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' } });
                    return;
                }
                const rows = [{ label: 'Monto Ahorro', value: `$${parseFloat(amt).toLocaleString()}`, class: 'monto-ahorro' }];
                const finalRes = await confirmAction('¿Confirmar ahorro?', 'swal-icon-add', rows, 'Sí, guardar', 'btn-amber', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'add_save');
                    formData.append('amount', amt);
                    sendData(formData);
                }
            }
        });
    }

    function openAddNecessaryModal() {
        Swal.fire({
            ...swalConfig,
            title: 'Agregar Gasto Necesario',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Categoría:</label>
                    <select id="sw_category" class="premium-swal-input">
                        <option value="Vivienda">Vivienda</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Salud">Salud</option>
                        <option value="Educación">Educación</option>
                        <option value="Otros">Otros</option>
                    </select>
                    <div id="sw_other_container" style="display:none;">
                        <input type="text" id="sw_other_category" class="premium-swal-input" placeholder="¿Cuál gasto necesario?">
                    </div>
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="sw_amount" class="premium-swal-input" placeholder="0">
                    <label class="ps-label">Estado:</label>
                    <select id="sw_state" class="premium-swal-input">
                        <option value="Pendiente">Pendiente</option>
                        <option value="Pagado">Pagado</option>
                    </select>
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="sw_description" class="premium-swal-input" placeholder="Nota opcional">
                </div>
            `,
            didOpen: () => {
                const catSelect = document.getElementById('sw_category');
                const otherContainer = document.getElementById('sw_other_container');
                catSelect.addEventListener('change', () => {
                    otherContainer.style.display = (catSelect.value === 'Otros') ? 'block' : 'none';
                });
            },
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-indigo', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                let cat = document.getElementById('sw_category').value;
                if (cat === 'Otros') {
                    cat = document.getElementById('sw_other_category').value || 'Otros';
                }
                const amt = unformatNumberCOP(document.getElementById('sw_amount').value);
                const state = document.getElementById('sw_state').value;
                const desc = document.getElementById('sw_description').value;
                if (!amt) {
                    Swal.fire({ ...swalConfig, title: 'Error', text: 'El monto es obligatorio', icon: 'error', showCancelButton: false, customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' } });
                    return;
                }
                const rows = [
                    { label: 'Categoría', value: cat },
                    { label: 'Monto', value: `$${parseFloat(amt).toLocaleString()}` },
                    { label: 'Estado', value: state, class: 'ps-badge' },
                    { label: 'Descripción', value: desc || 'Sin descripción' }
                ];
                const finalRes = await confirmAction('¿Agregar gasto necesario?', 'swal-icon-add', rows, 'Confirmar', 'btn-indigo', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'add_necessary');
                    formData.append('category', cat);
                    formData.append('amount', amt);
                    formData.append('state', state);
                    formData.append('description', desc);
                    sendData(formData);
                }
            }
        });
    }

    function openAddDebtModal() {
        Swal.fire({
            ...swalConfig,
            title: 'Nueva Deuda',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Concepto:</label>
                    <select id="sw_concept" class="premium-swal-input">
                        <option value="Yo debo">Yo debo (Deuda)</option>
                        <option value="Me deben">Me deben (Préstamo)</option>
                    </select>
                    <label class="ps-label">Monto:</label>
                    <input type="text" id="sw_amount" class="premium-swal-input" placeholder="0">
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="sw_description" class="premium-swal-input" placeholder="¿A quién?">
                </div>
            `,
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-indigo', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const concept = document.getElementById('sw_concept').value;
                const amt = unformatNumberCOP(document.getElementById('sw_amount').value);
                const desc = document.getElementById('sw_description').value;
                if (!amt) {
                    Swal.fire({ ...swalConfig, title: 'Error', text: 'El monto es obligatorio', icon: 'error', showCancelButton: false, customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' } });
                    return;
                }
                const rows = [
                    { label: 'Tipo', value: concept, class: 'ps-badge' },
                    { label: 'Descripción', value: desc },
                    { label: 'Monto', value: `$${parseFloat(amt).toLocaleString()}` }
                ];
                const finalRes = await confirmAction('¿Registrar deuda?', 'swal-icon-add', rows, 'Confirmar', 'btn-indigo', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'add_debt');
                    formData.append('concept', concept);
                    formData.append('amount', amt);
                    formData.append('description', desc);
                    formData.append('type_debt', 'Efectivo');
                    sendData(formData);
                }
            }
        });
    }

    function openAddPaymentModal() {
        const debtOptions = <?php 
            $pending = array_merge($yo_debo_pendientes, $me_deben_pendientes);
            echo json_encode(array_map(fn($d) => [
                'id' => $d['id_debt'], 
                'text' => 'ID ' . $d['id_debt'] . ' - ' . $d['description'],
                'max' => (float)$d['amount']
            ], $pending));
        ?>;
        
        if (debtOptions.length === 0) {
            Swal.fire({ 
                ...swalConfig, 
                title: 'Atención', 
                text: 'No hay deudas pendientes.', 
                icon: 'info', 
                showCancelButton: false,
                customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-info' }
            });
            return;
        }

        let optionsHtml = debtOptions.map(o => `<option value="${o.id}">${o.text}</option>`).join('');

        Swal.fire({
            ...swalConfig,
            title: 'Abonar a Deuda',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left;">
                    <label class="ps-label">Seleccionar Deuda:</label>
                    <select id="sw_id_debt" class="premium-swal-input">${optionsHtml}</select>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label class="ps-label">Monto del Abono:</label>
                        <span id="sw_current_debt" style="font-size: 0.85rem; color: #60a5fa; font-weight: 700;">Saldo: $0.00</span>
                    </div>
                    <input type="text" id="sw_amount" class="premium-swal-input" placeholder="0">
                    <span id="sw_amount_error" style="color: #ef4444; font-size: 0.8rem; font-weight: 600; display: none; margin-top: -5px;">⚠ El monto supera la deuda pendiente</span>
                    
                    <label class="ps-label">Método:</label>
                    <select id="sw_method" class="premium-swal-input">
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                    <label class="ps-label">Descripción:</label>
                    <input type="text" id="sw_pay_description" class="premium-swal-input" placeholder="Nota del abono">
                </div>
            `,
            didOpen: () => {
                const idSelect = document.getElementById('sw_id_debt');
                const amtInput = document.getElementById('sw_amount');
                const errorSpan = document.getElementById('sw_amount_error');
                const currentDebtSpan = document.getElementById('sw_current_debt');
                const confirmBtn = Swal.getConfirmButton();

                const validate = () => {
                    const id = idSelect.value;
                    const amt = parseFloat(unformatNumberCOP(amtInput.value)) || 0;
                    const debt = debtOptions.find(o => o.id == id);

                    // Update current balance display
                    currentDebtSpan.textContent = `Cantidad de deuda: $${debt.max.toLocaleString('es-CO', { minimumFractionDigits: 2 })}`;

                    if (amt > debt.max) {
                        errorSpan.style.display = 'block';
                        amtInput.style.borderColor = '#ef4444';
                        confirmBtn.disabled = true;
                        confirmBtn.style.opacity = '0.5';
                    } else {
                        errorSpan.style.display = 'none';
                        amtInput.style.borderColor = 'rgba(59, 130, 246, 0.2)';
                        confirmBtn.disabled = false;
                        confirmBtn.style.opacity = '1';
                    }
                };

                amtInput.addEventListener('input', validate);
                idSelect.addEventListener('change', validate);
                validate(); // Initial call
            },
            confirmButtonText: 'Siguiente',
            customClass: { ...swalConfig.customClass, confirmButton: 'premium-confirm-btn btn-amber', popup: 'premium-swal-popup swal-info' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const id = document.getElementById('sw_id_debt').value;
                const amt = parseFloat(unformatNumberCOP(document.getElementById('sw_amount').value));
                const desc = document.getElementById('sw_pay_description').value;
                const method = document.getElementById('sw_method').value;
                const debt = debtOptions.find(o => o.id == id);

                if (!amt || amt <= 0 || amt > debt.max) {
                    Swal.fire({
                        ...swalConfig,
                        title: 'Error',
                        text: 'Monto no válido o supera la deuda.',
                        icon: 'error',
                        showCancelButton: false,
                        customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
                    });
                    return;
                }

                const rows = [
                    { label: 'Deuda', value: debt.text },
                    { label: 'Abono', value: `$${amt.toLocaleString()}`, class: 'monto-ahorro' },
                    { label: 'Descripción', value: desc || 'Sin descripción' }
                ];
                const finalRes = await confirmAction('¿Confirmar abono?', 'swal-icon-edit', rows, 'Sí, abonar', 'btn-amber', 'swal-info');
                if (finalRes.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'add_payment');
                    formData.append('id_debt', id);
                    formData.append('amount', amt);
                    formData.append('method', method);
                    formData.append('description', desc);
                    sendData(formData);
                }
            }
        });
    }
    // --- HELPERS PARA FORMATO DE MONEDA (COP) ---
    function formatNumberCOP(n) {
        let str = n.toString().replace(/\D/g, "");
        return str.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function unformatNumberCOP(s) {
        return s.replace(/\./g, "");
    }

    // Escuchar cambios en cualquier input de monto (clase premium-swal-input)
    document.addEventListener('input', (e) => {
        if (e.target.id && (e.target.id.includes('amount') || e.target.id.includes('amt'))) {
            const cursorPosition = e.target.selectionStart;
            const originalLength = e.target.value.length;
            
            const formatted = formatNumberCOP(e.target.value);
            e.target.value = formatted;

            // Mantener posición del cursor
            const newLength = formatted.length;
            e.target.setSelectionRange(cursorPosition + (newLength - originalLength), cursorPosition + (newLength - originalLength));
        }
    });

  </script>
</body>
