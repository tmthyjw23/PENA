<?php
/**
 * Controller Dashboard - beranda setelah login.
 * Surat di ruang kerja aktif (pribadi atau organisasi) yang perlu ditindaklanjuti dan ringkasan status.
 */

use Pena\Service\LetterQuery;
use Pena\Service\WorkspaceService;
use Pena\Support\Db;

class dashboard extends Controller {

    public function index() {
        $this->start_default_session_app();

        if (empty($_SESSION['user-id'])) {
            header('Location: ' . APP_PATH . '/login/');
            exit();
        }

        $flash=$_SESSION['flash_message']??null;unset($_SESSION['flash_message']);
        $showGuide=false;
        $loadError = null;
        $org = null;
        $letters = [];
        $needsLink = [];
        $revisions = [];
        $workspaces = [];
        try {
            $db = Db::fromTemplate(new Database());
            $showGuide=(new \Pena\Service\UserGuide($db))->needsIntroduction((int)$_SESSION['user-id']) || ($_GET['petunjuk']??'')==='1';
            $query = new LetterQuery($db);
            $spaces = new WorkspaceService($db);
            $org = $spaces->current((int)$_SESSION['user-id'], isset($_SESSION['workspace-id']) ? (int)$_SESSION['workspace-id'] : null);
            $workspaces = $spaces->forUser((int)$_SESSION['user-id']);
            if ($org !== null) {
                $_SESSION['workspace-id'] = $org['id'];
                $letters = $query->letters((int)$org['id']);
                $needsLink = $query->lettersNeedingLink((int)$org['id']);
                // Latest revision request per letter that is waiting for the secretary.
                foreach ($db->all("SELECT c.letter_id, c.author_name, c.block_ref FROM letter_comments c
                                     JOIN letters l ON l.id = c.letter_id AND l.status = 'revision_requested'
                                    WHERE l.organization_id = ? AND c.kind = 'revision_request'
                                    ORDER BY c.id DESC", [(int)$org['id']]) as $row) {
                    $revisions[(int)$row['letter_id']] ??= $row;
                }
            }
        } catch (\Throwable $e) {
            error_log('Dashboard: ' . $e->getMessage());
            $loadError='Daftar surat belum dapat dimuat. Muat ulang halaman atau coba lagi nanti.';
        }

        $this->display('dashboard/index', [
            'title' => 'Beranda - PENA',
            'active_menu' => 'dashboard', 'show_guide'=>$showGuide, 'flash'=>$flash,
            'org' => $org,
            'letters' => $letters,
            'load_error'=>$loadError,
            'needs_link' => $needsLink,
            'revisions' => $revisions,
            'workspaces' => $workspaces,
        ]);
    }
}
