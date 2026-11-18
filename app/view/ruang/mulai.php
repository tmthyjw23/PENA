<?php
require dirname(__DIR__) . '/layout/app_top.php';
$old = $data['old'] ?? [];
$errors = $old['errors'] ?? [];
$profile = $data['personal']['profile'] ?? [];
$onlyOrg = $data['only_org'];
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="field-error" id="o-' . $k . '-err">' . e($errors[$k]) . '</p>' : '';
$aria = static fn(string $k, string $help = '') => ' aria-describedby="' . trim($help . (isset($errors[$k]) ? ' o-' . $k . '-err' : '')) . '"' . (isset($errors[$k]) ? ' aria-invalid="true"' : '');
?>
<header class="mb-6">
  <h1 class="font-bold"><?= $onlyOrg ? 'Ruang organisasi baru' : 'Surat ini untuk siapa?'; ?></h1>
  <p class="text-secondary"><?= $onlyOrg
      ? 'Ruang organisasi punya kop, nomor surat berseri, dan arsip sendiri. Anda menjadi pemiliknya.'
      : 'Pilih salah satu untuk memulai. Anda tetap bisa memakai keduanya; pindah ruang lewat menu Ruang kerja kapan saja.'; ?></p>
</header>
<?php pena_flash($data['flash']); ?>

<div class="grid items-start gap-6 <?= $onlyOrg ? 'max-w-[560px]' : 'lg:grid-cols-2'; ?>">
  <?php if (!$onlyOrg): ?>
  <section class="panel p-4 lg:p-5" aria-labelledby="pribadi">
    <h2 id="pribadi" class="flex items-center gap-3 font-bold"><?= pena_strip('Pribadi', 'ink'); ?>Diri sendiri</h2>
    <p class="mt-2 text-secondary">Surat izin tidak hadir, dispensasi, atau permohonan ke dosen. Tanpa kop dan nomor surat. Bisa langsung jadi, atau minta dosen wali mengetahui lebih dulu.</p>
    <form method="POST" action="<?= APP_PATH; ?>/ruang/pribadi/" class="mt-4 space-y-5">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <div>
        <label for="nama_mahasiswa" class="field-label">Nama lengkap</label>
        <input id="nama_mahasiswa" name="nama_mahasiswa" class="field" maxlength="150" autocomplete="name" value="<?= e($profile['nama_mahasiswa'] ?? ''); ?>">
      </div>
      <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
        <div>
          <label for="nim" class="field-label">NIM</label>
          <input id="nim" name="nim" class="field" maxlength="30" inputmode="numeric" value="<?= e($profile['nim'] ?? ''); ?>">
        </div>
        <div>
          <label for="program_studi" class="field-label">Program studi</label>
          <input id="program_studi" name="program_studi" class="field" maxlength="150" value="<?= e($profile['program_studi'] ?? ''); ?>">
        </div>
      </div>
      <p class="text-secondary">Opsional. Diisi sekali, lalu masuk otomatis ke setiap surat pribadi.</p>
      <button class="btn btn-primary">Mulai surat pribadi</button>
    </form>
  </section>
  <?php endif; ?>

  <section class="panel p-4 lg:p-5" aria-labelledby="organisasi">
    <h2 id="organisasi" class="flex items-center gap-3 font-bold"><?= pena_strip('Organisasi', 'carbon'); ?>Organisasi</h2>
    <p class="mt-2 text-secondary">Surat peminjaman dan permohonan atas nama organisasi, dengan pemeriksaan bertahap oleh ketua, advisor, atau dekan.</p>
    <form method="POST" action="<?= APP_PATH; ?>/ruang/organisasi/" class="mt-4 space-y-5" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <input type="hidden" name="asal" value="<?= $onlyOrg ? 'baru' : 'mulai'; ?>">
      <div>
        <label for="o-nama" class="field-label">Nama organisasi</label>
        <input id="o-nama" name="nama" class="field" maxlength="150" required value="<?= e($old['nama'] ?? ''); ?>"<?= $aria('nama', 'o-nama-h'); ?>>
        <p class="field-help" id="o-nama-h">Contoh: Badan Eksekutif Mahasiswa Fakultas Ilmu Komputer</p>
        <?= $err('nama'); ?>
      </div>
      <div>
        <label for="o-singkatan" class="field-label">Singkatan</label>
        <input id="o-singkatan" name="singkatan" class="field uppercase" maxlength="20" required value="<?= e($old['singkatan'] ?? ''); ?>"<?= $aria('singkatan', 'o-singkatan-h'); ?>>
        <p class="field-help" id="o-singkatan-h">Dipakai di nomor surat, contoh BEM-FILKOM</p>
        <?= $err('singkatan'); ?>
      </div>
      <p class="text-secondary">Kop surat diisi bersama surat pertama. Mengundang pengurus lain ke ruang ini belum tersedia.</p>
      <button class="btn <?= $onlyOrg ? 'btn-primary' : 'btn-secondary'; ?>">Buat ruang organisasi</button>
    </form>
  </section>
</div>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
