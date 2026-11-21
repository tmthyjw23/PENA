<?php
require dirname(__DIR__) . '/layout/app_top.php';

$old = $data['old'] ?? [];
$errors = $old['errors'] ?? [];
$template = $data['template'];
$isEdit = $data['mode'] === 'edit';
$base = APP_PATH . '/surat';

/** Current value of a field: what was just typed (after an error) or what is saved. */
$fieldValue = static function (array $spec) use ($old): string {
    if (isset($old['raw_fields'][$spec['key']])) return (string)$old['raw_fields'][$spec['key']];
    $saved = $old['fields'][$spec['key']] ?? '';
    if ($spec['type'] === 'table' && is_array($saved)) {
        $keys = array_column($spec['columns'] ?? [], 'key');
        return implode("\n", array_map(static fn($row) => implode(' | ', array_map(static fn($k) => $row[$k] ?? '', $keys)), $saved));
    }
    return is_array($saved) ? '' : (string)$saved;
};
$blockValue = static function (string $id) use ($old): string {
    if (isset($old['raw_blocks'][$id])) return (string)$old['raw_blocks'][$id];
    foreach ($old['blocks'] ?? [] as $b) if (($b['id'] ?? '') === $id) return (string)($b['text'] ?? '');
    return '';
};
$err = static fn(string $key, string $id) => isset($errors[$key]) ? '<p class="field-error" id="' . e($id) . '-err">' . e($errors[$key]) . '</p>' : '';
$aria = static fn(string $key, string $id, string $help = '') => ' aria-describedby="' . trim($help . (isset($errors[$key]) ? ' ' . $id . '-err' : '')) . '"' . (isset($errors[$key]) ? ' aria-invalid="true"' : '');
$personal = ($data['org']['kind'] ?? '') === 'personal';
$help = [
    'penerima_jabatan' => $personal ? 'Contoh: Dosen Pengampu Basis Data' : 'Contoh: Kepala Bagian Umum', 'penerima_instansi' => 'Ditulis di bawah jabatan',
    'tanggal_mulai' => 'Ditulis lengkap di surat', 'tanggal_selesai' => 'Kosongkan bila satu hari',
    'nominal' => 'Angka saja, contoh 3500000',
];
$letterheads = $data['letterheads'] ?? [];
?>
<header class="mb-6">
  <?php if ($isEdit): ?>
    <nav aria-label="Lokasi" class="hidden text-secondary lg:block"><a href="<?= $base; ?>/detail/<?= (int)$data['letter']['id']; ?>" class="no-underline hover:underline"><?= e($data['letter']['title']); ?></a> / Ubah draf</nav>
    <h1 class="font-bold">Ubah draf</h1>
    <p class="text-secondary"><?= e($template['name']); ?>. Jenis surat dan pemeriksa tetap mengikuti draf ini.</p>
  <?php else: ?>
    <h1 class="font-bold">Isi formulir surat</h1>
    <p class="text-secondary">Draf bisa diubah kapan saja sampai Anda mengajukannya.<?= $personal ? ' Data profil digunakan jika diperlukan oleh jenis surat.' : ' Nomor surat dibuat saat diajukan.'; ?>
      <a href="<?= $base; ?>/buat/" class="font-semibold text-action underline">Buat draf dengan AI</a></p>
  <?php endif; ?>
</header>
<?php $helpTopic='form';require dirname(__DIR__).'/bantuan/_context.php'; ?>

<?php pena_flash($data['flash']); ?>
<?php if (!empty($old['message'])): ?>
  <div class="mb-6 rounded-panel bg-rev-soft px-4 py-3" role="alert" tabindex="-1" data-error-summary>
    <p class="font-semibold"><?= e($old['message']); ?></p>
    <?php foreach ($errors as $key => $msg): if (!str_starts_with($key, 'field.') && !str_starts_with($key, 'block.')): ?><p><?= e($msg); ?></p><?php endif; endforeach; ?>
    <?php if (array_filter(array_keys($errors), static fn($k) => str_starts_with($k, 'field.') || str_starts_with($k, 'block.'))): ?><p>Bagian yang perlu diperbaiki ditandai di bawah.</p><?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$template): ?>
  <section class="panel max-w-[640px] p-6">
    <h2 class="font-bold">Belum ada kerangka surat</h2>
    <p class="mt-2 text-secondary">Belum ada kerangka untuk ruang kerja ini.</p>
  </section>
<?php else: $def = $template['definition']; ?>
<form data-draft-guard<?= $isEdit ? ' data-rewrite-endpoint="' . e($base . '/tulis_ulang_draf/' . (int)$data['letter']['id']) . '"' : ''; ?> method="POST" action="<?= $base; ?>/<?= $isEdit ? 'perbarui/' . (int)$data['letter']['id'] : 'simpan/'; ?>" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
  <input type="hidden" name="template_id" value="<?= (int)$template['id']; ?>">

  <div class="grid items-start gap-6 <?= $isEdit ? 'max-w-[760px]' : 'xl:grid-cols-[minmax(0,1fr)_minmax(0,340px)]'; ?>">
    <div class="min-w-0 space-y-6">

      <?php if (!$isEdit): ?>
      <section class="panel p-4 lg:p-5" aria-labelledby="jenis">
        <h2 id="jenis" class="font-bold">Jenis surat</h2>
        <p class="mt-2 text-secondary">Kerangka menentukan data yang diminta dan bagian surat yang boleh Anda tulis.</p>
        <?php if (count($letterheads) === 1): ?>
          <p class="mt-2 text-secondary">Kop surat: <?= e(ucwords(strtolower($letterheads[0]['name']))); ?>. Ini satu-satunya kop aktif, jadi dipakai otomatis. <a href="<?= APP_PATH; ?>/kop/" class="font-semibold text-action underline">Kelola kop surat</a></p>
          <input type="hidden" name="letterhead_id" value="<?= (int)$letterheads[0]['id']; ?>">
        <?php endif; ?>
        <div class="mt-4 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Jenis surat">
          <?php foreach ($data['templates'] as $t): $on = (int)$template['id'] === (int)$t['id']; ?>
            <a href="<?= $base; ?>/manual/?kerangka=<?= (int)$t['id']; ?>" role="radio" aria-checked="<?= $on ? 'true' : 'false'; ?>"
              class="block rounded-control border p-3 no-underline <?= $on ? 'border-plate bg-plate text-on-plate' : 'border-line hover:border-ink'; ?>">
              <span class="flex items-center gap-2 font-semibold"><?= pena_strip($t['code'], $on ? 'ink' : 'outline'); ?><?= e($t['name']); ?></span>
              <span class="mt-1 block <?= $on ? '' : 'text-secondary'; ?>"><?= e($t['description']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php if (!$personal && !$letterheads): $kop = $old['kop'] ?? []; ?>
          <fieldset class="mt-5 border-t border-line pt-4">
            <legend class="font-bold">Kop surat organisasi</legend>
            <p class="mt-1 text-secondary">Diisi sekali bersama surat pertama, lalu dipakai otomatis untuk surat berikutnya.</p>
            <div class="mt-3 grid gap-x-4 gap-y-5 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="field-label" for="kop_name">Nama di kop</label>
                <input id="kop_name" name="kop[name]" class="field" maxlength="150" value="<?= e($kop['name'] ?? mb_strtoupper($data['org']['name'])); ?>"<?= $aria('kop.name', 'kop_name'); ?>>
                <?= $err('kop.name', 'kop_name'); ?>
              </div>
              <div>
                <label class="field-label" for="kop_address">Alamat</label>
                <input id="kop_address" name="kop[address]" class="field" maxlength="255" value="<?= e($kop['address'] ?? ''); ?>"<?= $aria('kop.address', 'kop_address'); ?>>
                <?= $err('kop.address', 'kop_address'); ?>
              </div>
              <div>
                <label class="field-label" for="kop_contact">Kontak <span class="font-normal text-secondary">(opsional)</span></label>
                <input id="kop_contact" name="kop[contact]" class="field" maxlength="255" value="<?= e($kop['contact'] ?? ''); ?>" placeholder="Email atau nomor telepon"<?= $aria('kop.contact', 'kop_contact'); ?>>
                <?= $err('kop.contact', 'kop_contact'); ?>
              </div>
            </div>
          </fieldset>
        <?php endif; ?>
        <?php if (count($letterheads) > 1): ?>
          <label for="letterhead_id" class="field-label mt-4">Kop surat</label>
          <select id="letterhead_id" name="letterhead_id" class="field">
            <?php foreach ($letterheads as $lh): ?><option value="<?= (int)$lh['id']; ?>" <?= (int)($old['letterhead_id'] ?? 0) === (int)$lh['id'] ? 'selected' : ''; ?>><?= e($lh['name']); ?><?= (int)($lh['is_default'] ?? 0) === 1 ? ' (utama)' : ''; ?></option><?php endforeach; ?>
          </select>
          <p class="field-help"><a href="<?= APP_PATH; ?>/kop/" class="font-semibold text-action underline">Kelola kop surat</a></p>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <section class="panel p-4 lg:p-5" aria-labelledby="data">
        <h2 id="data" class="font-bold">Data surat</h2>
        <p class="mt-2 text-secondary">Lengkapi data surat. Teks yang memakai {{isian}} akan mengikuti data ini.</p>
        <div class="mt-4 grid gap-x-4 gap-y-5 sm:grid-cols-2">
          <div class="sm:col-span-2">
              <label class="field-label" for="title">Perihal</label>
              <input id="title" name="title" class="field" maxlength="200" value="<?= e($old['title'] ?? ''); ?>" required<?= $aria('title','title','title-h'); ?>>
              <p class="field-help" id="title-h">Muncul di kolom Perihal surat</p><?= $err('title','title'); ?>
            </div>
          <?php foreach ($def['fields'] as $spec): $key = 'field.' . $spec['key']; $id = 'f_' . $spec['key'];
            $h = $help[$spec['key']] ?? ($spec['type'] !== 'table' ? 'Dipakai sebagai {{' . $spec['key'] . '}}' : '');
            if (empty($spec['required']) && $spec['type'] !== 'table') $h = trim($h . ($h ? '. ' : '') . 'Opsional'); ?>
            <div class="<?= $spec['type'] === 'table' ? 'sm:col-span-2' : ''; ?>">
              <label class="field-label" for="<?= e($id); ?>"><?= e($spec['label']); ?></label>
              <?php if ($spec['type'] === 'table'): $cols = array_map(static fn($c) => ['key' => $c['key'], 'label' => $c['label']], $spec['columns']); ?>
                <textarea id="<?= e($id); ?>" name="fields[<?= e($spec['key']); ?>]" rows="4" class="field"
                  data-rows-editor="<?= e(json_encode($cols, JSON_UNESCAPED_UNICODE)); ?>"<?= $aria($key, $id, $id . '-h'); ?>><?= e($fieldValue($spec)); ?></textarea>
                <p class="field-help" id="<?= e($id); ?>-h" data-rows-fallback>Satu baris per item, kolom dipisah tanda |.</p>
              <?php else: ?>
                <input id="<?= e($id); ?>" type="<?= $spec['type'] === 'date' ? 'date' : 'text'; ?>" name="fields[<?= e($spec['key']); ?>]"
                  class="field" value="<?= e($fieldValue($spec)); ?>"<?= $aria($key, $id, $id . '-h'); ?><?= $spec['type'] === 'money' ? ' inputmode="numeric"' : ''; ?>>
                <p class="field-help" id="<?= e($id); ?>-h"><?= e($h); ?></p>
              <?php endif; ?>
              <?= $err($key, $id); ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel p-4 lg:p-5" aria-labelledby="isi">
        <?php if ($isEdit): ?>
          <input type="hidden" name="rewrite_version_id" value="<?= (int)$data['rewrite_context']['version_id']; ?>">
          <input type="hidden" name="rewrite_fingerprint" value="<?= e($data['rewrite_context']['fingerprint']); ?>">
          <input type="hidden" name="ai_connection_revision" value="<?= e($data['ai_connection_revision']); ?>">
        <?php endif; ?>
        <h2 id="isi" class="font-bold">Isi surat</h2>
        <p class="mt-2 text-secondary">Jangan mengetik tanggal, nama, atau nominal langsung. Pilih penanda untuk menyisipkannya di posisi kursor.</p>
        <div class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Penanda isian">
          <?php foreach ($def['fields'] as $spec): if ($spec['type'] === 'table') continue; ?>
            <button type="button" class="ph-chip" data-insert="{{<?= e($spec['key']); ?>}}">+ <?= e($spec['label']); ?></button>
          <?php endforeach; ?>
        </div>
        <div class="mt-5 space-y-5">
          <?php foreach ($def['blocks'] as $spec): $key = 'block.' . $spec['id']; $id = 'b_' . $spec['id']; ?>
            <?php if ($spec['type'] === 'paragraph' && $spec['source'] === 'fixed'): ?>
              <div class="rounded-control bg-raised p-3">
                <p class="colhead">Teks tetap</p>
                <p><?= e($spec['text']); ?></p>
              </div>
            <?php elseif ($spec['type'] === 'paragraph'): $canRewrite = $isEdit && $spec['source'] === 'ai'; ?>
              <div data-block-wrap="<?= e($spec['id']); ?>">
                <div class="flex items-center justify-between gap-3">
                  <label class="field-label" for="<?= e($id); ?>"><?= e(ucfirst(str_replace('_', ' ', $spec['id']))); ?><?= empty($spec['required']) ? ' <span class="font-normal text-secondary">(opsional)</span>' : ''; ?></label>
                  <?php if ($canRewrite): ?>
                    <button type="button" class="min-h-11 font-semibold text-action underline" data-rewrite="<?= e($spec['id']); ?>" aria-expanded="false" aria-controls="<?= e($id); ?>-rw">Tulis ulang</button>
                  <?php endif; ?>
                </div>
                <textarea id="<?= e($id); ?>" name="blocks[<?= e($spec['id']); ?>]" rows="4" class="field"<?= $aria($key, $id, !empty($spec['instruction']) ? $id . '-h' : ''); ?>><?= e($blockValue($spec['id'])); ?></textarea>
                <?php if (!empty($spec['instruction'])): ?><p class="field-help" id="<?= e($id); ?>-h">Panduan: <?= e($spec['instruction']); ?></p><?php endif; ?>
                <?= $err($key, $id); ?>
                <?php if ($canRewrite): ?>
                  <div id="<?= e($id); ?>-rw" class="mt-2 hidden gap-2" data-rewrite-panel>
                    <label class="sr-only" for="<?= e($id); ?>-ins">Instruksi tulis ulang</label>
                    <input id="<?= e($id); ?>-ins" class="field" maxlength="300" placeholder="Misal: lebih singkat, atau sesuaikan dengan catatan pemeriksa">
                    <button type="button" class="btn btn-secondary" data-rewrite-go="<?= e($spec['id']); ?>">Tulis ulang</button>
                  </div>
                  <ul class="mt-2 space-y-1" data-warnings aria-live="polite"></ul>
                <?php endif; ?>
              </div>
            <?php elseif ($spec['type'] === 'table'): ?>
              <p class="rounded-control border border-dashed border-line p-3 text-secondary">Tabel di surat diambil dari isian <?= e(strtolower($spec['field'] === 'barang' ? 'barang atau ruangan yang dipinjam' : $spec['field'])); ?> di atas.<?= $err($key, $id); ?></p>
            <?php else: ?>
              <p class="rounded-control border border-dashed border-line p-3 text-secondary">Nama penanda tangan mengikuti daftar pemeriksa.</p>
            <?php endif; ?>
          <?php endforeach; ?>
          <p class="flex items-start gap-3 rounded-control border border-dashed border-line p-3 text-secondary"><?= pena_strip('AI'); ?>
            <span><?= $isEdit
                ? 'Tulis ulang mengusulkan satu paragraf dari isian di halaman ini. Hasilnya belum tersimpan sampai Anda menekan Simpan perubahan.'
                : 'Simpan draf dulu, lalu buka Ubah draf bila ingin memakai Tulis ulang dari AI.'; ?></span></p>
        </div>
      </section>
    </div>

    <?php if (!$isEdit): ?>
    <div class="space-y-6 xl:sticky xl:top-6">
      <section class="panel p-4 lg:p-5" aria-labelledby="alur">
        <h2 id="alur" class="font-bold"><?= $personal ? 'Pemeriksa (opsional)' : 'Alur persetujuan'; ?></h2>
        <p class="mt-2 text-secondary"><?= $personal
            ? 'Tambahkan pemeriksa jika surat memerlukan persetujuan. Tanpa pemeriksa, Anda dapat menyelesaikan surat sendiri.'
            : 'Tahap berjalan berurutan. Semua pemeriksa dalam satu tahap harus setuju sebelum tahap berikutnya dibuka.'; ?></p>
        <?php
        $hasSigners = $data['signers'] !== [];
        // Stage names to suggest, how many to show at first, and the directory entry to pre-select.
        [$defaults, $visible] = match (true) {
            $personal => [[['', 'Dosen Wali'], ['', null], ['', null]], 0],
            $hasSigners => [[['Ketua Panitia', 'Ketua Panitia'], ['Advisor', 'Advisor'], ['Dekan', 'Dekan'], ['', null]], 3],
            default => [[['', null], ['', null], ['', null], ['', null]], 1],
        };
        foreach ($defaults as $i => [$defaultName, $hint]):
            $row = $old['stages'][$i] ?? null;
            $name = $row['name'] ?? $defaultName;
            $selected = array_map('intval', (array)($row['signer_ids'] ?? []));
            // Hidden stages still submit, so only visible ones get a suggested reviewer.
            if ($row === null && $hint !== null && $i < $visible) foreach ($data['signers'] as $s) if (stripos($s['position'], $hint) !== false) { $selected = [(int)$s['id']]; break; }
            $newName = (string)($row['new_name'] ?? '');
            $newPosition = (string)($row['new_position'] ?? '');
            $extra = $i >= $visible && $row === null; ?>
          <fieldset class="mt-4 border-t border-line pt-4"<?= $extra ? ' data-extra-stage hidden' : ''; ?>>
            <legend class="sr-only">Tahap <?= $i + 1; ?></legend>
            <div class="flex items-center gap-3">
              <?= pena_strip('T' . ($i + 1), 'carbon'); ?>
              <input name="stages[<?= $i; ?>][name]" class="field min-h-9 py-1" value="<?= e($name); ?>" placeholder="<?= $personal ? 'Nama tahap, contoh Mengetahui' : 'Nama tahap, contoh Ketua Panitia'; ?>" aria-label="Nama tahap <?= $i + 1; ?>">
            </div>
            <div class="mt-2">
              <?php foreach ($data['signers'] as $s): ?>
                <label class="flex min-h-9 cursor-pointer items-center gap-3">
                  <input type="checkbox" class="size-5 accent-action" name="stages[<?= $i; ?>][signer_ids][]" value="<?= (int)$s['id']; ?>" <?= in_array((int)$s['id'], $selected, true) ? 'checked' : ''; ?>>
                  <span><?= e($s['name']); ?><span class="text-secondary">, <?= e($s['position']); ?></span></span>
                </label>
              <?php endforeach; ?>
            </div>
            <details class="mt-2"<?= (!$hasSigners || $newName !== '' || $newPosition !== '') ? ' open' : ''; ?>>
              <summary class="flex min-h-9 cursor-pointer items-center font-semibold text-action"><?= $hasSigners ? '+ Pemeriksa baru' : 'Pemeriksa'; ?></summary>
              <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <input name="stages[<?= $i; ?>][new_name]" class="field" maxlength="150" value="<?= e($newName); ?>" placeholder="Nama lengkap" aria-label="Nama pemeriksa baru, tahap <?= $i + 1; ?>">
                <input name="stages[<?= $i; ?>][new_position]" class="field" maxlength="150" value="<?= e($newPosition); ?>" placeholder="Jabatan, contoh Dosen Wali" aria-label="Jabatan pemeriksa baru, tahap <?= $i + 1; ?>">
              </div>
              <p class="mt-1 text-[13.5px] text-secondary">Disimpan ke daftar pemeriksa ruang ini, jadi surat berikutnya tinggal dipilih.</p>
            </details>
          </fieldset>
        <?php endforeach; ?>
        <button type="button" class="btn btn-secondary mt-4" data-add-stage><?= $personal ? 'Minta diketahui pemeriksa' : 'Tambah tahap'; ?></button>
      </section>

      <div class="plate">
        <p>Menyimpan belum mengirim apa pun. Setelah disimpan, periksa pratinjau lalu ajukan dari halaman surat.</p>
        <button class="btn btn-primary btn-block mt-4">Simpan draf</button>
      </div>
    </div>
    <?php else: ?>
      <div class="plate">
        <p>Menyimpan belum mengirim apa pun. Isi versi ini baru dikunci saat Anda mengajukannya.</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <button class="btn btn-primary">Simpan perubahan</button>
          <a href="<?= $base; ?>/detail/<?= (int)$data['letter']['id']; ?>" class="btn btn-secondary">Batal</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</form>
<script<?= pena_nonce(); ?>>
  (function () {
    // Placeholder chips insert {{key}} at the cursor of the last focused paragraph.
    let last = null;
    document.querySelectorAll('textarea[name^="blocks"]').forEach(t => t.addEventListener('focus', () => { last = t; }));
    document.querySelectorAll('[data-insert]').forEach(b => b.addEventListener('click', () => {
      const t = last || document.querySelector('textarea[name^="blocks"]');
      if (!t) return;
      const s = t.selectionStart ?? t.value.length, end = t.selectionEnd ?? s, text = b.dataset.insert;
      t.value = t.value.slice(0, s) + text + t.value.slice(end);
      t.dispatchEvent(new Event('input',{bubbles:true}));t.focus(); t.selectionStart = t.selectionEnd = s + text.length;
    }));
    document.querySelector('[data-add-stage]')?.addEventListener('click', e => {
      const hidden = document.querySelector('[data-extra-stage][hidden]');
      if (hidden) { hidden.hidden = false; hidden.querySelector('input').focus(); }
      if (!document.querySelector('[data-extra-stage][hidden]')) e.currentTarget.hidden = true;
    });

  })();
</script>
<?php require __DIR__ . '/_rows_editor.php'; ?>
<?php if ($isEdit): ?><script src="<?= APP_PATH; ?>/assets/js/paragraph-rewrite.js?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/js/paragraph-rewrite.js') ?: 1; ?>" defer></script><?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
