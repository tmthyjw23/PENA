  </div>
</main>
<script>
  // Copy buttons: <button data-copy="inputId">.
  document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
    const input = document.getElementById(btn.dataset.copy);
    try { await navigator.clipboard.writeText(input.value); } catch (e) { input.select(); document.execCommand('copy'); }
    btn.textContent = 'Tersalin';
  }));
  // Dialog openers: <button data-open="dialogId">; dialogs close with their own "Kembali" (value=cancel) or Esc.
  document.querySelectorAll('[data-open]').forEach(btn => btn.addEventListener('click', () => {
    const d = document.getElementById(btn.dataset.open);
    if (d && d.showModal) { d.showModal(); d.querySelector('textarea,input:not([type=hidden]),select')?.focus(); }
  }));
  document.querySelectorAll('dialog [data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
  // ?buka=<dialogId> opens that dialog on arrival (e.g. "Mulai revisi" from Beranda).
  const buka = new URLSearchParams(location.search).get('buka');
  if (buka && /^dlg-[a-z]+$/.test(buka)) document.getElementById(buka)?.showModal?.();
</script>
</body>
</html>
