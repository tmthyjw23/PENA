<?php
require dirname(__DIR__) . '/layout/app_top.php';

$letter = $data['letter'];
$version = $data['version'];
$vno = (int)$version['version_no'];
$status = $letter['status'];
$isWorking = $version['submitted_at'] === null;
$open = !in_array($status, ['approved', 'cancelled'], true);
$base = APP_PATH . '/surat';
$id = (int)$letter['id'];
$csrf = '<input type="hidden" name="csrf_token" value="' . e($data['csrf_token']) . '">';
// Personal letters carry no number; "first submission" is version 1 instead. Without reviewers they are final when submitted.
$personal = ($letter['workspace_kind'] ?? '') === 'personal';
$firstSubmit = $personal ? $vno === 1 : $letter['letter_number'] === null;
$selfOnly = $personal && $data['stages'] === [];
$eventLabels = [
    'draft_created' => 'Draf dibuat', 'draft_updated' => 'Draf diperbarui', 'number_assigned' => 'Nomor diberikan',
    'submitted' => 'Diajukan', 'review_opened' => 'Dibuka pemeriksa', 'approved' => 'Disetujui',
    'revision_requested' => 'Minta revisi', 'revision_started' => 'Revisi dimulai', 'letter_approved' => 'Surat disetujui semua pihak',
    'cancelled' => 'Dibatalkan', 'signer_replaced' => 'Pemeriksa diganti', 'link_reissued' => 'Tautan dibuat',
    'ai_drafted' => 'Disusun dengan AI dari permintaan sekretaris',
];

// Reviewers that need a link from the secretary, and the request that asked for revision on this version.
$needLink = [];
$lastApproval = null;
foreach ($data['stages'] as $stage) foreach ($stage['members'] as $m) {
    $r = $m['request'];
    $expired = !empty($r['token_expires_at']) && strtotime($r['token_expires_at']) < time();
    if ($status === 'in_review' && ($r['status'] ?? '') === 'pending' && (!(int)$r['has_link'] || $expired)) {
        $needLink[] = $m + ['stage_order' => $stage['order'], 'expired' => $expired && (int)$r['has_link']];
    }
    if (($r['status'] ?? '') === 'approved' && ($lastApproval === null || $r['decided_at'] > $lastApproval['decided_at'])) $lastApproval = $r + ['name' => $m['signer_name']];
}
$revisionNote = null;
foreach ($data['comments'] as $c) {
    if ($c['kind'] === 'revision_request' && (int)$c['version_no'] === $vno) { $revisionNote = $c; break; }
}
$highlight = $status === 'revision_requested' ? ($revisionNote['block_ref'] ?? null) : null;

/** Route row status: [mark, label, colour, time, needs action]. */
$rowState = static function (array $stage, array $m) use ($status, $isWorking): array {
    $r = $m['request'];
    $prev = $stage['order'] > 1 ? 'Setelah tahap ' . ($stage['order'] - 1) : 'Setelah diajukan';
    if ($status === 'cancelled' && !in_array($r['status'] ?? '', ['approved', 'revision_requested'], true)) return ['cancelled', 'Dibatalkan', 'text-secondary', 'Tidak dilanjutkan', false];
    if ($isWorking || $r === null) return ['waiting', 'Belum giliran', 'text-secondary', $isWorking && $stage['order'] === 1 ? 'Dibuka saat diajukan' : $prev, false];
    $expired = !empty($r['token_expires_at']) && strtotime($r['token_expires_at']) < time();
    return match ($r['status']) {
        'approved' => ['approved', 'Disetujui', 'text-ok', pena_short($r['decided_at']), false],
        'revision_requested' => ['revision', 'Minta revisi', 'text-rev', pena_short($r['decided_at']), false],
        'pending' => $status !== 'in_review' ? ['waiting', 'Proses berhenti', 'text-secondary', '', false]
            : (!(int)$r['has_link'] ? ['pending', 'Menunggu tautan dari Anda', 'text-action', '', true]
            : ($expired ? ['revision', 'Tautan kedaluwarsa', 'text-rev', 'Sejak ' . pena_short($r['token_expires_at']), true]
            : ['pending', 'Menunggu keputusan', 'text-action', 'Tautan berlaku sampai ' . pena_date($r['token_expires_at'], false), false])),
        'cancelled' => ['cancelled', 'Dibatalkan', 'text-secondary', '', false],
        'superseded' => ['waiting', 'Digantikan revisi', 'text-secondary', '', false],
        default => ['waiting', 'Belum giliran', 'text-secondary', $prev, false],
    };
};

$statusLine = match (true) {
    $status === 'draft' && $firstSubmit => ['Draf, belum ' . ($selfOnly ? 'diselesaikan' : 'diajukan'), 'text-secondary'],
    $status === 'approved' && $selfOnly => ['Selesai', 'text-ok'],
    $status === 'draft' => ['Draf revisi, belum diajukan', 'text-secondary'],
    default => [pena_status($status)[0], pena_status($status)[2]],
};
?>
<header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
  <div>
    <nav aria-label="Lokasi" class="hidden text-secondary lg:block"><a href="<?= $base; ?>/" class="no-underline hover:underline">Surat</a> / <?= e($letter['title']); ?></nav>
    <h1 class="font-bold"><?= e($letter['title']); ?></h1>
    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
      <?= pena_strip($letter['template_code'] ?? ''); ?><?= pena_strip('V' . $vno); ?>
      <span class="text-secondary"><?= e($personal ? 'Surat pribadi, tanpa nomor' : ($letter['letter_number'] ?? ($status === 'cancelled' ? 'Tidak bernomor' : 'Nomor dibuat saat diajukan'))); ?></span>
      <span class="font-semibold <?= $statusLine[1]; ?>"><?= e($statusLine[0]); ?></span>
    </div>
  </div>
  <?php if ($open): ?>
    <div class="flex flex-wrap gap-2">
      <button type="button" class="btn btn-quiet" data-open="dlg-batal">Batalkan surat</button>
      <?php if ($status === 'in_review'): ?><button type="button" class="btn btn-secondary" data-open="dlg-revisi">Mulai revisi</button><?php endif; ?>
    </div>
  <?php endif; ?>
</header>

<?php pena_flash($data['flash']); ?>
<?php if (!empty($data['errors'])): ?>
  <div class="mb-6 rounded-panel bg-rev-soft px-4 py-3" role="alert">
    <p class="font-semibold">Belum bisa diajukan:</p>
    <ul class="mt-1 list-disc pl-5"><?php foreach ($data['errors'] as $msg): ?><li><?= e($msg); ?></li><?php endforeach; ?></ul>
    <a href="<?= $base; ?>/edit/<?= $id; ?>" class="mt-2 inline-flex min-h-11 items-center font-semibold text-action underline">Perbaiki draf</a>
  </div>
<?php endif; ?>

<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,480px)] xl:grid-rows-[auto_1fr] 2xl:grid-cols-[minmax(0,1fr)_520px]">

  <!-- Kanan atas: pelat berikutnya lalu jadwal di DOM (urutan fokus); di desktop jadwal tampil di atas pelat -->
  <div class="flex flex-col gap-6 xl:col-start-2 xl:row-start-1">
    <section id="langkah" class="plate scroll-mt-20 xl:order-2" aria-label="Langkah berikutnya">
      <?php if (!empty($data['links'])): ?>
        <?= pena_strip('Kirim tautan ini', 'ink'); ?>
        <p class="mt-3">Tautan hanya ditampilkan sekali. Kirim langsung ke orangnya, misalnya lewat WhatsApp.</p>
        <p class="mt-2">Siapa pun yang memegang tautan bisa memberi keputusan atas nama pemeriksa itu.</p>
        <?php foreach ($data['links'] as $link): $lid = 'link' . substr(md5($link['url']), 0, 8); ?>
          <label for="<?= $lid; ?>" class="mt-4 block font-semibold"><?= e($link['signer_name']); ?><?= $link['kind'] === 'reconfirm' ? ' (konfirmasi ulang)' : ''; ?></label>
          <div class="mt-2 flex gap-2">
            <input id="<?= $lid; ?>" class="field min-w-0 flex-1 text-ink" readonly value="<?= e($link['url']); ?>" onclick="this.select()">
            <button type="button" class="btn btn-primary" data-copy="<?= $lid; ?>">Salin tautan</button>
          </div>
        <?php endforeach; ?>

      <?php elseif ($needLink): ?>
        <?= pena_strip('Berikutnya', 'ink'); ?>
        <?php foreach ($needLink as $m): ?>
          <p class="mt-3"><?= $m['expired'] ? 'Tautan untuk ' . e($m['signer_name']) . ' sudah kedaluwarsa. Buat tautan baru, lalu kirim lagi.'
            : 'Tahap ' . (int)$m['stage_order'] . ' sudah terbuka. Buat tautan sekali pakai, lalu kirim ke ' . e($m['signer_name']) . ' lewat WhatsApp.'; ?></p>
          <form method="POST" action="<?= $base; ?>/tautan/<?= $id; ?>" class="mt-3">
            <?= $csrf; ?><input type="hidden" name="request_id" value="<?= (int)$m['request']['id']; ?>">
            <button class="btn btn-primary w-full sm:w-auto">Buat tautan untuk <?= e($m['signer_name']); ?></button>
          </form>
        <?php endforeach; ?>

      <?php elseif ($status === 'draft'): ?>
        <?= pena_strip('Draf', 'carbon'); ?>
        <?php if ($selfOnly): ?>
          <p class="mt-3">Surat ini tanpa pemeriksa. Saat diselesaikan, isinya dikunci dan surat langsung siap dikirim atau dicetak.</p>
        <?php elseif ($firstSubmit): ?>
          <p class="mt-3">Draf ini belum dikirim. Saat diajukan,<?= $personal ? '' : ' nomor surat dibuat,'; ?> isi versi <?= $vno; ?> dikunci, dan tahap 1 dibuka.</p>
        <?php else: ?>
          <p class="mt-3">Versi <?= $vno; ?> dibuat dari revisi.<?= $letter['letter_number'] ? ' Nomor ' . e($letter['letter_number']) . ' tetap.' : ''; ?> Saat diajukan, penyetuju versi sebelumnya diminta konfirmasi ulang dengan daftar perubahan.</p>
          <?php if (!empty($version['change_summary'])): ?><p class="mt-2">Ringkasan untuk pemeriksa: &ldquo;<?= e($version['change_summary']); ?>&rdquo;</p><?php endif; ?>
        <?php endif; ?>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary" data-open="dlg-ajukan"><?= $selfOnly ? 'Selesaikan surat' : ($firstSubmit ? 'Ajukan untuk review' : 'Ajukan versi ' . $vno); ?></button>
          <a href="<?= $base; ?>/edit/<?= $id; ?>" class="btn btn-secondary">Ubah draf</a>
        </div>

      <?php elseif ($status === 'revision_requested'): ?>
        <?= pena_strip('Revisi diminta', 'rev'); ?>
        <?php if ($revisionNote): ?>
          <p class="mt-3"><?= e($revisionNote['author_name']); ?> meminta revisi<?= $revisionNote['block_ref'] ? ' pada bagian ' . e(str_replace('_', ' ', $revisionNote['block_ref'])) : ''; ?>: &ldquo;<?= e($revisionNote['body']); ?>&rdquo;</p>
        <?php else: ?>
          <p class="mt-3">Pemeriksa diganti saat surat direview. Versi <?= $vno; ?> masih memuat pemeriksa lama, jadi surat perlu versi baru.</p>
        <?php endif; ?>
        <p class="mt-2">Mulai revisi untuk membuat versi <?= $vno + 1; ?>. Persetujuan yang sudah ada tetap tercatat dan penyetujunya diminta konfirmasi ulang.</p>
        <button type="button" class="btn btn-primary mt-4" data-open="dlg-revisi">Mulai revisi</button>

      <?php elseif ($status === 'approved'): ?>
        <?= pena_strip($selfOnly ? 'Selesai' : 'Disetujui semua pihak', 'ok'); ?>
        <?php if ($selfOnly): ?>
          <p class="mt-3">Versi <?= $vno; ?> diselesaikan pada <?= e(pena_date($letter['approved_at'])); ?> dan isinya terkunci. Kirim ke penerima, atau cetak untuk tanda tangan basah.</p>
        <?php else: ?>
        <p class="mt-3">Versi <?= $vno; ?> disetujui<?= $lastApproval ? ' ' . e($lastApproval['name']) . ' pada ' . e(pena_date($lastApproval['decided_at'])) : ''; ?>. Unduh untuk dicetak dan ditandatangani basah bila diperlukan.</p>
        <?php endif; ?>
        <p class="mt-2 text-on-plate/80">Ekspor Word dan PDF disambungkan di tahap fitur berikutnya.</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary" disabled>Unduh PDF</button>
          <button type="button" class="btn btn-secondary" disabled>Unduh Word</button>
        </div>

      <?php elseif ($status === 'cancelled'): ?>
        <?= pena_strip('Dibatalkan', 'carbon'); ?>
        <p class="mt-3">Dibatalkan pada <?= e(pena_date($letter['cancelled_at'])); ?>. Alasan: &ldquo;<?= e($letter['cancel_reason']); ?>&rdquo;</p>
        <?php if ($letter['letter_number']): ?><p class="mt-2">Nomor <?= e($letter['letter_number']); ?> tetap tercatat terpakai dan tidak diberikan ke surat lain. Tautan yang belum dipakai tidak berlaku lagi.</p><?php endif; ?>

      <?php else: ?>
        <?= pena_strip('Berikutnya', 'ink'); ?>
        <?php $waitingOn = [];
          foreach ($data['stages'] as $stage) foreach ($stage['members'] as $m) if (($m['request']['status'] ?? '') === 'pending') $waitingOn[] = $m['signer_name']; ?>
        <p class="mt-3">Menunggu keputusan <?= $waitingOn ? e(implode(', ', $waitingOn)) : 'pemeriksa'; ?>. Begitu tahap ini selesai, tahap berikutnya muncul di sini dan Anda membuat tautannya.</p>
      <?php endif; ?>
    </section>

    <section class="panel p-4 lg:p-5 xl:order-1" aria-labelledby="jadwal">
      <h2 id="jadwal" class="flex items-center gap-3 font-bold">Jadwal persetujuan <?= pena_strip('V' . $vno); ?></h2>
      <?php if ($selfOnly): ?>
        <p class="mt-2 text-secondary">Tanpa pemeriksa. Surat ditandatangani sendiri oleh pembuatnya.</p>
      <?php endif; ?>
      <ol class="mt-2">
        <?php foreach ($data['stages'] as $stage): foreach ($stage['members'] as $m):
          [$mk, $label, $cls, $time, $act] = $rowState($stage, $m);
          $reconfirm = ($m['request']['kind'] ?? '') === 'reconfirm'; ?>
          <li class="route-row<?= $act ? ' is-you' : ''; ?>">
            <span class="pt-0.5"><?= pena_strip('T' . (int)$stage['order'], 'carbon'); ?></span>
            <div class="min-w-0">
              <p class="font-semibold"><?= e($m['signer_name']); ?></p>
              <p class="text-secondary"><?= e($m['signer_position']); ?><?= $reconfirm ? ', setuju ulang untuk versi ' . $vno : ''; ?></p>
              <p class="flex flex-wrap items-center gap-x-3"><span class="inline-flex items-center gap-2 font-semibold <?= $cls; ?>"><?= pena_mark($mk); ?><?= e($label); ?></span>
                <?php if ($time !== ''): ?><span class="text-secondary"><?= e($time); ?></span><?php endif; ?></p>
              <?php if (!empty($m['request']['decision_note'])): ?><p class="mt-1">&ldquo;<?= e($m['request']['decision_note']); ?>&rdquo;</p><?php endif; ?>
              <?php if ($open && ($m['request']['status'] ?? '') !== 'approved'): ?>
                <button type="button" class="mt-1 min-h-11 font-semibold text-action underline" data-ganti="<?= (int)$m['member_id']; ?>" data-nama="<?= e($m['signer_name']); ?>" data-tahap="<?= (int)$stage['order']; ?>">Ganti pemeriksa</button>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; endforeach; ?>
      </ol>
      <?php if (!empty($data['stages']) && array_filter($data['stages'], static fn($s) => count($s['members']) > 1)): ?>
        <p class="mt-3 text-secondary">Dalam satu tahap, semua pemeriksa harus setuju sebelum tahap berikutnya dibuka.</p>
      <?php endif; ?>
    </section>
  </div>

  <!-- Kertas surat -->
  <div class="min-w-0 xl:col-start-1 xl:row-span-2 xl:row-start-1">
    <?php $preview = $data['preview']; include __DIR__ . '/_preview.php'; ?>
    <p class="mt-4 text-secondary"><?= $isWorking
      ? 'Sidik konten dibuat saat diajukan. Persetujuan nanti hanya berlaku untuk isi dengan sidik itu.'
      : 'Sidik konten versi ' . $vno . ': ' . e(substr((string)$version['content_hash'], 0, 16)) . '. Persetujuan hanya berlaku untuk isi dengan sidik ini.'; ?></p>
  </div>

  <!-- Kanan bawah: komentar + riwayat -->
  <div class="flex flex-col gap-6 xl:col-start-2 xl:row-start-2">
    <section class="panel p-4 lg:p-5" aria-labelledby="komentar">
      <h2 id="komentar" class="font-bold">Komentar dan permintaan revisi</h2>
      <?php if (empty($data['comments'])): ?>
        <p class="mt-2 text-secondary"><?= $letter['letter_number'] === null ? 'Belum ada komentar. Pemeriksa bisa berkomentar setelah surat diajukan.' : 'Belum ada komentar dari pemeriksa.'; ?></p>
      <?php endif; ?>
      <ul class="mt-2 space-y-4">
        <?php foreach ($data['comments'] as $c): $rev = $c['kind'] === 'revision_request'; ?>
          <li>
            <p class="flex flex-wrap items-center gap-2 font-semibold"><?= $rev ? pena_strip('Revisi', 'rev') : ''; ?>
              <span><?= e($c['author_name']); ?>, versi <?= (int)$c['version_no']; ?><?= $c['block_ref'] ? ', bagian ' . e(str_replace('_', ' ', $c['block_ref'])) : ''; ?></span></p>
            <p><?= nl2br(e($c['body'])); ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <details class="panel group p-4 lg:p-5">
      <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-bold [&::-webkit-details-marker]:hidden">
        Riwayat surat, <?= count($data['events']); ?> kejadian <span class="font-semibold text-action group-open:hidden">Buka</span><span class="hidden font-semibold text-action group-open:inline">Tutup</span>
      </summary>
      <ol class="mt-2">
        <?php foreach ($data['events'] as $ev): ?>
          <li class="grid grid-cols-[96px_1fr] gap-3 border-t border-line py-2">
            <time class="text-secondary" datetime="<?= e($ev['created_at']); ?>"><?= e(pena_short($ev['created_at'])); ?></time>
            <span><?= e($eventLabels[$ev['event']] ?? $ev['event']); ?><?= $ev['version_no'] ? ', versi ' . (int)$ev['version_no'] : ''; ?><span class="text-secondary">, <?= e($ev['actor_label']); ?></span></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </details>
  </div>
</div>

<?php if ($open): ?>
  <?php if ($status === 'draft'): ?>
  <dialog id="dlg-ajukan" class="sheet" aria-labelledby="dlg-ajukan-t">
    <form method="POST" action="<?= $base; ?>/ajukan/<?= $id; ?>" class="space-y-4">
      <?= $csrf; ?>
      <?php if ($selfOnly): ?>
        <h2 id="dlg-ajukan-t" class="font-bold">Selesaikan surat?</h2>
        <p class="text-secondary">Isi surat dikunci dan tidak bisa diubah lagi. Bila ada yang salah setelah ini, buat surat baru.</p>
        <div class="flex flex-wrap gap-3"><button class="btn btn-primary">Selesaikan</button><button type="button" class="btn btn-secondary" data-close>Kembali</button></div>
      <?php else: ?>
      <h2 id="dlg-ajukan-t" class="font-bold"><?= $firstSubmit ? 'Ajukan surat untuk review?' : 'Ajukan versi ' . $vno . '?'; ?></h2>
      <?php if (!$personal): ?><p class="text-secondary"><?= $letter['letter_number'] === null ? 'Nomor surat dibuat sekarang dan tetap tercatat terpakai, termasuk bila surat nanti dibatalkan.' : 'Nomor ' . e($letter['letter_number']) . ' tetap dipakai.'; ?></p><?php endif; ?>
      <p class="text-secondary">Isi versi <?= $vno; ?> dikunci. Perubahan setelah ini menjadi versi baru.</p>
      <p class="text-secondary">Tautan untuk pemeriksa yang sudah giliran tampil sekali di halaman ini; kirim sendiri lewat WhatsApp.</p>
      <div class="flex flex-wrap gap-3"><button class="btn btn-primary">Ajukan sekarang</button><button type="button" class="btn btn-secondary" data-close>Kembali</button></div>
      <?php endif; ?>
    </form>
  </dialog>
  <?php endif; ?>

  <?php if (in_array($status, ['in_review', 'revision_requested'], true)): ?>
  <dialog id="dlg-revisi" class="sheet" aria-labelledby="dlg-revisi-t">
    <form method="POST" action="<?= $base; ?>/revisi/<?= $id; ?>" class="space-y-4">
      <?= $csrf; ?>
      <h2 id="dlg-revisi-t" class="font-bold">Mulai revisi ke versi <?= $vno + 1; ?></h2>
      <p class="text-secondary">Versi <?= $vno; ?> berhenti direview dan tautan yang belum dipakai tidak berlaku lagi. Persetujuan yang sudah ada tetap tercatat untuk versi <?= $vno; ?>; penyetujunya diminta konfirmasi ulang setelah versi <?= $vno + 1; ?> diajukan.</p>
      <div>
        <label for="summary" class="field-label">Ringkasan perubahan (wajib)</label>
        <textarea id="summary" name="summary" rows="3" required class="field" aria-describedby="summary-h"></textarea>
        <p id="summary-h" class="field-help">Ditampilkan kepada pemeriksa yang mengonfirmasi ulang.</p>
      </div>
      <div class="flex flex-wrap gap-3"><button class="btn btn-primary">Buat versi <?= $vno + 1; ?></button><button type="button" class="btn btn-secondary" data-close>Kembali</button></div>
    </form>
  </dialog>
  <?php endif; ?>

  <dialog id="dlg-batal" class="sheet" aria-labelledby="dlg-batal-t">
    <form method="POST" action="<?= $base; ?>/batal/<?= $id; ?>" class="space-y-4">
      <?= $csrf; ?>
      <h2 id="dlg-batal-t" class="font-bold">Batalkan surat ini?</h2>
      <p class="text-secondary">Surat yang dibatalkan tidak bisa diajukan lagi.<?= $letter['letter_number'] ? ' Nomor ' . e($letter['letter_number']) . ' tetap tercatat terpakai, dan tautan yang belum dipakai tidak berlaku lagi.' : ''; ?></p>
      <div>
        <label for="reason" class="field-label">Alasan pembatalan (wajib)</label>
        <input id="reason" name="reason" required class="field" aria-describedby="reason-h">
        <p id="reason-h" class="field-help">Dicatat di riwayat surat.</p>
      </div>
      <div class="flex flex-wrap gap-3"><button class="btn btn-danger">Ya, batalkan surat</button><button type="button" class="btn btn-secondary" data-close>Kembali</button></div>
    </form>
  </dialog>

  <dialog id="dlg-ganti" class="sheet" aria-labelledby="dlg-ganti-t">
    <form method="POST" action="<?= $base; ?>/ganti/<?= $id; ?>" class="space-y-4">
      <?= $csrf; ?><input type="hidden" name="member_id" value="">
      <h2 id="dlg-ganti-t" class="font-bold">Ganti pemeriksa tahap <span data-slot="tahap"></span></h2>
      <p class="text-secondary"><span data-slot="nama"></span> akan diganti.</p>
      <div>
        <label for="signer_id" class="field-label">Pemeriksa pengganti</label>
        <select id="signer_id" name="signer_id" class="field" aria-describedby="signer-h">
          <?php foreach ($data['signers'] as $s): ?><option value="<?= (int)$s['id']; ?>"><?= e($s['name']); ?>, <?= e($s['position']); ?></option><?php endforeach; ?>
        </select>
        <p id="signer-h" class="field-help">Dari direktori penanda tangan organisasi.</p>
      </div>
      <?php if ($status === 'in_review'): ?>
        <p class="text-rev">Surat sedang direview. Setelah diganti, status menjadi Diminta revisi: mulai revisi lalu ajukan ulang, karena versi <?= $vno; ?> masih memuat nama pemeriksa lama. Tautan yang belum dipakai tidak berlaku lagi.</p>
      <?php endif; ?>
      <div class="flex flex-wrap gap-3"><button class="btn btn-primary">Ganti pemeriksa</button><button type="button" class="btn btn-secondary" data-close>Kembali</button></div>
    </form>
  </dialog>
  <script>
    document.querySelectorAll('[data-ganti]').forEach(b => b.addEventListener('click', () => {
      const d = document.getElementById('dlg-ganti');
      d.querySelector('[name=member_id]').value = b.dataset.ganti;
      d.querySelector('[data-slot=nama]').textContent = b.dataset.nama;
      d.querySelector('[data-slot=tahap]').textContent = b.dataset.tahap;
      d.showModal();
    }));
  </script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
