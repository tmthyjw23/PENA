<?php
require dirname(__DIR__) . '/layout/app_top.php';
$isOrg = ($data['org']['kind'] ?? '') === 'organization';
$csrf = '<input type="hidden" name="csrf_token" value="' . e($data['csrf_token']) . '">';
?>
<header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
  <div>
    <h1 class="font-bold">Kop surat</h1>
    <p class="text-secondary"><?= $isOrg
        ? 'Kop yang tersimpan bisa dipilih saat membuat surat. Kop utama terpilih lebih dulu.'
        : 'Kop surat hanya dipakai ruang organisasi. Surat pribadi tidak memakai kop.'; ?></p>
  </div>
  <?php if ($isOrg): ?><a href="<?= APP_PATH; ?>/kop/baru/" class="btn btn-primary self-start sm:self-auto">Tambah kop</a><?php endif; ?>
</header>
<?php pena_flash($data['flash']); ?>

<?php if (!$isOrg): ?>
  <section class="panel max-w-[640px] p-5">
    <p>Pindah ke ruang organisasi lewat menu Ruang kerja untuk mengelola kop surat.</p>
  </section>
<?php elseif (!$data['kops']): ?>
  <section class="panel max-w-[640px] p-5">
    <h2 class="font-bold">Belum ada kop surat</h2>
    <p class="mt-2 text-secondary">Tambahkan kop dari isian teks dan logo, atau unggah gambar kop yang sudah jadi.</p>
    <a href="<?= APP_PATH; ?>/kop/baru/" class="btn btn-primary mt-4">Tambah kop</a>
  </section>
<?php else: ?>
  <ul class="grid gap-6 xl:grid-cols-2">
    <?php foreach ($data['kops'] as $k): $id = (int)$k['id']; ?>
      <li class="panel flex min-w-0 flex-col p-4 lg:p-5">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="min-w-0 flex-1 truncate font-bold"><?= e($k['name']); ?></h2>
          <?php if ((int)$k['is_default'] === 1): ?><?= pena_strip('Utama', 'ink'); ?><?php endif; ?>
          <?= pena_strip($k['kind'] === 'image' ? 'Gambar' : 'Teks', 'outline'); ?>
        </div>
        <div class="paper mt-4 min-w-0 px-4 py-4 text-[13px] leading-5 [overflow-wrap:anywhere]" aria-label="Pratinjau kop <?= e($k['name']); ?>">
          <?php $lh = $k; include dirname(__DIR__) . '/surat/_kop.php'; ?>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <a href="<?= APP_PATH; ?>/kop/ubah/<?= $id; ?>" class="btn btn-secondary">Ubah</a>
          <?php if ((int)$k['is_default'] !== 1): ?>
            <form method="POST" action="<?= APP_PATH; ?>/kop/utama/<?= $id; ?>"><?= $csrf; ?><button class="btn btn-secondary">Jadikan utama</button></form>
          <?php endif; ?>
          <button type="button" class="btn btn-quiet" data-open="dlg-nonaktif-<?= $id; ?>">Nonaktifkan</button>
        </div>
        <dialog id="dlg-nonaktif-<?= $id; ?>" class="sheet" aria-labelledby="dlg-nonaktif-<?= $id; ?>-t">
          <form method="POST" action="<?= APP_PATH; ?>/kop/nonaktif/<?= $id; ?>" class="space-y-4">
            <?= $csrf; ?>
            <h2 id="dlg-nonaktif-<?= $id; ?>-t" class="font-bold">Nonaktifkan kop "<?= e($k['name']); ?>"?</h2>
            <p class="text-secondary">Kop ini tidak bisa dipilih untuk surat baru. Surat yang sudah memakainya tetap tampil dengan kop ini.</p>
            <div class="flex flex-wrap gap-3"><button class="btn btn-danger">Nonaktifkan</button><button type="button" class="btn btn-secondary" data-close>Kembali</button></div>
          </form>
        </dialog>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
