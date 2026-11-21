  </div>
</main>
<script>
  // Dialog openers: <button data-open="dialogId">; dialogs close with their own "Kembali" (value=cancel) or Esc.
  document.querySelectorAll('[data-open]').forEach(btn => btn.addEventListener('click', () => {
    const d = document.getElementById(btn.dataset.open);
    if (d && d.showModal) { d.showModal(); d.querySelector('textarea,input:not([type=hidden]),select')?.focus(); }
  }));
  document.querySelectorAll('dialog [data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
  // Details popovers: close when clicking outside or pressing Escape.
  document.addEventListener('click', e => {
    document.querySelectorAll('details[data-menu][open]').forEach(d => {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('details[data-menu][open]').forEach(d => d.removeAttribute('open'));
    }
  });
  // ?buka=<dialogId> opens that dialog on arrival (e.g. "Mulai revisi" from Beranda).
  const buka = new URLSearchParams(location.search).get('buka');
  if (buka && /^dlg-[a-z]+$/.test(buka)) document.getElementById(buka)?.showModal?.();
</script>
</body>
</html>
