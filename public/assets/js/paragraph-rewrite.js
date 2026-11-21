// "Tulis ulang" pada editor draf tersimpan (form[data-rewrite-endpoint]).
// Hasil AI hanya mengganti isi kotak paragraf; draf baru tersimpan saat pengguna menekan Simpan perubahan.
(() => {
  document.querySelectorAll('form[data-rewrite-endpoint]').forEach(form => {
    form.querySelectorAll('[data-rewrite]').forEach(button => button.addEventListener('click', () => {
      const panel = button.closest('[data-block-wrap]').querySelector('[data-rewrite-panel]');
      panel.classList.toggle('hidden'); panel.classList.toggle('flex');
      button.setAttribute('aria-expanded', panel.classList.contains('hidden') ? 'false' : 'true');
      if (!panel.classList.contains('hidden')) panel.querySelector('input').focus();
    }));
    form.querySelectorAll('[data-rewrite-panel] input').forEach(input => input.addEventListener('keydown', event => {
      if (event.key === 'Enter' && !event.isComposing) {
        event.preventDefault();
        input.closest('[data-rewrite-panel]').querySelector('[data-rewrite-go]').click();
      }
    }));
    form.querySelectorAll('[data-rewrite-go]').forEach(button => button.addEventListener('click', async () => {
      const wrap = button.closest('[data-block-wrap]'), area = wrap.querySelector('textarea'), list = wrap.querySelector('[data-warnings]');
      if (form.dataset.rewriting) { window.PenaFeedback?.notice('Tunggu penulisan ulang yang sedang berjalan selesai.'); return; }
      const body = new FormData(form);
      body.set('block', button.dataset.rewriteGo);
      body.set('current', area.value);
      body.set('instruction', wrap.querySelector('[data-rewrite-panel] input').value);
      const show = (messages, error = false) => {
        list.replaceChildren();
        for (const message of messages) {
          const li = document.createElement('li');
          li.className = error ? 'text-rev' : 'text-secondary';
          li.textContent = message;
          list.append(li);
        }
      };
      form.dataset.rewriting = 'true'; wrap.setAttribute('aria-busy', 'true'); button.disabled = true;
      const skeleton = document.createElement('div');
      skeleton.className = 'inline-skeleton'; skeleton.setAttribute('aria-hidden', 'true');
      skeleton.innerHTML = '<div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line"></div>';
      skeleton.style.minHeight = area.getBoundingClientRect().height + 'px';
      area.hidden = true; area.after(skeleton);
      show(['Menulis ulang paragraf.']);
      const abort = new AbortController(), timer = setTimeout(() => abort.abort(), 150000);
      try {
        const response = await fetch(form.dataset.rewriteEndpoint, { method: 'POST', body, headers: { Accept: 'application/json' }, signal: abort.signal });
        if (!response.headers.get('content-type')?.includes('application/json')) {
          throw Error('Sesi atau halaman berubah. Salin teks Anda bila perlu, lalu muat ulang halaman.');
        }
        const out = await response.json();
        if (!response.ok || out.status !== 'ok') throw Error(typeof out.message === 'string' ? out.message : 'Paragraf belum diperbarui. Coba lagi nanti.');
        if (typeof out.text !== 'string' || !out.text.trim()) throw Error('AI mengirim paragraf kosong. Teks sebelumnya tetap dipakai.');
        area.value = out.text;
        area.dispatchEvent(new Event('input', { bubbles: true }));
        show(Array.isArray(out.warnings) ? out.warnings.filter(x => typeof x === 'string') : []);
        window.PenaFeedback?.notice('Paragraf diperbarui. Periksa isinya, lalu simpan perubahan.');
      } catch (error) {
        show([error.name === 'AbortError' ? 'AI belum merespons. Teks sebelumnya tetap dipakai.' : error.message], true);
      } finally {
        clearTimeout(timer); skeleton.remove(); area.hidden = false; button.disabled = false;
        delete form.dataset.rewriting; wrap.removeAttribute('aria-busy');
      }
    }));
  });
})();
