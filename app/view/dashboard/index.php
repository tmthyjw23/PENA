<?php
require dirname(__DIR__) . '/layout/app_top.php';

$letters = $data['letters'];
$days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$today = $days[(int)date('w')] . ' ' . date('j') . ' ' . $months[(int)date('n')] . ' ' . date('Y');
$detail = static fn(array $l) => APP_PATH . '/surat/detail/' . (int)$l['id'];

// What waits for the secretary, most urgent first.
$todo = [];
foreach ($letters as $l) {
    $id = (int)$l['id'];
    if ($l['status'] === 'revision_requested') {
        $r = $data['revisions'][$id] ?? null;
        $why = $r ? $r['author_name'] . ' meminta revisi' . ($r['block_ref'] ? ' pada bagian ' . str_replace('_', ' ', $r['block_ref']) : '') : 'Pemeriksa meminta revisi';
        $todo[] = [0, $l, pena_strip('Revisi', 'rev'), $why, 'Mulai revisi', $detail($l) . '?buka=dlg-revisi', 'btn-primary'];
    } elseif (isset($data['needs_link'][$id])) {
        $n = $data['needs_link'][$id];
        $todo[] = [1, $l, pena_strip('Tautan', 'ink'), 'Tahap ' . $n['stage'] . ' terbuka, ' . implode(', ', $n['names']) . ' belum menerima tautan', 'Buat tautan', $detail($l) . '#langkah', 'btn-secondary'];
    } elseif ($l['status'] === 'draft') {
        $todo[] = [2, $l, pena_strip('Draf'), 'Draf belum diajukan sejak ' . pena_date($l['updated_at'], false), 'Lanjutkan draf', APP_PATH . '/surat/edit/' . (int)$l['id'], 'btn-secondary'];
    }
}
usort($todo, static fn($a, $b) => $a[0] <=> $b[0]);
$count = static fn(string $s) => count(array_filter($letters, static fn($l) => $l['status'] === $s));
?>
<header class="mb-6">
  <h1 class="font-bold">Perlu tindakan Anda</h1>
  <p class="text-secondary"><?= $data['org'] ? e($data['org']['name']) . ', ' : ''; ?><?= e($today); ?></p>
</header>

<?php if (!$data['org']): ?>
  <section class="panel max-w-[640px] p-6">
    <h2 class="font-bold">Akun ini belum terdaftar di organisasi</h2>
    <p class="mt-2 text-secondary">Halaman surat hanya untuk sekretaris atau admin organisasi. Minta admin mendaftarkan akun Anda sebagai pengurus.</p>
  </section>
<?php else: ?>
<div class="grid items-start gap-6 xl:grid-cols-[1fr_360px]">
  <section class="panel" aria-label="Surat yang perlu tindakan">
    <?php if (!$todo): ?>
      <div class="p-6">
        <h2 class="font-bold"><?= $letters ? 'Tidak ada yang menunggu Anda' : 'Belum ada surat'; ?></h2>
        <p class="mt-2 text-secondary"><?= $letters ? 'Semua surat sedang di tangan pemeriksa atau sudah selesai.' : 'Ceritakan surat yang Anda butuhkan, atau isi manual dari kerangka. Nomor surat dibuat saat diajukan.'; ?></p>
        <a href="<?= APP_PATH; ?>/surat/buat/" class="btn btn-primary mt-4">Buat surat</a>
      </div>
    <?php else: ?>
      <ul>
        <?php foreach ($todo as [, $l, $strip, $why, $cta, $href, $btn]): ?>
          <li class="grid grid-cols-[72px_1fr] items-center gap-x-4 gap-y-3 border-b border-line px-4 py-4 last:border-b-0 sm:grid-cols-[72px_1fr_auto] lg:px-5">
            <div><?= $strip; ?></div>
            <div class="min-w-0">
              <a href="<?= e(APP_PATH . '/surat/detail/' . (int)$l['id']); ?>" class="font-semibold no-underline hover:underline"><?= e($l['title']); ?></a>
              <p class="text-secondary"><?= e($why); ?></p>
            </div>
            <a href="<?= e($href); ?>" class="btn <?= $btn; ?> col-start-2 justify-self-start sm:col-start-3"><?= e($cta); ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel p-4 lg:p-5" aria-labelledby="ringkasan">
    <h2 id="ringkasan" class="font-bold">Semua surat</h2>
    <dl class="mt-2">
      <?php foreach (['draft', 'in_review', 'revision_requested', 'approved', 'cancelled'] as $s): [$label, $mark] = pena_status($s);
        if ($s === 'cancelled' && $count($s) === 0) continue; ?>
        <div class="flex items-center justify-between border-b border-line py-2">
          <dt class="flex items-center gap-2"><?= pena_mark($mark); ?><?= e($label); ?></dt>
          <dd class="font-semibold"><?= $count($s); ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
    <a href="<?= APP_PATH; ?>/surat/" class="mt-3 inline-flex min-h-11 items-center font-semibold text-action underline">Lihat daftar surat</a>
  </section>
</div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
