<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="styles/todos.css">
</head>

<body>

  <aside class="sidebar" id="sidebar">
    <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Alternar menú">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>

    <div class="profile-section">
      <div class="avatar">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
        </svg>
      </div>
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Usuario'); ?></div>
        <div class="user-role">Cliente</div>
      </div>
      <a href="../logout.php" class="logout-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
        Cerrar sesión
      </a>
    </div>

    <hr class="sidebar-divider">

    <nav class="nav-section">
      <div class="nav-section-group">
        <div class="section-label">Principal</div>

        <a class="nav-item" href="index.php?mod=dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
          </svg>
          Dashboard
        </a>

        <a class="nav-item" href="index.php?mod=HistorialGraficos">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
          </svg>
          Gráficos y resumen
        </a>

        <a class="nav-item" href="index.php?mod=perfil">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
          Perfil
        </a>
      </div>

      <hr class="sidebar-divider">

      <div class="nav-section-group">
        <div class="section-label">Movimientos</div>

        <a class="nav-item" href="index.php?mod=HistorialAcciones">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          Historial de movimientos
        </a>

        <a class="nav-item" href="index.php?mod=consultar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          Consultar
        </a>
      </div>
    </nav>
  </aside>

  <div class="main-content" id="main-content">

    <?php
    $mod = $_GET['mod'] ?? '';
    switch ($mod) {
      case '':
      case 'dashboard':
        require_once __DIR__ . "/modulos/dashboard.php";
        break;
      case 'HistorialGraficos':
        require_once __DIR__ . "/modulos/HistorialGraficos.php";
        break;
      case 'perfil':
        require_once __DIR__ . "/modulos/perfil.php";
        break;
      case 'HistorialAcciones':
        require_once __DIR__ . "/modulos/HistorialAcciones.php";
        break;
      case 'consultar':
        require_once __DIR__ . "/modulos/consultar.php";
        break;
      default:
        echo "<h1>404 - Página no encontrada</h1>";
    }
    ?>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const mainContent = document.getElementById('main-content');

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');
    });

    // Marcar el link activo según el parámetro 'mod' en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentMod = urlParams.get('mod') || 'dashboard';

    document.querySelectorAll('.nav-item').forEach(link => {
      const href = link.getAttribute('href');
      if (href) {
        const queryString = href.includes('?') ? href.split('?')[1] : '';
        const linkParams = new URLSearchParams(queryString);
        const linkMod = linkParams.get('mod') || 'dashboard';
        
        if (linkMod === currentMod) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      }
    });
  </script>

</body>

</html>