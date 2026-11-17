<?php
require dirname(__DIR__) . '/layout/app_top.php';
$step = 1;
$examples = [
    'Permohonan dana Malam Keakraban Rp 3.500.000 ke Wakil Dekan III, acara 12 Desember di Gedung F, ditandatangani ketua panitia dan diketahui advisor.',
    'Izin memakai lapangan untuk bakti sosial 20 Desember, ke Kepala Bagian Umum, ditandatangani ketua panitia dan diketahui dekan.',
];
$steps = [['kerangka', 'Memilih kerangka'], ['data', 'Mengisi data surat'], ['paragraf', 'Menulis paragraf']];
?>
<header class="mb-4">
  <h1 class="font-bold">Buat surat</h1>
  <p class="max-w-[68ch] text-secondary">Ceritakan surat yang Anda butuhkan. PENA memilih kerangka, mengisi data, dan menulis paragraf. Anda memeriksa semuanya sebelum disimpan.</p>
</header>
<?php require __DIR__ . '/_steps.php'; ?>
<?php pena_flash($data['flash']); ?>

<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
  <div class="space-y-6">
    <form method="POST" action="<?= APP_PATH; ?>/surat/susun/" class="panel p-4 lg:p-5" id="susun-form">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <label for="prompt" class="field-label">Surat apa yang Anda butuhkan?</label>
      <textarea id="prompt" name="prompt" rows="5" maxlength="1000" required class="field" aria-describedby="prompt-h"><?= e($data['prompt']); ?></textarea>
      <p id="prompt-h" class="field-help">Sebut siapa penerimanya, kegiatan, tanggal, tempat, barang atau dana yang diminta, dan siapa yang menandatangani. <span data-count><?= mb_strlen($data['prompt']); ?></span> dari 1.000 karakter.</p>

      <?php if (!$data['ai_ready']): ?>
        <p class="mt-4 flex items-start gap-3 rounded-control bg-hl p-3" role="status"><?= pena_mark('waiting', 'mt-1'); ?>
          <span>Layanan AI belum diatur di server ini (AI_BASE_URL dan AI_MODEL di .env, lalu jalankan 9router). Sementara itu, pakai isi manual.</span></p>
      <?php endif; ?>

      <?php if (!empty($data['clarify'])): ?>
        <section class="mt-4 rounded-control bg-hl p-4" aria-labelledby="ket-t" data-clarify>
          <h2 id="ket-t" class="font-bold">Butuh sedikit keterangan lagi</h2>
          <p class="mt-1"><?= e($data['clarify']['question']); ?></p>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($data['clarify']['options'] as $opt): ?>
              <button type="button" class="btn btn-secondary" data-answer="<?= e($opt); ?>"><?= e($opt); ?></button>
            <?php endforeach; ?>
          </div>
          <p class="mt-3 text-secondary">Pilihan akan ditambahkan ke kotak di atas. Tambahkan juga nama kegiatan, tanggal, dan kepada siapa surat ditujukan, lalu susun lagi.</p>
        </section>
      <?php endif; ?>

      <section class="mt-4 rounded-control bg-rev-soft p-4" role="alert" data-error<?= empty($data['ai_error']) ? ' hidden' : ''; ?>>
        <h2 class="flex items-center gap-2 font-bold"><?= pena_mark('revision'); ?>Surat belum bisa disusun</h2>
        <p class="mt-1" data-error-text><?= e($data['ai_error'] ?? ''); ?></p>
        <p class="mt-1">Permintaan Anda tetap tersimpan di kotak di atas.</p>
      </section>

      <div class="mt-4 flex flex-wrap gap-3">
        <button class="btn btn-primary" <?= $data['ai_ready'] ? '' : 'disabled'; ?>><?= !empty($data['ai_error']) ? 'Coba lagi' : 'Susun surat'; ?></button>
        <a href="<?= APP_PATH; ?>/surat/manual/" class="btn btn-secondary">Isi manual tanpa AI</a>
        <?php if ($data['has_result']): ?><a href="<?= APP_PATH; ?>/surat/hasil/" class="btn btn-quiet text-action">Kembali ke hasil terakhir</a><?php endif; ?>
      </div>
    </form>

    <section class="panel p-4 lg:p-5" aria-labelledby="proses-t" data-progress hidden>
      <h2 id="proses-t" class="font-bold">Menyusun dari permintaan Anda</h2>
      <p class="mt-1 text-secondary">Biasanya kurang dari satu menit. Jangan tutup halaman ini.</p>
      <ol class="mt-3">
        <li class="route-row grid-cols-[24px_1fr_auto]"><span><?= pena_mark('approved'); ?></span><span class="font-semibold">Membaca permintaan</span><span class="text-secondary">Selesai</span></li>
        <?php foreach ($steps as [$key, $label]): ?>
          <li class="route-row grid-cols-[24px_1fr_auto]" data-step="<?= $key; ?>">
            <span data-mark><?= pena_mark('waiting'); ?></span><span class="font-semibold"><?= e($label); ?></span><span class="text-secondary" data-status>Menunggu</span>
          </li>
        <?php endforeach; ?>
      </ol>
      <button type="button" class="btn btn-secondary mt-4" data-cancel>Batalkan</button>
    </section>
  </div>

  <aside class="panel p-4 lg:p-5" aria-labelledby="pena-kerja">
    <h2 id="pena-kerja" class="font-bold">Yang dilakukan PENA</h2>
    <dl class="mt-3 space-y-3">
      <div class="grid grid-cols-[64px_1fr] gap-3"><dt><?= pena_strip('PMJ'); ?></dt><dd>Memilih kerangka yang cocok dari kerangka organisasi Anda</dd></div>
      <div class="grid grid-cols-[64px_1fr] gap-3"><dt><?= pena_strip('Data'); ?></dt><dd>Mengisi isian hanya dari kalimat Anda; yang disimpulkan ditandai perlu dicek, yang tidak disebut dibiarkan kosong</dd></div>
      <div class="grid grid-cols-[64px_1fr] gap-3"><dt><?= pena_strip('Tulis'); ?></dt><dd>Menulis paragraf dengan penanda, bukan dengan fakta yang diketik ulang</dd></div>
    </dl>
    <p class="mt-4 text-secondary">Nomor surat tidak dibuat di sini. Nomor muncul saat Anda mengajukan surat.</p>
    <h3 class="colhead mt-5 border-t border-line pt-4">Contoh permintaan</h3>
    <ul class="mt-2 space-y-2">
      <?php foreach ($examples as $ex): ?>
        <li><button type="button" class="w-full rounded-control border border-line p-3 text-left hover:border-ink" data-example="<?= e($ex); ?>"><?= e($ex); ?></button></li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
<script>
  (function () {
    const form = document.getElementById('susun-form'), t = document.getElementById('prompt'), c = document.querySelector('[data-count]');
    const progress = document.querySelector('[data-progress]'), errBox = document.querySelector('[data-error]');
    const upd = () => { c.textContent = t.value.length.toLocaleString('id-ID'); };
    t.addEventListener('input', upd);
    document.querySelectorAll('[data-example]').forEach(b => b.addEventListener('click', () => { t.value = b.dataset.example; upd(); t.focus(); }));
    document.querySelectorAll('[data-answer]').forEach(b => b.addEventListener('click', () => {
      t.value = (t.value.trim() + ' ' + b.dataset.answer + '.').slice(0, 1000); upd(); t.focus();
    }));

    const marks = {
      waiting: <?= json_encode(pena_mark('waiting')); ?>, pending: <?= json_encode(pena_mark('pending')); ?>,
      approved: <?= json_encode(pena_mark('approved')); ?>, revision: <?= json_encode(pena_mark('revision')); ?>,
    };
    let controller = null;
    const setStep = (key, mark, text) => {
      const li = progress.querySelector(`[data-step="${key}"]`);
      li.querySelector('[data-mark]').innerHTML = marks[mark];
      li.querySelector('[data-status]').textContent = text;
    };
    const fail = message => {
      progress.hidden = true; form.hidden = false;
      errBox.hidden = false; errBox.querySelector('[data-error-text]').textContent = message;
      form.querySelector('button.btn-primary').textContent = 'Coba lagi';
      errBox.scrollIntoView({ block: 'nearest' });
    };
    form.addEventListener('submit', async e => {
      if (!window.fetch || !t.value.trim()) return; // without fetch, the form posts and the server runs all steps
      e.preventDefault();
      errBox.hidden = true; document.querySelector('[data-clarify]')?.remove();
      progress.querySelectorAll('[data-step]').forEach(li => setStep(li.dataset.step, 'waiting', 'Menunggu'));
      form.hidden = true; progress.hidden = false; progress.querySelector('h2').focus?.();
      controller = new AbortController();
      const body = new FormData(form);
      try {
        for (const [key] of <?= json_encode($steps); ?>) {
          setStep(key, 'pending', 'Sedang berjalan');
          const res = await fetch('<?= APP_PATH; ?>/surat/ai_' + key + '/', { method: 'POST', body, signal: controller.signal, headers: { 'Accept': 'application/json' } });
          const out = await res.json().catch(() => ({ status: 'error', message: 'Server mengirim jawaban yang tidak bisa dibaca.' }));
          if (out.status === 'clarify') { location.reload(); return; }
          if (out.status !== 'ok') { setStep(key, 'revision', 'Gagal'); fail(out.message || 'Layanan AI gagal.'); return; }
          setStep(key, 'approved', out.label || 'Selesai');
          if (out.next) { location.href = out.next; return; }
        }
      } catch (err) {
        if (err.name === 'AbortError') { progress.hidden = true; form.hidden = false; return; }
        fail('Server tidak bisa dihubungi. Coba lagi.');
      }
    });
    document.querySelector('[data-cancel]').addEventListener('click', () => controller?.abort());
  })();
</script>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
