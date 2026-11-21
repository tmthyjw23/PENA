<?php
declare(strict_types=1);

// "Tulis ulang" on a saved draft (surat/tulis_ulang_draf): suggests text only, never saves it,
// is refused for submitted versions, stale editor pages and a changed AI connection, and links the log
// to the draft's working version. Throwaway database; DB_NAME is never touched.
require __DIR__ . '/support/throwaway_db.php';
require dirname(__DIR__) . '/app/controller/surat.php';

use Pena\Service\AiClient;
use Pena\Service\LetterService;
use Pena\Support\Db;

function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
final class RewriteReply extends RuntimeException {
    public function __construct(public readonly array $data, public readonly int $status = 200) { parent::__construct('Fixture response'); }
}
final class RewriteRedirect extends RuntimeException {
    public function __construct(public readonly string $path) { parent::__construct('Fixture redirect'); }
}
final class SuratRewriteFixture extends surat {
    public array $page = [];
    public function __construct(private AiClient $client) {}
    public function start_default_session_app() {}
    protected function _aiClient(): ?AiClient { return $this->client; }
    protected function _go(string $path): never { throw new RewriteRedirect($path); }
    public function json_response($data, $status = 200) { throw new RewriteReply($data, $status); }
    public function display($view, $data = []) { $this->page = $data; }
}

[$conn, $drop] = pena_throwaway_db('pena_test_saved_rewrite');
try {
    $db = new Db($conn);
    $db->run('UPDATE users SET email_verified_at = NOW() WHERE id = 2');
    $queue = []; $calls = 0;
    $client = new AiClient('http://fixture.invalid/v1', 'fixture-model', 'fixture-key', 5,
        static function ($url, $headers, $body) use (&$queue, &$calls) {
            $calls++;
            $out = array_shift($queue);
            if ($out === null) throw new RuntimeException('Unexpected provider call');
            return [200, json_encode(['model' => 'fixture-model', 'choices' => [['message' => ['content' => json_encode($out)]]]])];
        });
    $controller = new SuratRewriteFixture($client);
    (new ReflectionProperty(surat::class, 'db'))->setValue($controller, $db);

    $letters = new LetterService($db);
    $fields = ['penerima_jabatan' => 'Kepala Bagian Umum', 'penerima_instansi' => 'Fakultas Ilmu Komputer',
               'nama_kegiatan' => 'Kegiatan Uji', 'tanggal_mulai' => '2026-11-28', 'tempat_kegiatan' => 'Aula Kampus',
               'barang' => [['nama' => 'Kursi', 'jumlah' => '20', 'keterangan' => '']]];
    $original = 'Untuk {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}, kami bermaksud meminjam barang berikut.';
    $blocks = [['id' => 'pembuka'], ['id' => 'maksud', 'text' => $original], ['id' => 'daftar_barang'], ['id' => 'penutup'], ['id' => 'tanda_tangan']];
    $letterId = $letters->createDraft(2, 1, 1, 'Peminjaman kursi uji', $fields, $blocks, [['name' => 'Ketua', 'signer_ids' => [1]]], 1);
    $versionId = (int)$db->value('SELECT current_version_id FROM letters WHERE id = ?', [$letterId]);

    // The editor page carries what identifies the saved working version.
    $_SESSION = ['user-id' => 2, 'workspace-id' => 1, 'csrf_token' => 'fixture-csrf'];
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = []; $_POST = [];
    $controller->edit($letterId);
    $context = $controller->page['rewrite_context'] ?? null;
    check(($context['version_id'] ?? 0) === $versionId && strlen($context['fingerprint'] ?? '') === 64, 'Editor page lacks the draft context');
    check(is_string($controller->page['ai_connection_revision'] ?? null), 'Editor page lacks the AI connection revision');

    // By reference: after "Mulai revisi" the editor page (and so $context) belongs to the new version.
    $post = static function (array $extra = []) use (&$context, $controller, $fields, $original): array { return array_merge([
        'csrf_token' => 'fixture-csrf', 'rewrite_version_id' => (string)$context['version_id'],
        'rewrite_fingerprint' => $context['fingerprint'], 'ai_connection_revision' => $controller->page['ai_connection_revision'],
        'fields' => array_merge(array_diff_key($fields, ['barang' => 1]), ['barang' => 'Kursi | 20 | ', 'tempat_kegiatan' => 'Ruang Koreksi']),
        'block' => 'maksud', 'current' => $original, 'instruction' => 'Lebih singkat',
    ], $extra); };
    $call = static function (array $body) use ($controller, $letterId): RewriteReply {
        $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $body;
        try { $controller->tulis_ulang_draf($letterId); } catch (RewriteReply $reply) { return $reply; }
        throw new RuntimeException('No JSON response');
    };

    // Success: text returned, nothing saved, log linked to the working version.
    $queue[] = ['text' => 'Kami memohon peminjaman barang untuk {{nama_kegiatan}} di {{tempat_kegiatan}}.'];
    $reply = $call($post());
    check($reply->status === 200 && $reply->data['status'] === 'ok' && str_contains($reply->data['text'], '{{tempat_kegiatan}}'), 'Rewrite on a saved draft failed: ' . json_encode($reply->data));
    $saved = json_decode((string)$db->value('SELECT blocks FROM letter_versions WHERE id = ?', [$versionId]), true);
    check($saved[1]['text'] === $original, 'Rewrite saved text without the user pressing save');
    check((int)$db->value("SELECT COUNT(*) FROM ai_generations WHERE letter_id = ? AND version_id = ? AND purpose = 'revise'", [$letterId, $versionId]) === 1, 'Rewrite log not linked to the draft');
    check($calls === 1, 'Unexpected provider call count');

    // Refusals happen before any provider call.
    $reply = $call($post(['rewrite_fingerprint' => str_repeat('0', 64)]));
    check($reply->status === 409 && $reply->data['status'] === 'error', 'Stale editor page accepted');
    $reply = $call($post(['ai_connection_revision' => 'other']));
    check($reply->status === 409, 'Changed AI connection accepted');
    $reply = $call($post(['block' => 'pembuka']));
    check($reply->status === 422, 'Fixed text block accepted for rewrite');
    check($calls === 1, 'A refused rewrite called the provider');

    // Once submitted, the version is locked; the editor page itself redirects away.
    $letters->submit($letterId, 2);
    $reply = $call($post());
    check($reply->status === 409 && str_contains($reply->data['message'], 'terkunci'), 'Submitted version accepted a rewrite');
    check($calls === 1, 'Submitted version called the provider');

    // After "Mulai revisi" the new working version can be rewritten again (the reported bug).
    $letters->startRevision($letterId, 2, 'Tempat berubah');
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = [];
    $controller->edit($letterId);
    $context = $controller->page['rewrite_context'];
    check($context['version_id'] !== $versionId, 'Revision editor still points at the submitted version');
    $queue[] = ['text' => 'Kami memohon izin meminjam barang untuk {{nama_kegiatan}} di {{tempat_kegiatan}}.'];
    $reply = $call($post());
    check($reply->status === 200 && $reply->data['status'] === 'ok', 'Rewrite after starting a revision failed: ' . json_encode($reply->data));
    check((int)$db->value("SELECT COUNT(*) FROM ai_generations WHERE letter_id = ? AND version_id = ?", [$letterId, $context['version_id']]) === 1, 'Revision rewrite not linked to the new version');
    echo "PASS saved-draft rewrite: suggests without saving, locked after submit, works after Mulai revisi, stale/connection/fixed-block refused before provider call\n";
} finally {
    $conn->close();
    $drop();
}
