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
$help = [
    'penerima_jabatan' => 'Contoh: Kepala Bagian Umum', 'penerima_instansi' => 'Ditulis di bawah jabatan',
    'tanggal_mulai' => 'Ditulis lengkap di surat', 'tanggal_selesai' => 'Kosongkan bila satu hari',
    'nominal' => 'Angka saja, contoh 3500000',
];
$letterheads = $data['letterheads'] ?? [];
?>
<header class="mb-6">
  <?php if ($isEdit): ?>
    <nav aria-label="Lokasi" class="hidden text-secondary lg:block"><a href="<?= $base; ?>/detail/<?= (int)$data['letter']['id']; ?>" class="no-underline hover:underline"><?= e($data['letter']['title']); ?></a> / Ubah draf</nav>
    <h1 class="font-bold">Ubah draf</h1>
    <p class="text-secondary"><?= e($template['name']); ?>. Jenis surat dan alur persetujuan tidak berubah di sini.</p>
  <?php else: ?>
    <h1 class="font-bold">Buat surat, isi manual</h1>
    <p class="text-secondary">Draf bisa diubah kapan saja sampai Anda mengajukannya. Nomor surat dibuat saat diajukan.
      <a href="<?= $base; ?>/buat/" class="font-semibold text-action underline">Ceritakan saja ke PENA</a></p>
  <?php endif; ?>
</header>

<?php pena_flash($data['flash']); ?>
<?php if (!empty($old['message'])): ?>
  <div class="mb-6 rounded-panel bg-rev-soft px-4 py-3" role="alert">
    <p class="font-semibold"><?= e($old['message']); ?></p>
    <?php foreach ($errors as $key => $msg): if (!str_starts_with($key, 'field.') && !str_starts_with($key, 'block.')): ?><p><?= e($msg); ?></p><?php endif; endforeach; ?>
    <?php if (array_filter(array_keys($errors), static fn($k) => str_starts_with($k, 'field.') || str_starts_with($k, 'block.'))): ?><p>Bagian yang perlu diperbaiki ditandai di bawah.</p><?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$template): ?>
  <section class="panel max-w-[640px] p-6">
    <h2 class="font-bold">Belum ada kerangka surat</h2>
    <p class="mt-2 text-secondary">Organisasi ini belum punya kerangka. Admin perlu menambahkannya terlebih dahulu.</p>
  </section>
<?php else: $def = $template['definition']; ?>
<form method="POST" action="<?= $base; ?>/<?= $isEdit ? 'perbarui/' . (int)$data['letter']['id'] : 'simpan/'; ?>" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token']); ?>">
  <input type="hidden" name="template_id" value="<?= (int)$template['id']; ?>">

  <div class="grid items-start gap-6 <?= $isEdit ? 'max-w-[760px]' : 'xl:grid-cols-[minmax(0,1fr)_minmax(0,340px)]'; ?>">
    <div class="min-w-0 space-y-6">

      <?php if (!$isEdit): ?>
      <section class="panel p-4 lg:p-5" aria-labelledby="jenis">
        <h2 id="jenis" class="font-bold">Jenis surat</h2>
        <p class="mt-2 text-secondary">Kerangka menentukan data yang diminta dan bagian surat yang boleh Anda tulis.</p>
        <?php if (count($letterheads) === 1): ?>
          <p class="mt-2 text-secondary">Kop surat: <?= e(ucwords(strtolower($letterheads[0]['name']))); ?>. Ini satu-satunya kop aktif, jadi dipakai otomatis.</p>
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
        <?php if (count($letterheads) > 1): ?>
          <label for="letterhead_id" class="field-label mt-4">Kop surat</label>
          <select id="letterhead_id" name="letterhead_id" class="field">
            <?php foreach ($letterheads as $lh): ?><option value="<?= (int)$lh['id']; ?>" <?= (int)($old['letterhead_id'] ?? 0) === (int)$lh['id'] ? 'selected' : ''; ?>><?= e($lh['name']); ?></option><?php endforeach; ?>
          </select>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <section class="panel p-4 lg:p-5" aria-labelledby="data">
        <h2 id="data" class="font-bold">Data surat</h2>
        <p class="mt-2 text-secondary">Fakta resmi diisi sekali di sini. Paragraf merujuk lewat penanda, jadi mengubah tanggal di sini ikut mengubah kalimat surat.</p>
        <div class="mt-4 grid gap-x-4 gap-y-5 sm:grid-cols-2">
          <?php if (!$isEdit): ?>
            <div class="sm:col-span-2">
              <label class="field-label" for="title">Perihal</label>
              <input id="title" name="title" class="field" maxlength="200" value="<?= e($old['title'] ?? ''); ?>" required aria-describedby="title-h">
              <p class="field-help" id="title-h">Muncul di kolom Perihal surat</p>
            </div>
          <?php endif; ?>
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
                <p class="colhead">Teks baku kerangka, terkunci</p>
                <p><?= e($spec['text']); ?></p>
              </div>
            <?php elseif ($spec['type'] === 'paragraph'): ?>
              <div>
                <label class="field-label" for="<?= e($id); ?>"><?= e(ucfirst(str_replace('_', ' ', $spec['id']))); ?><?= empty($spec['required']) ? ' <span class="font-normal text-secondary">(opsional)</span>' : ''; ?></label>
                <textarea id="<?= e($id); ?>" name="blocks[<?= e($spec['id']); ?>]" rows="4" class="field"<?= $aria($key, $id, !empty($spec['instruction']) ? $id . '-h' : ''); ?>><?= e($blockValue($spec['id'])); ?></textarea>
                <?php if (!empty($spec['instruction'])): ?><p class="field-help" id="<?= e($id); ?>-h">Panduan: <?= e($spec['instruction']); ?></p><?php endif; ?>
                <?= $err($key, $id); ?>
              </div>
            <?php elseif ($spec['type'] === 'table'): ?>
              <p class="rounded-control border border-dashed border-line p-3 text-secondary">Tabel di surat diambil dari isian <?= e(strtolower($spec['field'] === 'barang' ? 'barang atau ruangan yang dipinjam' : $spec['field'])); ?> di atas.<?= $err($key, $id); ?></p>
            <?php else: ?>
              <p class="rounded-control border border-dashed border-line p-3 text-secondary">Kolom tanda tangan disusun dari alur persetujuan.</p>
            <?php endif; ?>
          <?php endforeach; ?>
          <p class="flex items-start gap-3 rounded-control border border-dashed border-line p-3 text-secondary"><?= pena_strip('AI'); ?>
            <span>Bantuan AI untuk satu paragraf ("Tulis ulang") disambungkan bersama layanan AI. Sampai saat itu, tulis paragraf sendiri dengan penanda.</span></p>
        </div>
      </section>
    </div>

    <?php if (!$isEdit): ?>
    <div class="space-y-6 xl:sticky xl:top-6">
      <section class="panel p-4 lg:p-5" aria-labelledby="alur">
        <h2 id="alur" class="font-bold">Alur persetujuan</h2>
        <p class="mt-2 text-secondary">Tahap berjalan berurutan. Semua pemeriksa dalam satu tahap harus setuju sebelum tahap berikutnya dibuka.</p>
        <?php
        $defaults = [['Ketua Panitia', 'Ketua Panitia'], ['Advisor', 'Advisor'], ['Dekan', 'Dekan'], ['', null]];
        foreach ($defaults as $i => [$defaultName, $hint]):
            $row = $old['stages'][$i] ?? null;
            $name = $row['name'] ?? $defaultName;
            $selected = array_map('intval', (array)($row['signer_ids'] ?? []));
            if ($row === null && $hint !== null) foreach ($data['signers'] as $s) if (stripos($s['position'], $hint) !== false) { $selected = [(int)$s['id']]; break; }
            $extra = $i === 3 && $row === null; ?>
          <fieldset class="mt-4 border-t border-line pt-4"<?= $extra ? ' data-extra-stage hidden' : ''; ?>>
            <legend class="sr-only">Tahap <?= $i + 1; ?></legend>
            <div class="flex items-center gap-3">
              <?= pena_strip('T' . ($i + 1), 'carbon'); ?>
              <input name="stages[<?= $i; ?>][name]" class="field min-h-9 py-1" value="<?= e($name); ?>" placeholder="Nama tahap" aria-label="Nama tahap <?= $i + 1; ?>">
            </div>
            <div class="mt-2">
              <?php foreach ($data['signers'] as $s): ?>
                <label class="flex min-h-9 cursor-pointer items-center gap-3">
                  <input type="checkbox" class="size-5 accent-action" name="stages[<?= $i; ?>][signer_ids][]" value="<?= (int)$s['id']; ?>" <?= in_array((int)$s['id'], $selected, true) ? 'checked' : ''; ?>>
                  <span><?= e($s['name']); ?><span class="text-secondary">, <?= e($s['position']); ?></span></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
        <button type="button" class="btn btn-secondary mt-4" data-add-stage>Tambah tahap</button>
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
<script>
  (function () {
    // Placeholder chips insert {{key}} at the cursor of the last focused paragraph.
    let last = null;
    document.querySelectorAll('textarea[name^="blocks"]').forEach(t => t.addEventListener('focus', () => { last = t; }));
    document.querySelectorAll('[data-insert]').forEach(b => b.addEventListener('click', () => {
      const t = last || document.querySelector('textarea[name^="blocks"]');
      if (!t) return;
      const s = t.selectionStart ?? t.value.length, end = t.selectionEnd ?? s, text = b.dataset.insert;
      t.value = t.value.slice(0, s) + text + t.value.slice(end);
      t.focus(); t.selectionStart = t.selectionEnd = s + text.length;
    }));
    document.querySelector('[data-add-stage]')?.addEventListener('click', e => {
      const hidden = document.querySelector('[data-extra-stage][hidden]');
      if (hidden) { hidden.hidden = false; hidden.querySelector('input').focus(); }
      if (!document.querySelector('[data-extra-stage][hidden]')) e.currentTarget.hidden = true;
    });

  })();
</script>
<?php require __DIR__ . '/_rows_editor.php'; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layout/app_bottom.php'; ?>
