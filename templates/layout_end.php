    </div><!-- /content -->
</div><!-- /main -->

<script>
/* ── CSRF: auto-inject hidden field into every form + send header on fetch() ── */
(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  if (!token) return;

  // Inject into all existing and future forms
  document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(addField);

  // Watch for dynamically added forms
  new MutationObserver(mutations => {
    mutations.forEach(m => m.addedNodes.forEach(node => {
      if (node.nodeType !== 1) return;
      if (node.matches('form')) addField(node);
      node.querySelectorAll?.('form[method="POST"], form[method="post"]').forEach(addField);
    }));
  }).observe(document.body, { childList: true, subtree: true });

  function addField(form) {
    if (form.querySelector('input[name="_csrf"]')) return;
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = '_csrf'; inp.value = token;
    form.appendChild(inp);
  }

  // Patch fetch() to include CSRF header on same-origin POST requests
  const _fetch = window.fetch;
  window.fetch = function (url, opts = {}) {
    if ((opts.method || 'GET').toUpperCase() === 'POST') {
      opts.headers = Object.assign({ 'X-CSRF-Token': token }, opts.headers ?? {});
    }
    return _fetch.call(this, url, opts);
  };
})();

/* ── Auto-dismiss alerts after 4 seconds ── */
(function () {
  function dismissAlert(el) {
    el.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
    el.style.opacity    = '0';
    el.style.maxHeight  = '0';
    el.style.margin     = '0';
    el.style.padding    = '0';
    el.style.overflow   = 'hidden';
    setTimeout(() => el.remove(), 420);
  }

  document.querySelectorAll('.alert, .alert-success, .alert-error').forEach(el => {
    setTimeout(() => dismissAlert(el), 4000);
  });
})();
</script>
</body>
</html>
