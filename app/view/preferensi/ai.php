<?php
require dirname(__DIR__).'/layout/app_top.php';
$s=$data['settings'];
$setupRequired=$s['mode']==='unconfigured';
?>
<header class="mb-6"><h1 class="font-bold"><?= $setupRequired?'Hubungkan AI Anda':'Pengaturan AI'; ?></h1><p class="mt-1 max-w-[68ch] text-secondary">Pilih AI untuk akun Anda. Pilihan ini berlaku di ruang pribadi dan organisasi, tanpa membagikan kunci kepada anggota lain.</p></header>
<?php $helpTopic='ai';require dirname(__DIR__).'/bantuan/_context.php'; ?>
<?php pena_flash($data['flash']); ?>
<?php if($setupRequired): ?><p class="mb-5 rounded-control bg-hl p-4" role="status">Sebelum membuka ruang kerja, pilih provider dan simpan API key Anda. AI bawaan PENA hanya untuk operator. Menyimpan pengaturan tidak mengirim permintaan ke provider.</p><?php endif; ?>
<div class="grid items-start gap-6 lg:grid-cols-2">
<section class="panel p-5">
  <h2 class="font-bold">Koneksi yang dipakai</h2>
  <p class="mt-2"><?= $s['mode']==='personal'?e($data['providers'][$s['provider']]['name']??'Provider pribadi').' · '.e($s['model']):($s['platform_allowed']?'AI bawaan PENA · operator':'Belum terhubung'); ?></p>
  <form data-draft-guard method="post" action="<?= APP_PATH; ?>/preferensi/simpan/" class="mt-5 space-y-4" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
    <?php if($s['platform_allowed']): ?>
    <label class="field-label" for="ai-mode">Sumber AI</label>
    <select class="field" name="mode" id="ai-mode"><option value="platform" <?= $s['mode']==='platform'?'selected':''; ?>>AI bawaan PENA · operator</option><option value="personal" <?= $s['mode']==='personal'?'selected':''; ?>>Provider dan API key saya</option></select>
    <?php else: ?><input type="hidden" name="mode" id="ai-mode" value="personal"><?php endif; ?>
    <div id="personal-ai-fields" class="space-y-4">
      <?php if(!$s['storage_ready']): ?><p class="text-rev" role="status">Penyimpanan kunci belum tersedia. Hubungi pengelola PENA.</p><?php endif; ?>
      <div><label class="field-label" for="ai-provider">Provider</label><select class="field" name="provider" id="ai-provider" required><?php if($setupRequired): ?><option value="" selected disabled>Pilih provider</option><?php endif; ?><?php foreach($data['providers']as$id=>$p): ?><option value="<?= e($id); ?>" <?= !$setupRequired&&$s['provider']===$id?'selected':''; ?>><?= e($p['name']); ?></option><?php endforeach; ?></select></div>
      <div id="custom-ai-url"><label class="field-label" for="ai-base-url">Base URL endpoint kustom</label><input class="field" id="ai-base-url" name="base_url" type="url" maxlength="500" value="<?= $s['provider']==='custom'?e($s['base_url']):''; ?>" placeholder="https://ai.contoh.com/v1" spellcheck="false" aria-describedby="ai-url-help"><p class="field-help" id="ai-url-help">HTTPS, port 443, dan domain publik. Tanpa /chat/completions, kredensial, query, atau fragmen. Gunakan gateway yang Anda percaya: pengelolanya menerima API key dan data yang dikirim. Mengganti alamat memerlukan API key baru.</p></div>
      <p id="google-ai-help" class="field-help">Gunakan API key dari <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="underline">Google AI Studio</a>. Alamat API dan model awal sudah diisi. Buka Model jika ingin menggantinya; ketersediaan serta kuota mengikuti akun Google Anda.</p>
      <details id="ai-model-options" open><summary class="cursor-pointer font-semibold">Model</summary><div class="mt-3"><label class="field-label" for="ai-model">ID model</label><input class="field" id="ai-model" name="model" maxlength="150" value="<?= e($s['model']); ?>" aria-describedby="ai-model-help" spellcheck="false"><p class="field-help" id="ai-model-help">Salin ID dari dashboard provider. Gunakan model teks yang mendukung Chat Completions. Tidak semua model cocok untuk draf terstruktur.</p></div></details>
      <div><label class="field-label" for="ai-key">API key <?= $s['has_key']?'pengganti (opsional)':''; ?></label><input class="field" type="password" name="api_key" id="ai-key" maxlength="4096" autocomplete="new-password" spellcheck="false" aria-describedby="ai-key-help"><p class="field-help" id="ai-key-help"><?= $s['has_key']?'Kunci tersimpan dan tidak ditampilkan kembali. Kosongkan untuk mempertahankan kunci pada provider yang sama. Ganti provider berarti perlu kunci baru.':'Kunci disimpan terenkripsi. Gunakan kunci khusus PENA dengan batas biaya di provider.'; ?></p></div>
      <label class="flex items-start gap-3"><input class="mt-1" type="checkbox" name="consent" value="1"><span>Saya setuju data yang diperlukan untuk menyusun surat dikirim ke provider pilihan saya. Biaya dan kuotanya mengikuti akun provider saya.</span></label>
    </div>
    <div><label class="field-label" for="ai-password">Kata sandi akun PENA</label><input class="field" id="ai-password" type="password" name="password" required autocomplete="current-password"><p class="field-help">Diperlukan untuk mengubah koneksi. API key dan kata sandi tidak disimpan di browser oleh PENA.</p></div>
    <button class="btn btn-primary">Simpan pengaturan AI</button>
  </form>
</section>
<div class="space-y-6">
<section class="panel p-5"><h2 class="font-bold">Tentang kunci Anda</h2><p class="mt-3 text-secondary">Kunci dipakai oleh server PENA untuk menghubungi provider. Anggota organisasi dan panel operator tidak dapat membacanya. Enkripsi database tidak melindungi kunci jika server beserta kunci enkripsinya dikuasai pihak lain.</p><p class="mt-3 text-secondary">Saat memakai AI pribadi, PENA tidak beralih ke AI bawaan jika terjadi kegagalan. AI bawaan hanya tersedia bagi operator platform. Kebijakan penyimpanan data mengikuti provider atau pengelola gateway yang Anda pilih. Endpoint kustom harus kompatibel dengan OpenAI Chat Completions; API native Anthropic belum didukung.</p><details class="mt-4"><summary class="min-h-9 cursor-pointer font-semibold">Menghapus dan mencabut kunci</summary><p class="mt-3 text-secondary">Menghapus koneksi menghentikan pemakaian berikutnya. Permintaan yang sudah terkirim dan salinan dalam cadangan tidak langsung terhapus. Cabut API key di provider bila tidak dipakai lagi.</p></details></section>
<?php if($s['mode']==='personal'): ?><section class="panel p-5"><h2 class="font-bold">Uji koneksi</h2><p class="mt-2 text-secondary">Mengirim satu pesan contoh tanpa isi surat. Uji ini memakai kuota provider; jeda antar-uji satu menit.</p><form method="post" action="<?= APP_PATH; ?>/preferensi/uji/" class="mt-4"><input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>"><button class="btn btn-secondary">Uji koneksi pribadi</button></form></section><?php endif; ?>
<?php if($s['has_key']): ?><section class="panel p-5"><h2 class="font-bold">Hapus koneksi pribadi</h2><p class="mt-2 text-secondary"><?= $s['platform_allowed']?'Akun operator kembali memakai AI bawaan.':'Anda perlu menyimpan koneksi baru sebelum membuka ruang kerja lagi.'; ?> Surat yang sudah tersimpan tetap ada.</p><form method="post" action="<?= APP_PATH; ?>/preferensi/hapus/" class="mt-4 space-y-4"><input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>"><label class="field-label" for="delete-password">Kata sandi akun PENA</label><input class="field" id="delete-password" type="password" name="password" required autocomplete="current-password"><button class="btn btn-danger">Hapus koneksi pribadi</button></form></section><?php endif; ?>
</div></div>
<script<?= pena_nonce(); ?>>
(()=>{
 const mode=document.getElementById('ai-mode'),fields=document.getElementById('personal-ai-fields'),provider=document.getElementById('ai-provider'),url=document.getElementById('custom-ai-url');
 const model=document.getElementById('ai-model'),options=document.getElementById('ai-model-options'),help=document.getElementById('google-ai-help');
 const defaultModel=<?= json_encode($data['providers']['google']['default_model'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
 const update=()=>{const google=provider.value==='google';fields.hidden=mode.value!=='personal';url.hidden=provider.value!=='custom';const endpoint=document.getElementById('ai-base-url');endpoint.disabled=mode.value!=='personal'||provider.value!=='custom';endpoint.required=!endpoint.disabled;provider.disabled=mode.value!=='personal';help.hidden=!google;options.open=!google;if(google&&model.value==='')model.value=defaultModel;};
 mode.addEventListener('change',update);provider.addEventListener('change',()=>{if(provider.value==='google')model.value=defaultModel;update();});update();
})();
</script>
<?php require dirname(__DIR__).'/layout/app_bottom.php'; ?>
