<?php
require_once __DIR__ . "/../../config/db.php";

$user_id = $_SESSION['user_id'];

// Obtener mes y año seleccionados (inicialmente el actual)
$mes_sel = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio_sel = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Función para formatear moneda
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return '$' . number_format($amount, 2, ',', '.');
    }
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
    $stmt = $pdo->prepare("SELECT type, SUM(amount::NUMERIC) as total FROM movements WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? GROUP BY type");
    $stmt->execute([$user_id, $num, $anio_sel]);
    $movs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $ingreso = $movs['Ingreso'] ?? 0;
    $gasto = $movs['Gasto'] ?? 0;

    // Ahorros
    $stmt = $pdo->prepare("SELECT SUM(amount::NUMERIC) FROM save WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ?");
    $stmt->execute([$user_id, $num, $anio_sel]);
    $ahorro = $stmt->fetchColumn() ?? 0;

    // Gastos Necesarios
    $stmt = $pdo->prepare("SELECT state, SUM(amount::NUMERIC) as total FROM necessary_expense WHERE id_user = ? AND EXTRACT(MONTH FROM created_at) = ? AND EXTRACT(YEAR FROM created_at) = ? GROUP BY state");
    $stmt->execute([$user_id, $num, $anio_sel]);
    $nec_sums = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $pagados = $nec_sums['Pagado'] ?? 0;
    $pendientes = $nec_sums['Pendiente'] ?? 0;

    $deberia_tener = $ingreso - $gasto - $ahorro - $pagados;
    $puedo_gastar = $deberia_tener - $pendientes;

    $resumen_mensual[$num] = [
        'nombre' => $nombre,
        'ingreso' => (float)$ingreso,
        'gasto' => (float)$gasto,
        'ahorro' => (float)$ahorro,
        'necesarios_pagados' => (float)$pagados,
        'necesarios_pendientes' => (float)$pendientes,
        'deberia_tener' => (float)$deberia_tener,
        'puedo_gastar' => (float)$puedo_gastar
    ];
}

// --- LÓGICA DE DEUDAS (GLOBAL) ---
// 1. Obtener saldos pendientes actuales
$stmt = $pdo->prepare("SELECT concept, SUM(amount::NUMERIC) as total FROM debts WHERE id_user = ? AND state = 'Pendiente' GROUP BY concept");
$stmt->execute([$user_id]);
$pendientes_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$deuda_pend = (float)($pendientes_raw['Yo debo'] ?? 0);
$me_deben_pend = (float)($pendientes_raw['Me deben'] ?? 0);

// 2. Obtener lo que YA SE PAGÓ (sumando los abonos realizados)
$stmt = $pdo->prepare("
    SELECT d.concept, SUM(p.amount::NUMERIC) as total_pagado 
    FROM pay_debt p
    JOIN debts d ON p.id_debt = d.id_debt
    WHERE d.id_user = ?
    GROUP BY d.concept
");
$stmt->execute([$user_id]);
$pagados_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$deuda_pag = (float)($pagados_raw['Yo debo'] ?? 0);
$me_deben_pag = (float)($pagados_raw['Me deben'] ?? 0);
?>

<body>
  <div class="dashboard-wrapper">
    <div class="dashboard-banner">
      <h1 class="banner-title">Gráficos y Resumen</h1>
      <p class="banner-subtitle">Análisis visual de tus finanzas en tiempo real.</p>
    </div>

    <!-- Carrusel -->
    <div class="month-selector">
      <div class="carousel-wrapper">
        <h2 class="section-title">Selecciona un mes (<?php echo $anio_sel; ?>)</h2>
        <div class="carousel-outer">
          <button class="btn-nav btn-prev" id="prevMonth">‹</button>
          <div class="carousel-track" id="carouselTrack">
            <?php foreach ($resumen_mensual as $num => $data): ?>
              <div class="month-card <?php echo ($num == $mes_sel) ? 'active' : ''; ?>" 
                   onclick="selectMonth(<?php echo $num; ?>)" id="card-<?php echo $num; ?>">
                <div class="month-card-title"><?php echo $data['nombre'] . ' ' . $anio_sel; ?></div>
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

    <!-- Sección de Gráficos -->
    <div id="no-data-msg" class="card" style="display: none; text-align: center; padding: 60px; margin-top: 20px;">
        <div class="swal-icon-circle swal-icon-info">ℹ</div>
        <h2 class="section-title">Sin datos para este mes</h2>
        <p style="color: #94a3b8;">No se encontraron movimientos registrados en este periodo.</p>
    </div>

    <div class="charts-section" id="charts-container">
      <div class="chart-card">
        <h3 class="chart-title" id="title-balance">Balance de Mes</h3>
        <div class="chart-canvas-wrapper">
          <canvas id="chartBalance"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <h3 class="chart-title">Ingresos vs Salidas</h3>
        <div class="chart-canvas-wrapper">
          <canvas id="chartBarras"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <h3 class="chart-title">Distribución de Flujo</h3>
        <div class="chart-canvas-wrapper">
          <canvas id="chartDonut1"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <h3 class="chart-title">Estado Global de Deudas</h3>
        <div class="chart-canvas-wrapper">
          <canvas id="chartDonut2"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const allData = <?php echo json_encode($resumen_mensual); ?>;
    const debtData = {
        pend: <?php echo $deuda_pend; ?>,
        pag: <?php echo $deuda_pag; ?>,
        me_pend: <?php echo $me_deben_pend; ?>,
        me_pag: <?php echo $me_deben_pag; ?>
    };

    let chartBalance, chartBarras, chartDonut1, chartDonut2;

    function initCharts() {
        const ctx1 = document.getElementById('chartBalance').getContext('2d');
        chartBalance = new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Disponible', 'Salidas'],
                datasets: [{ data: [0, 0], backgroundColor: ['#4ade80', '#1e293b'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } }
        });

        const ctx2 = document.getElementById('chartBarras').getContext('2d');
        chartBarras = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Mes'],
                datasets: [
                    { label: 'Ingresos', data: [0], backgroundColor: '#4ade80', borderRadius: 8 },
                    { label: 'Ahorros', data: [0], backgroundColor: '#a78bfa', borderRadius: 8 },
                    { label: 'Gastos', data: [0], backgroundColor: '#f87171', borderRadius: 8 },
                    { label: 'Nec. Pagados', data: [0], backgroundColor: '#60a5fa', borderRadius: 8 },
                    { label: 'Nec. Pendientes', data: [0], backgroundColor: '#facc15', borderRadius: 8 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const ctx3 = document.getElementById('chartDonut1').getContext('2d');
        chartDonut1 = new Chart(ctx3, {
            type: 'pie',
            data: {
                labels: ['Ingresos', 'Gastos Totales', 'Ahorros'],
                datasets: [{ data: [0, 0, 0], backgroundColor: ['#4ade80', '#f87171', '#60a5fa'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } }
        });

        const ctx4 = document.getElementById('chartDonut2').getContext('2d');
        const hasDebts = (debtData.pend + debtData.pag + debtData.me_pend + debtData.me_pag) > 0;
        
        chartDonut2 = new Chart(ctx4, {
            type: 'doughnut',
            data: {
                labels: hasDebts ? ['Yo Debo (Pend)', 'Yo Debo (Pag)', 'Me Deben (Pend)', 'Me Deben (Pag)'] : ['Sin deudas'],
                datasets: [{
                    data: hasDebts ? [debtData.pend, debtData.pag, debtData.me_pend, debtData.me_pag] : [1],
                    backgroundColor: hasDebts ? ['#ef4444', '#16a34a', '#f59e0b', '#2563eb'] : ['#1e293b'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } }
        });
    }

    function selectMonth(num) {
        // Actualizar UI
        document.querySelectorAll('.month-card').forEach(c => c.classList.remove('active'));
        document.getElementById('card-' + num).classList.add('active');

        const data = allData[num];
        const hayDatos = (data.ingreso > 0 || data.gasto > 0 || data.ahorro > 0 || data.necesarios_pagados > 0 || data.necesarios_pendientes > 0);

        if (!hayDatos) {
            document.getElementById('charts-container').style.display = 'none';
            document.getElementById('no-data-msg').style.display = 'block';
        } else {
            document.getElementById('charts-container').style.display = 'grid';
            document.getElementById('no-data-msg').style.display = 'none';

            document.getElementById('title-balance').textContent = 'Balance de ' + data.nombre;

            // Update Balance
            chartBalance.data.datasets[0].data = [data.deberia_tener, data.gasto + data.ahorro + data.necesarios_pagados];
            chartBalance.update();

            // Update Barras
            chartBarras.data.labels = [data.nombre];
            chartBarras.data.datasets[0].data = [data.ingreso];
            chartBarras.data.datasets[1].data = [data.ahorro];
            chartBarras.data.datasets[2].data = [data.gasto];
            chartBarras.data.datasets[3].data = [data.necesarios_pagados];
            chartBarras.data.datasets[4].data = [data.necesarios_pendientes];
            chartBarras.update();

            // Update Pie
            chartDonut1.data.datasets[0].data = [data.ingreso, data.gasto + data.necesarios_pagados, data.ahorro];
            chartDonut1.update();
        }
    }

    // Inicializar
    initCharts();
    selectMonth(<?php echo $mes_sel; ?>);

    // Carrusel Nav
    const track = document.getElementById('carouselTrack');
    document.getElementById('prevMonth').addEventListener('click', () => track.scrollBy({ left: -300, behavior: 'smooth' }));
    document.getElementById('nextMonth').addEventListener('click', () => track.scrollBy({ left: 300, behavior: 'smooth' }));
    
    // Auto-scroll to active
    setTimeout(() => {
        const active = track.querySelector('.month-card.active');
        if (active) active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }, 100);
  </script>
</body>
