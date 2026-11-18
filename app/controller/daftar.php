<?php
/**
 * Controller Daftar: siapa pun bisa membuat akun sendiri (keputusan pemilik 18 Nov 2026).
 * Satu formulir: nama, email, password. Ruang pribadi dibuat bersamaan; jenis ruang kerja dipilih sesudahnya.
 */

use Pena\Service\PenaException;
use Pena\Service\WorkspaceService;
use Pena\Support\Db;

class daftar extends Controller {

    public function index() {
        $this->start_default_session_app();
        if (!empty($_SESSION['user-id'])) $this->_go('/dashboard/');
        $old = $_SESSION['daftar_old'] ?? [];
        unset($_SESSION['daftar_old']);
        $this->display('daftar/index', ['title' => 'Daftar - ' . APP_NAME, 'old' => $old]);
    }

    public function simpan() {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_go('/daftar/');
        $name = (string)($_POST['nama'] ?? '');
        $email = (string)($_POST['email'] ?? '');
        if (!$this->verify_csrf_token()) $this->_back($name, $email, ['form' => 'Sesi formulir kedaluwarsa. Silakan coba lagi.']);

        $database = new Database();
        if (!$database->isConnected()) $this->_back($name, $email, ['form' => 'Database belum terhubung. Coba lagi nanti.']);
        try {
            $userId = (new WorkspaceService(Db::fromTemplate($database)))->register($name, $email, (string)($_POST['password'] ?? ''));
        } catch (PenaException $e) {
            $this->_back($name, $email, $e->errors ?: ['form' => $e->getMessage()]);
        }

        session_regenerate_id(true);
        $user = Db::fromTemplate($database)->one('SELECT id, username, nama_lengkap, email FROM users WHERE id = ?', [$userId]);
        $_SESSION['user-id'] = (int)$user['id'];
        $_SESSION['user-name'] = $user['nama_lengkap'];
        $_SESSION['user-username'] = $user['username'];
        $_SESSION['user-email'] = $user['email'];
        $this->_go('/ruang/mulai/');
    }

    private function _back(string $name, string $email, array $errors): never {
        $_SESSION['daftar_old'] = ['nama' => $name, 'email' => $email, 'errors' => $errors];
        $this->_go('/daftar/');
    }

    private function _go(string $path): never {
        header('Location: ' . APP_PATH . $path);
        exit();
    }
}
