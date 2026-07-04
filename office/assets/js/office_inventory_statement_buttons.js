document.addEventListener('DOMContentLoaded', function () {
  if (!/\/inventory_movements\.php$/i.test(window.location.pathname)) return;

  function text(el) { return (el ? el.textContent : '').trim(); }
  function today() { return new Date().toISOString().slice(0, 10); }
  function makeButton(productName) {
    var a = document.createElement('a');
    a.className = 'btn btn-sm btn-outline-primary ms-1 inventory-statement-row-btn';
    a.textContent = 'Statement';
    a.href = 'inventory_statement.php?from=2000-01-01&to=' + encodeURIComponent(today()) + '&product_q=' + encodeURIComponent(productName) + '&movement_type=';
    return a;
  }

  var tables = document.querySelectorAll('table');
  tables.forEach(function (table) {
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
});
