<?php
$page = $data['page'];
$req = $page['request'];
$version = $page['version'];
$letter = $page['letter'];
$vno = (int)$version['version_no'];
$actionable = $req['status'] === 'pending' && $letter['status'] === 'in_review';
$bodyClass = $actionable ? 'pb-24 lg:pb-0' : '';
require __DIR__ . '/_layout_top.php';
$base = APP_PATH . '/review';
$token = rawurlencode($data['token']);
$csrfOnly = '<input type="hidden" name="csrf_token" value="' . e($data['csrf_token']) . '">';
$csrf = $csrfOnly . '<input type="hidden" name="content_hash" value="' . e($version['content_hash']) . '">';
$blockOptions = array_filter($data['preview']['blocks'], static fn($b) => $b['type'] !== 'signatures');
$code = $version['snapshot']['template']['code'] ?? '';
$rowState = static function (array $r): array {
    return match ($r['status']) {
        'approved' => ['approved', 'Disetujui', 'text-ok', pena_short($r['decided_at'])],
        'revision_requested' => ['revision', 'Minta revisi', 'text-rev', pena_short($r['decided_at'])],
        'pending' => ['pending', 'Menunggu keputusan', 'text-action', ''],
        'cancelled' => ['cancelled', 'Dibatalkan', 'text-secondary', ''],
        default => ['waiting', 'Belum giliran', 'text-secondary', 'Setelah tahap ' . ((int)$r['stage_order'] - 1)],
    };
};
?>
<header class="mb-6">
  <p class="text-secondary">Untuk <?= e($req['signer_name']); ?>, <?= e($req['signer_position']); ?></p>
  <h1 class="font-bold"><?= e($letter['title']); ?></h1>
  <div class="mt-1 flex flex-wrap items-center gap-2">
    <?= $code ? pena_strip($code) : ''; ?><?= pena_strip('V' . $vno); ?>
    <span class="text-secondary"><?= e($letter['letter_number']); ?></span>
    <?php if ($req['kind'] === 'reconfirm'): ?><?= pena_strip('Konfirmasi ulang', 'rev'); ?><?php endif; ?>
  </div>
</header>

<?php pena_flash($data['flash']); ?>

<?php if (!$actionable): ?>
  <section class="mb-6 max-w-[640px] rounded-panel bg-surface p-4 lg:p-5" role="status">
    <p class="flex items-center gap-3 font-bold"><?= pena_mark('revision'); ?>Proses sedang berhenti</p>
    <p class="mt-2 text-secondary">Pemeriksa lain di tahap ini meminta revisi, atau sekretaris sedang mengubah surat. Keputusan Anda belum diperlukan. Setelah surat diperbaiki, sekretaris mengirim tautan baru untuk versi berikutnya. Halaman ini hanya bisa dibaca.</p>
  </section>
<?php endif; ?>

<?php if ($req['kind'] === 'reconfirm'): ?>
  <section class="mb-6 rounded-panel bg-surface p-4 lg:p-5" aria-labelledby="ulang">
    <h2 id="ulang" class="font-bold">Surat ini direvisi setelah Anda menyetujui versi <?= (int)($page['previous']['version_no'] ?? $vno - 1); ?></h2>
    <p class="mt-2 text-secondary">Persetujuan lama hanya berlaku untuk versi sebelumnya. Periksa perubahan di bawah, lalu beri keputusan untuk versi <?= $vno; ?>. Keputusan dicatat atas nama pemegang tautan ini.</p>
    <?php if (!empty($version['change_summary'])): ?><p class="mt-2">Catatan sekretaris: &ldquo;<?= e($version['change_summary']); ?>&rdquo;</p><?php endif; ?>
    <?php if (!empty($data['changes'])): ?>
      <h3 class="colhead mt-4">Yang berubah</h3>
      <dl class="mt-1">
        <?php foreach ($data['changes'] as $c): ?>
          <div class="border-t border-line py-3">
            <dt class="font-semibold"><?= e($c['label']); ?></dt>
            <dd class="mt-1 grid gap-2 sm:grid-cols-2">
              <p><span class="colhead block">Lama</span><span class="text-secondary line-through decoration-muted"><?= e($c['before']); ?></span></p>
              <p><span class="colhead block">Baru</span><span class="bg-hl"><?= e($c['after']); ?></span></p>
            </dd>
          </div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
    <p class="mt-3 text-secondary">Surat lengkap versi <?= $vno; ?> ada di bawah. Mohon periksa seluruhnya.</p>
  </section>
<?php endif; ?>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
  <div class="min-w-0 space-y-6">
    <section aria-labelledby="jadwal">
      <h2 id="jadwal" class="colhead">Jadwal persetujuan</h2>
      <ol class="mt-1">
        <?php foreach ($page['schedule'] as $r): [$mk, $label, $cls, $time] = $rowState($r); $you = (int)$r['id'] === (int)$req['id']; ?>
          <li class="route-row<?= $you && $actionable ? ' is-you' : ''; ?>">
            <span class="pt-0.5"><?= pena_strip('T' . (int)$r['stage_order'], 'carbon'); ?></span>
            <div>
              <p class="font-semibold"><?= e($r['signer_name']); ?><?= $you ? ' (Anda)' : ''; ?></p>
              <p class="text-secondary"><?= e($r['signer_position']); ?></p>
              <p class="flex flex-wrap items-center gap-x-3"><span class="inline-flex items-center gap-2 font-semibold <?= $cls; ?>"><?= pena_mark($mk); ?><?= e($label); ?></span>
                <?php if ($time !== ''): ?><span class="text-secondary"><?= e($time); ?></span><?php endif; ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php $preview = $data['preview']; include dirname(__DIR__) . '/surat/_preview.php'; ?>
  </div>

  <div class="space-y-6 lg:sticky lg:top-20">
    <?php if ($actionable): ?>
      <section id="setuju" class="plate scroll-mt-20" aria-labelledby="keputusan">
        <h2 id="keputusan"><?= pena_strip('Keputusan Anda', 'ink'); ?></h2>
        <p class="mt-3">Berlaku untuk versi <?= $vno; ?> yang Anda baca. PENA tidak memeriksa identitas: keputusan dicatat atas nama pemegang tautan ini.</p>
        <form method="POST" action="<?= $base; ?>/setuju/<?= $token; ?>" class="mt-4">
          <?= $csrf; ?>
          <label for="note-ok" class="field-label">Catatan (opsional)</label>
          <textarea id="note-ok" name="note" rows="2" class="field"></textarea>
          <button class="btn btn-primary btn-block mt-4">Setujui versi <?= $vno; ?></button>
        </form>
      </section>

      <section id="revisi" class="scroll-mt-20" aria-labelledby="revisi-t">
        <h2 id="revisi-t" class="font-bold">Minta revisi</h2>
        <p class="mt-2 text-secondary">Proses berhenti sampai sekretaris mengirim versi baru kepada Anda.</p>
        <form method="POST" action="<?= $base; ?>/revisi/<?= $token; ?>" class="mt-4 space-y-4">
          <?= $csrf; ?>
          <div>
            <label for="block-ref" class="field-label">Bagian surat</label>
            <select id="block-ref" name="block_ref" class="field">
              <option value="">Seluruh surat</option>
              <?php foreach ($blockOptions as $b): ?><option value="<?= e($b['id']); ?>"><?= e(ucfirst(str_replace('_', ' ', $b['id']))); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="note-rev" class="field-label">Apa yang perlu diperbaiki?</label>
            <textarea id="note-rev" name="note" rows="3" required class="field"></textarea>
          </div>
          <button class="btn btn-secondary btn-block">Kirim permintaan revisi</button>
        </form>
      </section>

      <section aria-labelledby="komentar-t">
        <h2 id="komentar-t" class="font-bold">Komentar</h2>
        <p class="mt-2 text-secondary">Untuk pertanyaan atau catatan. Komentar tidak menghentikan proses.</p>
        <form method="POST" action="<?= $base; ?>/komentar/<?= $token; ?>" class="mt-4">
          <?= $csrfOnly; ?>
          <label for="comment-body" class="sr-only">Isi komentar</label>
          <textarea id="comment-body" name="body" rows="2" required class="field"></textarea>
          <button class="btn btn-secondary mt-3">Kirim komentar</button>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($page['comments']): ?>
      <section aria-labelledby="catatan-t">
        <h2 id="catatan-t" class="font-bold">Komentar sebelumnya</h2>
        <ul class="mt-2 space-y-3">
          <?php foreach ($page['comments'] as $c): ?>
            <li><p class="flex flex-wrap items-center gap-2 font-semibold"><?= $c['kind'] === 'revision_request' ? pena_strip('Revisi', 'rev') : ''; ?><?= e($c['author_name']); ?></p><p><?= nl2br(e($c['body'])); ?></p></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <p class="text-secondary">Tautan ini dikirim untuk <?= e($req['signer_name']); ?> dan berlaku sampai <?= e(pena_date((string)$req['expires_at'])); ?>. Jangan teruskan tautan ini.</p>
  </div>
</div>

<?php if ($actionable): ?>
  <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-2 gap-3 border-t border-line bg-surface px-4 py-3 lg:hidden" aria-label="Keputusan">
    <a href="#revisi" class="btn btn-secondary">Minta revisi</a>
    <a href="#setuju" class="btn btn-primary">Setujui</a>
  </nav>
<?php endif; ?>
  </div>
</main>
</body>
</html>
