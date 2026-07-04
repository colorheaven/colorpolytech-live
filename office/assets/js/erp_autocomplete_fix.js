document.addEventListener('DOMContentLoaded', function () {
  var suggestionPattern = /(suggest|autocomplete|typeahead|search-result|lookup-result|dropdown-menu|results)/i;

  function isSuggestionBox(el) {
    if (!el || el === document.body || el === document.documentElement) return false;
    var idClass = ((el.id || '') + ' ' + (el.className || '')).toString();
    return suggestionPattern.test(idClass);
  }

  function suggestionBoxes() {
    return Array.from(document.querySelectorAll('[id*="suggest" i], [class*="suggest" i], [id*="autocomplete" i], [class*="autocomplete" i], [id*="typeahead" i], [class*="typeahead" i], [id*="result" i], [class*="result" i], .dropdown-menu'));
  }

  function hideBox(box) {
    if (!box) return;
    box.classList.remove('show', 'open', 'active', 'd-block');
    box.setAttribute('aria-hidden', 'true');
    if (isSuggestionBox(box)) box.style.display = 'none';
  }

  function closeAllSuggestions(except) {
    suggestionBoxes().forEach(function (box) {
      if (except && (box === except || box.contains(except))) return;
      hideBox(box);
    });
  }

  function closestSuggestionBox(el) {
    while (el && el !== document.body) {
      if (isSuggestionBox(el)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function makeProductInputsMobileFriendly() {
    var selector = 'input[name*="product" i], input[id*="product" i], select[name*="product" i], select[id*="product" i]';
    document.querySelectorAll(selector).forEach(function (input) {
      input.classList.add('erp-product-lookup-input');
      var cell = input.closest('td, .col, [class*="col-"]');
      if (cell) cell.classList.add('erp-product-lookup-cell');
      var table = input.closest('table');
      if (table) table.classList.add('erp-voucher-items-table');
    });
  }

  makeProductInputsMobileFriendly();
  setTimeout(makeProductInputsMobileFriendly, 600);
  setTimeout(makeProductInputsMobileFriendly, 1500);

  document.addEventListener('click', function (event) {
    var box = closestSuggestionBox(event.target);
    var isLookupInput = event.target.matches && event.target.matches('input, select, textarea');

    if (box) {
      // Let the original click handler fill the input first, then close the list.
      setTimeout(function () { closeAllSuggestions(); }, 120);
      return;
    }

    if (!isLookupInput) closeAllSuggestions();
  }, true);

  document.addEventListener('touchend', function (event) {
    var box = closestSuggestionBox(event.target);
    if (box) setTimeout(function () { closeAllSuggestions(); }, 160);
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeAllSuggestions();
    if (event.key === 'Enter') {
      var box = closestSuggestionBox(document.activeElement);
      setTimeout(function () { closeAllSuggestions(box); }, 120);
    }
  });

  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches('input, select')) {
      setTimeout(closeAllSuggestions, 120);
    }
  }, true);

  document.addEventListener('blur', function (event) {
    if (event.target && event.target.matches('input, select, textarea')) {
      setTimeout(function () {
        var active = document.activeElement;
        if (!closestSuggestionBox(active)) closeAllSuggestions(active);
      }, 220);
    }
  }, true);

  window.addEventListener('resize', closeAllSuggestions);
  window.addEventListener('orientationchange', closeAllSuggestions);

  // Watch dynamically added rows/items and style product lookup fields.
  var mo = new MutationObserver(function () { makeProductInputsMobileFriendly(); });
  mo.observe(document.body, { childList: true, subtree: true });
});
