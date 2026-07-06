document.addEventListener('DOMContentLoaded', function () {
  var path = window.location.pathname.toLowerCase();
  var isOrderOrCollection = /sales_orders\.php|orders\.php|collections\.php|collection/i.test(path);
  if (!isOrderOrCollection) return;

  document.body.classList.add('erp-mobile-voucher-entry');

  function enhanceTables() {
    document.querySelectorAll('table').forEach(function (table) {
      var text = table.innerText.toLowerCase();
      var hasVoucherFields = text.indexOf('product') !== -1 || text.indexOf('quantity') !== -1 || text.indexOf('qty') !== -1 || text.indexOf('rate') !== -1 || text.indexOf('amount') !== -1;
      if (!hasVoucherFields) return;
      table.classList.add('erp-voucher-items-table');
      var wrap = table.closest('.table-responsive');
      if (wrap && !wrap.querySelector('.erp-scroll-hint')) {
        var hint = document.createElement('div');
        hint.className = 'erp-scroll-hint text-muted small px-2 py-1 no-print';
        hint.textContent = 'Swipe left/right to see full item details';
        wrap.insertBefore(hint, wrap.firstChild);
      }
    });
  }

  function enhanceInputs() {
    var lookupSelector = 'input[name*="customer" i], input[id*="customer" i], input[name*="product" i], input[id*="product" i], select[name*="customer" i], select[id*="customer" i], select[name*="product" i], select[id*="product" i]';
    document.querySelectorAll(lookupSelector).forEach(function (el) {
      el.classList.add('erp-voucher-lookup');
      if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'off');
    });
  }

  function enhanceActionButtons() {
    document.querySelectorAll('form button, form .btn').forEach(function (btn) {
      var text = (btn.innerText || btn.value || '').toLowerCase();
      if (/save|submit|approve|update|create/.test(text)) btn.classList.add('btn-lg');
    });
  }

  enhanceTables();
  enhanceInputs();
  enhanceActionButtons();

  var observer = new MutationObserver(function () {
    enhanceTables();
    enhanceInputs();
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
