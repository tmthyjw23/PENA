<?php
require dirname(__DIR__) . '/layout/app_top.php';
$kop = $data['kop'];
$old = $data['old'] ?? [];
$errors = $old['errors'] ?? [];
$in = ($old['input'] ?? []) + ($kop ?? []);
$kind = ($in['kind'] ?? 'text') === 'image' ? 'image' : 'text';
$action = APP_PATH . ($kop ? '/kop/perbarui/' . (int)$kop['id'] : '/kop/simpan/');
$err = static fn(string $k) => isset($errors[$k]) ? '<p class="field-error" id="k-' . $k . '-err">' . e($errors[$k]) . '</p>' : '';
$aria = static fn(string $k, string $help = '') => ' aria-describedby="' . trim($help . (isset($errors[$k]) ? ' k-' . $k . '-err' : '')) . '"' . (isset($errors[$k]) ? ' aria-invalid="true"' : '');
?>
<header class="mb-6">
  <nav aria-label="Lokasi" class="hidden text-secondary lg:block"><a href="<?= APP_PATH; ?>/kop/" class="no-underline hover:underline">Kop surat</a> / <?= $kop ? 'Ubah' : 'Tambah'; ?></nav>
  <h1 class="font-bold"><?= $kop ? 'Ubah kop surat' : 'Tambah kop surat'; ?></h1>
  <p class="text-secondary"><?= $kop
      ? 'Perubahan berlaku untuk surat baru dan draf yang belum diajukan. Surat yang sudah diajukan tetap memakai kop lamanya.'
      : 'Isi kop dari teks dan logo, atau unggah gambar kop yang sudah jadi.'; ?></p>
</header>
<?php pena_flash($data['flash']); ?>
<?php if (!empty($old['message'])): ?>
  <div class="mb-6 rounded-panel bg-rev-soft px-4 py-3" role="alert"><p class="font-semibold"><?= e($old['message']); ?></p><p>Bagian yang perlu diperbaiki ditandai di bawah.</p></div>
<?php endif; ?>

<form method="POST" action="<?= $action; ?>" enctype="multipart/form-data" class="max-w-[760px] space-y-6" novalidate data-kop-form>
  <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">

  <section class="panel p-4 lg:p-5" aria-labelledby="k-jenis">
    <h2 id="k-jenis" class="font-bold">Bentuk kop</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <label class="flex cursor-pointer items-start gap-3 rounded-control border border-line p-3 has-[:checked]:border-ink">
        <input type="radio" name="kind" value="text" class="mt-1 size-5 accent-action" <?= $kind === 'text' ? 'checked' : ''; ?>>
        <span><span class="block font-semibold">Teks dan logo</span><span class="block text-secondary">PENA menyusun nama, alamat, dan logo. Teks bisa diubah kapan saja.</span></span>
      </label>
      <label class="flex cursor-pointer items-start gap-3 rounded-control border border-line p-3 has-[:checked]:border-ink">
        <input type="radio" name="kind" value="image" class="mt-1 size-5 accent-action" <?= $kind === 'image' ? 'checked' : ''; ?>>
        <span><span class="block font-semibold">Gambar kop utuh</span><span class="block text-secondary">Unggah kop yang sudah didesain. Dipasang apa adanya di atas surat.</span></span>
      </label>
    </div>
    <div class="mt-5">
      <label for="k-name" class="field-label">Nama di kop</label>
      <input id="k-name" name="name" class="field" maxlength="150" value="<?= e($in['name'] ?? mb_strtoupper($data['org']['name'])); ?>"<?= $aria('name', 'k-name-h'); ?>>
      <p class="field-help" id="k-name-h">Untuk kop teks, ini baris utama kop. Untuk gambar kop, ini nama di daftar dan teks alternatif gambar.</p>
      <?= $err('name'); ?>
    </div>
  </section>

  <section class="panel p-4 lg:p-5" aria-labelledby="k-teks" data-kop-kind="text">
    <h2 id="k-teks" class="font-bold">Teks dan logo</h2>
    <div class="mt-4 space-y-5">
      <div>
        <label for="k-subtitle" class="field-label">Baris tambahan <span class="font-normal text-secondary">(opsional)</span></label>
        <textarea id="k-subtitle" name="subtitle" rows="3" class="field" maxlength="255"<?= $aria('subtitle', 'k-subtitle-h'); ?>><?= e($in['subtitle'] ?? ''); ?></textarea>
        <p class="field-help" id="k-subtitle-h">Maksimal tiga baris, misalnya nama fakultas dan universitas.</p>
        <?= $err('subtitle'); ?>
      </div>
      <div>
        <label for="k-address" class="field-label">Alamat</label>
        <input id="k-address" name="address" class="field" maxlength="255" value="<?= e($in['address'] ?? ''); ?>"<?= $aria('address'); ?>>
        <?= $err('address'); ?>
      </div>
      <div>
        <label for="k-contact" class="field-label">Kontak <span class="font-normal text-secondary">(opsional)</span></label>
        <input id="k-contact" name="contact" class="field" maxlength="255" value="<?= e($in['contact'] ?? ''); ?>" placeholder="Email, telepon, atau situs"<?= $aria('contact'); ?>>
        <?= $err('contact'); ?>
      </div>
      <div>
        <label for="k-logo" class="field-label">Logo <span class="font-normal text-secondary">(opsional)</span></label>
        <?php if (!empty($kop['logo_src'])): ?>
          <div class="mt-1 flex items-center gap-4">
            <img src="<?= e($kop['logo_src']); ?>" alt="Logo saat ini" class="size-16 rounded-control border border-line object-contain">
            <label class="flex items-center gap-2"><input type="checkbox" name="remove_logo" value="1" class="size-5 accent-action" <?= ($in['remove_logo'] ?? '') === '1' ? 'checked' : ''; ?>>Hapus logo</label>
          </div>
        <?php endif; ?>
        <input id="k-logo" type="file" name="logo" accept="image/png,image/jpeg" class="field mt-2"<?= $aria('logo', 'k-logo-h'); ?>>
        <p class="field-help" id="k-logo-h">PNG atau JPG, maksimal 2 MB. Tampil di kiri teks kop.<?= !empty($kop['logo_src']) ? ' Kosongkan untuk tetap memakai logo saat ini.' : ''; ?></p>
        <?= $err('logo'); ?>
      </div>
    </div>
  </section>

  <section class="panel p-4 lg:p-5" aria-labelledby="k-gambar" data-kop-kind="image">
    <h2 id="k-gambar" class="font-bold">Gambar kop utuh</h2>
    <?php if (!empty($kop['image_src'])): ?>
      <img src="<?= e($kop['image_src']); ?>" alt="Gambar kop saat ini" class="mt-3 block h-auto w-full rounded-control border border-line">
    <?php endif; ?>
    <label for="k-image" class="field-label mt-4">Berkas gambar</label>
    <input id="k-image" type="file" name="image" accept="image/png,image/jpeg" class="field"<?= $aria('image', 'k-image-h'); ?>>
    <p class="field-help" id="k-image-h">PNG atau JPG melebar (lebar minimal dua kali tinggi), maksimal 2 MB. Gunakan resolusi tinggi agar tajam saat dicetak.<?= !empty($kop['image_src']) ? ' Kosongkan untuk tetap memakai gambar saat ini.' : ''; ?></p>
    <?= $err('image'); ?>
  </section>

  <?php if (!$kop): ?>
    <label class="flex items-center gap-3"><input type="checkbox" name="default" value="1" class="size-5 accent-action" <?= ($in['default'] ?? '') === '1' ? 'checked' : ''; ?>>Jadikan kop utama</label>
  <?php endif; ?>

  <div class="flex flex-wrap gap-2">
    <button class="btn btn-primary"><?= $kop ? 'Simpan perubahan' : 'Simpan kop'; ?></button>
    <a href="<?= APP_PATH; ?>/kop/" class="btn btn-secondary">Batal</a>
  </div>
</form>
<script<?= pena_nonce(); ?>>
  // Show only the fields of the chosen kind; without JavaScript both stay visible and the server ignores the other kind.
  (() => {
    const form = document.querySelector('[data-kop-form]');
    const sync = () => {
      const kind = form.querySelector('[name=kind]:checked')?.value || 'text';
      form.querySelectorAll('[data-kop-kind]').forEach(s => { s.hidden = s.dataset.kopKind !== kind; });
    };
    form.querySelectorAll('[name=kind]').forEach(r => r.addEventListener('change', sync));
    sync();
  })();
</script>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
