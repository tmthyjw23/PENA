<?php
/**
 * Controller Kop surat: template kop milik ruang organisasi (teks + logo, atau gambar kop utuh).
 * Semua anggota ruang boleh mengelola; tidak ada peran admin. Aturan bisnis di Pena\Service\LetterheadService.
 * Metode bantu diawali "_" agar router tidak membukanya sebagai URL.
 */

use Pena\Service\LetterheadService;
use Pena\Service\PenaException;
use Pena\Service\WorkspaceService;
use Pena\Support\Db;

class kop extends Controller {

    public function index() {
        [$org] = $this->_context();
        $kops = $org['kind'] === 'organization'
            ? array_map(fn($row) => $this->_service()->find((int)$org['id'], (int)$row['id']) ?? $row, $this->_service()->list((int)$org['id']))
            : [];
        $this->_display('kop/index', 'Kop surat', ['org' => $org, 'kops' => $kops]);
    }

    public function baru() {
        [$org] = $this->_organization();
        $this->_display('kop/form', 'Tambah kop surat', ['org' => $org, 'kop' => null, 'old' => $this->_takeOld()]);
    }

    public function simpan() {
        [$org, $userId] = $this->_organization(true);
        try {
            $this->_service()->create((int)$org['id'], $userId, $_POST, $this->_files());
            $this->_flash('success', 'Kop surat disimpan.');
            $this->_go('/kop/');
        } catch (PenaException $e) {
            $this->_keepOld($e);
            $this->_go('/kop/baru/');
        }
    }

    public function ubah($id = 0) {
        [$org] = $this->_organization();
        $kop = $this->_service()->find((int)$org['id'], (int)$id);
        if (!$kop) $this->abort(404, 'Kop surat tidak ditemukan atau sudah tidak aktif.');
        $this->_display('kop/form', 'Ubah kop surat', ['org' => $org, 'kop' => $kop, 'old' => $this->_takeOld()]);
    }

    public function perbarui($id = 0) {
        [$org, $userId] = $this->_organization(true);
        try {
            $this->_service()->revise((int)$org['id'], $userId, (int)$id, $_POST, $this->_files());
            $this->_flash('success', 'Perubahan kop disimpan. Draf yang belum diajukan ikut memakai kop baru; surat yang sudah diajukan tetap memakai kop lamanya.');
            $this->_go('/kop/');
        } catch (PenaException $e) {
            $this->_keepOld($e);
            $this->_go('/kop/ubah/' . (int)$id);
        }
    }

    public function utama($id = 0) {
        [$org, $userId] = $this->_organization(true);
        try {
            $this->_service()->setDefault((int)$org['id'], $userId, (int)$id);
            $this->_flash('success', 'Kop utama diganti. Surat baru memakai kop ini lebih dulu.');
        } catch (PenaException $e) {
            $this->_flash('danger', $e->getMessage());
        }
        $this->_go('/kop/');
    }

    public function nonaktif($id = 0) {
        [$org, $userId] = $this->_organization(true);
        try {
            $this->_service()->deactivate((int)$org['id'], $userId, (int)$id);
            $this->_flash('success', 'Kop dinonaktifkan. Surat yang sudah memakainya tidak berubah.');
        } catch (PenaException $e) {
            $this->_flash('danger', $e->getMessage());
        }
        $this->_go('/kop/');
    }

    // ------------------------------------------------------------ helpers

    /** @return array{0: array, 1: int} current workspace and user id; $post also checks method + CSRF */
    private function _context(bool $post = false): array {
        $this->start_default_session_app();
        header('Cache-Control: private, no-store');
        if (empty($_SESSION['user-id'])) $this->_go('/login/');
        if ($post) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->abort(405);
            if (!$this->verify_csrf_token()) {
                $this->_flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
                $this->_go('/kop/');
            }
        }
        $org = $this->_workspaces()->current((int)$_SESSION['user-id'], isset($_SESSION['workspace-id']) ? (int)$_SESSION['workspace-id'] : null);
        if (!$org) $this->abort(403, 'Akun ini belum punya ruang kerja.');
        $_SESSION['workspace-id'] = $org['id'];
        return [$org, (int)$_SESSION['user-id']];
    }

    private function _organization(bool $post = false): array {
        $context = $this->_context($post);
        if ($context[0]['kind'] !== 'organization') $this->_go('/kop/');
        return $context;
    }

    /** Only the two known upload slots; anything else in $_FILES is ignored. */
    private function _files(): array {
        return ['logo' => $_FILES['logo'] ?? null, 'image' => $_FILES['image'] ?? null];
    }

    private function _keepOld(PenaException $e): void {
        $_SESSION['kop_old'] = ['input' => array_intersect_key($_POST, array_flip(['name', 'subtitle', 'address', 'contact', 'kind', 'default', 'remove_logo'])),
                                'message' => $e->getMessage(), 'errors' => $e->errors];
    }

    private function _takeOld(): array {
        $old = $_SESSION['kop_old'] ?? [];
        unset($_SESSION['kop_old']);
        return $old;
    }

    private function _display(string $view, string $title, array $data): void {
        $flash = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        $this->display($view, $data + ['title' => $title . ' - PENA', 'active_menu' => 'kop', 'mobile_title' => 'Kop surat', 'flash' => $flash,
                                       'workspaces' => $this->_workspaces()->forUser((int)$_SESSION['user-id'])]);
    }

    private function _flash(string $type, string $text): void {
        $_SESSION['flash_message'] = ['type' => $type, 'text' => $text];
    }

    private function _go(string $path): never {
        header('Location: ' . APP_PATH . $path);
        exit();
    }

    private ?Db $db = null;
    private function _db(): Db { return $this->db ??= Db::fromTemplate(new Database()); }
    private function _service(): LetterheadService { return new LetterheadService($this->_db()); }
    private function _workspaces(): WorkspaceService { return new WorkspaceService($this->_db()); }
}
