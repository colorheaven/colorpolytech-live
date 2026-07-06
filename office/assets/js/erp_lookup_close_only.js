document.addEventListener('DOMContentLoaded', function () {
  var page = window.location.pathname.toLowerCase();
  if (!/sales_orders\.php|orders\.php|sales_invoices\.php|quotations\.php|collections\.php/.test(page)) return;

  function isRealInput(el) {
    return el && el.matches && el.matches('input, select, textarea');
  }

  function looksLikeLookupItem(el) {
    if (!el) return false;
    var txt = (el.textContent || '').trim();
    if (!txt) return false;
    if (isRealInput(el)) return false;
    return /stock\s*:|due\s*:|masterbatch|pigment|customer|supplier|\btk\b|\d{4}/i.test(txt);
  }

  function findLookupBoxFromItem(item) {
    var el = item;
    for (var i = 0; i < 5 && el && el !== document.body; i++, el = el.parentElement) {
      var txt = (el.textContent || '').trim();
      var inputs = el.querySelectorAll ? el.querySelectorAll('input, select, textarea').length : 0;
      var buttons = el.querySelectorAll ? el.querySelectorAll('button, a, div, span').length : 0;
      var idClass = ((el.id || '') + ' ' + (el.className || '')).toString();
      if (/suggest|autocomplete|lookup|result|dropdown|typeahead/i.test(idClass)) return el;
      if (txt.length > 25 && buttons >= 2 && inputs <= 1 && /stock\s*:|due\s*:|masterbatch|pigment/i.test(txt)) return el;
    }
    return item.parentElement;
  }

  function closeLookupBox(box) {
    if (!box) return;
    box.classList.remove('show', 'open', 'active', 'd-block');
    box.setAttribute('aria-hidden', 'true');
    box.style.display = 'none';
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (isRealInput(target)) return;

    var item = target.closest ? target.closest('button, a, div, span, li') : target;
    if (!looksLikeLookupItem(item)) return;

    setTimeout(function () {
      closeLookupBox(findLookupBoxFromItem(item));
    }, 250);
  }, false);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== 'Escape') return;
    if (event.key === 'Escape') {
      document.querySelectorAll('[class*="suggest" i], [id*="suggest" i], [class*="autocomplete" i], [id*="autocomplete" i], [class*="lookup" i], [id*="lookup" i], [class*="result" i], [id*="result" i]').forEach(closeLookupBox);
    }
  });
});
