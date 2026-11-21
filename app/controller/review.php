<?php
/**
 * Controller Review - halaman pemeriksa lewat tautan, tanpa akun.
 * Token di URL adalah satu-satunya bukti; keputusan dicatat apa adanya sebagai "lewat tautan".
 */

use Pena\Service\ApprovalService;
use Pena\Service\LetterRenderer;
use Pena\Service\PenaException;
use Pena\Support\Db;

class review extends Controller {

    public function index() {
        $this->_fail('Buka tautan review lengkap yang Anda terima dari sekretaris.');
    }

    public function lihat($token = '') {
        $this->start_default_session_app();
        header('Referrer-Policy: no-referrer'); // the token must not leak to other sites
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store, private');
        try {
            $page = $this->_service()->open((string)$token);
        } catch (PenaException $e) {
            $this->_fail($e->getMessage());
        }
        $snapshot = $page['version']['snapshot'];
        $preview = (new LetterRenderer())->render($snapshot['template']['definition'], $page['version']['fields'],
            $page['version']['blocks'], (new \Pena\Service\LetterheadService(Db::fromTemplate(new Database())))->decorate($snapshot['letterhead'] ?? []), $snapshot['letter_number'],
            $page['letter']['title'], array_merge($snapshot['approval'] ?? [], !empty($snapshot['author']) ? [$snapshot['author']] : []),
            $page['version']['submitted_at'], ($snapshot['organization']['kind'] ?? 'organization') !== 'personal');
        $preview=(new \Pena\Service\SignatureService(Db::fromTemplate(new Database())))->decorate((int)$page['version']['id'],$preview,$snapshot['author']??null);
        $flash = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        $this->display('review/index', [
            'title' => 'Pemeriksaan: ' . $page['letter']['title'],
            'page' => $page,
            'preview' => $preview,
            'changes' => $this->_changes($page, $snapshot['template']['definition']),
            'token' => (string)$token,
            'flash' => $flash,
            'csrf_token' => $this->get_csrf_token(),
        ]);
    }

    public function setuju($token = '') {
        $this->_guardPost();
        try {
            $result = $this->_service()->approve((string)$token, (string)($_POST['content_hash'] ?? ''),
                trim((string)($_POST['note'] ?? '')) ?: null, $this->_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '', \Pena\Service\SignatureService::fromRequest($_POST,$_FILES));
            $this->_done('Persetujuan Anda tercatat', $result['letter_status'] === 'approved'
                ? 'Surat ini sudah disetujui semua pihak. Halaman ini boleh ditutup.'
                : 'Sekretaris akan mengirim surat ini ke pemeriksa berikutnya. Halaman ini boleh ditutup.', 'approved');
        } catch (PenaException $e) {
            $this->_back((string)$token, $e->getMessage());
        }
    }

    public function revisi($token = '') {
        $this->_guardPost();
        try {
            $this->_service()->requestRevision((string)$token, (string)($_POST['content_hash'] ?? ''),
                (string)($_POST['note'] ?? ''), trim((string)($_POST['block_ref'] ?? '')) ?: null, $this->_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '');
            $this->_done('Permintaan revisi terkirim', 'Proses berhenti sampai sekretaris memperbaiki surat. Anda akan menerima tautan baru untuk versi berikutnya, berisi daftar perubahan.', 'revision');
        } catch (PenaException $e) {
            $this->_back((string)$token, $e->getMessage());
        }
    }

    public function komentar($token = '') {
        $this->_guardPost();
        try {
            $this->_service()->comment((string)$token, (string)($_POST['body'] ?? ''), trim((string)($_POST['block_ref'] ?? '')) ?: null);
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Komentar terkirim. Proses persetujuan tetap berjalan.'];
        } catch (PenaException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $e->getMessage()];
        }
        header('Location: ' . APP_PATH . '/review/lihat/' . rawurlencode((string)$token));
        exit();
    }

    // ------------------------------------------------------------ helpers

    /** Field and block differences from the previous version, for reconfirming reviewers. */
    private function _changes(array $page, array $definition): array {
        if ($page['previous'] === null) return [];
        $changes = [];
        if(isset($page['previous']['title'],$page['version']['title'])&&$page['previous']['title']!==$page['version']['title']){
            $changes[]=['label'=>'Perihal','before'=>$page['previous']['title'],'after'=>$page['version']['title']];
        }
        $labels = array_column($definition['fields'], 'label', 'key');
        $types = array_column($definition['fields'], 'type', 'key');
        $before = $page['previous']['fields'];
        $after = $page['version']['fields'];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (($before[$key] ?? null) == ($after[$key] ?? null)) continue;
            $show = static fn($v) => match (true) {
                is_array($v) => implode("\n", array_map(static fn($row) => implode(' | ', array_map(static fn($cell) => (string)$cell, (array)$row)), $v)),
                $v === null => '(kosong)',
                ($types[$key] ?? '') === 'date' => \Pena\Service\TemplateValidator::formatDate((string)$v),
                default => (string)$v,
            };
            $changes[] = ['label' => $labels[$key] ?? $key, 'before' => $show($before[$key] ?? null), 'after' => $show($after[$key] ?? null)];
        }
        // Compare paragraphs as the reviewer reads them: placeholders filled with each version's own facts,
        // so a changed date shows up in the sentence that uses it.
        $validator = new \Pena\Service\TemplateValidator();
        $text = static fn(array $blocks, array $fields) => array_map(
            static fn($t) => $validator->render((string)$t, $definition, $fields),
            array_column($blocks, 'text', 'id'));
        $tb = $text($page['previous']['blocks'], $before);
        $ta = $text($page['version']['blocks'], $after);
        foreach (array_unique(array_merge(array_keys($tb), array_keys($ta))) as $id) {
            if (($tb[$id] ?? null) !== ($ta[$id] ?? null)) {
                $changes[] = ['label' => 'Paragraf ' . str_replace('_', ' ', $id), 'before' => $tb[$id] ?? '(kosong)', 'after' => $ta[$id] ?? '(kosong)'];
            }
        }
        return $changes;
    }

    private function _guardPost(): void {
        $this->start_default_session_app();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->abort(405);
        if (!$this->verify_csrf_token()) $this->_fail('Sesi halaman kedaluwarsa. Buka kembali tautan review Anda.');
    }

    private function _back(string $token, string $message): never {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $message];
        header('Location: ' . APP_PATH . '/review/lihat/' . rawurlencode($token));
        exit();
    }

    private function _done(string $title, string $message, string $mark): never {
        $this->display('review/selesai', ['title' => $title, 'message' => $message, 'mark' => $mark, 'ok' => true]);
        exit();
    }

    private function _fail(string $message): never {
        http_response_code(410);
        $this->display('review/selesai', ['title' => 'Tautan ini tidak bisa dipakai', 'message' => $message, 'mark' => 'cancelled', 'ok' => false]);
        exit();
    }

    private function _ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

    private function _service(): ApprovalService { return new ApprovalService(Db::fromTemplate(new Database())); }
}
