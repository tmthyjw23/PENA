<?php
/**
 * Controller Masuk/Keluar untuk sekretaris dan admin organisasi. Pemeriksa tidak masuk; mereka memakai tautan.
 */
class login extends Controller {

    public function index() {
        $this->start_default_session_app();
        if (!empty($_SESSION['user-id'])) {
            header('Location: ' . APP_PATH . '/dashboard/');
            exit();
        }
        $error = $_SESSION['login_error'] ?? (isset($_GET['timeout']) ? 'Sesi berakhir karena lama tidak dipakai. Silakan masuk lagi.' : null);
        unset($_SESSION['login_error']);
        $this->display('login/index', ['title' => 'Masuk - ' . APP_NAME, 'error' => $error]);
    }

    public function authenticate() {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_back();
        if (!$this->verify_csrf_token()) $this->_back('Sesi formulir kedaluwarsa. Silakan coba lagi.');

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') $this->_back('Username dan password wajib diisi.');

        $db = new Database();
        if (!$db->isConnected()) $this->_back('Database belum terhubung. Periksa MySQL dan pengaturan DB di .env.');

        $user = (new User_model())->validate_credentials($username, $password);
        if (!$user) $this->_back('Username atau password salah. Periksa lalu coba lagi.');

        session_regenerate_id(true); // prevents session fixation
        $_SESSION['user-id'] = (int)$user['id'];
        $_SESSION['user-name'] = $user['nama_lengkap'];
        $_SESSION['user-username'] = $user['username'];
        $_SESSION['user-email'] = $user['email'];
        header('Location: ' . APP_PATH . '/dashboard/');
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
