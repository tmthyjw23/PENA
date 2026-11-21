/* Account-owned onboarding; no field values or API keys are stored in the browser. */
(() => {
  const panel = document.getElementById('pena-tour');
  if (!panel) return;
  const nativePopover = 'popover' in HTMLElement.prototype;
  if (!nativePopover) panel.removeAttribute('popover');
  const steps = [
    ['workspace', 'Pilih ruang kerja', 'Pilih ruang pribadi untuk surat sendiri, atau organisasi untuk memakai kop dan nomor surat. Ruang aktif ditampilkan di sini.'],
    ['buat', 'Mulai dari Buat surat', 'Jelaskan surat yang Anda butuhkan, lalu periksa hasil AI. Pilih Isi formulir untuk menulis sendiri.'],
    ['surat', 'Temukan draf dan statusnya', 'Draf yang disimpan ada di sini. Buka detailnya untuk memeriksa isi, mengajukan, atau menyelesaikan surat. Isian tidak tersimpan otomatis.'],
    ['help', 'Buka panduan saat diperlukan', 'Di ponsel, Bantuan dan Pengaturan AI ada di menu akun. Di desktop, keduanya ada di bagian bawah navigasi. Hint ini bisa diulang dari Bantuan.'],
  ];
  const title = panel.querySelector('h2'), copy = panel.querySelector('#tour-copy');
  const back = panel.querySelector('[data-tour-back]'), next = panel.querySelector('[data-tour-next]');
  let index = 0, target = null, open = false, saving = false;
  const visibleTarget = key => [...document.querySelectorAll('[data-tour="' + key + '"]')].find(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
  const clearTarget = () => {
    if (!target) return;
    target.classList.remove('tour-target');
    target.style.removeProperty('anchor-name');
    target.removeAttribute('aria-details');
  };
  const place = () => {
    if (!open) return;
    const current = visibleTarget(steps[index][0]);
    if (!current) { finish(); return; }
    if (target !== current) {
      clearTarget(); target = current;
      target.classList.add('tour-target');
      target.style.setProperty('anchor-name', '--pena-tour-target');
      target.setAttribute('aria-details', 'pena-tour');
      // Place the explanation next to its control in reading order, including on resize.
      const shown = nativePopover && panel.matches(':popover-open');
      if (shown) panel.hidePopover();
      (target.tagName === 'SUMMARY' ? target.parentElement : target).before(panel);
      if (shown) panel.showPopover();
    }
    const anchored = innerWidth >= 1024 && CSS.supports('position-area: right') && CSS.supports('anchor-name: --a');
    panel.classList.toggle('tour-anchored', anchored);
    if (anchored) { panel.style.removeProperty('left'); panel.style.removeProperty('top'); return; }
    const r = target.getBoundingClientRect(), p = panel.getBoundingClientRect();
    const left = Math.max(8, Math.min(r.left, innerWidth - p.width - 8));
    const top = r.bottom + p.height + 12 <= innerHeight ? r.bottom + 8 : Math.max(8, r.top - p.height - 8);
    panel.style.left = left + 'px'; panel.style.top = Math.min(top, Math.max(8, innerHeight - p.height - 8)) + 'px';
  };
  function show(moveFocus) {
    clearTarget(); target = null;
    title.textContent = steps[index][1]; copy.textContent = steps[index][2];
    panel.querySelector('[data-tour-count]').textContent = 'Petunjuk ' + (index + 1) + ' dari ' + steps.length;
    back.hidden = index === 0; next.textContent = index === steps.length - 1 ? 'Selesai' : 'Lanjut';
    panel.hidden = false; open = true;
    // Moving an open popover can remove it from the top layer in older browsers.
    if (nativePopover && panel.matches(':popover-open')) panel.hidePopover();
    place();
    if (nativePopover) panel.showPopover();
    place();
    if (moveFocus) title.focus({preventScroll:true});
  }
  async function finish() {
    if (saving) return;
    saving = true;
    const error = panel.querySelector('[data-tour-error]');
    error.hidden = true;
    try {
      const response = await fetch(panel.dataset.finishUrl, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json'}, body:new URLSearchParams({csrf_token:panel.dataset.csrf}), signal:AbortSignal.timeout(15000)});
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json') || (await response.json()).status !== 'ok') throw Error('dismiss');
      const focusTarget = target;
      open = false;
      if (nativePopover && panel.matches(':popover-open')) panel.hidePopover();
      panel.hidden = true; clearTarget(); focusTarget?.focus({preventScroll:true});
    } catch {
      error.textContent = 'Penutupan belum tersimpan. Coba Tutup lagi, atau lanjutkan memakai halaman ini.';
      error.hidden = false;
    } finally { saving = false; }
  }
  back.addEventListener('click', () => { if (index > 0) { index--; show(true); } });
  next.addEventListener('click', () => { if (index === steps.length - 1) finish(); else { index++; show(true); } });
  panel.querySelector('[data-tour-close]').addEventListener('click', finish);
  document.addEventListener('keydown', e => { if (open && e.key === 'Escape') { e.preventDefault(); finish(); } });
  document.addEventListener('focusin', e => {
    if (!open || panel.contains(e.target)) return;
    const a=e.target.getBoundingClientRect(), b=panel.getBoundingClientRect();
    // Keep keyboard users' focused controls visible without blocking the rest of the app.
    if(a.width && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top) finish();
  });
  addEventListener('resize', place);
  addEventListener('scroll', place, {passive:true});
  // Automatic hints supplement the page; explicit replay may move focus into the hint.
  show(new URLSearchParams(location.search).get('petunjuk') === '1');
})();
