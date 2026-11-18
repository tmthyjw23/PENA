<?php require_once dirname(__DIR__) . '/surat/_helpers.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__) . '/layout/head.php'; ?>
  <title>Masuk - PENA</title>
</head>
<body class="min-h-dvh">
<div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
  <section class="flex flex-col bg-rail px-4 py-5 lg:px-11 lg:py-10">
    <a href="<?= APP_PATH; ?>/" class="flex min-h-11 items-center gap-3 font-semibold no-underline" aria-label="PENA, ke beranda">
      <span class="strip strip-carbon">PENA</span>
      <span>Surat organisasi</span>
    </a>
    <div class="mt-8 lg:mt-auto">
      <h1 class="font-cond text-[40px] font-bold leading-[1] tracking-[-0.01em] lg:text-[64px]">Satu jadwal untuk setiap surat</h1>
      <p class="mt-3 max-w-[44ch] text-secondary">Lihat di tahap mana surat berada dan siapa yang sedang ditunggu, tanpa bertanya di grup WhatsApp.</p>
    </div>
  </section>

  <section class="flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-[400px]">
      <h2 class="font-bold">Masuk</h2>
      <p class="mt-2 text-secondary">Gunakan akun yang terdaftar di organisasi Anda.</p>

      <?php if (!empty($data['error'])): ?>
        <div class="mt-4 flex items-start gap-3 rounded-panel bg-rev-soft px-4 py-3" role="alert">
          <?= pena_mark('revision', 'mt-1'); ?>
          <p><?= e($data['error']); ?></p>
        </div>
      <?php endif; ?>

      <form action="<?= APP_PATH; ?>/login/authenticate/" method="POST" class="mt-6 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
        <div>
          <label for="username" class="field-label">Username atau email</label>
          <input type="text" class="field" id="username" name="username" autocomplete="username" required autofocus>
        </div>
        <div>
          <label for="password" class="field-label">Password</label>
          <input type="password" class="field" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Masuk ke PENA</button>
      </form>

      <div class="mt-6 border-t border-line pt-4">
        <p class="text-secondary">Akun contoh untuk pengujian lokal</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <button type="button" class="btn btn-secondary" data-demo="sekretaris">Sekretaris</button>
          <button type="button" class="btn btn-secondary" data-demo="admin">Admin</button>
        </div>
      </div>
      <a href="<?= APP_PATH; ?>/" class="mt-6 inline-flex min-h-11 items-center text-action underline">Kembali ke halaman depan</a>
    </div>
  </section>
</div>
<script>
  // Demo accounts from database/pena_schema.sql (local testing only).
  const demo = { sekretaris: 'sekretaris123', admin: 'admin123' };
  const fill = u => {
    const uEl = document.getElementById('username');
    const pEl = document.getElementById('password');
    if (uEl && pEl) {
      uEl.value = u;
      pEl.value = demo[u] || '';
      pEl.focus();
    }
  };
  document.querySelectorAll('[data-demo]').forEach(b => b.addEventListener('click', () => fill(b.dataset.demo)));
  const q = new URLSearchParams(location.search).get('user');
  if (demo[q]) fill(q);
</script>
</body>
</html>
