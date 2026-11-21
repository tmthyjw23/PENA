<?php
use Pena\Service\{AccountService, PenaException, RateLimiter};
use Pena\Support\Db;

/** Account links never use a token in the URL path/query or automatically consume it on GET. */
class akun extends Controller {
    public function verifikasi() {
        $this->start_default_session_app();
        if (empty($_SESSION['user-id'])) $this->_go('/login/');
        $db = $this->_db();
        $verified = $db->value('SELECT email_verified_at FROM users WHERE id=?', [$_SESSION['user-id']]);
        if ($verified !== null) $this->_go('/dashboard/');
        $this->_page('verify');
    }

    public function kirim() {
        $this->_post();
        if (empty($_SESSION['user-id'])) $this->_go('/login/');
        try {
            $this->_service()->sendVerification((int)$_SESSION['user-id']);
            $this->_go('/akun/verifikasi/');
        } catch (PenaException $e) {
            $this->_page('verify', null, $e->getMessage());
        }
    }

    public function lupa() {
        $this->start_default_session_app();
        $this->_page('forgot');
    }

    public function pemulihan() {
        $this->_post();
        $db = $this->_db();
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $limiter = new RateLimiter($db);
        $allowed = $limiter->allow('reset-ip:'.($_SERVER['REMOTE_ADDR'] ?? ''),10,3600)
            && $limiter->allow('reset-email:'.$email,3,3600);
        // FPM completes the identical public response before any account lookup/provider latency.
        $this->_page('forgot', 'Jika email tersebut terkait akun aktif, permintaan akan diproses. Periksa email atau coba lagi nanti.');
        session_write_close();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        if ($allowed) {
            try { $this->_service()->requestPasswordReset($email); }
            catch (PenaException) { error_log('PENA password reset: request could not be processed'); }
        }
    }

    public function konfirmasi() {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->_page('confirm'); return; }
        $this->_post();
        try {
            $this->_service()->verifyEmail((string)($_POST['token'] ?? ''));
            $_SESSION['account_notice'] = 'Email berhasil diverifikasi. Silakan masuk.';
            $this->_go('/login/');
        } catch (PenaException $e) { $this->_page('confirm', null, $e->getMessage()); }
    }

    public function reset() {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->_page('reset'); return; }
        $this->_post();
        try {
            $this->_service()->resetPassword((string)($_POST['token'] ?? ''), (string)($_POST['password'] ?? ''), (string)($_POST['password_confirmation'] ?? ''));
            $this->destroy_session_cleanly();
            $this->start_default_session_app();
            session_regenerate_id(true);
            $_SESSION['account-reset-complete'] = true;
            $this->_go('/akun/selesai/');
        } catch (PenaException $e) { $this->_page('reset', null, $e->getMessage()); }
    }

    public function selesai() {
        $this->start_default_session_app();
        if (empty($_SESSION['account-reset-complete'])) $this->_go('/login/');
        unset($_SESSION['account-reset-complete']);
        $this->_page('complete', 'Kata sandi diubah. Semua sesi sebelumnya telah diakhiri. Masuk kembali dengan kata sandi baru.');
    }

    private function _post(): void {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->abort(405);
        if (!$this->verify_csrf_token()) $this->abort(403, 'Sesi formulir kedaluwarsa. Buka kembali halaman ini.');
        if (!(new RateLimiter($this->_db()))->allow('account-actions:'.($_SERVER['REMOTE_ADDR'] ?? ''),30,900)) {
            $this->abort(429, 'Terlalu banyak percobaan. Silakan coba lagi nanti.');
        }
    }

    private function _db(): Db {
        $database = new Database();
        if (!$database->isConnected()) $this->abort(503, 'Layanan akun belum tersedia. Coba lagi nanti.');
        return Db::fromTemplate($database);
    }

    private function _service(): AccountService {
        return new AccountService($this->_db(), null, (string)($_ENV['APP_URL'] ?? ''));
    }

    private function _page(string $mode, ?string $notice=null, ?string $error=null): void {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        $this->display('akun/index', ['mode'=>$mode, 'notice'=>$notice, 'error'=>$error,
            'email'=>$_SESSION['user-email'] ?? '', 'token'=>$_POST['token'] ?? '',
            'verification_pending'=>$mode==='verify' && !empty($_SESSION['user-id']) && $this->_service()->hasPendingVerification((int)$_SESSION['user-id'])]);
    }

    private function _go(string $path): never {
        header('Location: '.APP_PATH.$path);
        exit();
    }
}
