<?php
require_once dirname(__DIR__).'/surat/_helpers.php';
$mode=$data['mode'];
$waiting=$mode==='verify'&&!empty($data['verification_pending']);
$titles=['verify'=>'Verifikasi email','confirm'=>'Konfirmasi email','forgot'=>'Lupa kata sandi','reset'=>'Kata sandi baru','complete'=>'Kata sandi diperbarui'];
if($waiting)$titles['verify']='Menunggu verifikasi email';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__).'/layout/head.php'; ?>
<title><?= e($titles[$mode]); ?> - PENA</title>
<meta name="referrer" content="no-referrer">
</head>
<body class="min-h-dvh bg-paper">
<header class="border-b border-line px-4 py-5"><a href="<?= APP_PATH; ?>/" class="font-semibold no-underline"><span class="strip strip-carbon">PENA</span></a></header>
<main class="mx-auto w-full max-w-[480px] px-4 py-10">
  <h1 class="font-cond text-[40px] font-bold leading-tight"><?= e($titles[$mode]); ?></h1>
  <?php if($data['notice']): ?><p class="mt-4" role="status"><?= e($data['notice']); ?></p><?php endif; ?>
  <?php if($data['error']): ?><p class="mt-4 rounded-panel bg-rev-soft px-4 py-3" role="alert"><?= e($data['error']); ?></p><?php endif; ?>

  <?php if($mode==='verify'): ?>
    <?php if($waiting): ?>
    <div class="mt-6 rounded-panel border border-line bg-surface p-5" data-verification-waiting>
      <p class="font-semibold" role="status">Permintaan pengiriman diterima.</p>
      <p class="mt-2 text-secondary">Buka email dari PENA di <strong class="break-all text-ink"><?= e($data['email']); ?></strong>, lalu tekan tautan dan pilih Konfirmasi email.</p>
      <p class="mt-3 text-secondary">Belum terlihat? Periksa folder Spam atau Promosi. Tautan berlaku 24 jam.</p>
      <a class="btn btn-primary mt-5" href="<?= APP_PATH; ?>/akun/verifikasi/?periksa=1">Periksa status verifikasi</a>
    </div>
    <?php else: ?>
    <p class="mt-4 text-secondary">Verifikasi <strong class="break-all text-ink"><?= e($data['email']); ?></strong> sebelum membuka ruang kerja. Kirim tautan untuk mengonfirmasi alamat ini; tautan berlaku 24 jam.</p>
    <?php endif; ?>
    <form action="<?= APP_PATH; ?>/akun/kirim/" method="post" class="mt-6">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <button class="btn <?= $waiting?'btn-secondary':'btn-primary'; ?>" type="submit"><?= $waiting?'Kirim ulang email':'Kirim email verifikasi'; ?></button>
      <?php if($waiting): ?><p class="field-help mt-3">Jika mengirim ulang, gunakan tautan dari email terbaru. Maksimal tiga permintaan pengiriman per jam, termasuk pengiriman pertama.</p><?php endif; ?>
    </form>
    <form method="POST" action="<?= APP_PATH; ?>/login/logout/" class="mt-6">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <button class="inline-flex min-h-11 items-center text-action underline">Keluar dan gunakan akun lain</button>
    </form>
  <?php elseif($mode==='forgot'): ?>
    <p class="mt-4 text-secondary">Masukkan email akun Anda. Tautan pemulihan berlaku 30 menit.</p>
    <form action="<?= APP_PATH; ?>/akun/pemulihan/" method="post" class="mt-6 space-y-5">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <div><label class="field-label" for="email">Email</label><input class="field" type="email" id="email" name="email" maxlength="150" autocomplete="email" required></div>
      <button class="btn btn-primary" type="submit">Kirim tautan pemulihan</button>
    </form>
  <?php elseif(in_array($mode,['confirm','reset'],true)): ?>
    <p class="mt-4 text-secondary"><?= $mode==='confirm' ? 'Konfirmasikan bahwa alamat email ini milik Anda.' : 'Buat kata sandi baru untuk akun Anda.'; ?></p>
    <form action="<?= APP_PATH; ?>/akun/<?= $mode==='confirm' ? 'konfirmasi' : 'reset'; ?>/" method="post" class="mt-6 space-y-5" id="token-form">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <input type="hidden" id="account-token" name="token" value="<?= e($data['token']); ?>">
      <?php if($mode==='reset'): ?>
        <div><label class="field-label" for="password">Kata sandi baru</label><input class="field" id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required aria-describedby="password-help"><p class="field-help" id="password-help">Minimal 8 karakter.</p></div>
        <div><label class="field-label" for="confirmation">Ulangi kata sandi baru</label><input class="field" id="confirmation" type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required></div>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $mode==='confirm' ? 'Konfirmasi email' : 'Simpan kata sandi'; ?></button>
    </form>
    <noscript><p class="mt-4" role="alert">Aktifkan JavaScript untuk menggunakan tautan email ini.</p></noscript>
    <script<?= pena_nonce(); ?>>
      const token = location.hash.slice(1);
      if (/^[a-f0-9]{64}$/.test(token)) document.getElementById('account-token').value = token;
      if (location.hash) history.replaceState(null, '', location.pathname);
    </script>
  <?php endif; ?>
  <?php if($mode!=='verify'): ?><a href="<?= APP_PATH; ?>/login/" class="mt-6 inline-flex min-h-11 items-center text-action underline">Kembali ke masuk</a><?php endif; ?>
</main>
</body>
</html>
