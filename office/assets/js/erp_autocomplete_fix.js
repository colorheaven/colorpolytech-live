document.addEventListener('DOMContentLoaded', function () {
  var suggestionPattern = /(suggest|autocomplete|typeahead|search-result|lookup-result|dropdown-menu|results)/i;
  var lookupInputSelector = 'input[name*="customer" i], input[id*="customer" i], input[name*="product" i], input[id*="product" i], input[name*="supplier" i], input[id*="supplier" i], input[name*="lead" i], input[id*="lead" i], input[list]';

  function isSuggestionBox(el) {
    if (!el || el === document.body || el === document.documentElement) return false;
    var idClass = ((el.id || '') + ' ' + (el.className || '')).toString();
    return suggestionPattern.test(idClass);
  }

  function isLookupInput(el) {
    return !!(el && el.matches && el.matches(lookupInputSelector));
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

  function closestSuggestionBox(el) {
    while (el && el !== document.body) {
      if (isSuggestionBox(el)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function closeAllSuggestions(except) {
    suggestionBoxes().forEach(function (box) {
      if (except && (box === except || box.contains(except))) return;
      hideBox(box);
    });
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

  // Important: do NOT close suggestions when tapping/focusing customer/product inputs.
  // Close only when user taps outside both the input and suggestion list.
  document.addEventListener('click', function (event) {
    var target = event.target;
    var box = closestSuggestionBox(target);

    if (box) {
      // Let existing app code select/fill the input first, then close the list.
      setTimeout(function () { closeAllSuggestions(); }, 320);
      return;
    }

    if (isLookupInput(target) || (target.closest && target.closest('form'))) {
      return;
    }

    closeAllSuggestions();
  }, false);

  document.addEventListener('touchend', function (event) {
    var target = event.target;
    var box = closestSuggestionBox(target);
    if (box) {
      setTimeout(function () { closeAllSuggestions(); }, 420);
      return;
    }
    if (isLookupInput(target)) return;
  }, false);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeAllSuggestions();
    if (event.key === 'Enter') {
      var box = closestSuggestionBox(document.activeElement);
      setTimeout(function () { closeAllSuggestions(box); }, 250);
    }
  });

  // Do not close on blur/change; mobile Safari fires these before the suggestion tap finishes.
  window.addEventListener('resize', closeAllSuggestions);
  window.addEventListener('orientationchange', closeAllSuggestions);

  var mo = new MutationObserver(function () { makeProductInputsMobileFriendly(); });
  mo.observe(document.body, { childList: true, subtree: true });
});
