document.addEventListener('DOMContentLoaded', () => {
    const loginButton = document.getElementById('login-button');
    const roleInput = document.getElementById('role-input');
    const passwordInput = document.getElementById('password-input');
    const loginError = document.getElementById('login-error');

    async function handleLogin() {
        const username = roleInput.value.trim();
        const password = passwordInput.value;

        loginError.textContent = '';
        roleInput.classList.remove('shake');
        passwordInput.classList.remove('shake');
        loginButton.disabled = true;
        loginButton.textContent = 'Memverifikasi...';

        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username: username, password: password })
            });

            if (!response.ok) {
                throw new Error('Gagal terhubung ke server verifikasi.');
            }

            const result = await response.json();

            if (result.success) {
                localStorage.setItem('currentUserRole', result.role);
                window.location.href = 'index.html';
            } else {
                loginError.textContent = result.message || 'Username atau password salah.';
                roleInput.classList.add('shake');
                passwordInput.classList.add('shake');
            }
        } catch (error) {
            loginError.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
            console.error('Login error:', error);
        } finally {
            loginButton.disabled = false;
            loginButton.textContent = 'Login';
        }
    }

    const savedTheme = localStorage.getItem('dashboardTheme') || 'dark-mode';
    document.body.className = `login-page ${savedTheme}`;

    // Setup event listener untuk login
    loginButton.addEventListener('click', handleLogin);
    roleInput.addEventListener('keypress', (event) => { if (event.key === 'Enter') passwordInput.focus(); });
    passwordInput.addEventListener('keypress', (event) => { if (event.key === 'Enter') handleLogin(); });

    // Jika pengguna sudah login, arahkan kembali ke index.html
    if (localStorage.getItem('currentUserRole')) {
        window.location.href = 'index.html';
    }
});