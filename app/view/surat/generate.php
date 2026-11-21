<?php
require dirname(__DIR__) . '/layout/app_top.php';
$step = 1;
$personal = ($data['org']['kind'] ?? '') === 'personal';
$examples = $personal ? [
    'Buat surat permintaan maaf untuk pacar karena aku terlambat menepati janji. Gunakan bahasa hangat, jujur, dan tidak berlebihan.',
    'Buat surat lamaran kerja untuk posisi desainer grafis. Saya ingin gaya profesional dan ringkas; pengalaman serta nama perusahaan akan saya lengkapi.',
    'Izin tidak masuk kuliah Basis Data tanggal 20 November karena sakit, kepada dosen pengampu.',
] : [
    'Permohonan dana Malam Keakraban Rp 3.500.000 ke Wakil Dekan III, acara 12 Desember di Gedung F, ditandatangani ketua panitia dan diketahui advisor.',
    'Izin memakai lapangan untuk bakti sosial 20 Desember, ke Kepala Bagian Umum, ditandatangani ketua panitia dan diketahui dekan.',
];
$steps = [['kerangka', $personal ? 'Merancang susunan' : 'Memilih kerangka'], ['data', 'Mengisi data surat'], ['paragraf', 'Menulis paragraf']];
?>
<header class="mb-4">
  <h1 class="font-bold">Buat surat</h1>
  <p class="max-w-[68ch] text-secondary">Jelaskan surat yang Anda perlukan. PENA <?= $personal ? 'menyusun draf sesuai tujuan dan gaya bahasa yang Anda minta' : 'memilih kerangka'; ?>. Periksa dan lengkapi hasilnya sebelum menyimpan.</p>
</header>
<?php $helpTopic='buat';require dirname(__DIR__).'/bantuan/_context.php'; ?>
<p class="mb-4 text-secondary">AI: <?= ($data['ai_connection']['mode']??'platform')==='personal'?'provider pribadi':'bawaan PENA'; ?>. <a class="underline" href="<?= APP_PATH; ?>/preferensi/">Atur koneksi</a></p>
<?php require __DIR__ . '/_steps.php'; ?>
<?php pena_flash($data['flash']); ?>

<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
  <div class="space-y-6">
    <form data-draft-guard method="POST" action="<?= APP_PATH; ?>/surat/susun/" class="panel p-4 lg:p-5" id="susun-form">
      <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
      <input type="hidden" name="ai_run" value="<?= e($data['ai_run']); ?>">
      <input type="hidden" name="ai_revision" value="<?= (int)$data['ai_revision']; ?>">
      <input type="hidden" name="workspace_id" value="<?= (int)$data['org']['id']; ?>">
      <label for="prompt" class="field-label">Surat apa yang Anda butuhkan?</label>
      <textarea id="prompt" name="prompt" rows="5" maxlength="1000" required class="field" aria-describedby="prompt-h"><?= e($data['prompt']); ?></textarea>
      <p id="prompt-h" class="field-help"><?= $personal ? 'Ceritakan tujuan, penerima, gaya bahasa, dan hal penting yang ingin disampaikan. Data yang belum disebut bisa dilengkapi setelah draf tersusun.' : 'Sebut siapa penerimanya, kegiatan, tanggal, tempat, barang atau dana yang diminta, dan siapa yang menandatangani.'; ?> <span data-count><?= mb_strlen($data['prompt']); ?></span> dari 1.000 karakter.</p>

      <?php if (!$data['ai_ready']): ?>
        <p class="mt-4 flex items-start gap-3 rounded-control bg-hl p-3" role="status"><?= pena_mark('waiting', 'mt-1'); ?>
          <span>Bantuan AI sedang tidak tersedia. Anda tetap dapat membuat surat melalui isian manual.</span></p>
      <?php endif; ?>

      <?php if (!empty($data['unsupported'])): ?>
        <section class="mt-4 rounded-control bg-raised p-4" aria-labelledby="scope-title" role="status" data-unsupported>
          <h2 id="scope-title" class="font-bold">Belum ada kerangka yang sesuai di ruang ini</h2>
          <p class="mt-1"><?= e($data['unsupported']); ?></p>
          <p class="mt-3 text-secondary">Jenis yang tersedia: <?= e(implode(', ',array_column($data['supported_templates'],'name'))); ?>. Ubah permintaan jika Anda ingin membuat salah satu jenis tersebut.</p>
        </section>
      <?php endif; ?>

      <?php if (!empty($data['clarify'])): ?>
        <section class="mt-4 rounded-control bg-hl p-4" aria-labelledby="ket-t" data-clarify>
          <h2 id="ket-t" class="font-bold">Lengkapi permintaan</h2>
          <p class="mt-1"><?= e($data['clarify']['question']); ?></p>
          <?php if (($data['clarify']['mode']??'')==='personal'): ?>
            <label class="field-label mt-3" for="clarification-answer">Keterangan tambahan</label>
            <input class="field" id="clarification-answer" name="clarification_answer" maxlength="300" placeholder="Jawab singkat di sini">
            <button type="submit" class="btn btn-secondary mt-3">Lanjutkan dengan jawaban ini</button>
            <p class="mt-3 text-secondary">Jawaban ini melengkapi permintaan awal Anda.</p>
          <?php else: ?>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($data['clarify']['options'] as $opt): ?>
              <button type="submit" class="btn btn-secondary" name="template_choice" value="<?= e($opt['code']); ?>"><?= e($opt['label']); ?></button>
            <?php endforeach; ?>
          </div>
          <p class="mt-3 text-secondary">Pilih jenis surat untuk melanjutkan. Data yang belum lengkap dapat diisi pada langkah berikutnya.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="mt-4 rounded-control bg-rev-soft p-4" role="alert" data-error<?= empty($data['ai_error']) ? ' hidden' : ''; ?>>
        <h2 class="flex items-center gap-2 font-bold"><?= pena_mark('revision'); ?>Draf belum dapat dibuat</h2>
        <p class="mt-1" data-error-text><?= e($data['ai_error'] ?? ''); ?></p>
        <p class="mt-1">Permintaan Anda tetap ada di formulir.</p>
      </section>

      <div class="mt-4 flex flex-wrap gap-3">
        <button class="btn btn-primary" <?= $data['ai_ready'] ? '' : 'disabled'; ?>><?= !empty($data['ai_error']) ? 'Coba lagi' : 'Buat draf'; ?></button>
        <a href="<?= APP_PATH; ?>/surat/manual/" class="btn btn-secondary">Isi formulir sendiri</a>
        <?php if ($data['has_result']): ?><a href="<?= APP_PATH; ?>/surat/hasil/?proses=<?= e($data['ai_run']); ?>" class="btn btn-quiet text-action">Kembali ke hasil terakhir</a><?php endif; ?>
      </div>
    </form>

    <section class="panel p-4 lg:p-5" aria-labelledby="proses-t" data-progress role="status" aria-live="polite" hidden>
      <h2 id="proses-t" class="font-bold">Menyusun draf</h2>
      <p class="mt-1 text-secondary">Periksa hasilnya sebelum menyimpan draf.</p>
      <div class="inline-skeleton" aria-hidden="true"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line"></div></div>
      <ol class="sr-only mt-3">
        <?php foreach ($steps as [$key, $label]): ?>
          <li class="route-row grid-cols-[24px_1fr_auto]" data-step="<?= $key; ?>">
            <span data-mark><?= pena_mark('waiting'); ?></span><span class="font-semibold"><?= e($label); ?></span><span class="text-secondary" data-status>Menunggu</span>
          </li>
        <?php endforeach; ?>
      </ol>
      <button type="button" class="btn btn-secondary mt-4" data-cancel>Kembali ke isian</button>
    </section>
  </div>

  <aside class="panel p-4 lg:p-5" aria-labelledby="pena-kerja">
    <h2 id="pena-kerja" class="font-bold">Tentang draf ini</h2>
    <dl class="mt-3 space-y-3">
      <div class="grid grid-cols-[64px_1fr] gap-3"><dt><?= pena_strip($personal ? 'AI' : 'PMJ'); ?></dt><dd><?= $personal ? 'Merancang bagian dan gaya surat berdasarkan kebutuhan Anda' : 'Memilih kerangka yang cocok dari kerangka organisasi Anda'; ?></dd></div>
      <div class="grid grid-cols-[64px_1fr] gap-3"><dt><?= pena_strip('Data'); ?></dt><dd>Data yang disimpulkan ditandai untuk diperiksa. Lengkapi isian yang masih kosong</dd></div>
      <div class="grid grid-cols-[64px_1fr] gap-3"><dt><?= pena_strip('Tulis'); ?></dt><dd>Isi surat dapat diperiksa dan diubah sebelum disimpan</dd></div>
    </dl>
    <p class="mt-4 text-secondary"><?= $personal ? 'Surat pribadi tidak memakai nomor organisasi. Tanpa pemeriksa, Anda dapat menyelesaikan surat sendiri.' : 'Nomor surat tidak dibuat di sini. Nomor muncul saat Anda mengajukan surat.'; ?></p>
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
    document.querySelectorAll('[data-example]').forEach(b => b.addEventListener('click', () => { t.value = b.dataset.example;t.dispatchEvent(new Event('input',{bubbles:true}));t.focus(); }));


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
      if (controller) return;
      const clarificationAnswer=form.elements.clarification_answer?.value;
      errBox.hidden = true; document.querySelector('[data-clarify]')?.remove(); document.querySelector('[data-unsupported]')?.remove();
      progress.querySelectorAll('[data-step]').forEach(li => setStep(li.dataset.step, 'waiting', 'Menunggu'));
      form.hidden = true; progress.hidden = false; progress.querySelector('h2').focus?.();
      controller = new AbortController();
      const body = new FormData(form);
      if (clarificationAnswer) body.set('clarification_answer',clarificationAnswer);
      if (e.submitter?.name === 'template_choice') body.set('template_choice', e.submitter.value);
      try {
        for (const [key] of <?= json_encode($steps); ?>) {
          setStep(key, 'pending', 'Sedang berjalan');
          const res = await fetch('<?= APP_PATH; ?>/surat/ai_' + key + '/', { method: 'POST', body, signal: controller.signal, headers: { 'Accept': 'application/json' } });
          const out = await res.json().catch(() => ({ status: 'error', message: 'Server mengirim jawaban yang tidak bisa dibaca.' }));
          if (Number.isInteger(out.revision)) {
            body.set('ai_revision', out.revision);
            form.elements.ai_revision.value = out.revision;
          }
          if (out.status === 'clarify' || out.status === 'unsupported') { window.PenaDraftGuard?.allowNavigation();location.reload(); return; }
          if (out.status !== 'ok') { setStep(key, 'revision', 'Gagal'); fail(out.message || 'Layanan AI gagal.'); return; }
          setStep(key, 'approved', out.label || 'Selesai');
          if (out.next) { window.PenaDraftGuard?.allowNavigation();location.href = out.next; return; }
        }
      } catch (err) {
        if (err.name === 'AbortError') { progress.hidden = true; form.hidden = false; return; }
        fail('Koneksi terputus. Permintaan Anda tetap tersedia. Coba lagi setelah koneksi pulih.');
      } finally { controller = null; }
    });
    document.querySelector('[data-cancel]').addEventListener('click', () => { if(window.PenaDraftGuard&&!window.PenaDraftGuard.confirmLeave())return;controller?.abort();window.PenaDraftGuard?.allowNavigation();location.reload(); });
  })();
</script>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
