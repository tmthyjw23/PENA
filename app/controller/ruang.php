<?php
/**
 * Controller Ruang kerja: memilih jenis ruang setelah daftar, membuat ruang organisasi, dan berpindah ruang.
 * Tidak ada peran admin: pembuat ruang organisasi menjadi pemiliknya (penanda, bukan peran).
 */

use Pena\Service\PenaException;
use Pena\Service\WorkspaceService;
use Pena\Support\Db;

class ruang extends Controller {

    /** "Surat ini untuk siapa?" Dibuka sekali setelah daftar; bisa dibuka lagi kapan saja. */
    public function mulai() {
        $userId = $this->_user();
        $this->_page('ruang/mulai', 'Mulai', $userId, ['only_org' => false]);
    }

    /** Membuat ruang organisasi tambahan. */
    public function baru() {
        $userId = $this->_user();
        $this->_page('ruang/mulai', 'Ruang organisasi baru', $userId, ['only_org' => true]);
    }

    /** Pilihan "Diri sendiri": simpan identitas (opsional), lalu langsung ke surat pertama. */
    public function pribadi() {
        $userId = $this->_user(true);
        $service = $this->_service();
        $id = $service->ensurePersonal($userId);
        $service->rememberProfile($id, $userId, [
            'nama_mahasiswa' => (string)($_POST['nama_mahasiswa'] ?? ''),
            'nim' => (string)($_POST['nim'] ?? ''),
            'program_studi' => (string)($_POST['program_studi'] ?? ''),
        ]);
        $_SESSION['workspace-id'] = $id;
        $this->_flash('success', 'Ruang pribadi dibuka. Anda dapat mulai membuat surat.');
        $this->_go('/surat/buat/');
    }

    /** Pilihan "Organisasi": buat ruang organisasi; kop diisi bersama surat pertama. */
    public function organisasi() {
        $userId = $this->_user(true);
        try {
            $id = $this->_service()->createOrganization($userId, (string)($_POST['nama'] ?? ''), (string)($_POST['singkatan'] ?? ''));
        } catch (PenaException $e) {
            $_SESSION['ruang_old'] = ['nama' => $_POST['nama'] ?? '', 'singkatan' => $_POST['singkatan'] ?? '', 'errors' => $e->errors];
            $this->_go(($_POST['asal'] ?? '') === 'baru' ? '/ruang/baru/' : '/ruang/mulai/');
        }
        $_SESSION['workspace-id'] = $id;
        $this->_flash('success', 'Ruang organisasi dibuat. Kop surat diisi saat Anda menyimpan surat pertama.');
        $this->_go('/surat/buat/');
    }

    /** Pindah ruang kerja. */
    public function pilih($id = 0) {
        $userId = $this->_user(true);
        try {
            $workspace = $this->_service()->requireMember((int)$id, $userId);
        } catch (PenaException $e) {
            $this->abort(403, $e->getMessage());
        }
        $_SESSION['workspace-id'] = $workspace['id'];
        unset($_SESSION['pena_ai'], $_SESSION['pena_old']); // a draft in progress belongs to the previous workspace
        $this->_go('/dashboard/');
    }

    // ------------------------------------------------------------ helpers

    private function _user(bool $post = false): int {
        $this->start_default_session_app();
        if (empty($_SESSION['user-id'])) $this->_go('/login/');
        if ($post) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->abort(405);
            if (!$this->verify_csrf_token()) {
                $this->_flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
                $this->_go('/ruang/mulai/');
            }
        }
        return (int)$_SESSION['user-id'];
    }

    private function _page(string $view, string $title, int $userId, array $data): void {
        $service = $this->_service();
        $workspaces = $service->forUser($userId);
        $current = $service->current($userId, isset($_SESSION['workspace-id']) ? (int)$_SESSION['workspace-id'] : null);
        $old = $_SESSION['ruang_old'] ?? [];
        $flash = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['ruang_old'], $_SESSION['flash_message']);
        $personal = null;
        foreach ($workspaces as $w) if ($w['kind'] === 'personal') $personal = $w;
        $this->display($view, $data + [
            'title' => $title . ' - PENA',
            'active_menu' => 'ruang',
            'mobile_title' => $title,
            'org' => $current,
            'workspaces' => $workspaces,
            'personal' => $personal,
            'old' => $old,
            'flash' => $flash,
        ]);
    }

    private function _flash(string $type, string $text): void {
        $_SESSION['flash_message'] = ['type' => $type, 'text' => $text];
    }

    private function _go(string $path): never {
        header('Location: ' . APP_PATH . $path);
        exit();
    }

    private ?WorkspaceService $service = null;
    private function _service(): WorkspaceService {
        return $this->service ??= new WorkspaceService(Db::fromTemplate(new Database()));
    }
}
