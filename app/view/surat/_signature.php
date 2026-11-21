<?php require_once __DIR__.'/_helpers.php'; // Shared by the personal submit form and the guest approval form. ?>
<details class="signature-panel mt-4 rounded-control border border-line bg-surface p-3 text-ink" data-signature>
  <summary class="cursor-pointer font-semibold">Tambahkan tanda tangan (opsional)</summary>
  <p class="mt-2 text-secondary">Gambar sendiri atau unggah PNG maksimal 512 KB, 1600 × 800 piksel. Gambar hanya dipakai untuk versi ini.</p>
  <div class="mt-3 flex flex-wrap gap-4">
    <label class="flex min-h-11 items-center gap-2"><input type="radio" name="signature_method" value="draw" checked> Gambar langsung</label>
    <label class="flex min-h-11 items-center gap-2"><input type="radio" name="signature_method" value="upload"> Unggah PNG</label>
  </div>
  <div data-draw>
    <canvas width="800" height="280" class="mt-2 w-full touch-none rounded-control border border-line bg-white" style="height:180px" aria-label="Area menggambar tanda tangan. Untuk alternatif dengan keyboard, pilih Unggah PNG."></canvas>
    <button type="button" class="btn btn-secondary mt-2" data-clear>Hapus gambar</button>
  </div>
  <div data-upload hidden>
    <label class="field-label mt-2">Berkas PNG<input type="file" name="signature_file" accept=".png,image/png" class="field mt-1" disabled></label>
    <img data-upload-preview hidden class="mt-3 max-h-24 max-w-full" alt="Pratinjau PNG tanda tangan pilihan Anda">
  </div>
  <input type="hidden" name="signature_data" value="">
  <input type="hidden" name="signature_version_id" value="<?= (int)$version['id']; ?>">
  <input type="hidden" name="signature_preview_hash" value="<?= e(\Pena\Service\SignatureService::previewHash($data['preview'])); ?>">
  <label class="mt-3 flex items-start gap-2"><input class="mt-1" type="checkbox" name="signature_consent" value="1"><span>Saya membubuhkan tanda tangan saya dan mengizinkan gambar ini dipakai pada versi <?= (int)$version['version_no']; ?> yang saya periksa.</span></label>
  <p class="mt-2 text-secondary" data-signature-status role="status">Belum ada gambar. Persetujuan tanpa gambar tetap dapat dilakukan. Ini gambar tanda tangan, belum tanda tangan tersertifikasi.</p>
</details>
<script src="<?= APP_PATH; ?>/assets/js/signature.js" defer></script>
