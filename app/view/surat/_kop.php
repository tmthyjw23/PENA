<?php
// Letterhead header on the paper (letter preview, review page, kop management). Expects $lh:
// kind text|image, name, subtitle, address, contact, logo_src / image_src (data: URIs from LetterheadService).
require_once __DIR__ . '/_helpers.php';
$lh = $lh ?? [];
?>
<?php if (($lh['kind'] ?? 'text') === 'image' && !empty($lh['image_src'])): ?>
  <header class="border-b-4 border-double border-ink pb-2">
    <img src="<?= e($lh['image_src']); ?>" alt="Kop surat <?= e($lh['name'] ?? ''); ?>" class="block h-auto w-full">
  </header>
<?php else: ?>
  <header class="flex items-center gap-4 border-b-4 border-double border-ink pb-2">
    <?php if (!empty($lh['logo_src'])): ?>
      <img src="<?= e($lh['logo_src']); ?>" alt="Logo <?= e($lh['name'] ?? ''); ?>" class="size-14 shrink-0 object-contain sm:size-20">
    <?php endif; ?>
    <div class="min-w-0 flex-1 text-center">
      <p class="font-bold uppercase"><?= e($lh['name'] ?? '(Kop surat diisi saat menyimpan draf)'); ?></p>
      <?php foreach (array_filter(explode("\n", (string)($lh['subtitle'] ?? ''))) as $line): ?><p class="font-semibold uppercase"><?= e($line); ?></p><?php endforeach; ?>
      <?php if (!empty($lh['address'])): ?><p><?= e($lh['address']); ?></p><?php endif; ?>
      <?php if (!empty($lh['contact'])): ?><p><?= e($lh['contact']); ?></p><?php endif; ?>
    </div>
    <?php if (!empty($lh['logo_src'])): ?><span class="size-14 shrink-0 sm:size-20" aria-hidden="true"></span><?php endif; ?>
  </header>
<?php endif; ?>
