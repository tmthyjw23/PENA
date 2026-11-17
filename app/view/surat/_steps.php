<?php
// Stepper for "Buat surat dengan AI": $step = 1..3.
$labels = [1 => 'Ceritakan', 2 => 'Periksa hasil', 3 => 'Simpan draf'];
?>
<ol class="mb-6 grid grid-cols-3 border-t border-line" aria-label="Langkah">
  <?php foreach ($labels as $n => $label): $on = $n === $step; $done = $n < $step; ?>
    <li class="-mt-px flex items-center gap-2 border-t-[3px] pt-3 <?= $on ? 'border-ink font-semibold' : ($done ? 'border-ink text-secondary' : 'border-transparent text-secondary'); ?>"<?= $on ? ' aria-current="step"' : ''; ?>>
      <span class="strip <?= $on || $done ? 'strip-carbon' : 'strip-outline'; ?>"><?= $n; ?></span><span class="<?= $on ? '' : 'hidden sm:inline'; ?>"><?= $label; ?></span>
    </li>
  <?php endforeach; ?>
</ol>
