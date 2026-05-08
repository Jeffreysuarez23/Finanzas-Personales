<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .login-bg {
            min-height: 100vh;
            background: #060910;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 0 60px rgba(59, 130, 246, 0.08);
        }

        /* ── Header azul oscuro ── */
        .login-header {
            background: #1a2744;
            padding: 36px 40px 28px;
            text-align: center;
            border-bottom: 1px solid rgba(59, 130, 246, 0.15);
        }

        .login-title {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #a855f7);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        /* ── Body ── */
        .login-body {
            background: #151f35;
            padding: 36px 40px 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ── Campos ── */
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #e2e8f0;
            text-align: center;
        }

        .field-input {
            background: #0f172a;
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 14px;
            color: #cbd5e1;
            padding: 14px 18px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            box-sizing: border-box;
        }

        .field-input::placeholder {
            color: #475569;
        }

        .field-input:focus {
            border-color: rgba(59, 130, 246, 0.6);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* ── Botón ── */
        .btn-login {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 16px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 6px;
            transition: all 0.25s;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.55);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* ── Links ── */
        .login-links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }

        .login-link {
            font-size: 0.88rem;
            color: #64748b;
            text-decoration: none;
        }

        .login-link a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .login-link a:hover {
            color: #93c5fd;
        }

        .login-link:hover {
            color: #94a3b8;
        }

        /* Mensajes */
        .mensaje-exito {
            color: #4ade80;
            background: rgba(74, 222, 128, 0.1);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
        }

        .mensaje-error {
            color: #f87171;
            background: rgba(248, 113, 113, 0.1);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="login-bg">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Iniciar sesión</h1>
            </div>

            <form class="login-body" id="loginForm">
                <div id="mensajeExito" class="mensaje-exito" style="display: none;"></div>
                <div id="mensajeError" class="mensaje-error" style="display: none;"></div>

                <div class="field-group">
                    <label class="field-label">Usuario o Correo Electrónico</label>
                    <input type="text" class="field-input" id="username" name="username" placeholder="Ej. john.doe@example.com" required />
                </div>

                <div class="field-group">
                    <label class="field-label">Contraseña</label>
                    <input type="password" class="field-input" id="password" name="password" placeholder="••••••••••" required />
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    Iniciar sesión
                </button>

                <div class="login-links">
                    <span class="login-link">¿No tienes una cuenta? <a href="registrar.php">Regístrate</a></span>
                    <a href="#" class="login-link">¿Olvidaste tu contraseña?</a>
                    <a href="/" class="login-link">Dashboard</a>
                </div>
            </form>
        </div>
        <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = document.getElementById('btnLogin');
            const msgExito = document.getElementById('mensajeExito');
            const msgError = document.getElementById('mensajeError');

            btn.disabled = true;
            btn.textContent = 'Iniciando sesión...';
            msgExito.style.display = 'none';
            msgError.style.display = 'none';

            fetch('auth/login_backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Iniciar sesión';

                if (data.status === 'success') {
                    Swal.fire({
                        title: '¡Bienvenido!',
                        text: data.message,
                        icon: 'success',
                        background: '#151f35',
                        color: '#fff',
                        confirmButtonColor: '#2563eb',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.redirect;
                    });
                } else {
                    msgError.textContent = data.message;
                    msgError.style.display = 'block';
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.textContent = 'Iniciar sesión';
                msgError.textContent = 'Error en la conexión con el servidor.';
                msgError.style.display = 'block';
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>