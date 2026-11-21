<?php // Editable grid for table fields: writes back to the "a | b | c" textarea the server reads. ?>
<script>
  (function () {
    document.querySelectorAll('textarea[data-rows-editor]').forEach(area => {
      const cols = JSON.parse(area.dataset.rowsEditor);
      const wrap = document.createElement('div');
      const head = document.createElement('div');
      head.className = 'hidden sm:grid gap-2 mb-1';
      head.style.gridTemplateColumns = `24px repeat(${cols.length}, minmax(0,1fr)) 64px`;
      head.innerHTML = '<span class="colhead">No</span>' + cols.map(c => `<span class="colhead"></span>`).join('') + '<span></span>';
      head.querySelectorAll('.colhead').forEach((s, i) => { if (i > 0) s.textContent = cols[i - 1].label; });
      const body = document.createElement('div');
      body.className = 'space-y-2';
      const sync = () => {
        area.value = [...body.children].map(r => [...r.querySelectorAll('input')].map(i => i.value.replace(/\|/g, '/').trim()).join(' | '))
          .filter(line => line.replace(/[\s|]/g, '') !== '').join('\n');
        [...body.children].forEach((r, i) => { r.querySelector('[data-n]').textContent = i + 1; });
        area.dispatchEvent(new Event('input',{bubbles:true}));
      };
      const addRow = (values = []) => {
        const r = document.createElement('div');
        r.className = 'rows-row gap-2 items-center rounded-control border border-line p-2 sm:border-0 sm:p-0';
        r.style.setProperty('--row-columns',cols.length);
        r.innerHTML = '<span data-n class="text-secondary"></span>' + cols.map(() => '<input class="field">').join('')
          + '<button type="button" class="min-h-11 font-semibold text-rev underline">Hapus</button>';
        r.querySelectorAll('input').forEach((input, i) => {
          input.value = values[i] ?? '';
          input.setAttribute('aria-label', cols[i].label);
          if(area.hasAttribute('aria-describedby'))input.setAttribute('aria-describedby',area.getAttribute('aria-describedby'));
          if(area.getAttribute('aria-invalid')==='true')input.setAttribute('aria-invalid','true');
          input.placeholder = cols[i].label;
          input.addEventListener('input', sync);
        });
        r.querySelector('button').addEventListener('click', () => { r.remove(); if (!body.children.length) addRow(); sync(); window.PenaFeedback?.notice('Baris dihapus. Simpan draf untuk menyimpan perubahan.'); });
        body.appendChild(r);
        return r;
      };
      area.value.split(/\r?\n/).filter(l => l.trim() !== '').forEach(l => addRow(l.split('|').map(v => v.trim())));
      if (!body.children.length) addRow();
      const add = document.createElement('button');
      add.type = 'button';
      add.className = 'btn btn-secondary mt-3';
      add.textContent = 'Tambah baris';
      add.addEventListener('click', () => { addRow().querySelector('input').focus(); sync(); window.PenaFeedback?.notice('Baris ditambahkan.'); });
      wrap.append(head, body, add);
      area.after(wrap);
      area.hidden = true;
      area.parentElement.querySelector('[data-rows-fallback]')?.remove();
      document.querySelector(`label[for="${area.id}"]`)?.removeAttribute('for');
      sync();
    });
  })();
</script>
