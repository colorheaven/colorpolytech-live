document.addEventListener('DOMContentLoaded', function () {
  var path = window.location.pathname.toLowerCase();
  if (!/sales_orders\.php|orders\.php|sales_invoices\.php|quotations\.php/.test(path)) return;

  function cellText(el) { return (el ? el.textContent : '').trim(); }

  function findProductHeaderIndex(table) {
    var heads = Array.from(table.querySelectorAll('thead th'));
    for (var i = 0; i < heads.length; i++) {
      var t = cellText(heads[i]).toLowerCase();
      if (t === 'product' || t.indexOf('product') !== -1) return i;
    }
    return -1;
  }

  function visibleLookupInCell(cell) {
    if (!cell) return null;
    return cell.querySelector('input:not([type="hidden"]), select, textarea');
  }

  function rowIndex(tr) {
    var rows = Array.from(tr.parentElement ? tr.parentElement.children : []);
    return Math.max(0, rows.indexOf(tr));
  }

  function productEndpoint(q) {
    return 'product_suggest.php?q=' + encodeURIComponent(q);
  }

  function fallbackEndpoint(q) {
    return 'inventory_product_suggest.php?q=' + encodeURIComponent(q);
  }

  function normalizeSuggestion(row) {
    if (!row) return null;
    if (typeof row === 'string') return { id: row, name: row, label: row };
    var name = row.name || row.product_name || row.label || row.text || row.title || row.value || '';
    var id = row.id || row.product_id || row.value || name;
    var label = row.label || row.text || name;
    return name ? { id: id, name: name, label: label } : null;
  }

  function triggerChange(el) {
    ['input', 'change', 'keyup'].forEach(function (name) {
      el.dispatchEvent(new Event(name, { bubbles: true }));
    });
  }

  function setHiddenProductValue(cell, tr, suggestion) {
    var hidden = cell.querySelector('input[type="hidden"][name*="product" i], input[type="hidden"][id*="product" i]') ||
      tr.querySelector('input[type="hidden"][name*="product" i], input[type="hidden"][id*="product" i]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'product_id[]';
      cell.appendChild(hidden);
    }
    hidden.value = suggestion.id || suggestion.name;
    triggerChange(hidden);
  }

  function selectSuggestion(input, cell, tr, box, suggestion) {
    input.value = suggestion.name;
    input.dataset.productId = suggestion.id || suggestion.name;
    setHiddenProductValue(cell, tr, suggestion);
    box.style.display = 'none';
    triggerChange(input);
  }

  function renderSuggestions(input, cell, tr, box, rows) {
    box.innerHTML = '';
    rows.map(normalizeSuggestion).filter(Boolean).slice(0, 12).forEach(function (s) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'erp-product-suggestion-item';
      btn.innerHTML = '<strong>' + escapeHtml(s.name) + '</strong><br><small>' + escapeHtml(s.label) + '</small>';
      btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      btn.addEventListener('click', function () { selectSuggestion(input, cell, tr, box, s); });
      box.appendChild(btn);
    });
    box.style.display = box.children.length ? 'block' : 'none';
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>'"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[c];
    });
  }

  function attachLookup(cell, tr) {
    if (!cell || cell.dataset.productLookupFixed === '1') return;
    var existing = visibleLookupInCell(cell);
    if (existing) {
      existing.classList.add('erp-order-product-input');
      cell.dataset.productLookupFixed = '1';
      return;
    }

    var idx = rowIndex(tr);
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control erp-order-product-input';
    input.name = 'product_search[]';
    input.placeholder = 'Type product name/code...';
    input.autocomplete = 'off';
    input.inputMode = 'text';

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'product_id[]';

    var box = document.createElement('div');
    box.className = 'erp-order-product-suggestions suggestions';
    box.style.display = 'none';

    cell.classList.add('erp-order-product-cell');
    cell.innerHTML = '';
    cell.appendChild(input);
    cell.appendChild(hidden);
    cell.appendChild(box);
    cell.dataset.productLookupFixed = '1';

    var timer = null;
    input.addEventListener('input', function () {
      var q = input.value.trim();
      hidden.value = '';
      clearTimeout(timer);
      if (q.length < 1) { box.style.display = 'none'; return; }
      timer = setTimeout(function () {
        fetch(productEndpoint(q), { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : []; })
          .catch(function () { return fetch(fallbackEndpoint(q), { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : []; }); })
          .then(function (rows) { renderSuggestions(input, cell, tr, box, Array.isArray(rows) ? rows : []); })
          .catch(function () { box.style.display = 'none'; });
      }, 220);
    });

    input.addEventListener('focus', function () {
      if (box.children.length) box.style.display = 'block';
    });

    document.addEventListener('click', function (e) {
      if (!cell.contains(e.target)) box.style.display = 'none';
    });
  }

  function fixProductCells() {
    document.querySelectorAll('table').forEach(function (table) {
      var pi = findProductHeaderIndex(table);
      if (pi < 0) return;
      var rows = table.querySelectorAll('tbody tr');
      rows.forEach(function (tr) {
        var cells = tr.children;
        if (!cells || !cells[pi]) return;
        attachLookup(cells[pi], tr);
      });
    });
  }

  fixProductCells();
  setTimeout(fixProductCells, 500);
  setTimeout(fixProductCells, 1200);

  var observer = new MutationObserver(function () { fixProductCells(); });
  observer.observe(document.body, { childList: true, subtree: true });
});
