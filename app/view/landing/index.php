<?php
require_once dirname(__DIR__) . '/surat/_helpers.php';
$diag = $data['db_diagnostics'];
$steps = [
    ['Susun', 'Buat draf', 'Jelaskan kebutuhan surat atau isi formulir. Periksa data dan kalimat sebelum menyimpan.'],
    ['Ajukan', 'Kirim untuk diperiksa', 'Tambahkan pemeriksa bila diperlukan. Surat organisasi mendapat nomor saat diajukan.'],
    ['Periksa', 'Beri keputusan', 'Pemeriksa membuka tautan untuk menyetujui, memberi catatan, atau meminta revisi.'],
    ['Revisi', 'Perbarui versi', 'Perubahan disimpan sebagai versi baru. Persetujuan sebelumnya tetap tercatat pada versi lama.'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__) . '/layout/head.php'; ?>
  <title>PENA: surat pribadi dan organisasi</title>
  <meta name="description" content="Susun surat pribadi atau organisasi, minta pemeriksaan, dan unduh hasilnya dalam Word atau PDF.">
</head>
<body class="min-h-dvh">
<div class="bg-rail">
  <header class="mx-auto flex h-16 max-w-[1240px] items-center gap-3 px-4 lg:h-20 lg:px-8">
    <a href="<?= APP_PATH; ?>/" class="flex min-h-11 items-center gap-3 font-semibold no-underline" aria-label="PENA, beranda">
      <span class="strip strip-carbon">PENA</span>
      <span class="hidden sm:inline font-bold">Ruang surat</span>
    </a>
    <nav class="ml-auto flex items-center gap-3" aria-label="Halaman depan">
      <a href="#cara-kerja" class="inline-flex min-h-11 items-center px-2 font-semibold no-underline hover:underline">Cara kerja</a>
      <a href="<?= APP_PATH; ?>/login/" class="btn btn-secondary">Masuk</a>
    </nav>
  </header>

  <?php if (!empty($data['flash_message']) || !$diag['is_connected'] || !$diag['is_schema_ready']): ?>
    <div class="mx-auto max-w-[1240px] px-4 lg:px-8">
      <?php if (!empty($data['flash_message'])): pena_flash(['type' => $data['flash_message']['type'] ?? 'info', 'text' => trim(($data['flash_message']['title'] ?? '') . ' ' . ($data['flash_message']['text'] ?? ''))]); endif; ?>
      <?php if (!$diag['is_connected'] || !$diag['is_schema_ready']): ?>
        <div class="mb-6 rounded-panel bg-surface p-4" role="alert">
          <p class="font-semibold">Layanan sedang tidak tersedia</p>
          <p class="mt-2 text-secondary">Silakan coba kembali beberapa saat lagi.</p>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <section aria-label="Tentang PENA" class="mx-auto max-w-[1240px] px-4 pb-14 pt-6 lg:px-8 lg:pb-20 lg:pt-10">
    <div>
      <h1 class="font-cond text-[56px] font-bold leading-[0.95] tracking-[-0.015em] sm:text-[80px] lg:text-[96px]">Surat pribadi dan organisasi</h1>
      <p class="mt-5 max-w-[48ch] text-[16px] leading-6">Susun draf, periksa isinya, lalu unduh Word atau PDF. Untuk surat yang perlu persetujuan, kirim tautan kepada pemeriksa sesuai urutannya.</p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="<?= APP_PATH; ?>/login/" class="btn btn-primary">Masuk ke PENA</a>
        <a href="#cara-kerja" class="btn btn-secondary">Lihat cara kerja</a>
      </div>
    </div>
  </section>
</div>

<main>
  <section id="cara-kerja" class="mx-auto max-w-[1240px] scroll-mt-4 px-4 py-14 lg:px-8 lg:py-16">
    <h2 class="border-b-2 border-ink pb-4 font-cond text-[40px] font-bold leading-[1]">Dari draf ke surat selesai</h2>
    <ol class="mt-6 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($steps as [$code, $title, $text]): ?>
        <li>
          <?= pena_strip($code, 'carbon'); ?>
          <h3 class="mt-3 font-semibold"><?= e($title); ?></h3>
          <p class="mt-1 max-w-[36ch] text-secondary"><?= e($text); ?></p>
        </li>
      <?php endforeach; ?>
    </ol>

    <h2 class="mt-14 font-bold">Yang perlu Anda ketahui</h2>
    <ul class="mt-3 max-w-[72ch] space-y-3">
      <li>Pemeriksa tidak memerlukan akun. Siapa pun yang memiliki tautan dapat memberi keputusan; kirim tautan hanya kepada orang yang dituju.</li>
      <li>Draf berbantuan AI tetap perlu diperiksa. Pastikan data, isi, dan gaya bahasa sesuai kebutuhan Anda.</li>
    </ul>
  </section>
</main>

<footer class="border-t border-rule">
  <div class="mx-auto max-w-[1240px] px-4 py-6 lg:px-8 text-secondary">PENA</div>
</footer>
</body>
</html>
