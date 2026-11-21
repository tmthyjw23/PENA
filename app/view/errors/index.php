<?php
require_once dirname(__DIR__) . '/surat/_helpers.php';
$code = (int)($data['code'] ?? 404);
$title = match (true) {
    $code === 404 => 'Halaman tidak ditemukan',
    $code === 403 => 'Anda tidak punya akses ke halaman ini',
    $code === 400 => 'Permintaan ditolak',
    $code === 405 => 'Tindakan tidak tersedia',
    $code >= 500 => 'Halaman belum dapat dimuat',
    default => trim(preg_replace('/\s*\(.*\)$/', '', (string)($data['status_title'] ?? "Kesalahan $code"))),
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__) . '/layout/head.php'; ?>
  <title><?= e($code . ' ' . $title); ?> - PENA</title>
</head>
<body class="min-h-dvh">
<header class="flex h-14 items-center gap-3 bg-rail px-4 lg:px-8">
  <a href="<?= APP_PATH; ?>/" class="flex min-h-11 items-center gap-3 font-semibold no-underline"><span class="strip strip-carbon">PENA</span>Ruang surat</a>
</header>
<main class="px-4 py-10 lg:px-8 lg:py-16">
  <section class="mx-auto max-w-[1160px]">
    <?= pena_strip((string)$code, 'carbon'); ?>
    <h1 class="mt-4 font-cond text-[40px] font-bold leading-[1] lg:text-[56px]"><?= e($title); ?></h1>
    <p class="mt-3 max-w-[56ch] text-secondary"><?= e($data['message'] ?? 'Permintaan Anda tidak dapat diselesaikan.'); ?></p>
    <?php if (!empty($data['reference_id'])): ?><p class="mt-2 text-secondary">Kode referensi: <?= e($data['reference_id']); ?></p><?php endif; ?>
    <div class="mt-6 flex flex-wrap gap-3">
      <a href="<?= APP_PATH; ?>/<?= !empty($_SESSION['user-id']) ? 'surat/' : ''; ?>" class="btn btn-primary"><?= !empty($_SESSION['user-id']) ? 'Ke daftar surat' : 'Ke halaman depan'; ?></a>
      <button type="button" class="btn btn-secondary" data-history-back>Kembali</button>
    </div>
  </section>
</main>
</body>
</html>
