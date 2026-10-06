/*!
 * NURA WhatsApp Order pop-up (NURA Experience 1.43.0).
 * Ported from the Tabarak Electronics order funnel, restyled for NURA.
 * Every trigger is a real wa.me link, so ordering still works without JS.
 */
(function () {
  'use strict';

  var C = window.NURAX_WO || {};
  var d = document;
  var modal = d.querySelector('[data-nwo-modal]');
  if (!modal) { return; }

  var panel = modal.querySelector('.nwo__panel');
  var form = modal.querySelector('[data-nwo-form]');
  var sumEl = modal.querySelector('[data-nwo-sum]');
  var optsEl = modal.querySelector('[data-nwo-opts]');
  var qtyRow = modal.querySelector('[data-nwo-qtyrow]');
  var qtyIn = form.elements.qty;
  var totalEl = modal.querySelector('[data-nwo-total]');
  var doneEl = modal.querySelector('[data-nwo-done]');
  var deliveryEl = modal.querySelector('[data-nwo-delivery]');
  var pickupEl = modal.querySelector('[data-nwo-pickup]');
  var formErr = modal.querySelector('[data-nwo-formerr]');
  var steps = modal.querySelectorAll('[data-nwo-step]');
  var STORE = C.store || 'nura_wa_order_contact';
  var SEP = '--------------------';
  var T0 = Date.now();
  var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

  var state = null; // { mode, product, option, price, img, items, total, vdata }
  var lastTrigger = null;
  var sending = false;

  /* ---------- helpers ---------- */
  function money(n) {
    var v = Math.round(Number(n) || 0);
    return 'KSh ' + String(v).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function clean(s) { return String(s == null ? '' : s).replace(/\s+/g, ' ').trim(); }
  function val(name) {
    var el = form.elements[name];
    if (!el) { return ''; }
    if (el.length && !el.tagName) { // RadioNodeList
      for (var i = 0; i < el.length; i++) { if (el[i].checked) { return el[i].value; } }
      return '';
    }
    return clean(el.value);
  }
  function setRadio(name, v) {
    var el = form.elements[name];
    if (!el || !el.length || !v) { return; }
    for (var i = 0; i < el.length; i++) { el[i].checked = (el[i].value === v); }
  }
  function normPhone(raw) {
    var p = String(raw || '').replace(/\D/g, '');
    if (p.length === 10 && p.charAt(0) === '0') { p = '254' + p.slice(1); }
    else if (p.length === 9 && (p.charAt(0) === '7' || p.charAt(0) === '1')) { p = '254' + p; }
    return /^254[17]\d{8}$/.test(p) ? p : '';
  }
  function makeRef() {
    var t = new Date();
    var yy = String(t.getFullYear()).slice(2);
    var mm = ('0' + (t.getMonth() + 1)).slice(-2);
    var dd = ('0' + t.getDate()).slice(-2);
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', r = '';
    for (var i = 0; i < 4; i++) { r += chars.charAt(Math.floor(Math.random() * chars.length)); }
    return 'NURA-' + yy + mm + dd + '-' + r;
  }
  function parseData(el) {
    try { return JSON.parse(el.getAttribute('data-nwo') || '{}') || {}; } catch (e) { return {}; }
  }
  function qty() {
    var q = parseInt(qtyIn.value, 10);
    if (isNaN(q) || q < 1) { q = 1; }
    if (q > 20) { q = 20; }
    return q;
  }

  /* ---------- remember details ---------- */
  function remember() {
    try {
      localStorage.setItem(STORE, JSON.stringify({
        name: val('name'), phone: val('phone'), location: val('location'),
        area: val('area'), fulfil: val('fulfil'), payment: val('payment')
      }));
    } catch (e) {}
  }
  function prefill() {
    var s = null;
    try { s = JSON.parse(localStorage.getItem(STORE) || 'null'); } catch (e) {}
    if (!s) { return; }
    ['name', 'phone', 'location', 'area'].forEach(function (k) {
      var el = form.elements[k];
      if (el && !el.value && s[k]) { el.value = s[k]; }
    });
    setRadio('fulfil', s.fulfil);
    setRadio('payment', s.payment);
  }

  /* ---------- inline errors ---------- */
  function setErr(el, msg) {
    if (!el) { return; }
    var box = d.getElementById('nwo-err-' + el.name);
    if (box) { box.textContent = msg || ''; }
    if (msg) { el.setAttribute('aria-invalid', 'true'); } else { el.removeAttribute('aria-invalid'); }
    var wrap = el.closest('.nwo-f');
    if (wrap) { wrap.classList.toggle('is-error', !!msg); }
  }
  function validate() {
    var ok = true, first = null;
    var n = form.elements.name, p = form.elements.phone, l = form.elements.location;
    if (clean(n.value).length < 2) { setErr(n, 'Please enter your full name.'); ok = false; first = first || n; } else { setErr(n, ''); }
    if (!normPhone(p.value)) { setErr(p, 'Enter a valid Kenyan number, e.g. 0712 345 678 or 0110 345 678.'); ok = false; first = first || p; } else { setErr(p, ''); }
    if (val('fulfil') !== 'pickup' && !l.value) { setErr(l, 'Choose your county or town.'); ok = false; first = first || l; } else { setErr(l, ''); }
    var sels = optsEl.querySelectorAll('select');
    for (var i = 0; i < sels.length; i++) {
      var s = sels[i], box = s.parentNode.querySelector('.nwo-err');
      if (!s.value) {
        if (box) { box.textContent = 'Please choose ' + s.getAttribute('data-label').toLowerCase() + '.'; }
        s.setAttribute('aria-invalid', 'true'); s.parentNode.classList.add('is-error');
        ok = false; first = first || s;
      } else {
        if (box) { box.textContent = ''; }
        s.removeAttribute('aria-invalid'); s.parentNode.classList.remove('is-error');
      }
    }
    if (first) { first.focus(); }
    return ok;
  }

  /* ---------- summary + totals ---------- */
  function renderSummary() {
    if (state.mode === 'cart') {
      var rows = state.items.map(function (it, i) {
        return '<li><span class="nwo-sum__n">' + (i + 1) + '.</span><span class="nwo-sum__item"><strong>' + esc(it.name) + '</strong>' +
          (it.option ? '<em>' + esc(it.option) + '</em>' : '') + '<small>' + esc(it.qty) + ' x ' + esc(money(it.price)) + '</small></span>' +
          '<span class="nwo-sum__line">' + esc(money(it.line)) + '</span></li>';
      }).join('');
      sumEl.innerHTML = '<ul class="nwo-sum__list">' + rows + '</ul><div class="nwo-sum__total"><span>Cart total</span><strong>' + esc(money(state.total)) + '</strong></div>';
      qtyRow.hidden = true;
      return;
    }
    var p = state.product;
    var priceTxt = state.price > 0 ? ((p.variable && !state.option) ? 'From ' + money(state.price) : money(state.price)) : '';
    sumEl.innerHTML = '<div class="nwo-sum__prod">' +
      (state.img ? '<img class="nwo-sum__img" src="' + esc(state.img) + '" alt="" width="72" height="72" loading="lazy">' : '') +
      '<div class="nwo-sum__info"><p class="nwo-sum__name">' + esc(p.name) + '</p>' +
      (state.option ? '<p class="nwo-sum__opt">' + esc(state.option) + '</p>' : '') +
      (priceTxt ? '<p class="nwo-sum__price">' + esc(priceTxt) + '</p>' : '') + '</div></div>';
    qtyRow.hidden = false;
    updateTotal();
  }
  function updateTotal() {
    if (!state || state.mode === 'cart') { return; }
    var t = state.price * qty();
    totalEl.textContent = state.price > 0 ? ((state.product.variable && !state.option) ? 'From ' + money(t) : money(t)) : 'To be confirmed';
  }

  /* ---------- variation helpers ---------- */
  function matchVariation(vars, chosen) {
    for (var i = 0; i < vars.length; i++) {
      var a = vars[i].attributes || vars[i].a || {}, ok = true;
      for (var k in chosen) {
        if (Object.prototype.hasOwnProperty.call(chosen, k)) {
          if (a[k] !== undefined && a[k] !== '' && a[k] !== chosen[k]) { ok = false; break; }
        }
      }
      if (ok) { return vars[i]; }
    }
    return null;
  }

  // Product page: read the chosen variation from the WooCommerce variations form.
  function readPdpVariation(btn, p) {
    var scope = btn.closest('.nura-qv-info') || btn.closest('.summary') || btn.closest('.product') || d;
    var vform = btn.closest('form.variations_form') || scope.querySelector('form.variations_form');
    if (!vform) { return { ok: true }; }
    var sels = vform.querySelectorAll('.variations select');
    var chosen = {}, texts = [], missing = [];
    [].forEach.call(sels, function (sel) {
      var row = sel.closest('tr') || sel.parentNode;
      var lab = row ? row.querySelector('th label, td.label label, label') : null;
      var name = lab ? clean(lab.textContent).replace(/[:*]+.*$/, '').trim() : 'option';
      var opt = sel.options[sel.selectedIndex];
      if (sel.value && opt) { chosen[sel.name] = sel.value; texts.push(clean(opt.text)); }
      else { missing.push(name.toLowerCase()); }
    });
    if (missing.length) { return { ok: false, missing: missing, form: vform }; }
    var price = 0, img = '', vid = 0, v = null;
    var raw = vform.getAttribute('data-product_variations');
    if (raw && raw !== 'false') { try { v = matchVariation(JSON.parse(raw) || [], chosen); } catch (e) {} }
    if (!v && vform._nwoVar) { v = vform._nwoVar; }
    if (v) {
      price = Number(v.display_price) || 0;
      vid = v.variation_id || 0;
      img = (v.image && (v.image.thumb_src || v.image.src)) || '';
    }
    if (!price) {
      var pe = vform.querySelector('.woocommerce-variation-price .price ins .amount, .woocommerce-variation-price .price .amount');
      if (pe) { price = Number(clean(pe.textContent).replace(/[^0-9.]/g, '')) || 0; }
    }
    var q = vform.querySelector('input.qty');
    return { ok: true, option: texts.join(' / '), price: price || p.price, img: img, vid: vid, qty: q ? parseInt(q.value, 10) || 1 : 1 };
  }

  // Product card: fetch options so the shopper can choose inside the pop-up.
  function loadOptions(p) {
    optsEl.innerHTML = '<p class="nwo-opts__loading">Loading options&hellip;</p>';
    optsEl.hidden = false;
    var url = (C.ajax || '/wp-admin/admin-ajax.php') + '?action=nurax_wa_product&id=' + encodeURIComponent(p.id);
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res || !res.success || !res.data || !res.data.attrs || !res.data.attrs.length) { optsEl.innerHTML = ''; optsEl.hidden = true; return; }
      state.vdata = res.data;
      optsEl.innerHTML = res.data.attrs.map(function (a, i) {
        var id = 'nwo-opt-' + i;
        return '<div class="nwo-f"><label for="' + id + '">' + esc(a.label) + ' <span class="nwo-req" aria-hidden="true">*</span></label>' +
          '<select id="' + id + '" data-key="' + esc(a.key) + '" data-label="' + esc(a.label) + '"><option value="">Choose ' + esc(a.label.toLowerCase()) + '</option>' +
          a.options.map(function (o) { return '<option value="' + esc(o.v) + '">' + esc(o.t) + '</option>'; }).join('') +
          '</select><p class="nwo-err"></p></div>';
      }).join('');
    }).catch(function () { optsEl.innerHTML = ''; optsEl.hidden = true; });
  }
  function onOptionChange() {
    if (!state || !state.vdata) { return; }
    var sels = optsEl.querySelectorAll('select'), chosen = {}, texts = [], all = true;
    [].forEach.call(sels, function (s) {
      if (s.value) { chosen[s.getAttribute('data-key')] = s.value; texts.push(clean(s.options[s.selectedIndex].text)); } else { all = false; }
    });
    if (all) {
      var v = matchVariation(state.vdata.vars || [], chosen);
      state.option = texts.join(' / ');
      state.vid = v ? v.id : 0;
      if (v && v.p) { state.price = v.p; }
      if (v && v.img) { state.img = v.img; }
      state.stockNote = (v && !v.stock) ? 'This option may be out of stock - we will confirm on WhatsApp.' : '';
    } else {
      state.option = '';
      state.price = state.product.price;
      state.img = state.product.img;
    }
    renderSummary();
  }

  /* ---------- message ---------- */
  function buildMessage(ref) {
    var L = ['*NEW ORDER - ' + (C.brand || 'NURA Beauty') + '*', 'Ref: ' + ref, SEP];
    if (state.mode === 'cart') {
      L.push('*Items:*');
      state.items.forEach(function (it, i) {
        L.push((i + 1) + '. ' + it.name + (it.option ? ' - ' + it.option : '') + ' x ' + it.qty + ' = ' + money(it.line));
      });
      L.push('*Total:* ' + money(state.total));
    } else {
      var q = qty(), from = (state.product.variable && !state.option) ? 'From ' : '';
      L.push('*Product:* ' + state.product.name);
      if (state.option) { L.push('*Option:* ' + state.option); }
      if (state.price > 0) { L.push('*Price:* ' + from + money(state.price)); }
      L.push('*Qty:* ' + q);
      if (state.price > 0) { L.push('*Total:* ' + from + money(state.price * q)); }
    }
    L.push(SEP);
    L.push('*Customer:* ' + val('name'));
    L.push('*Phone:* +' + normPhone(val('phone')));
    if (val('fulfil') === 'pickup') {
      L.push('*Delivery:* ' + (C.pickup || 'Pick-up at NURA studio'));
    } else {
      var loc = val('location'), area = val('area');
      L.push('*Delivery:* ' + loc + (area ? ', ' + area : ''));
    }
    if (val('payment')) { L.push('*Payment:* ' + val('payment')); }
    if (val('note')) { L.push('*Note:* ' + val('note')); }
    L.push(SEP);
    if (state.mode !== 'cart' && state.product.url) { L.push(state.product.url); }
    L.push('Please confirm availability and delivery cost. Thank you.');
    return L.join('\n');
  }
  function waUrl(text) { return 'https://wa.me/' + (C.wa || '') + '?text=' + encodeURIComponent(text); }

  function orderItems() {
    if (state.mode === 'cart') {
      return state.items.map(function (it) { return { id: it.id, name: it.name, option: it.option, qty: it.qty, price: it.price }; });
    }
    return [{ id: state.vid || state.product.id, name: state.product.name, option: state.option || '', qty: qty(), price: state.price || 0 }];
  }
  function orderTotal() {
    return state.mode === 'cart' ? state.total : (state.price || 0) * qty();
  }

  function saveLead(ref) {
    var fd = new FormData();
    fd.append('action', 'nurax_wa_order');
    fd.append('ref', ref);
    fd.append('tt', String(Date.now() - T0));
    fd.append('source', state.mode);
    fd.append('page', location.href);
    fd.append('items', JSON.stringify(orderItems()));
    ['name', 'phone', 'location', 'area', 'fulfil', 'payment', 'note', 'website'].forEach(function (k) { fd.append(k, val(k)); });
    var url = C.ajax || '/wp-admin/admin-ajax.php';
    try {
      if (window.fetch) { fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }).catch(function () {}); }
      else if (navigator.sendBeacon) { navigator.sendBeacon(url, fd); }
    } catch (e) {
      try { if (navigator.sendBeacon) { navigator.sendBeacon(url, fd); } } catch (x) {}
    }
  }

  function track() {
    var value = Math.round(orderTotal());
    var cur = C.currency || 'KES';
    if (typeof window.gtag === 'function') {
      try { window.gtag('event', 'generate_lead', { method: 'whatsapp', currency: cur, value: value, source: state.mode }); } catch (e) {}
    }
    if (typeof window.fbq === 'function') {
      try { window.fbq('track', 'Lead', { currency: cur, value: value, content_name: state.mode === 'cart' ? 'Cart order' : state.product.name }); } catch (e) {}
    }
  }

  /* ---------- open / close ---------- */
  function setStep(i) {
    [].forEach.call(steps, function (s) {
      var n = parseInt(s.getAttribute('data-nwo-step'), 10);
      s.classList.toggle('is-active', n === i);
      s.classList.toggle('is-done', n < i);
      if (n === i) { s.setAttribute('aria-current', 'step'); } else { s.removeAttribute('aria-current'); }
    });
  }
  function syncFulfil() {
    var pick = val('fulfil') === 'pickup';
    deliveryEl.hidden = pick;
    pickupEl.hidden = !pick;
    if (pick) { setErr(form.elements.location, ''); }
  }
  function focusables() {
    return [].filter.call(panel.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (el) {
      return !el.disabled && el.tabIndex !== -1 && el.offsetParent !== null && !el.closest('[hidden]') && !el.closest('.nwo-hp');
    });
  }
  function open(trigger) {
    lastTrigger = trigger;
    form.hidden = false;
    doneEl.hidden = true;
    formErr.hidden = true;
    sending = false;
    setStep(0);
    prefill();
    syncFulfil();
    renderSummary();
    modal.hidden = false;
    d.documentElement.classList.add('nwo-lock');
    // Force reflow so the open transition runs.
    void modal.offsetWidth;
    modal.classList.add('is-open');
    var first = form.elements.name.value ? (form.elements.phone.value ? form.querySelector('.nwo-submit') : form.elements.phone) : form.elements.name;
    setTimeout(function () { if (optsEl.querySelector('select') && !optsEl.hidden) { optsEl.querySelector('select').focus(); } else if (first) { first.focus(); } }, 60);
  }
  function close() {
    if (modal.hidden) { return; }
    modal.classList.remove('is-open');
    d.documentElement.classList.remove('nwo-lock');
    setTimeout(function () { modal.hidden = true; }, 180);
    if (lastTrigger && lastTrigger.focus) { try { lastTrigger.focus(); } catch (e) {} }
  }

  function hint(btn, msg) {
    var wrap = btn.closest('.nwo-pdp');
    var h = wrap ? wrap.querySelector('[data-nwo-hint]') : null;
    if (h) { h.textContent = msg; h.hidden = !msg; }
  }

  /* ---------- triggers ---------- */
  d.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-nwo-open]') : null;
    if (!btn) { return; }
    var mode = btn.getAttribute('data-nwo-open');
    var data = parseData(btn);
    optsEl.innerHTML = '';
    optsEl.hidden = true;

    if (mode === 'cart') {
      if (!data.items || !data.items.length) { return; } // fall back to the plain wa.me link
      e.preventDefault();
      state = { mode: 'cart', items: data.items, total: Number(data.total) || 0 };
      open(btn);
      return;
    }
    if (!data.id) { return; }
    e.preventDefault();
    state = { mode: mode === 'card' ? 'card' : 'product', product: data, option: '', price: Number(data.price) || 0, img: data.img || '', vid: 0 };

    if (mode === 'product' && data.variable) {
      var r = readPdpVariation(btn, data);
      if (!r.ok) {
        var msg = 'Please choose your ' + r.missing.join(' and ') + ' first.';
        hint(btn, msg);
        var tbl = r.form.querySelector('.variations');
        if (tbl) {
          tbl.classList.add('nwo-attn');
          setTimeout(function () { tbl.classList.remove('nwo-attn'); }, 1600);
          try { tbl.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (x) { tbl.scrollIntoView(); }
          var firstSel = tbl.querySelector('select');
          if (firstSel) { setTimeout(function () { try { firstSel.focus({ preventScroll: true }); } catch (y) {} }, 350); }
        }
        return;
      }
      hint(btn, '');
      state.option = r.option || '';
      state.price = Number(r.price) || state.price;
      state.vid = r.vid || 0;
      if (r.img) { state.img = r.img; }
      qtyIn.value = Math.min(20, Math.max(1, r.qty || 1));
    } else if (mode === 'product') {
      var scope = btn.closest('form.cart');
      var q = scope ? scope.querySelector('input.qty') : null;
      qtyIn.value = q ? Math.min(20, Math.max(1, parseInt(q.value, 10) || 1)) : 1;
    } else {
      qtyIn.value = 1;
      if (data.variable) { loadOptions(data); }
    }
    open(btn);
  });

  // Remember the variation WooCommerce found (used when the form has no inline variation data).
  if (window.jQuery) {
    window.jQuery(d.body).on('found_variation', 'form.variations_form', function (ev, v) { this._nwoVar = v; });
    window.jQuery(d.body).on('reset_data', 'form.variations_form', function () { this._nwoVar = null; });
    window.jQuery(d.body).on('found_variation', 'form.variations_form', function () {
      var h = this.querySelector('[data-nwo-hint]'); if (h) { h.hidden = true; h.textContent = ''; }
    });
  }

  /* ---------- modal events ---------- */
  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-nwo-close]')) { e.preventDefault(); close(); return; }
    var qb = e.target.closest('[data-nwo-qty]');
    if (qb) {
      e.preventDefault();
      qtyIn.value = Math.min(20, Math.max(1, qty() + parseInt(qb.getAttribute('data-nwo-qty'), 10)));
      updateTotal();
    }
  });
  qtyIn.addEventListener('input', updateTotal);
  qtyIn.addEventListener('change', function () { qtyIn.value = qty(); updateTotal(); });
  optsEl.addEventListener('change', onOptionChange);
  form.addEventListener('change', function (e) {
    if (e.target.name === 'fulfil') { syncFulfil(); }
    if (e.target.name === 'location' && e.target.value) { setErr(e.target, ''); }
  });
  form.addEventListener('input', function (e) {
    var t = e.target;
    if (t.getAttribute('aria-invalid') === 'true') {
      if (t.name === 'phone' && normPhone(t.value)) { setErr(t, ''); }
      if (t.name === 'name' && clean(t.value).length >= 2) { setErr(t, ''); }
    }
  });

  d.addEventListener('keydown', function (e) {
    if (modal.hidden) { return; }
    if (e.key === 'Escape' || e.key === 'Esc') { e.preventDefault(); close(); return; }
    if (e.key === 'Tab') {
      var f = focusables();
      if (!f.length) { return; }
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && (d.activeElement === first || !panel.contains(d.activeElement))) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && (d.activeElement === last || !panel.contains(d.activeElement))) { e.preventDefault(); first.focus(); }
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending || !state) { return; }
    formErr.hidden = true;
    if (!validate()) { return; }
    if (!C.wa) { formErr.textContent = 'WhatsApp ordering is not set up yet. Please call us.'; formErr.hidden = false; return; }
    sending = true;
    var ref = makeRef();
    var url = waUrl(buildMessage(ref));
    remember();
    saveLead(ref);
    track();

    // Success screen first, then open WhatsApp (inside the same tap so pop-up blockers allow it).
    form.hidden = true;
    doneEl.hidden = false;
    doneEl.querySelector('[data-nwo-ref]').textContent = ref;
    doneEl.querySelector('[data-nwo-again]').href = url;
    setStep(1);
    var title = doneEl.querySelector('.nwo-done__title');
    if (title) { title.focus(); }

    if (isMobile) {
      setTimeout(function () { window.location.href = url; }, 250);
    } else {
      var w = null;
      try { w = window.open(url, '_blank', 'noopener'); } catch (x) { w = null; }
      if (!w) { setTimeout(function () { window.location.href = url; }, 250); }
    }
    sending = false;
  });
})();
