<?php
require_once dirname(__DIR__) . '/surat/_helpers.php';
$diag = $data['db_diagnostics'];
$months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$sample = [
    ['PMJ', 'ok', 'Peminjaman kursi FilkomDay', 'Dekan', 'Disetujui', 'text-ok-on-board'],
    ['PMJ', 'ink', 'Proyektor seminar UI', 'Advisor', 'Menunggu tautan', 'text-white'],
    ['PMH', 'rev', 'Dana Malam Keakraban', 'Advisor', 'Minta revisi', 'text-rev-on-board'],
    ['PMH', 'carbon', 'Izin bakti sosial', 'Draf', 'Belum diajukan', 'text-on-board-muted'],
];
$steps = [
    ['Susun', 'Dari kerangka', 'Pilih jenis surat, isi data sekali, tulis paragraf dengan penanda isian.'],
    ['Ajukan', 'Nomor dan kunci', 'Nomor surat dibuat otomatis dan isi versi dikunci saat diajukan.'],
    ['Periksa', 'Berurutan', 'Tiap pemeriksa membuka tautan, lalu setuju, berkomentar, atau minta revisi.'],
    ['Revisi', 'Dengan jejak', 'Revisi menjadi versi baru. Penyetuju lama melihat daftar perubahan lalu mengonfirmasi ulang.'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__) . '/layout/head.php'; ?>
  <title>PENA: surat organisasi dari draf sampai disetujui</title>
  <meta name="description" content="PENA membantu sekretaris organisasi mahasiswa menyusun surat dari kerangka dan meminta persetujuan bertahap lewat tautan.">
</head>
<body class="min-h-dvh">
<div class="bg-rail">
  <header class="mx-auto flex h-16 max-w-[1240px] items-center gap-3 px-4 lg:h-20 lg:px-8">
    <a href="<?= APP_PATH; ?>/" class="flex min-h-11 items-center gap-3 font-semibold no-underline"><span class="strip strip-carbon">PENA</span><span class="hidden sm:inline">Surat organisasi</span></a>
    <nav class="ml-auto flex items-center gap-2" aria-label="Halaman depan">
      <a href="#cara-kerja" class="inline-flex min-h-11 items-center px-2 font-semibold no-underline hover:underline">Cara kerja</a>
      <a href="<?= APP_PATH; ?>/login/" class="btn btn-secondary">Masuk</a>
    </nav>
  </header>

  <?php if (!empty($data['flash_message']) || !$diag['is_connected'] || !$diag['is_schema_ready']): ?>
    <div class="mx-auto max-w-[1240px] px-4 lg:px-8">
      <?php if (!empty($data['flash_message'])): pena_flash(['type' => $data['flash_message']['type'] ?? 'info', 'text' => trim(($data['flash_message']['title'] ?? '') . ' ' . ($data['flash_message']['text'] ?? ''))]); endif; ?>
      <?php if (!$diag['is_connected'] || !$diag['is_schema_ready']): ?>
        <div class="mb-6 rounded-panel bg-surface p-4" role="alert">
          <p class="flex items-center gap-3 font-bold"><?= pena_mark('revision'); ?><?= !$diag['is_connected'] ? 'Database belum terhubung' : 'Tabel database belum diimpor'; ?></p>
          <p class="mt-2 text-secondary"><?= !$diag['is_connected']
            ? 'Tidak bisa terhubung ke database ' . e($diag['database']) . ' di ' . e($diag['host']) . '. Pastikan MySQL berjalan, lalu jalankan composer db:install.'
            : 'Database ' . e($diag['database']) . ' terhubung, tetapi tabel PENA belum lengkap. Jalankan composer db:install (aman diulang) atau impor database/pena_schema.sql.'; ?></p>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <section class="mx-auto grid max-w-[1240px] items-center gap-10 px-4 pb-14 pt-6 lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:px-8 lg:pb-20 lg:pt-10">
    <div>
      <h1 class="font-cond text-[56px] font-bold leading-[0.95] tracking-[-0.015em] sm:text-[80px] lg:text-[96px]">Surat organisasi, dari draf sampai disetujui</h1>
      <p class="mt-5 max-w-[48ch]">Sekretaris menyusun surat dari kerangka resmi. Ketua, advisor, dan dekan memeriksa lewat tautan, berurutan, tanpa perlu akun.</p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="<?= APP_PATH; ?>/login/" class="btn btn-primary">Masuk ke PENA</a>
        <a href="#cara-kerja" class="btn btn-secondary">Lihat cara kerja</a>
      </div>
    </div>
    <figure class="rounded-panel bg-board p-4 text-white shadow-board lg:p-5" aria-label="Contoh papan jadwal persetujuan">
      <figcaption class="flex items-center justify-between gap-3"><span class="colhead text-white">Jadwal persetujuan</span><span class="text-on-board-muted">Contoh, <?= date('j') . ' ' . $months[(int)date('n')] . ' ' . date('Y'); ?></span></figcaption>
      <table class="mt-3 w-full text-left">
        <thead><tr class="border-b border-white/70"><th class="colhead py-2 font-semibold text-on-board-muted">Kode</th><th class="colhead py-2 text-on-board-muted">Perihal</th><th class="colhead hidden py-2 text-on-board-muted sm:table-cell">Tahap</th><th class="colhead py-2 text-on-board-muted">Status</th></tr></thead>
        <tbody>
          <?php foreach ($sample as [$code, $tone, $title, $stage, $status, $cls]): ?>
            <tr class="border-b border-board-row">
              <td class="py-3 pr-3"><span class="strip strip-<?= $tone; ?> <?= $tone === 'carbon' ? 'border border-white/30' : ''; ?>"><?= $code; ?></span></td>
              <td class="py-3 pr-3 font-semibold"><?= e($title); ?></td>
              <td class="hidden py-3 pr-3 text-on-board-muted sm:table-cell"><?= e($stage); ?></td>
              <td class="py-3 font-semibold <?= $cls; ?>"><?= e($status); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </figure>
  </section>
</div>

<main>
  <section id="cara-kerja" class="mx-auto max-w-[1240px] scroll-mt-4 px-4 py-14 lg:px-8 lg:py-16">
    <h2 class="border-b-2 border-ink pb-4 font-cond text-[40px] font-bold leading-[1]">Cara surat bergerak</h2>
    <ol class="mt-6 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($steps as [$code, $title, $text]): ?>
        <li><?= pena_strip($code, 'carbon'); ?><h3 class="mt-3 font-semibold"><?= e($title); ?></h3><p class="mt-1 max-w-[36ch] text-secondary"><?= e($text); ?></p></li>
      <?php endforeach; ?>
    </ol>
    <h2 class="mt-14 font-bold">Yang perlu Anda ketahui</h2>
    <ul class="mt-3 max-w-[72ch] space-y-3">
      <li>Pemeriksa tidak login. Sistem mencatat keputusan atas pemegang tautan, jadi tautan hanya dikirim ke orangnya langsung.</li>
      <li>PDF dibuat lewat Microsoft Word di komputer server.</li>
      <li>AI hanya menyusun draf dari cerita Anda. Nomor surat dan setiap keputusan tidak dibuat AI. Layanan AI belum tersambung di versi ini.</li>
    </ul>
  </section>
</main>

<footer class="bg-plate text-on-plate">
  <div class="mx-auto flex max-w-[1240px] flex-wrap justify-between gap-3 px-4 py-6 lg:px-8">
    <span>PENA, aplikasi PHP MVC.</span>
    <span class="text-on-plate/80">PHP <?= e($data['php_version']); ?><?= $diag['is_connected'] ? ', database ' . e($diag['database']) . ' terhubung' : ''; ?></span>
  </div>
</footer>
</body>
</html>
