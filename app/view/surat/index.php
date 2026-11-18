<?php
require dirname(__DIR__) . '/layout/app_top.php';
$base = APP_PATH . '/surat/';
$link = static fn(string $tab) => $base . '?' . http_build_query(array_filter(['status' => $tab === 'semua' ? null : $tab, 'q' => $data['q'] ?: null]));
?>
<header class="mb-6 flex items-center justify-between gap-4">
  <h1 class="font-bold">Surat</h1>
  <a href="<?= $base; ?>buat/" class="btn btn-primary">Buat surat</a>
</header>

<?php pena_flash($data['flash']); ?>

<div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
  <nav aria-label="Saring status" class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
    <ul class="flex gap-2">
      <?php foreach ($data['tabs'] as $key => $label): $on = $data['tab'] === $key;
        if ($key === 'cancelled' && $data['counts'][$key] === 0 && !$on) continue; ?>
        <li><a href="<?= e($link($key)); ?>"<?= $on ? ' aria-current="page"' : ''; ?>
          class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-control border border-line bg-surface px-3 no-underline hover:border-ink aria-[current=page]:border-plate aria-[current=page]:bg-plate aria-[current=page]:text-on-plate">
          <span class="font-semibold"><?= e($label); ?></span><span><?= (int)$data['counts'][$key]; ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <form method="GET" action="<?= $base; ?>" role="search" class="lg:w-[280px]">
    <?php if ($data['tab'] !== 'semua'): ?><input type="hidden" name="status" value="<?= e($data['tab']); ?>"><?php endif; ?>
    <label for="cari" class="sr-only">Cari perihal atau nomor</label>
    <input id="cari" type="search" name="q" value="<?= e($data['q']); ?>" placeholder="Cari perihal atau nomor" class="field">
  </form>
</div>

<section class="panel" aria-label="Daftar surat">
  <?php if (!$data['letters']): ?>
    <div class="p-6">
      <?php if ($data['total'] === 0): ?>
        <h2 class="font-bold">Belum ada surat</h2>
        <p class="mt-2 text-secondary">Ceritakan surat yang Anda butuhkan, atau isi manual dari kerangka. Nomor surat dibuat saat diajukan.</p>
        <a href="<?= $base; ?>buat/" class="btn btn-primary mt-4">Buat surat pertama</a>
      <?php else: ?>
        <h2 class="font-bold">Tidak ada surat yang cocok</h2>
        <p class="mt-2 text-secondary"><?= $data['q'] !== '' ? 'Tidak ada perihal atau nomor yang memuat "' . e($data['q']) . '".' : 'Tidak ada surat dengan status ini.'; ?></p>
        <a href="<?= $base; ?>" class="mt-3 inline-flex min-h-11 items-center font-semibold text-action underline">Tampilkan semua surat</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="hidden grid-cols-[72px_1fr_200px_120px] gap-4 border-b border-ink px-4 py-3 lg:grid" aria-hidden="true">
      <span class="colhead">Jenis</span><span class="colhead">Perihal dan nomor</span><span class="colhead">Status</span><span class="colhead text-right">Diperbarui</span>
    </div>
    <ul>
      <?php foreach ($data['letters'] as $l): ?>
        <li class="relative grid grid-cols-[56px_1fr] items-center gap-x-3 gap-y-1 border-b border-line px-4 py-4 last:border-b-0 hover:bg-page lg:grid-cols-[72px_1fr_200px_120px] lg:gap-4">
          <span class="row-span-2 self-start lg:row-span-1 lg:self-center"><?= pena_strip($l['template_code'] ?? ''); ?></span>
          <div class="min-w-0">
            <a href="<?= $base; ?>detail/<?= (int)$l['id']; ?>" class="font-semibold no-underline after:absolute after:inset-0 hover:underline"><?= e($l['title']); ?></a>
            <p class="text-secondary"><?= $l['letter_number'] ? e($l['letter_number']) : ($l['kind'] === 'personal' ? 'Surat pribadi, tanpa nomor' : 'Belum bernomor'); ?></p>
          </div>
          <div class="col-start-2 flex items-center justify-between gap-3 lg:contents">
            <span><?= pena_chip($l['status']); ?></span>
            <span class="text-secondary lg:text-right"><?= e(pena_date($l['updated_at'], false)); ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
