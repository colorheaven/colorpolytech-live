document.addEventListener('DOMContentLoaded', function () {
  if (/\/inventory_movements\.php$/i.test(window.location.pathname)) {
    function text(el) { return (el ? el.textContent : '').trim(); }
    function today() { return new Date().toISOString().slice(0, 10); }
    function makeButton(productName) {
      var a = document.createElement('a');
      a.className = 'btn btn-sm btn-outline-primary ms-1 inventory-statement-row-btn';
      a.textContent = 'Statement';
      a.href = 'inventory_statement.php?from=2000-01-01&to=' + encodeURIComponent(today()) + '&product_q=' + encodeURIComponent(productName) + '&movement_type=';
      return a;
    }

    document.querySelectorAll('table').forEach(function (table) {
      var headers = Array.from(table.querySelectorAll('thead th')).map(function (th) { return text(th).toLowerCase(); });
      var productIndex = headers.findIndex(function (h) { return h === 'product id' || h === 'product' || h.indexOf('product') !== -1; });
      var actionIndex = headers.findIndex(function (h) { return h === 'actions' || h === 'action' || h.indexOf('action') !== -1; });
      if (productIndex < 0 || actionIndex < 0) return;

      table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.querySelector('.inventory-statement-row-btn')) return;
        var cells = tr.children;
        if (!cells || cells.length <= Math.max(productIndex, actionIndex)) return;
        var productName = text(cells[productIndex]);
        if (!productName || productName === '—' || productName === '-') return;
        cells[actionIndex].appendChild(makeButton(productName));
      });
    });
  }

  // Safe close-only helper for existing live customer/product suggestions.
  // It does not create, hide, or modify any input field.
  var page = window.location.pathname.toLowerCase();
  if (!/sales_orders\.php|orders\.php|sales_invoices\.php|quotations\.php|collections\.php/.test(page)) return;

  function isInput(el) { return el && el.matches && el.matches('input, select, textarea'); }
  function itemText(el) { return (el && el.textContent ? el.textContent : '').trim(); }
  function looksLikeLookupItem(el) {
    if (!el || isInput(el)) return false;
    var txt = itemText(el);
    if (!txt) return false;
    return /stock\s*:|due\s*:|masterbatch|pigment|017|\btk\b|\d{4}/i.test(txt);
  }
  function hideLookupContainer(el) {
    if (!el) return;
    el.classList.remove('show', 'open', 'active', 'd-block');
    el.setAttribute('aria-hidden', 'true');
    el.style.display = 'none';
  }
  function findContainer(item) {
    var el = item;
    for (var i = 0; i < 5 && el && el !== document.body; i++, el = el.parentElement) {
      var idClass = ((el.id || '') + ' ' + (el.className || '')).toString();
      var txt = itemText(el);
      var inputs = el.querySelectorAll ? el.querySelectorAll('input, select, textarea').length : 0;
      if (/suggest|autocomplete|lookup|result|dropdown|typeahead/i.test(idClass)) return el;
      if (txt.length > 30 && inputs <= 1 && /stock\s*:|due\s*:|masterbatch|pigment/i.test(txt)) return el;
    }
    return item.parentElement;
  }
  document.addEventListener('click', function (event) {
    if (isInput(event.target)) return;
    var item = event.target.closest ? event.target.closest('button, a, div, span, li') : event.target;
    if (!looksLikeLookupItem(item)) return;
    setTimeout(function () { hideLookupContainer(findContainer(item)); }, 250);
  }, false);
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[class*="suggest" i], [id*="suggest" i], [class*="autocomplete" i], [id*="autocomplete" i], [class*="lookup" i], [id*="lookup" i], [class*="result" i], [id*="result" i]').forEach(hideLookupContainer);
  });
});
