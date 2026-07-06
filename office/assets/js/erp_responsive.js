document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.querySelector('.sidebar');
  var toggle = document.querySelector('[data-sidebar-toggle]');
  if (sidebar && toggle) {
    var backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop no-print';
    document.body.appendChild(backdrop);
    function openSidebar(){sidebar.classList.add('is-open');backdrop.classList.add('is-open');document.body.classList.add('sidebar-open');}
    function closeSidebar(){sidebar.classList.remove('is-open');backdrop.classList.remove('is-open');document.body.classList.remove('sidebar-open');}
    toggle.addEventListener('click', function(){sidebar.classList.contains('is-open')?closeSidebar():openSidebar();});
    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function(e){if(e.key==='Escape')closeSidebar();});
    sidebar.querySelectorAll('nav a').forEach(function(a){a.addEventListener('click',function(){if(window.innerWidth<992)closeSidebar();});});
    window.addEventListener('resize', function(){if(window.innerWidth>=992)closeSidebar();});
  }

  var path = location.pathname.toLowerCase();
  if (!/sales_orders\.php|orders\.php|sales_invoices\.php|quotations\.php|collections\.php/.test(path)) return;
  var activeBox = null, timer = null;
  function esc(s){return String(s||'').replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
  function closeBox(){if(activeBox){activeBox.remove();activeBox=null;}}
  function typeOfInput(el){
    if(!el || !el.matches || !el.matches('input:not([type="hidden"]):not([readonly])')) return '';
    var s=((el.name||'')+' '+(el.id||'')+' '+(el.placeholder||'')).toLowerCase();
    if(s.indexOf('customer')>=0) return 'customer';
    if(s.indexOf('product')>=0) return 'product';
    var td=el.closest('td'), tr=td&&td.parentElement, table=el.closest('table');
    if(td&&tr&&table){var i=[].indexOf.call(tr.children,td), th=table.querySelectorAll('thead th')[i], h=(th&&th.textContent||'').toLowerCase(); if(h.indexOf('product')>=0)return'product';}
    return '';
  }
  function endpoint(t,q){return (t==='customer'?'ajax_customer_lookup.php?q=':'ajax_product_lookup.php?q=')+encodeURIComponent(q);}
  function placeBox(input){
    closeBox(); var wrap=input.parentElement; if(getComputedStyle(wrap).position==='static')wrap.style.position='relative';
    activeBox=document.createElement('div'); activeBox.className='erp-live-lookup-box';
    activeBox.style.cssText='position:absolute;left:0;top:100%;width:min(520px,92vw);max-height:310px;overflow:auto;background:white;border:1px solid #ced4da;border-radius:8px;box-shadow:0 12px 28px rgba(0,0,0,.18);z-index:9999';
    wrap.appendChild(activeBox); return activeBox;
  }
  function setNearbyValue(input,t,row){
    var scope=input.closest('tr')||input.closest('form')||document;
    var h=scope.querySelector(t==='customer'?'input[type="hidden"][name*="customer" i]':'input[type="hidden"][name*="product" i]');
    if(h){h.value=row.id||''; h.dispatchEvent(new Event('change',{bubbles:true}));}
    if(t==='product'){
      var tr=input.closest('tr'); if(tr){
        var price=tr.querySelector('input[name*="price" i],input[name*="rate" i]');
        if(price && row.price && (!price.value || Number(price.value)===0)){price.value=row.price;price.dispatchEvent(new Event('input',{bubbles:true}));}
        var unit=tr.querySelector('select[name*="unit" i]');
        if(unit && row.unit_name){[].some.call(unit.options,function(o){if((o.textContent||'').trim().toLowerCase()===String(row.unit_name).toLowerCase()){unit.value=o.value;unit.dispatchEvent(new Event('change',{bubbles:true}));return true;}return false;});}
      }
    }
  }
  function show(input,t,rows){
    var box=placeBox(input); if(!rows.length){closeBox();return;}
    rows.slice(0,12).forEach(function(row){var b=document.createElement('button');b.type='button';b.style.cssText='display:block;width:100%;border:0;border-bottom:1px solid #eee;background:white;text-align:left;padding:9px 12px;color:#0d6efd';b.innerHTML='<b>'+esc(row.name||row.label)+'</b><br><small>'+esc(row.label||row.name)+'</small>';b.onmousedown=function(e){e.preventDefault();};b.onclick=function(){input.value=row.name||row.label||'';setNearbyValue(input,t,row);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));closeBox();};box.appendChild(b);});
  }
  document.addEventListener('input',function(e){var input=e.target,t=typeOfInput(input); if(!t)return; clearTimeout(timer); timer=setTimeout(function(){var q=input.value.trim(); if(!q){closeBox();return;} fetch(endpoint(t,q),{credentials:'same-origin'}).then(function(r){return r.ok?r.json():[];}).then(function(rows){show(input,t,Array.isArray(rows)?rows:[]);}).catch(closeBox);},200);},true);
  document.addEventListener('click',function(e){if(activeBox && !activeBox.contains(e.target) && e.target!==document.activeElement) closeBox();});
});
