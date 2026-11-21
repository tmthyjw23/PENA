<div id="pena-tour" class="tour-hint" popover="manual" role="dialog" aria-labelledby="tour-title" aria-describedby="tour-copy" hidden data-finish-url="<?= APP_PATH; ?>/bantuan/selesai/" data-csrf="<?= e($data['csrf_token']); ?>">
  <div class="flex items-start justify-between gap-3">
    <p class="text-sm text-secondary" data-tour-count>Petunjuk ruang kerja</p>
    <button type="button" class="tour-close" data-tour-close aria-label="Tutup petunjuk">Tutup</button>
  </div>
  <h2 id="tour-title" class="mt-2 font-bold" tabindex="-1"></h2>
  <p id="tour-copy" class="mt-2"></p>
  <p class="mt-3 text-sm text-secondary">Panduan lengkap tetap ada di Bantuan.</p>
  <div class="mt-4 flex flex-wrap justify-between gap-3">
    <button type="button" class="btn btn-secondary" data-tour-back>Sebelumnya</button>
    <button type="button" class="btn btn-primary" data-tour-next>Lanjut</button>
  </div>
  <p class="mt-3 text-sm text-rev" role="status" data-tour-error hidden></p>
</div>
<noscript><p class="mb-5">Petunjuk interaktif memerlukan JavaScript. <a class="underline" href="<?= APP_PATH; ?>/bantuan/">Baca panduan penggunaan</a>.</p></noscript>
<script src="<?= APP_PATH; ?>/assets/js/user-tour.js?v=<?= @filemtime(dirname(__DIR__,3).'/public/assets/js/user-tour.js') ?: 1; ?>" defer></script>
