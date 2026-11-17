<?php
/**
 * Controller Dashboard - beranda setelah login.
 * Untuk pengurus organisasi: surat yang perlu ditindaklanjuti dan ringkasan status.
 */

use Pena\Service\LetterQuery;
use Pena\Support\Db;

class dashboard extends Controller {

    public function index() {
        $this->start_default_session_app();

        if (empty($_SESSION['user-id'])) {
            header('Location: ' . APP_PATH . '/login/');
            exit();
        }

        $org = null;
        $letters = [];
        $needsLink = [];
        $revisions = [];
        try {
            $db = Db::fromTemplate(new Database());
            $query = new LetterQuery($db);
            $org = $query->managedOrganization((int)$_SESSION['user-id']);
            if ($org !== null) {
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
        }

        $this->display('dashboard/index', [
            'title' => 'Beranda - PENA',
            'active_menu' => 'dashboard',
            'org' => $org,
            'letters' => $letters,
            'needs_link' => $needsLink,
            'revisions' => $revisions,
        ]);
    }
}
