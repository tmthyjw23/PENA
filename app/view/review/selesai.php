<?php require __DIR__ . '/_layout_top.php'; ?>
<section class="max-w-[560px] py-4">
  <?= pena_mark($data['mark'] ?? 'approved', 'size-10'); ?>
  <h1 class="mt-4 font-bold"><?= e($data['title']); ?></h1>
  <p class="mt-2 max-w-[46ch] text-secondary"><?= e($data['message']); ?></p>
  <?php if (!$data['ok']): ?>
    <p class="mt-2 max-w-[46ch] text-secondary">Tautan berlaku 7 hari dan hanya untuk satu keputusan. Jika Anda masih perlu memeriksa surat ini, minta tautan baru kepada sekretaris organisasi.</p>
  <?php endif; ?>
</section>
  </div>
</main>
</body>
</html>
