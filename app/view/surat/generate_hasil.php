<?php
require dirname(__DIR__) . '/layout/app_top.php';
$step = 2;
$s = $data['state'];
$def = $data['template']['definition'];
$errors = $data['errors'];
$specs = array_column($def['fields'], null, 'key');
$flagged = count(array_filter($s['fields'], static fn($f) => $f['flag'] === 'cek'));
$empty = count(array_filter($s['fields'], static fn($f, $k) => $f['flag'] === 'kosong' && !empty($specs[$k]['required']), ARRAY_FILTER_USE_BOTH));
$filled = count(array_filter($s['fields'], static fn($f) => $f['value'] !== null));
$help = ['tanggal_selesai' => 'Kosongkan bila satu hari', 'nominal' => 'Angka saja, contoh 3500000'];
$tableText = static function (array $spec, $value): string {
    if (!is_array($value)) return '';
    $keys = array_column($spec['columns'] ?? [], 'key');
    return implode("\n", array_map(static fn($row) => implode(' | ', array_map(static fn($k) => $row[$k] ?? '', $keys)), $value));
};
$display = static function (array $spec, $value): string {
    if ($value === null) return 'Belum ada';
    if ($spec['type'] === 'table') return implode(', ', array_map(static fn($r) => trim(($r['nama'] ?? reset($r)) . ' ' . ($r['jumlah'] ?? '')), $value));
    if ($spec['type'] === 'date') return \Pena\Service\TemplateValidator::formatDate((string)$value);
    return (string)$value;
};
$letterheads = $data['letterheads'];
$personal = ($data['org']['kind'] ?? '') === 'personal';
$missing = array_values($s['missing'] ?? []);
?>
<header class="mb-4">
  <h1 class="font-bold">Periksa hasil susunan</h1>
  <p class="text-secondary">Ubah apa pun yang kurang tepat. Belum ada yang tersimpan atau terkirim.</p>
</header>
<?php require __DIR__ . '/_steps.php'; ?>

<?php if (!empty($data['error_message'])): ?>
  <div class="mb-6 rounded-panel bg-rev-soft px-4 py-3" role="alert">
    <p class="font-semibold"><?= e($data['error_message']); ?></p>
    <ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $msg): ?><li><?= e($msg); ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="POST" action="<?= APP_PATH; ?>/surat/simpan_ai/" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
  <input type="hidden" name="template_id" value="<?= (int)$data['template']['id']; ?>">
  <?php if (count($letterheads) === 1): ?><input type="hidden" name="letterhead_id" value="<?= (int)$letterheads[0]['id']; ?>"><?php endif; ?>

  <div class="min-w-0 space-y-6">
    <section class="flex items-start gap-3 rounded-panel bg-raised p-4" aria-label="Permintaan Anda">
      <?= pena_strip('Anda'); ?>
      <p class="min-w-0 flex-1">&ldquo;<?= e(mb_strimwidth($s['prompt'], 0, 260, '...')); ?>&rdquo;</p>
      <a href="<?= APP_PATH; ?>/surat/buat/" class="font-semibold text-action underline">Ubah</a>
    </section>

    <section class="panel p-4 lg:p-5" aria-labelledby="kerangka">
      <h2 id="kerangka" class="font-bold">Kerangka</h2>
      <div class="mt-3 flex items-start gap-3">
        <?= pena_strip($data['template']['code'], 'ink'); ?>
        <div class="min-w-0">
          <p class="font-semibold"><?= e($data['template']['name']); ?></p>
          <?php if ($s['reason'] !== ''): ?><p class="text-secondary">Dipilih karena: <?= e($s['reason']); ?></p><?php endif; ?>
          <?php if (count($letterheads) === 1): ?><p class="text-secondary">Kop surat: <?= e(ucwords(strtolower($letterheads[0]['name']))); ?>, satu-satunya kop aktif.</p><?php endif; ?>
          <p class="text-secondary">Kerangka salah? Ubah permintaan Anda lalu susun lagi, atau isi manual.</p>
        </div>
      </div>
      <?php if (!$personal && !$letterheads): $kop = $s['kop'] ?? []; ?>
        <fieldset class="mt-5 border-t border-line pt-4">
          <legend class="font-bold">Kop surat organisasi</legend>
          <p class="mt-1 text-secondary">Surat pertama ruang ini. Kop diisi sekali, lalu dipakai otomatis.</p>
          <div class="mt-3 grid gap-x-4 gap-y-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="field-label" for="kop_name">Nama di kop</label>
              <input id="kop_name" name="kop[name]" class="field" maxlength="150" value="<?= e($kop['name'] ?? mb_strtoupper($data['org']['name'])); ?>"<?= isset($errors['kop.name']) ? ' aria-invalid="true"' : ''; ?>>
              <?php if (isset($errors['kop.name'])): ?><p class="field-error"><?= e($errors['kop.name']); ?></p><?php endif; ?>
            </div>
            <div>
              <label class="field-label" for="kop_address">Alamat</label>
              <input id="kop_address" name="kop[address]" class="field" maxlength="255" value="<?= e($kop['address'] ?? ''); ?>"<?= isset($errors['kop.address']) ? ' aria-invalid="true"' : ''; ?>>
              <?php if (isset($errors['kop.address'])): ?><p class="field-error"><?= e($errors['kop.address']); ?></p><?php endif; ?>
            </div>
            <div>
              <label class="field-label" for="kop_contact">Kontak <span class="font-normal text-secondary">(opsional)</span></label>
              <input id="kop_contact" name="kop[contact]" class="field" maxlength="255" value="<?= e($kop['contact'] ?? ''); ?>" placeholder="Email atau nomor telepon">
            </div>
          </div>
        </fieldset>
      <?php endif; ?>
      <?php if (count($letterheads) > 1): ?>
        <label for="letterhead_id" class="field-label mt-4">Kop surat</label>
        <select id="letterhead_id" name="letterhead_id" class="field"><?php foreach ($letterheads as $lh): ?><option value="<?= (int)$lh['id']; ?>"><?= e($lh['name']); ?></option><?php endforeach; ?></select>
      <?php endif; ?>
    </section>

    <section class="panel p-4 lg:p-5" aria-labelledby="data">
      <h2 id="data" class="flex items-center justify-between font-bold">Data surat <?= pena_strip($filled . ' dari ' . count($s['fields'])); ?></h2>
      <p class="mt-2 text-secondary">Diambil dari kalimat Anda. Yang tidak Anda sebut tidak dikarang; kutipan sumber hanya ditampilkan bila benar-benar ada di kalimat Anda.</p>
      <div class="mt-4">
        <label for="title" class="field-label">Perihal</label>
        <input id="title" name="title" class="field" maxlength="200" value="<?= e($s['title']); ?>" required>
      </div>
      <div class="mt-2">
        <?php foreach ($def['fields'] as $spec): $key = $spec['key']; $f = $s['fields'][$key] ?? ['value' => null, 'quote' => null, 'flag' => 'kosong', 'note' => 'Tidak disebut'];
          $id = 'f_' . $key; $err = $errors['field.' . $key] ?? null;
          $bg = $f['flag'] === 'cek' ? 'bg-hl' : ($f['flag'] === 'kosong' && !empty($spec['required']) ? 'bg-rev-soft' : ''); ?>
          <div class="-mx-4 border-b border-line px-4 py-4 last:border-b-0 lg:-mx-5 lg:px-5 <?= $bg; ?>">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <label for="<?= e($id); ?>" class="font-semibold"><?= e($spec['label']); ?><?= empty($spec['required']) ? ' <span class="font-normal text-secondary">(opsional)</span>' : ''; ?></label>
              <?= $f['flag'] === 'cek' ? pena_strip('Perlu dicek') : ($f['flag'] === 'kosong' ? pena_strip('Kosong', !empty($spec['required']) ? 'rev' : 'outline') : ''); ?>
            </div>
            <?php if ($spec['type'] === 'table'): $cols = array_map(static fn($c) => ['key' => $c['key'], 'label' => $c['label']], $spec['columns']); ?>
              <textarea id="<?= e($id); ?>" name="fields[<?= e($key); ?>]" rows="3" class="field mt-2" data-rows-editor="<?= e(json_encode($cols, JSON_UNESCAPED_UNICODE)); ?>"
                aria-describedby="<?= e($id); ?>-src"<?= $err ? ' aria-invalid="true"' : ''; ?>><?= e($tableText($spec, $f['value'])); ?></textarea>
            <?php else: ?>
              <input id="<?= e($id); ?>" type="<?= $spec['type'] === 'date' ? 'date' : 'text'; ?>" name="fields[<?= e($key); ?>]" class="field mt-2"
                value="<?= e(is_scalar($f['value']) ? (string)$f['value'] : ''); ?>"<?= $spec['type'] === 'money' ? ' inputmode="numeric"' : ''; ?>
                aria-describedby="<?= e($id); ?>-src"<?= $err ? ' aria-invalid="true"' : ''; ?>>
            <?php endif; ?>
            <p id="<?= e($id); ?>-src" class="mt-2 text-secondary"><?php
              if ($f['quote'] !== null) echo 'Dari: &ldquo;' . e($f['quote']) . '&rdquo;' . ($f['flag'] === 'cek' ? '. ' . e($f['note'] ?? 'Disimpulkan') : '');
              elseif ($f['value'] !== null) echo e($f['note'] ?? 'Disimpulkan') . ': ' . e($display($spec, $f['value']));
              else echo e($f['note'] ?? 'Tidak disebut') . (isset($help[$key]) ? '. ' . e($help[$key]) : '');
            ?></p>
            <?php if ($err): ?><p class="field-error"><?= e($err); ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel p-4 lg:p-5" aria-labelledby="paragraf">
      <h2 id="paragraf" class="flex items-center justify-between font-bold">Paragraf <?= pena_strip('Ditulis AI'); ?></h2>
      <p class="mt-2 text-secondary">Fakta ditulis lewat penanda seperti {{tanggal_mulai}}, jadi selalu sama dengan data di atas. PENA menolak paragraf yang mengetik tanggal, angka, atau nama secara langsung.</p>
      <?php foreach ($def['blocks'] as $b): if ($b['type'] !== 'paragraph') continue; $bid = 'b_' . $b['id']; ?>
        <?php if ($b['source'] === 'fixed'): ?>
          <div class="mt-4 rounded-control bg-raised p-3"><p class="colhead">Teks baku kerangka, terkunci</p><p><?= e($b['text']); ?></p></div>
        <?php else: $blk = $s['blocks'][$b['id']] ?? ['text' => '', 'warnings' => []]; $err = $errors['block.' . $b['id']] ?? null; ?>
          <div class="mt-4" data-block-wrap="<?= e($b['id']); ?>">
            <div class="flex items-center justify-between gap-3">
              <label for="<?= e($bid); ?>" class="font-semibold"><?= e(ucfirst(str_replace('_', ' ', $b['id']))); ?><?= empty($b['required']) ? ' <span class="font-normal text-secondary">(opsional)</span>' : ''; ?></label>
              <button type="button" class="min-h-11 font-semibold text-action underline" data-rewrite="<?= e($b['id']); ?>">Tulis ulang</button>
            </div>
            <textarea id="<?= e($bid); ?>" name="blocks[<?= e($b['id']); ?>]" rows="4" class="field mt-2"<?= $err ? ' aria-invalid="true"' : ''; ?>><?= e($blk['text']); ?></textarea>
            <div class="mt-2 hidden gap-2" data-rewrite-panel>
              <label class="sr-only" for="<?= e($bid); ?>-ins">Instruksi tulis ulang</label>
              <input id="<?= e($bid); ?>-ins" class="field" placeholder="Misal: lebih singkat, sebut kesanggupan menjaga barang">
              <button type="button" class="btn btn-secondary" data-rewrite-go="<?= e($b['id']); ?>">Tulis ulang</button>
            </div>
            <ul class="mt-2 space-y-1" data-warnings>
              <?php foreach ($blk['warnings'] as $w): ?><li class="flex items-start gap-2 text-secondary"><?= pena_mark('waiting', 'mt-1'); ?><?= e($w); ?></li><?php endforeach; ?>
            </ul>
            <?php if ($err): ?><p class="field-error"><?= e($err); ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </section>

    <section class="panel p-4 lg:p-5" aria-labelledby="alur">
      <h2 id="alur" class="font-bold"><?= $personal ? 'Pemeriksa (opsional)' : 'Alur persetujuan'; ?></h2>
      <p class="mt-2 text-secondary"><?= $personal
          ? 'Kosongkan bila surat tidak perlu diketahui siapa pun; surat langsung jadi saat Anda menyelesaikannya.'
          : 'Dibaca dari kalimat Anda. Tahap berjalan berurutan; ubah nama tahap atau pemeriksanya bila perlu.'; ?></p>
      <?php if (!empty($s['missing'])): ?>
        <p class="mt-2 flex items-start gap-2 rounded-control bg-hl p-3"><?= pena_mark('waiting', 'mt-1'); ?>Belum ada di daftar pemeriksa: <?= e(implode(', ', $s['missing'])); ?>. Tambahkan sebagai pemeriksa baru pada tahapnya di bawah.</p>
      <?php endif; ?>
      <?php $route = $s['route']; $miss = 0;
        for ($i = 0; $i < max(count($route), 1) + 1; $i++): $r = $route[$i] ?? null;
          // A reviewer the AI read from the sentence but who is not in the list yet: suggest them as new.
          $suggest = ($r !== null && empty($r['signer_id']) && empty($r['new_position'])) ? ($missing[$miss++] ?? '') : '';
          $newName = (string)($r['new_name'] ?? ''); $newPosition = (string)($r['new_position'] ?? $suggest); ?>
        <div class="mt-3 grid items-center gap-3 border-t border-line pt-3 sm:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.4fr)]">
          <?= pena_strip('T' . ($i + 1), 'carbon'); ?>
          <input name="stages[<?= $i; ?>][name]" class="field" value="<?= e($r['stage'] ?? ''); ?>" placeholder="<?= $r ? '' : 'Tahap tambahan (opsional)'; ?>" aria-label="Nama tahap <?= $i + 1; ?>">
          <select name="stages[<?= $i; ?>][signer_ids][]" class="field" aria-label="Pemeriksa tahap <?= $i + 1; ?>">
            <option value="">Pilih pemeriksa</option>
            <?php foreach ($data['signers'] as $sg): ?><option value="<?= (int)$sg['id']; ?>" <?= (int)($r['signer_id'] ?? 0) === (int)$sg['id'] ? 'selected' : ''; ?>><?= e($sg['name']); ?>, <?= e($sg['position']); ?></option><?php endforeach; ?>
          </select>
          <?php if (!empty($r['quote'])): ?><p class="text-secondary sm:col-start-2 sm:col-span-2">Dari: &ldquo;<?= e($r['quote']); ?>&rdquo;</p><?php endif; ?>
          <details class="sm:col-start-2 sm:col-span-2"<?= ($newName !== '' || $newPosition !== '' || !$data['signers']) ? ' open' : ''; ?>>
            <summary class="flex min-h-9 cursor-pointer items-center font-semibold text-action">+ Pemeriksa baru</summary>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
              <input name="stages[<?= $i; ?>][new_name]" class="field" maxlength="150" value="<?= e($newName); ?>" placeholder="Nama lengkap" aria-label="Nama pemeriksa baru, tahap <?= $i + 1; ?>">
              <input name="stages[<?= $i; ?>][new_position]" class="field" maxlength="150" value="<?= e($newPosition); ?>" placeholder="Jabatan, contoh Dosen Wali" aria-label="Jabatan pemeriksa baru, tahap <?= $i + 1; ?>">
            </div>
          </details>
        </div>
      <?php endfor; ?>
    </section>
  </div>

  <div class="min-w-0 space-y-6 xl:sticky xl:top-6 sm:[&_article]:px-6">
    <section class="plate" aria-label="Langkah berikutnya">
      <?= pena_strip('Berikutnya', 'ink'); ?>
      <p class="mt-3"><?= $flagged || $empty ? 'Periksa ' . implode(' dan ', array_filter([$flagged ? $flagged . ' isian bertanda' : '', $empty ? $empty . ' isian wajib yang kosong' : ''])) . ', lalu simpan sebagai draf.' : 'Semua isian punya sumber di kalimat Anda. Periksa sekali lagi, lalu simpan sebagai draf.'; ?> Anda masih bisa mengubah semuanya setelah disimpan.</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <button class="btn btn-primary">Simpan draf</button>
        <a href="<?= APP_PATH; ?>/surat/buat/" class="btn btn-secondary">Susun ulang</a>
      </div>
    </section>
    <?php $preview = $data['preview']; include __DIR__ . '/_preview.php'; ?>
    <p class="text-secondary">Pratinjau dari hasil susunan; perubahan Anda di kiri terlihat setelah disimpan. Permintaan, model<?= $data['model'] ? ' (' . e($data['model']) . ')' : ''; ?>, dan waktu penyusunan dicatat di riwayat surat. Isi surat tetap tanggung jawab sekretaris.</p>
  </div>
</form>
<?php require __DIR__ . '/_rows_editor.php'; ?>
<script>
  (function () {
    const csrf = <?= json_encode($data['csrf_token']); ?>;
    const waitMark = <?= json_encode(pena_mark('waiting', 'mt-1')); ?>;
    document.querySelectorAll('[data-rewrite]').forEach(b => b.addEventListener('click', () => {
      const panel = b.closest('[data-block-wrap]').querySelector('[data-rewrite-panel]');
      panel.classList.toggle('hidden'); panel.classList.toggle('flex');
      if (!panel.classList.contains('hidden')) panel.querySelector('input').focus();
    }));
    document.querySelectorAll('[data-rewrite-go]').forEach(b => b.addEventListener('click', async () => {
      const wrap = b.closest('[data-block-wrap]'), area = wrap.querySelector('textarea'), list = wrap.querySelector('[data-warnings]');
      const body = new FormData();
      body.append('csrf_token', csrf); body.append('block', b.dataset.rewriteGo);
      body.append('current', area.value); body.append('instruction', wrap.querySelector('[data-rewrite-panel] input').value);
      b.disabled = true; b.textContent = 'Menulis...';
      try {
        const res = await fetch('<?= APP_PATH; ?>/surat/tulis_ulang/', { method: 'POST', body, headers: { 'Accept': 'application/json' } });
        const out = await res.json();
        const items = out.status === 'ok' ? out.warnings : [out.message];
        if (out.status === 'ok') area.value = out.text;
        list.innerHTML = '';
        items.forEach(w => { const li = document.createElement('li'); li.className = 'flex items-start gap-2 ' + (out.status === 'ok' ? 'text-secondary' : 'text-rev'); li.innerHTML = waitMark; li.append(w); list.append(li); });
      } catch (e) {
        list.innerHTML = ''; const li = document.createElement('li'); li.className = 'text-rev'; li.textContent = 'Server tidak bisa dihubungi. Paragraf lama tetap dipakai.'; list.append(li);
      } finally { b.disabled = false; b.textContent = 'Tulis ulang'; }
    }));
  })();
</script>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
