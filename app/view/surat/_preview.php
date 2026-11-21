<?php
// Paper preview of one rendered version (LetterRenderer::render output in $preview).
// Optional $highlight: block id to mark, e.g. the block a revision request points at.
require_once __DIR__ . '/_helpers.php';
$lh = $preview['letterhead'] ?? [];
$hl = $highlight ?? null;
?>
<article class="paper px-5 py-6 text-[14px] leading-[21px] sm:px-10 sm:py-10 sm:text-base sm:leading-6 lg:px-14" aria-label="Pratinjau surat">
  <?php if ($preview['kop'] ?? true): ?>
    <?php include __DIR__ . '/_kop.php'; ?>
  <?php endif; ?>
  <?php if ($preview['metadata']!==[] || $preview['date']!==''): ?>
  <div class="mt-5 flex flex-wrap justify-between gap-x-6 gap-y-1">
    <table>
      <?php foreach ($preview['metadata'] as $label => $value): ?>
        <tr><td class="pr-2 align-top"><?= e($label); ?></td><td class="pr-2 align-top">:</td><td><?= e($value); ?></td></tr>
      <?php endforeach; ?>
    </table>
    <p><?= e($preview['date']); ?></p>
  </div>
  <?php endif; ?>
  <?php if (!empty($preview['recipient'])): ?>
    <p class="mt-5">Kepada<br><?php foreach ($preview['recipient'] as $line): ?><?= e($line); ?><br><?php endforeach; ?>di Tempat</p>
  <?php endif; ?>
  <div class="mt-5 space-y-4">
    <?php foreach ($preview['blocks'] as $block): $mark = ($hl !== null && $hl === ($block['id'] ?? '')) ? ' is-marked' : ''; ?>
      <?php if ($block['type'] === 'paragraph'): ?>
        <p data-block="<?= e($block['id']); ?>" class="<?= $mark; ?>"><?= nl2br(e($block['text'])); ?></p>
      <?php elseif ($block['type'] === 'table'): ?>
        <div class="overflow-x-auto<?= $mark; ?>" tabindex="0" role="region" aria-label="<?= e('Tabel '.str_replace('_',' ',$block['id'])); ?>" data-block="<?= e($block['id']); ?>">
          <table class="grid">
            <?php foreach ($block['rows'] as $i => $row): ?>
              <tr><?php foreach ($row as $cell): ?><?= $i === 0 ? '<th>' . e($cell) . '</th>' : '<td>' . e($cell) . '</td>'; ?><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php elseif ($block['type'] === 'signatures'): $n = count($block['signers']); ?>
        <div class="grid grid-cols-2 gap-x-8 gap-y-6 pt-4 text-center" data-block="<?= e($block['id']); ?>">
          <?php foreach ($block['signers'] as $i => $s): ?>
            <div class="<?= ($n % 2 === 1 && $i === $n - 1) ? 'col-span-2' : ''; ?>">
              <p><?= e($s['role']); ?></p><div class="flex h-20 items-center justify-center"><?php if(!empty($s['signature_png'])): ?><img src="<?= e($s['signature_png']); ?>" alt="Gambar tanda tangan <?= e($s['name']); ?>" class="w-[180px] max-w-full max-h-[72px] object-contain"><?php endif; ?></div><p class="font-bold"><?= e($s['name']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</article>
