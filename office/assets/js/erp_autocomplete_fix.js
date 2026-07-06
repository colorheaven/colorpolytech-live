document.addEventListener('DOMContentLoaded', function () {
  var suggestionPattern = /(suggest|autocomplete|typeahead|search-result|lookup-result|dropdown-menu|results)/i;
  var lookupInputSelector = [
    'input:not([type="hidden"])[name*="customer" i]',
    'input:not([type="hidden"])[id*="customer" i]',
    'input:not([type="hidden"])[name*="product" i]',
    'input:not([type="hidden"])[id*="product" i]',
    'input:not([type="hidden"])[name*="supplier" i]',
    'input:not([type="hidden"])[id*="supplier" i]',
    'input:not([type="hidden"])[name*="lead" i]',
    'input:not([type="hidden"])[id*="lead" i]',
    'input:not([type="hidden"])[list]',
    'select[name*="customer" i]',
    'select[id*="customer" i]',
    'select[name*="product" i]',
    'select[id*="product" i]',
    'select[name*="supplier" i]',
    'select[id*="supplier" i]'
  ].join(',');

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

  function protectLookupInput(input) {
    if (!isLookupInput(input)) return;
    input.classList.add('erp-lookup-visible');
    input.removeAttribute('hidden');
    input.style.visibility = 'visible';
    input.style.opacity = '1';
    input.style.pointerEvents = 'auto';
    if (input.style.display === 'none') input.style.display = 'block';
    var wrap = input.closest('td, .col, [class*="col-"], .form-group, .mb-3, .mb-2, .field, .form-floating');
    if (wrap) wrap.classList.add('erp-lookup-visible-wrap');
  }

  function protectAllLookups() {
    document.querySelectorAll(lookupInputSelector).forEach(protectLookupInput);
  }

  function makeProductInputsMobileFriendly() {
    var selector = 'input:not([type="hidden"])[name*="product" i], input:not([type="hidden"])[id*="product" i], select[name*="product" i], select[id*="product" i]';
    document.querySelectorAll(selector).forEach(function (input) {
      input.classList.add('erp-product-lookup-input');
      protectLookupInput(input);
      var cell = input.closest('td, .col, [class*="col-"]');
      if (cell) cell.classList.add('erp-product-lookup-cell');
      var table = input.closest('table');
      if (table) table.classList.add('erp-voucher-items-table');
    });
  }

  protectAllLookups();
  makeProductInputsMobileFriendly();
  setTimeout(function () { protectAllLookups(); makeProductInputsMobileFriendly(); }, 600);
  setTimeout(function () { protectAllLookups(); makeProductInputsMobileFriendly(); }, 1500);

  document.addEventListener('focusin', function (event) {
    if (isLookupInput(event.target)) {
      protectLookupInput(event.target);
    }
  }, true);

  // Important: do NOT close suggestions when tapping/focusing customer/product inputs.
  // Close only when user selects a suggestion or taps clearly outside the form area.
  document.addEventListener('click', function (event) {
    var target = event.target;
    var box = closestSuggestionBox(target);

    if (box) {
      setTimeout(function () { closeAllSuggestions(); protectAllLookups(); }, 380);
      return;
    }

    if (isLookupInput(target)) {
      protectLookupInput(target);
      return;
    }

    if (target.closest && target.closest('form')) return;

    closeAllSuggestions();
  }, false);

  document.addEventListener('touchend', function (event) {
    var target = event.target;
    var box = closestSuggestionBox(target);
    if (box) {
      setTimeout(function () { closeAllSuggestions(); protectAllLookups(); }, 480);
      return;
    }
    if (isLookupInput(target)) {
      protectLookupInput(target);
      return;
    }
  }, false);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeAllSuggestions();
    if (event.key === 'Enter') {
      var box = closestSuggestionBox(document.activeElement);
      setTimeout(function () { closeAllSuggestions(box); protectAllLookups(); }, 250);
    }
  });

  window.addEventListener('resize', closeAllSuggestions);
  window.addEventListener('orientationchange', closeAllSuggestions);

  var mo = new MutationObserver(function () { protectAllLookups(); makeProductInputsMobileFriendly(); });
  mo.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
});
