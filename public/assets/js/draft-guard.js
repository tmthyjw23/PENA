(() => {
  const forms = [...document.querySelectorAll('form[data-draft-guard]')];
  let leaving = false;
  const dirty = () => forms.some(form => form.dataset.dirty === 'true');
  const confirmLeave = () => !dirty() || window.confirm('Ada perubahan yang belum disimpan. Tinggalkan halaman dan abaikan perubahan itu?');
  const mark = event => {
    const form = event.target.closest('form[data-draft-guard]');
    if (!form) return;
    form.dataset.dirty = 'true';
    let status = form.querySelector('[data-unsaved-status]');
    if (!status) {
      status = document.createElement('p'); status.dataset.unsavedStatus = '';
      status.className = 'col-span-full mb-4 text-rev font-semibold'; status.setAttribute('role', 'status');
      status.textContent = 'Perubahan belum disimpan.'; form.prepend(status);
    }
  };
  document.addEventListener('input', mark);
  document.addEventListener('change', mark);
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    const url = new URL(link.href, location.href);
    if (!/^https?:$/.test(url.protocol) || (url.pathname === location.pathname && url.search === location.search && url.origin === location.origin)) return;
    if (!confirmLeave()) { event.preventDefault(); event.stopImmediatePropagation(); }
    else leaving = true;
  }, true);
  document.addEventListener('submit', event => {
    if (event.defaultPrevented || event.target.method.toLowerCase() === 'dialog') return;
    if (!forms.includes(event.target) && dirty() && !confirmLeave()) {
      event.preventDefault(); event.stopImmediatePropagation();
    }
  }, true);
  document.addEventListener('submit', event => {
    if (event.defaultPrevented || event.target.dataset.rewriting || event.target.method.toLowerCase() === 'dialog') return;
    queueMicrotask(() => { if (!event.defaultPrevented) leaving = true; });
  });
  window.addEventListener('beforeunload', event => {
    if (!dirty() || leaving) return;
    event.preventDefault(); event.returnValue = '';
  });
  window.addEventListener('pageshow', () => { leaving = false; });
  window.PenaDraftGuard = { confirmLeave, allowNavigation: () => { leaving = true; } };
  const summary = document.querySelector('[data-error-summary]');
  if (summary) summary.focus();
})();
