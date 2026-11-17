<?php
/**
 * Controller halaman depan PENA. Menampilkan peringatan bila database belum siap.
 */
class landing extends Controller {

    public function index() {
        $this->start_default_session_app();
        $flash = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        $this->display('landing/index', [
            'title' => 'PENA: surat organisasi dari draf sampai disetujui',
            'db_diagnostics' => (new Database())->getDiagnostics(),
            'php_version' => PHP_VERSION,
            'flash_message' => $flash,
        ]);
    }
}
