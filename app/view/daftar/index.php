<?php require_once dirname(__DIR__) . '/surat/_helpers.php';
$old = $data['old'] ?? [];
$errors = $old['errors'] ?? [];
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="field-error" id="' . $k . '-err">' . e($errors[$k]) . '</p>' : '';
$aria = static fn(string $k, string $help = '') => ' aria-describedby="' . trim($help . (isset($errors[$k]) ? ' ' . $k . '-err' : '')) . '"' . (isset($errors[$k]) ? ' aria-invalid="true"' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__) . '/layout/head.php'; ?>
  <title>Daftar - PENA</title>
</head>
<body class="min-h-dvh">
<div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
  <section aria-label="Tentang PENA" class="flex flex-col bg-rail px-4 py-5 lg:px-11 lg:py-10">
    <a href="<?= APP_PATH; ?>/" class="flex min-h-11 items-center gap-3 font-semibold no-underline"><span class="strip strip-carbon">PENA</span>Ruang surat</a>
    <div class="mt-8 lg:mt-auto">
      <h1 class="font-cond text-[40px] font-bold leading-[1] tracking-[-0.01em] lg:text-[64px]">Buat akun PENA</h1>
      <p class="mt-3 max-w-[44ch]">Satu akun untuk menyusun surat pribadi dan mengurus surat organisasi.</p>
    </div>
  </section>

  <main id="konten" class="flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-[400px]">
      <h2 class="font-bold">Daftar</h2>
      <p class="mt-2 text-secondary">Setelah mendaftar, pilih ruang pribadi atau buat ruang organisasi.</p>

      <?php if (isset($errors['form'])): ?>
        <div class="mt-4 flex items-start gap-3 rounded-panel bg-rev-soft px-4 py-3" role="alert">
          <?= pena_mark('revision', 'mt-1'); ?><p><?= e($errors['form']); ?></p>
        </div>
      <?php endif; ?>

      <form action="<?= APP_PATH; ?>/daftar/simpan/" method="POST" class="mt-6 space-y-5" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
        <div>
          <label for="nama" class="field-label">Nama lengkap</label>
          <input type="text" class="field" id="nama" name="nama" autocomplete="name" maxlength="100" required autofocus
            value="<?= e($old['nama'] ?? ''); ?>"<?= $aria('nama'); ?>>
          <?= $err('nama'); ?>
        </div>
        <div>
          <label for="email" class="field-label">Email</label>
          <input type="email" class="field" id="email" name="email" autocomplete="email" maxlength="150" required
            value="<?= e($old['email'] ?? ''); ?>"<?= $aria('email', 'email-h'); ?>>
          <p class="field-help" id="email-h">Dipakai untuk masuk dan menerima tautan verifikasi.</p>
          <?= $err('email'); ?>
        </div>
        <div>
          <label for="password" class="field-label">Kata sandi</label>
          <input type="password" class="field" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required<?= $aria('password', 'password-h'); ?>>
          <p class="field-help" id="password-h">Minimal 8 karakter</p>
          <?= $err('password'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Buat akun</button>
      </form>

      <p class="mt-6 border-t border-line pt-4">Sudah punya akun? <a href="<?= APP_PATH; ?>/login/" class="font-semibold text-action underline">Masuk</a></p>
    </div>
  </main>
</div>
</body>
</html>
