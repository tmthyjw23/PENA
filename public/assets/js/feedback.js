(() => {
  let layer, notice, pending = false, navigationTimer, returnFocus;
  let suspendedDialogs=[], suspendedPopovers=[];
  const inertStates = new Map();
  const hideLayer = () => {
    if(layer)layer.hidden=true;
    for(const [element,wasInert] of inertStates)element.inert=wasInert;
    inertStates.clear();
    for(const popover of suspendedPopovers)if(popover.isConnected&&!popover.hidden&&!popover.matches(':popover-open'))popover.showPopover();
    suspendedPopovers=[];
    for(const dialog of suspendedDialogs)if(dialog.isConnected&&!dialog.open)dialog.showModal();
    suspendedDialogs=[];
    if(returnFocus?.isConnected)returnFocus.focus({preventScroll:true});
    returnFocus=null;
  };
  const messages = action => {
    if (/\/akun\/kirim\//.test(action)) return 'Mengirim email verifikasi';
    if (/\/preferensi\/uji\//.test(action)) return 'Menguji koneksi pribadi';
    if (/\/preferensi\/hapus\//.test(action)) return 'Menghapus koneksi pribadi';
    if (/\/preferensi\/simpan\//.test(action)) return 'Menyimpan pengaturan AI';
    if (/\/unduh\//.test(action)) return 'Menyiapkan dokumen';
    if (/\/operator\/uji_ai\//.test(action)) return 'Menguji koneksi AI';
    if (/\/operator\/status\//.test(action)) return 'Memperbarui status akun';
    if (/\/setuju\//.test(action)) return 'Mencatat persetujuan';
    if (/\/revisi\//.test(action)) return 'Menyimpan revisi';
    if (/\/batal\//.test(action)) return 'Membatalkan surat';
    if (/\/tautan\//.test(action)) return 'Membuat tautan pemeriksaan';
    if (/\/login\//.test(action)) return 'Memeriksa akun';
    if (/\/daftar\//.test(action)) return 'Membuat akun';
    if (/\/akun\//.test(action)) return 'Memproses permintaan akun';
    if (/\/ruang\//.test(action)) return 'Membuka ruang kerja';
    return 'Menyimpan perubahan';
  };
  const say = (message, error = false) => {
    if (!notice) {
      notice = document.createElement('div'); notice.className = 'action-notice';
      const text = document.createElement('p'); text.dataset.noticeText = '';
      const close = document.createElement('button'); close.type = 'button'; close.className = 'btn btn-quiet'; close.textContent = 'Tutup';
      close.addEventListener('click', () => { notice.hidden = true; }); notice.append(text, close); document.body.append(notice);
    }
    const host=document.querySelector('dialog[open]')||document.body;
    if(notice.parentElement!==host)host.append(notice);
    notice.setAttribute('role', error ? 'alert' : 'status'); notice.dataset.error = error ? 'true' : 'false';
    notice.querySelector('[data-notice-text]').textContent = message; notice.hidden = false;
  };
  const begin = message => {
    if (!layer) {
      layer = document.createElement('div'); layer.className = 'loading-layer'; layer.hidden = true;
      layer.innerHTML = '<section class="loading-content" tabindex="-1" role="status" aria-live="polite"><p class="font-semibold" data-loading-label></p><div class="loading-placeholder" aria-hidden="true"><div class="skeleton skeleton-heading"></div><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton-panels"><div class="skeleton skeleton-card"></div><div class="skeleton skeleton-card"></div></div></div></section>';
      document.body.append(layer);
    }
    if(notice)notice.hidden=true;
    const wasHidden=layer.hidden;
    layer.querySelector('[data-loading-label]').textContent = message; layer.hidden = false;
    if(wasHidden){
      returnFocus=document.activeElement;
      // Native modal dialogs occupy the top layer above any ordinary loading overlay.
      suspendedPopovers='popover' in HTMLElement.prototype?[...document.querySelectorAll(':popover-open')]:[];
      for(const popover of suspendedPopovers)popover.hidePopover();
      suspendedDialogs=[...document.querySelectorAll('dialog[open]')];
      for(const dialog of suspendedDialogs)dialog.close();
      for(const element of document.body.children){
        if(element===layer||element===notice||['SCRIPT','STYLE'].includes(element.tagName))continue;
        inertStates.set(element,element.inert);element.inert=true;
      }
      layer.querySelector('[tabindex]').focus();
    }
  };
  const end = () => {
    pending = false; clearTimeout(navigationTimer); hideLayer();
    document.querySelectorAll('[data-action-pending]').forEach(form => { delete form.dataset.actionPending; form.removeAttribute('aria-busy'); });
  };
  window.PenaFeedback = { begin, end, notice: say };
  window.addEventListener('pageshow', end);
  const navigation = label => {
    begin(label); clearTimeout(navigationTimer);
    navigationTimer = setTimeout(() => { hideLayer(); say('Belum ada respons dari server. Periksa koneksi dan status surat sebelum mengulang tindakan.', true); }, 45000);
  };
  document.addEventListener('click', async event => {
    const copy = event.target.closest('[data-copy]');
    if (copy) {
      const input = document.getElementById(copy.dataset.copy); if (!input) return;
      try {
        try { await navigator.clipboard.writeText(input.value); }
        catch { input.focus(); input.select(); if (!document.execCommand('copy')) throw new Error('Clipboard unavailable'); }
        const label = copy.textContent; copy.textContent = 'Tautan tersalin'; say('Tautan tersalin. Kirim langsung kepada pemeriksa yang dituju.');
        setTimeout(() => { copy.textContent = label; }, 2000);
      } catch { say('Tautan belum tersalin. Pilih teks tautan, lalu salin secara manual.', true); }
      return;
    }
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol) || (url.pathname === location.pathname && url.search === location.search)) return;
    navigation('Membuka halaman');
  });
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (event.defaultPrevented || form.id === 'susun-form' || form.dataset.async !== undefined) return;
    if(form.dataset.rewriting){event.preventDefault();say('Tunggu penulisan ulang selesai sebelum menyimpan.');return;}
    if (pending || form.dataset.actionPending) { event.preventDefault(); return; }
    const submitter = event.submitter, body = new FormData(form);
    if (submitter?.name) body.set(submitter.name, submitter.value);
    if (/\/surat\/unduh\/\d+\/?$/.test(new URL(form.action).pathname)) {
      event.preventDefault(); pending = true; form.dataset.actionPending = 'true'; form.setAttribute('aria-busy', 'true'); begin('Menyiapkan dokumen');
      const controller=new AbortController(), timeout=setTimeout(()=>controller.abort(),120000);
      try {
        const response = await fetch(form.action, { method: 'POST', body, signal:controller.signal });
        const type = (response.headers.get('content-type') || '').split(';')[0];
        if (!response.ok || !['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(type)) {
          const page = new DOMParser().parseFromString(await response.text(), 'text/html');
          const error = page.querySelector('[role=alert]')?.textContent.trim();
          if (response.redirected && new URL(response.url).pathname.includes('/login')) throw new Error('Sesi berakhir. Masuk kembali sebelum mengunduh.');
          throw new Error(error || 'Dokumen belum tersedia. Periksa status surat, lalu coba unduh kembali.');
        }
        const blob = await response.blob(); if (!blob.size) throw new Error('Dokumen yang diterima kosong. Coba unduh kembali.');
        const url = URL.createObjectURL(blob), anchor = document.createElement('a');
        const name = response.headers.get('content-disposition')?.match(/filename="?([^";]+)/)?.[1];
        anchor.href = url; anchor.download = (name || 'surat.' + (type === 'application/pdf' ? 'pdf' : 'docx')).replace(/[\\/]/g, '_');
        document.body.append(anchor); anchor.click(); anchor.remove(); setTimeout(() => URL.revokeObjectURL(url), 60000);
        say('Dokumen siap diunduh. Periksa daftar unduhan browser.');
      } catch (error) { say(error.name==='AbortError'?'Unduhan belum selesai. Coba unduh kembali; status persetujuan tidak berubah.':error.message || 'Unduhan gagal. Periksa koneksi, lalu coba lagi.', true); }
      finally { clearTimeout(timeout); end(); submitter?.focus(); }
      return;
    }
    if (form.method.toLowerCase() === 'dialog') return;
    pending = true; form.dataset.actionPending = 'true'; form.setAttribute('aria-busy', 'true');
    navigation(form.method.toLowerCase() === 'get' ? 'Memuat hasil' : messages(form.action));
  });
  // Replacements for inline onclick handlers, which the Content-Security-Policy blocks.
  document.addEventListener('click', event => {
    if (event.target.closest('[data-history-back]')) history.back();
  });
  document.addEventListener('focusin', event => {
    if (event.target.matches?.('[data-select-on-focus]')) event.target.select();
  });
  document.addEventListener('click', event => {
    if (event.target.matches?.('[data-select-on-focus]')) event.target.select();
  });
})();
