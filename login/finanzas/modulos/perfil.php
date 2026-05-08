<?php
require_once __DIR__ . "/../../config/db.php";

$user_id = $_SESSION['user_id'];

// Obtener datos del usuario
$stmt = $pdo->prepare("
    SELECT u.*, r.name as rol_name 
    FROM users u 
    LEFT JOIN roles r ON u.id_rol = r.id_rol 
    WHERE u.id_user = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "<h1>Error: Usuario no encontrado.</h1>";
    exit;
}
?>

<body>
  <div class="dashboard-wrapper">

    <!-- Hero Banner -->
    <div class="dashboard-banner">
      <h1 class="banner-title">Mi Perfil</h1>
      <p class="banner-subtitle">Consulta y actualiza los datos de tu cuenta.</p>
    </div>

    <!-- Información personal -->
    <div class="perfil-card">
      <h2 class="perfil-title">Información personal</h2>

      <div class="perfil-grid">
        <div class="campo-group">
          <label class="campo-label">Nombre</label>
          <input class="campo-input" type="text" value="<?php echo htmlspecialchars($user['name']); ?>" id="nombreInput" />
        </div>
        <div class="campo-group">
          <label class="campo-label">Apellidos</label>
          <input class="campo-input" type="text" value="<?php echo htmlspecialchars($user['lastname']); ?>" id="apellidosInput" />
        </div>
        <div class="campo-group">
          <label class="campo-label">Usuario</label>
          <input class="campo-input" type="text" value="<?php echo htmlspecialchars($user['user_name']); ?>" id="usuarioInput" />
        </div>
        <div class="campo-group">
          <label class="campo-label">Correo electrónico</label>
          <input class="campo-input" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" id="correoInput" readonly/>
        </div>
      </div>

      <h2 class="perfil-title" style="margin-top: 30px;">Seguridad</h2>
      <div class="perfil-grid perfil-grid--pass">
        <div class="campo-group">
          <label class="campo-label">Contraseña actual (requerida para cambios)</label>
          <input class="campo-input" type="password" placeholder="Ingresa tu contraseña actual" id="passActual" />
        </div>
        <div class="campo-group">
          <label class="campo-label">Nueva contraseña</label>
          <input class="campo-input" type="password" placeholder="Mínimo 6 caracteres" id="nuevaPass" />
        </div>
        <div class="campo-group">
          <label class="campo-label">Confirmar nueva contraseña</label>
          <input class="campo-input" type="password" placeholder="Repítela aquí" id="confirmPass" />
        </div>
      </div>

      <div class="perfil-actions">
        <button class="btn-agregar" id="guardarBtn">Guardar cambios</button>
      </div>
    </div>

    <!-- Datos de la cuenta -->
    <div class="perfil-card">
      <h2 class="perfil-title">Detalles de la cuenta</h2>

      <div class="perfil-grid">
        <div class="campo-group">
          <label class="campo-label">Rol</label>
          <div class="campo-readonly"><?php echo htmlspecialchars($user['rol_name'] ?? 'Cliente'); ?></div>
        </div>
        <div class="campo-group">
          <label class="campo-label">Estado</label>
          <div class="campo-readonly"><?php echo $user['user_active'] ? 'Activo' : 'Pendiente'; ?></div>
        </div>
        <div class="campo-group">
          <label class="campo-label">Fecha de registro</label>
          <div class="campo-readonly"><?php echo $user['created_at']; ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    const swalConfig = {
        background: '#151f35',
        color: '#ffffff',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#1e293b',
        customClass: {
            popup: 'premium-swal-popup',
            confirmButton: 'premium-confirm-btn',
            cancelButton: 'premium-cancel-btn'
        }
    };

    document.getElementById('guardarBtn').addEventListener('click', async () => {
        const nombre = document.getElementById('nombreInput').value;
        const apellidos = document.getElementById('apellidosInput').value;
        const usuario = document.getElementById('usuarioInput').value;
        const correo = document.getElementById('correoInput').value;
        const nuevaPass = document.getElementById('nuevaPass').value;
        const confirmPass = document.getElementById('confirmPass').value;
        const passActual = document.getElementById('passActual').value;

        if (!nombre || !apellidos || !usuario || !correo || !passActual) {
            Swal.fire({
                ...swalConfig,
                title: 'Error',
                text: 'Por favor, ingresa tu contraseña actual.',
                icon: 'error',
                customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
            });
            return;
        }

        if (nuevaPass && nuevaPass !== confirmPass) {
            Swal.fire({
                ...swalConfig,
                title: 'Error',
                text: 'Las nuevas contraseñas no coinciden.',
                icon: 'error',
                customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
            });
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('name', nombre);
        formData.append('lastname', apellidos);
        formData.append('user_name', usuario);
        formData.append('email', correo);
        formData.append('current_password', passActual);
        if (nuevaPass) formData.append('new_password', nuevaPass);

        try {
            const response = await fetch('modulos/dashboard_actions.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                Swal.fire({
                    ...swalConfig,
                    title: '¡Éxito!',
                    text: data.message + ' Por seguridad, debes iniciar sesión nuevamente.',
                    icon: 'success',
                    customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-success' }
                }).then(() => {
                    window.location.href = '../logout.php';
                });
            } else {
                Swal.fire({
                    ...swalConfig,
                    title: 'Error',
                    text: data.message,
                    icon: 'error',
                    customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
                });
            }
        } catch (error) {
            console.error(error);
            Swal.fire({
                ...swalConfig,
                title: 'Error fatal',
                text: 'Hubo un problema al conectar con el servidor.',
                icon: 'error',
                customClass: { ...swalConfig.customClass, popup: 'premium-swal-popup swal-error' }
            });
        }
    });
  </script>
</body>
