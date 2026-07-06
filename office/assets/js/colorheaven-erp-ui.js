document.addEventListener('DOMContentLoaded', function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const dummy = {
    customers: [
      {id: 128, name: 'Rong Roshayon (RM)', due: 348500, mobile: '01714446740', address: 'Chawkbazar, Dhaka', label: 'Rong Roshayon (RM) · 01714446740 · Due: 348,500.00'},
      {id: 124, name: 'Mim Plastic Products', due: 0, mobile: '01700000001', address: 'Keraniganj, Dhaka', label: 'Mim Plastic Products · 01700000001 · Due: 0.00'},
      {id: 195, name: 'Surovi Plastic', due: 62500, mobile: '01800000002', address: 'Narsingdi', label: 'Surovi Plastic · 01800000002 · Due: 62,500.00'}
    ],
    suppliers: [
      ['China Polymer Trading Co.', 1250000, '+861380000000'],
      ['Local Resin Supplier', 420000, '01811111111']
    ],
    products: [
      {id: 2001, name: 'White CMB-2001', grade: 'Filler Masterbatch', kg_per_bag: 25, price: 510, label: 'White CMB-2001 · Stock: 0.00 Kilogram'},
      {id: 2002, name: 'White CMB-2002', grade: 'Filler Masterbatch', kg_per_bag: 25, price: 500, label: 'White CMB-2002 · Stock: 0.00 Kilogram'},
      {id: 2003, name: 'White CMB-2003', grade: 'Filler Masterbatch', kg_per_bag: 25, price: 500, label: 'White CMB-2003 · Stock: -300.00 Kilogram'},
      {id: 7009, name: 'Red CMB-7009', grade: 'Color Masterbatch', kg_per_bag: 25, price: 430, label: 'Red CMB-7009 · Stock: 250.00 Kilogram'}
    ]
  };

  function toast(msg) {
    const t = $('#chToast');
    if (t && window.bootstrap) {
      $('.toast-body', t).textContent = msg;
      bootstrap.Toast.getOrCreateInstance(t).show();
    }
  }
  function loader(on) { $('#chLoader')?.classList.toggle('d-none', !on); }
  function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  $$('[data-mobile-sidebar]').forEach(b => b.addEventListener('click', () => $('#chSidebar')?.classList.add('open')));
  $$('[data-sidebar-collapse]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('sidebar-collapsed')));
  document.addEventListener('click', e => { if (!e.target.closest('.ch-sidebar,[data-mobile-sidebar]')) $('#chSidebar')?.classList.remove('open'); });

  let confirmCb = null;
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-confirm]');
    if (!b) return;
    confirmCb = () => toast('Confirmed successfully');
    const p = $('#chConfirmModal .modal-body p');
    if (p) p.textContent = b.dataset.confirm;
    if (window.bootstrap) bootstrap.Modal.getOrCreateInstance('#chConfirmModal').show();
  });
  $('[data-confirm-ok]')?.addEventListener('click', () => {
    if (window.bootstrap) bootstrap.Modal.getOrCreateInstance('#chConfirmModal').hide();
    if (confirmCb) confirmCb();
  });

  $$('[data-step-target]').forEach(btn => btn.addEventListener('click', () => {
    $$('[data-step-target]').forEach(x => x.classList.remove('active'));
    btn.classList.add('active');
    $$('.ch-form-step').forEach(x => x.classList.remove('active'));
    $(`[data-step="${btn.dataset.stepTarget}"]`)?.classList.add('active');
  }));

  function closeSuggest() { $$('.ch-suggest').forEach(b => b.remove()); }
  function renderSuggest(input, rows, onPick) {
    closeSuggest();
    if (!rows.length) return;
    const wrap = input.parentElement;
    if (getComputedStyle(wrap).position === 'static') wrap.style.position = 'relative';
    const box = document.createElement('div');
    box.className = 'ch-suggest';
    box.style.cssText = 'position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid #dbe3ea;border-radius:12px;box-shadow:0 14px 30px rgba(0,0,0,.12);z-index:4000;max-height:280px;overflow:auto';
    rows.slice(0, 10).forEach(row => {
      const a = document.createElement('button');
      a.type = 'button';
      a.className = 'btn text-start w-100 border-0 border-bottom rounded-0';
      a.innerHTML = `<strong>${esc(row.name || row[0])}</strong><br><small class="text-muted">${esc(row.label || (Array.isArray(row) ? row.slice(1).join(' · ') : row.name))}</small>`;
      a.addEventListener('mousedown', e => e.preventDefault());
      a.addEventListener('click', () => { onPick(row); closeSuggest(); });
      box.appendChild(a);
    });
    wrap.appendChild(box);
  }
  function ajaxRows(url, fallback, cb) {
    fetch(url, { credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : [])
      .then(rows => cb(Array.isArray(rows) && rows.length ? rows : fallback))
      .catch(() => cb(fallback));
  }

  const timers = new WeakMap();
  document.addEventListener('input', e => {
    const input = e.target;
    if (!input.matches('[data-customer-search],[data-product-search],[data-supplier-search]')) return;
    clearTimeout(timers.get(input));
    const q = input.value.trim();
    if (!q) { closeSuggest(); return; }
    timers.set(input, setTimeout(() => {
      if (input.matches('[data-customer-search]')) {
        ajaxRows('ajax_customer_lookup.php?q=' + encodeURIComponent(q), dummy.customers.filter(r => r.label.toLowerCase().includes(q.toLowerCase())), rows => {
          renderSuggest(input, rows, row => {
            input.value = row.name || row[0] || '';
            const form = input.closest('form');
            const id = form?.querySelector('[data-customer-id]'); if (id) id.value = row.id || '';
            const mob = form?.querySelector('[data-customer-mobile]'); if (mob) mob.value = row.mobile || row[2] || '';
            const addr = form?.querySelector('[data-customer-address]'); if (addr) addr.value = row.address || row[3] || '';
            const due = form?.querySelector('[data-previous-due]'); if (due) due.value = Number(row.due ?? row[1] ?? 0).toFixed(2);
            calcCollection();
          });
        });
      } else if (input.matches('[data-product-search]')) {
        ajaxRows('ajax_product_lookup.php?q=' + encodeURIComponent(q), dummy.products.filter(r => r.label.toLowerCase().includes(q.toLowerCase())), rows => {
          renderSuggest(input, rows, row => {
            input.value = row.name || '';
            const tr = input.closest('[data-item-row]');
            if (tr) {
              const id = tr.querySelector('[data-product-id]'); if (id) id.value = row.id || '';
              const grade = tr.querySelector('[data-grade]'); if (grade) grade.value = row.grade || row.code || grade.value || '';
              const kg = tr.querySelector('[data-kg-bag]'); if (kg && (row.kg_per_bag || row.bag)) kg.value = row.kg_per_bag || row.bag;
              const rate = tr.querySelector('[data-rate]'); if (rate && row.price) rate.value = row.price;
              calcRow(tr);
            }
          });
        });
      } else {
        renderSuggest(input, dummy.suppliers.filter(r => r.join(' ').toLowerCase().includes(q.toLowerCase())), row => {
          input.value = row[0];
          const bal = $('[data-supplier-balance]'); if (bal) bal.value = Number(row[1] || 0).toFixed(2);
        });
      }
    }, 180));
  }, true);

  function calcRow(row) {
    const bag = Number($('[data-bag]', row)?.value || 0);
    const kg = Number($('[data-kg-bag]', row)?.value || 0);
    const rate = Number($('[data-rate]', row)?.value || 0);
    const totalKg = bag * kg;
    const total = totalKg * rate;
    if ($('[data-total-kg]', row)) $('[data-total-kg]', row).value = totalKg.toFixed(2);
    if ($('[data-line-total]', row)) $('[data-line-total]', row).value = total.toFixed(2);
    calcTotals();
  }
  function calcTotals() {
    let sub = 0;
    $$('[data-line-total]').forEach(i => sub += Number(i.value || 0));
    const disc = Number($('[data-discount]')?.value || 0);
    const trans = Number($('[data-transport]')?.value || 0);
    const vat = Number($('[data-vat]')?.value || 0);
    const grand = sub - disc + trans + vat;
    $$('[data-subtotal]').forEach(i => i.value = sub.toFixed(2));
    $$('[data-grand]').forEach(i => i.value = grand.toFixed(2));
    $$('[data-grand-text]').forEach(i => i.textContent = grand.toFixed(2));
  }
  function calcCollection() {
    const due = Number($('[data-previous-due]')?.value || 0);
    const amount = Number($('[data-collection-amount]')?.value || 0);
    if ($('[data-remaining-due]')) $('[data-remaining-due]').value = (due - amount).toFixed(2);
  }
  function attachProduct(row) { calcRow(row); }
  document.addEventListener('input', e => {
    const row = e.target.closest('[data-item-row]');
    if (row && e.target.matches('input')) calcRow(row);
    if (e.target.matches('[data-discount],[data-transport],[data-vat]')) calcTotals();
    if (e.target.matches('[data-collection-amount]')) calcCollection();
  });
  $$('[data-item-row]').forEach(attachProduct);
  $$('[data-add-row]').forEach(btn => btn.addEventListener('click', () => {
    const tbody = $(btn.dataset.addRow);
    const tpl = $(btn.dataset.template);
    if (tbody && tpl) {
      const clone = tpl.content.firstElementChild.cloneNode(true);
      tbody.appendChild(clone);
      attachProduct(clone);
      toast('New row added');
    }
  }));
  document.addEventListener('click', e => {
    if (e.target.closest('[data-remove-row]')) { e.target.closest('tr')?.remove(); calcTotals(); }
    if (!e.target.closest('.ch-suggest,[data-customer-search],[data-product-search],[data-supplier-search]')) closeSuggest();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSuggest(); });

  function dynamic(select, cls) {
    const v = select.value.toLowerCase().replace(/\s+/g, '-');
    $$('.' + cls).forEach(x => x.classList.remove('active'));
    $(`.${cls}[data-type="${v}"]`)?.classList.add('active');
  }
  $$('[data-payment-type]').forEach(s => { s.onchange = () => dynamic(s, 'payment-dynamic'); dynamic(s, 'payment-dynamic'); });
  $$('[data-collection-type]').forEach(s => { s.onchange = () => dynamic(s, 'collection-dynamic'); dynamic(s, 'collection-dynamic'); });
  $$('form[data-demo]').forEach(f => f.onsubmit = e => { e.preventDefault(); loader(true); setTimeout(() => { loader(false); toast('Saved for approval successfully'); }, 650); });

  function chart(canvasId, vals, color) {
    const c = document.getElementById(canvasId); if (!c) return;
    const ctx = c.getContext('2d'), w = c.width = c.offsetWidth * 2, h = c.height = c.offsetHeight * 2;
    ctx.clearRect(0,0,w,h); ctx.strokeStyle = color; ctx.lineWidth = 5; ctx.beginPath();
    vals.forEach((v,i) => { const x = i * (w/(vals.length-1)), y = h - (v/Math.max(...vals))*h*.82 - 30; i ? ctx.lineTo(x,y) : ctx.moveTo(x,y); });
    ctx.stroke(); ctx.fillStyle = color + '22'; ctx.lineTo(w,h); ctx.lineTo(0,h); ctx.closePath(); ctx.fill();
  }
  chart('salesChart', [20,34,26,44,39,60,72], '#0F3D5E');
  chart('collectionChart', [10,25,18,35,30,48,55], '#00A88E');
});
