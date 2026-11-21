<?php
declare(strict_types=1);

// AI drafting rules against a throwaway database and a fake OpenAI-compatible transport (no network).
require __DIR__ . '/support/throwaway_db.php';

use Pena\Service\AiClient;
use Pena\Service\AiException;
use Pena\Service\LetterAiService;
use Pena\Service\LetterQuery;
use Pena\Service\LetterService;
use Pena\Support\Db;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

/** Fake transport: answers from a queue of model texts (or ['status' => int, 'body' => string]); records requests. */
function fakeAi(array &$queue, array &$sent): AiClient {
    $transport = static function (string $url, array $headers, string $body, int $timeout) use (&$queue, &$sent): array {
        $sent[] = ['url' => $url, 'headers' => $headers, 'body' => json_decode($body, true)];
        $next = array_shift($queue);
        if ($next instanceof Throwable) throw $next;
        if (is_array($next)) return [$next['status'], $next['body']];
        return [200, json_encode(['model' => 'cc/test-model', 'choices' => [['message' => ['content' => $next]]],
                                  'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]])];
    };
    return new AiClient('http://127.0.0.1:20128/v1', 'pena-combo', 'test-key', 30, $transport);
}

[$conn, $drop] = pena_throwaway_db('pena_test_ai');
try {
    $db = new Db($conn);
    $now = new DateTimeImmutable('2026-11-17 09:00:00');
    $clock = static fn() => $now;
    $queue = []; $sent = [];
    $ai = new LetterAiService($db, fakeAi($queue, $sent), $clock);
    $query = new LetterQuery($db);
    $templates = $query->templates(1);
    $pmj = array_values(array_filter($templates, static fn($t) => $t['code'] === 'PMJ'))[0];
    $signers = $query->signers(1);
    $org = 1; $user = 2;
    $prompt = 'Kami mau pinjam 50 kursi lipat dan 10 meja panjang ke Bagian Umum fakultas untuk FilkomDay 2026, tanggal 7 Desember di Aula Fakultas. Ditandatangani ketua panitia, diketahui advisor dan dekan.';

    // --- recommend_template
    $queue[] = "```json\n" . json_encode(['status' => 'ok', 'template' => 'PMJ', 'reason' => 'Anda menyebut "pinjam" dan daftar barang', 'title' => 'Peminjaman kursi FilkomDay']) . "\n```";
    $r = $ai->recommendTemplate($org, $user, $prompt, $templates);
    check($r['status'] === 'ok' && $r['code'] === 'PMJ' && $r['template_id'] === (int)$pmj['id'], 'recommend picks PMJ from fenced JSON');
    check($sent[0]['url'] === 'http://127.0.0.1:20128/v1/chat/completions' && in_array('Authorization: Bearer test-key', $sent[0]['headers'], true)
          && $sent[0]['body']['model'] === 'pena-combo' && $sent[0]['body']['stream'] === false, 'OpenAI-compatible request to 9router');
    $queue[] = json_encode(['status' => 'ok', 'template' => 'XYZ', 'reason' => '-', 'title' => '-']);
    try { $ai->recommendTemplate($org, $user, $prompt, $templates); check(false, 'unknown template accepted'); } catch (AiException) {}
    $queue[] = json_encode(['status' => 'clarify', 'question' => 'Surat ini untuk meminjam atau memohon?', 'options' => ['PMJ', 'PMH']]);
    $c = $ai->recommendTemplate($org, $user, 'bikin surat buat acara minggu depan', $templates);
    check($c['status'] === 'clarify' && count($c['options']) === 2, 'vague request asks for clarification');
    echo "PASS recommend: template from the organization list only, clarification for vague requests, OpenAI-compatible call\n";

    // --- extract
    $queue[] = json_encode(['fields' => [
        // The model claims this was not inferred, but "Kepala" is not in the request: still "perlu dicek".
        'penerima_jabatan' => ['value' => 'Kepala Bagian Umum', 'quote' => 'ke Bagian Umum', 'inferred' => false],
        'penerima_instansi' => ['value' => 'Fakultas Ilmu Komputer', 'quote' => 'Fakultas Ilmu Komputer', 'inferred' => false],
        'nama_kegiatan' => ['value' => 'FilkomDay 2026', 'quote' => 'untuk FilkomDay 2026', 'inferred' => false],
        'tanggal_mulai' => ['value' => '2026-12-07', 'quote' => 'tanggal 7 Desember', 'inferred' => false],
        'tanggal_selesai' => ['value' => '7 Des', 'quote' => 'x', 'inferred' => false],
        'tempat_kegiatan' => ['value' => 'Aula Fakultas', 'quote' => 'di Aula Fakultas', 'inferred' => false],
        'barang' => ['value' => [['nama' => 'Kursi lipat', 'jumlah' => 50], ['nama' => 'Meja panjang', 'jumlah' => '10', 'warna' => 'merah']], 'quote' => '50 kursi lipat dan 10 meja panjang'],
        'nomor_surat' => ['value' => '001/X', 'quote' => '-'],
    ], 'route' => [['stage' => 'Ketua Panitia', 'signer_id' => 1, 'quote' => 'Ditandatangani ketua panitia'],
                   ['stage' => 'Advisor', 'signer_id' => 3, 'quote' => 'diketahui advisor'],
                   ['stage' => 'Dekan', 'signer_id' => 4, 'quote' => 'dan dekan'],
                   ['stage' => 'Rektor', 'signer_id' => 99, 'quote' => 'rektor'], ['stage' => 'Dobel', 'signer_id' => 1]],
       'missing' => ['Rektor']]);
    $x = $ai->extract($org, $user, $prompt, $pmj, $signers);
    $f = $x['fields'];
    check($f['nama_kegiatan']['flag'] === null && $f['nama_kegiatan']['quote'] === 'untuk FilkomDay 2026', 'quoted fact keeps its source');
    check($f['penerima_jabatan']['flag'] === 'cek', 'value not written in the request flagged perlu dicek despite the model');
    check($f['tanggal_mulai']['flag'] === 'cek', 'date whose year is not written is flagged');
    check($f['penerima_instansi']['flag'] === 'cek' && $f['penerima_instansi']['quote'] === null, 'quote not in the request is dropped and flagged');
    check($f['tanggal_selesai']['flag'] === 'kosong' && $f['tanggal_selesai']['value'] === null, 'invalid date format becomes empty');
    check($f['barang']['value'] === [['nama' => 'Kursi lipat', 'jumlah' => '50', 'keterangan' => ''], ['nama' => 'Meja panjang', 'jumlah' => '10', 'keterangan' => '']], 'table rows normalized to template columns');
    check(!isset($f['nomor_surat']), 'undeclared field (letter number) ignored');
    check(array_column($x['route'], 'signer_id') === [1, 3, 4], 'route keeps known signers once, in order');
    check($x['missing'] === ['Rektor'], 'missing role reported');
    echo "PASS extract: quotes verified against the request, inferred/empty flags, formats enforced, no letter number, route from directory only\n";

    // --- generate with one retry
    $fields = array_map(static fn($v) => $v['value'], $f);
    $queue[] = json_encode(['blocks' => ['maksud' => 'Sehubungan dengan FilkomDay 2026 pada 7 Desember di {{tempat_kegiatan}}, kami meminjam barang.', 'ketentuan' => 'Barang dikembalikan utuh.']]);
    $queue[] = json_encode(['blocks' => ['maksud' => 'Sehubungan dengan kegiatan {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}, kami bermaksud meminjam barang berikut.', 'ketentuan' => 'Barang akan dikembalikan dalam keadaan baik.']]);
    $g = $ai->writeParagraphs($org, $user, $prompt, $pmj, $fields);
    check(str_contains($g['blocks']['maksud']['text'], '{{tanggal_mulai}}') && $g['blocks']['maksud']['warnings'] === [], 'retry produced placeholder-only paragraph');
    check(str_contains($g['blocks']['ketentuan']['warnings'][0] ?? '', 'tidak berisi fakta'), 'paragraph without facts gets a warning');
    check(count($g['generation_ids']) === 2, 'both attempts logged');
    $retryPrompt = end($sent)['body']['messages'];
    check(str_contains(end($retryPrompt)['content'], 'angka'), 'retry tells the model what was wrong');

    $queue[] = json_encode(['blocks' => ['maksud' => 'Pada 7 Desember kami meminjam.']]);
    $queue[] = json_encode(['blocks' => ['maksud' => 'Kegiatan Aula Fakultas berlangsung meriah.']]);
    $g2 = $ai->writeParagraphs($org, $user, $prompt, $pmj, $fields);
    check($g2['blocks']['maksud']['text'] === '' && str_contains($g2['blocks']['maksud']['warnings'][0], 'ditolak'), 'leaking paragraph rejected after retry');
    echo "PASS generate: typed dates, numbers, month names and field values rejected, one retry with reasons, warnings shown\n";

    // --- rewrite
    $queue[] = json_encode(['text' => 'Kami bermaksud meminjam barang untuk {{nama_kegiatan}} di {{tempat_kegiatan}}.']);
    $rw = $ai->rewrite($org, $user, $pmj, $fields, 'maksud', 'lama', 'lebih singkat');
    check(str_contains($rw['text'], '{{nama_kegiatan}}'), 'rewrite returns placeholder text');
    $queue[] = json_encode(['text' => 'Pada 12 Januari kami meminjam.']);
    try { $ai->rewrite($org, $user, $pmj, $fields, 'maksud', 'lama', ''); check(false, 'leaking rewrite accepted'); } catch (AiException $e) { check(str_contains($e->getMessage(), 'ditolak'), 'rewrite rejection message'); }
    try { $ai->rewrite($org, $user, $pmj, $fields, 'pembuka', 'x', ''); check(false, 'fixed block rewritten'); } catch (AiException) {}
    echo "PASS rewrite: one paragraph, same placeholder rules, fixed text never rewritten\n";

    // --- failures
    $queue[] = ['status' => 503, 'body' => json_encode(['error' => ['message' => 'All accounts exhausted']])];
    try { $ai->recommendTemplate($org, $user, $prompt, $templates); check(false, 'HTTP error ignored'); } catch (AiException $e) { check(str_contains($e->getMessage(), '503'), 'HTTP status in message'); }
    $queue[] = 'maaf saya tidak bisa';
    try { $ai->recommendTemplate($org, $user, $prompt, $templates); check(false, 'non-JSON accepted'); } catch (AiException) {}
    $queue[] = new AiException('Layanan AI tidak merespons dalam 30 detik.');
    try { $ai->extract($org, $user, $prompt, $pmj, $signers); check(false, 'timeout ignored'); } catch (AiException $e) { check(str_contains($e->getMessage(), '30 detik'), 'timeout message'); }
    try { $ai->recommendTemplate($org, $user, '   ', $templates); check(false, 'empty prompt sent'); } catch (AiException) {}
    echo "PASS failures: HTTP error, unreadable answer, timeout and empty request reported, never silently filled\n";

    // --- log and attach
    $stats = $db->all('SELECT purpose, status, COUNT(*) n FROM ai_generations GROUP BY purpose, status ORDER BY purpose, status');
    $byKey = []; foreach ($stats as $s) $byKey[$s['purpose'] . ':' . $s['status']] = (int)$s['n'];
    check(($byKey['recommend_template:ok'] ?? 0) === 2 && ($byKey['recommend_template:invalid'] ?? 0) === 2 && ($byKey['recommend_template:error'] ?? 0) === 1, 'recommend log rows ' . json_encode($byKey));
    check(($byKey['generate:invalid'] ?? 0) === 3 && ($byKey['generate:ok'] ?? 0) === 1 && ($byKey['extract:error'] ?? 0) === 1, 'generate/extract log rows ' . json_encode($byKey));
    check((int)$db->value("SELECT COUNT(*) FROM ai_generations WHERE model = 'cc/test-model' AND latency_ms IS NOT NULL AND input_tokens = 100") > 0, 'model, latency and tokens recorded');
    $letters = new LetterService($db, $clock);
    $letterId = $letters->createDraft($user, $org, (int)$pmj['id'], 'Peminjaman kursi FilkomDay', array_filter($fields, static fn($v) => $v !== null),
        [['id' => 'pembuka'], ['id' => 'maksud', 'text' => $g['blocks']['maksud']['text']], ['id' => 'daftar_barang'], ['id' => 'penutup'], ['id' => 'tanda_tangan']],
        [['name' => 'Ketua Panitia', 'signer_ids' => [1]]], 1, $prompt);
    $versionId = (int)$db->value('SELECT current_version_id FROM letters WHERE id = ?', [$letterId]);
    $ai->attach(array_merge([$r['generation_id'], $x['generation_id']], $g['generation_ids']), $org, $letterId, $versionId);
    check((int)$db->value('SELECT COUNT(*) FROM ai_generations WHERE letter_id = ? AND version_id = ?', [$letterId, $versionId]) === 4, 'generations linked to the saved letter');
    check($db->value('SELECT source_prompt FROM letters WHERE id = ?', [$letterId]) === $prompt, 'request kept on the letter');
    check(LetterAiService::parseJson('Berikut hasilnya: {"a":1} terima kasih') === ['a' => 1], 'JSON inside prose parsed');
    echo "PASS log: every call in ai_generations with status, model, latency, tokens; linked to the letter on save\n";
} finally {
    $drop();
}
