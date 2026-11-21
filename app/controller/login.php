<?php
/**
 * Controller Masuk/Keluar. Setiap akun adalah sekretaris di ruang kerjanya; pemeriksa tidak masuk, mereka memakai tautan.
 */
class login extends Controller {

    public function index() {
        $this->start_default_session_app();
        if (!empty($_SESSION['user-id'])) {
            header('Location: ' . APP_PATH . '/dashboard/');
            exit();
        }
        $error = $_SESSION['login_error'] ?? (isset($_GET['timeout']) ? 'Sesi berakhir karena lama tidak dipakai. Silakan masuk lagi.' : null);
        $notice = $_SESSION['account_notice'] ?? null;
        unset($_SESSION['login_error'], $_SESSION['account_notice']);
        $this->display('login/index', ['title' => 'Masuk - ' . APP_NAME, 'error' => $error, 'notice' => $notice]);
    }

    public function authenticate() {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_back();
        if (!$this->verify_csrf_token()) $this->_back('Sesi formulir kedaluwarsa. Silakan coba lagi.');

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') $this->_back('Masukkan email dan kata sandi.');

        $db = new Database();
        if (!$db->isConnected()) $this->_back('Layanan akun belum tersedia. Coba lagi nanti.');

        $limiter = new \Pena\Service\RateLimiter(\Pena\Support\Db::fromTemplate($db));
        if (!$limiter->allow('login-ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 60, 900)
            || !$limiter->allow('login-account:' . mb_strtolower($username), 10, 900)) {
            $this->_back('Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.');
        }
        $user = (new User_model())->validate_credentials($username, $password);
        if (!$user) $this->_back('Email atau kata sandi salah. Periksa lalu coba lagi.');

        session_regenerate_id(true); // prevents session fixation
        $_SESSION['user-id'] = (int)$user['id'];
        $_SESSION['user-name'] = $user['nama_lengkap'];
        $_SESSION['user-username'] = $user['username'];
        $_SESSION['user-email'] = $user['email'];
        $_SESSION['user-auth-version'] = (int)$user['auth_version'];
        unset($_SESSION['workspace-id']);
        header('Location: ' . APP_PATH . ($user['email_verified_at'] === null ? '/akun/verifikasi/' : '/dashboard/'));
        exit();
    }

    public function logout() {
        $this->start_default_session_app();
        $this->destroy_session_cleanly();
        header('Location: ' . APP_PATH . '/login/');
        exit();
    }

    private function _back(?string $error = null): never {
        if ($error !== null) $_SESSION['login_error'] = $error;
        header('Location: ' . APP_PATH . '/login/');
        exit();
    }
}
