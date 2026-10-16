/* NURA boost (v1.60.1): Lipa Mdogo Mdogo calculator + plan review pop-up, TikTok tap-to-play, copy referral code. */
(function () {
	'use strict';
	var L = window.nuraBoost || {};
	function t(k, d) { return L[k] || d; }
	function ksh(n) { return 'KSh ' + Math.round(n).toLocaleString('en-KE'); }
	function fill(s, a) { return String(s).replace(/%(\d)\$[sd]/g, function (m, n) { var v = a[parseInt(n, 10) - 1]; return v === undefined ? '' : v; }); }
	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function day(d) { return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }); }
	function each(list, fn) { Array.prototype.forEach.call(list, fn); }

	/* Plan maths: deposit rounded up, equal weekly payments, last payment absorbs rounding. */
	function plan(price, depPct, weeks) {
		var dep = Math.ceil(price * depPct / 100), bal = Math.max(0, price - dep);
		var wk = Math.ceil(bal / weeks), last = bal - wk * (weeks - 1), rows = [], now = new Date();
		rows.push({ date: now, label: t('depLabel', 'Deposit - reserves your wig'), amt: dep, today: true });
		for (var i = 1; i <= weeks; i++) {
			var d = new Date(now.getTime()); d.setDate(d.getDate() + 7 * i);
			rows.push({ date: d, label: fill(t('instLabel', 'Instalment %1$s of %2$s'), [i, weeks]), amt: i === weeks ? last : wk });
		}
		return { price: price, dep: dep, bal: bal, wk: wk, last: last, weeks: weeks, rows: rows };
	}

	/* Lipa Mdogo Mdogo. */
	each(document.querySelectorAll('[data-nura-lmm]'), function (box) {
		var price = parseFloat(box.getAttribute('data-price')) || 0;
		var depPct = parseFloat(box.getAttribute('data-deposit')) || 30;
		var wa = box.getAttribute('data-wa') || '';
		var name = box.getAttribute('data-name') || '';
		var url = box.getAttribute('data-url') || '';
		var img = box.getAttribute('data-img') || '';
		var sku = box.getAttribute('data-sku') || '';
		var pid = box.getAttribute('data-pid') || '', vid = '';
		var lead = box.querySelector('[data-nura-lmm-lead]');
		var cta = box.querySelector('[data-nura-lmm-cta]');
		var form = document.querySelector('form.variations_form');
		var waOk = /^https:\/\/(wa\.me|api\.whatsapp\.com)\//.test(wa);
		var weekOpts = [];
		each(box.querySelectorAll('.nura-lmm__wk'), function (b) { weekOpts.push(parseInt(b.getAttribute('data-weeks'), 10) || 4); });
		if (!weekOpts.length) { weekOpts = [2, 3, 4]; }

		function current() {
			var v = form && form.querySelector('.woocommerce-variation-price ins .amount, .woocommerce-variation-price .amount');
			if (v) { var n = parseFloat(v.textContent.replace(/[^0-9.]/g, '')); if (n > 0) { return n; } }
			return price;
		}
		function weeksOn() {
			var on = box.querySelector('.nura-lmm__wk.is-on');
			return on ? parseInt(on.getAttribute('data-weeks'), 10) || 4 : weekOpts[weekOpts.length - 1];
		}
		function setWeeks(w) {
			each(box.querySelectorAll('.nura-lmm__wk'), function (x) {
				var on = parseInt(x.getAttribute('data-weeks'), 10) === w;
				x.classList.toggle('is-on', on); x.setAttribute('aria-checked', on ? 'true' : 'false');
			});
		}
		function options() {
			var out = [];
			if (!form) { return out; }
			each(form.querySelectorAll('select[name^="attribute_"]'), function (s) {
				if (!s.value) { return; }
				var lab = form.querySelector('label[for="' + s.id + '"]');
				var o = s.options[s.selectedIndex];
				out.push((lab ? lab.textContent.trim().replace(/:$/, '') : s.name.replace(/^attribute_(pa_)?/, '')) + ': ' + (o ? o.textContent.trim() : s.value));
			});
			return out;
		}
		function message(p, f) {
			var opts = options(), lines = [];
			lines.push('*' + t('msgTitle', 'NURA Beauty - Lipa Mdogo Mdogo plan request') + '*');
			if (f.ref) { lines.push('Ref: ' + f.ref); }
			lines.push('');
			lines.push('*Product:* ' + name);
			if (opts.length) { lines.push('*Options:* ' + opts.join(', ')); }
			if (sku) { lines.push('*SKU:* ' + sku); }
			lines.push('*Price:* ' + ksh(p.price), '');
			lines.push('*Plan:* ' + p.weeks + ' weeks, 0% interest, no fees');
			lines.push('*Deposit today:* ' + ksh(p.dep) + ' (' + depPct + '%)');
			lines.push('*Weekly:* ' + ksh(p.wk) + (p.last !== p.wk ? ' (final ' + ksh(p.last) + ')' : '') + ' x ' + p.weeks, '');
			lines.push('*Payment schedule*');
			p.rows.forEach(function (r, i) { lines.push((i + 1) + '. ' + day(r.date) + ' - ' + (r.today ? 'Deposit' : 'Week ' + i) + ' - ' + ksh(r.amt)); });
			lines.push('*Total payable:* ' + ksh(p.price), '');
			if (f.name) { lines.push('*Name:* ' + f.name); }
			if (f.phone) { lines.push('*Phone:* ' + f.phone); }
			if (f.where) { lines.push('*Delivery:* ' + f.where); }
			if (f.name || f.phone || f.where) { lines.push(''); }
			lines.push('I have read and accept the Lipa Mdogo Mdogo Terms & Conditions.');
			lines.push(url, '', t('msgAsk', 'Please confirm my plan and share the M-Pesa payment details for the deposit.'));
			return lines.join('\n');
		}
		function waLink(text) { return wa.split('?')[0] + '?text=' + encodeURIComponent(text); }
		function update() {
			var p = plan(current(), depPct, weeksOn());
			if (lead) { lead.textContent = fill(t('lead', 'Pay %1$s deposit, then %2$s a week for %3$s weeks.'), [ksh(p.dep), ksh(p.wk), p.weeks]); }
			if (cta && waOk) { cta.href = waLink(message(p, {})); }
		}
		each(box.querySelectorAll('.nura-lmm__wk'), function (b) {
			b.addEventListener('click', function () { setWeeks(parseInt(b.getAttribute('data-weeks'), 10)); update(); });
		});
		if (form) {
			form.addEventListener('change', function () { setTimeout(update, 80); });
			if (window.jQuery) {
				window.jQuery(form).on('found_variation', function (e, v) {
					if (v && v.sku) { sku = v.sku; }
					if (v && v.variation_id) { vid = String(v.variation_id); }
					if (v && v.image && (v.image.thumb_src || v.image.src)) { img = v.image.thumb_src || v.image.src; }
					setTimeout(update, 80);
				}).on('reset_data', function () { vid = ''; sku = box.getAttribute('data-sku') || ''; img = box.getAttribute('data-img') || ''; setTimeout(update, 80); });
			}
		}
		update();
		if (!cta || !waOk) { return; }

		/* Plan review pop-up. */
		var modal = null, lastFocus = null, fields = { name: '', phone: '', where: '', ref: '' };
		function makeRef() {
			var d = new Date(), c = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', r = '';
			for (var i = 0; i < 4; i++) { r += c.charAt(Math.floor(Math.random() * c.length)); }
			return 'LMM-' + String(d.getFullYear()).slice(2) + ('0' + (d.getMonth() + 1)).slice(-2) + ('0' + d.getDate()).slice(-2) + '-' + r;
		}
		function save() {
			if (!L.lmmAjax || !window.fetch || !window.FormData) { return; }
			var fd = new FormData();
			fd.append('action', 'nurax_lmm_request'); fd.append('ref', fields.ref); fd.append('product_id', pid); fd.append('variation_id', vid);
			fd.append('weeks', String(weeksOn())); fd.append('name', fields.name); fd.append('phone', fields.phone); fd.append('fulfil', fields.where);
			fd.append('options', options().join(', ')); fd.append('website', ''); fd.append('terms', '1');
			try { fetch(L.lmmAjax, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }).catch(function () {}); } catch (x) {}
		}
		function build() {
			modal = document.createElement('div');
			modal.className = 'nura-plan';
			modal.setAttribute('hidden', '');
			var wkBtns = weekOpts.map(function (w) { return '<button type="button" class="nura-plan__wk" role="radio" data-weeks="' + w + '">' + w + ' ' + esc(t('weeks', 'weeks')) + '</button>'; }).join('');
			var where = [t('w1', 'Pick up - Imenti House, Nairobi CBD'), t('w2', 'Delivery within Nairobi'), t('w3', 'Delivery elsewhere in Kenya'), t('w4', 'Delivery outside Kenya')];
			modal.innerHTML =
				'<div class="nura-plan__backdrop" data-plan-close></div>' +
				'<div class="nura-plan__panel" role="dialog" aria-modal="true" aria-labelledby="nura-plan-title" tabindex="-1">' +
				'<button type="button" class="nura-plan__x" data-plan-close aria-label="' + esc(t('close', 'Close')) + '">&times;</button>' +
				'<div class="nura-plan__body">' +
				'<header class="nura-plan__head"><span class="nura-plan__eyebrow">' + esc(t('eyebrow', 'Lipa Mdogo Mdogo')) + '</span>' +
				'<h2 id="nura-plan-title" class="nura-plan__title">' + esc(t('title', 'Your payment plan')) + '</h2>' +
				'<p class="nura-plan__sub">' + esc(t('sub', 'Review your plan below. When you are ready, send it to our team on WhatsApp and we will confirm it with you.')) + '</p></header>' +
				'<div class="nura-plan__product"><img class="nura-plan__img" alt="" data-p="img"><div class="nura-plan__pinfo"><strong data-p="name"></strong><span data-p="opts"></span><span class="nura-plan__sku" data-p="sku"></span></div><div class="nura-plan__price" data-p="price"></div></div>' +
				'<div class="nura-plan__label">' + esc(t('payOver', 'Pay over')) + '</div><div class="nura-plan__weeks" role="radiogroup" aria-label="' + esc(t('payOver', 'Pay over')) + '">' + wkBtns + '</div>' +
				'<dl class="nura-plan__sum">' +
				'<div><dt>' + esc(t('sDep', 'Deposit due today')) + '</dt><dd data-p="dep"></dd></div>' +
				'<div><dt>' + esc(t('sWk', 'Weekly payment')) + '</dt><dd data-p="wk"></dd></div>' +
				'<div><dt>' + esc(t('sFee', 'Interest and fees')) + '</dt><dd>KSh 0</dd></div>' +
				'<div class="is-total"><dt>' + esc(t('sTot', 'Total payable')) + '</dt><dd data-p="tot"></dd></div></dl>' +
				'<div class="nura-plan__label">' + esc(t('sched', 'Payment schedule')) + '</div>' +
				'<ol class="nura-plan__sched" data-p="sched"></ol>' +
				'<div class="nura-plan__fields"><label><span>' + esc(t('fName', 'Your name (optional)')) + '</span><input type="text" autocomplete="name" data-f="name" maxlength="60"></label>' +
				'<label><span>' + esc(t('fPhone', 'WhatsApp number (optional)')) + '</span><input type="tel" autocomplete="tel" inputmode="tel" data-f="phone" maxlength="20" placeholder="0712 345 678"></label>' +
				'<label><span>' + esc(t('fWhere', 'Collection or delivery (optional)')) + '</span><select data-f="where"><option value="">' + esc(t('choose', 'Choose...')) + '</option>' + where.map(function (w) { return '<option>' + esc(w) + '</option>'; }).join('') + '</select></label></div>' +
				'<ul class="nura-plan__terms">' +
				'<li>' + esc(t('t1', 'Your wig is reserved for you as soon as the deposit is received.')) + '</li>' +
				'<li>' + esc(t('t2', 'Pay weekly by M-Pesa. You can pay more, or settle early, at any time.')) + '</li>' +
				'<li>' + esc(t('t3', 'No interest and no hidden fees - the price shown is the price you pay.')) + '</li>' +
				'<li>' + esc(t('t4', 'Collect your wig or have it delivered once the plan is fully paid.')) + '</li>' +
				'<li>' + esc(t('t5', 'Payment details and plan terms are confirmed by our team on WhatsApp before you pay anything.')) + '</li></ul>' +
				'</div>' +
				'<label class="nura-plan__agree"><input type="checkbox" data-nura-agree> <span>' + esc(t('agree1', 'I have read and agree to the')) + ' <a href="' + esc(L.lmmTerms || '/lipa-mdogo-mdogo-terms/') + '" target="_blank" rel="noopener">' + esc(t('agree2', 'Lipa Mdogo Mdogo Terms & Conditions')) + '</a></span></label>' +
				'<footer class="nura-plan__foot"><a class="nura-plan__send" target="_blank" rel="noopener" data-p="send"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.3-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.5-.6-2.7-1.2-4.4-3.9-4.6-4.1-.1-.2-1.1-1.4-1.1-2.7s.7-1.9.9-2.2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.1.1.3 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1.9 2 1.2 2.3 1.4.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.1.1.6-.1 1.2Z"/></svg><span>' + esc(t('send', 'Send my plan on WhatsApp')) + '</span></a>' +
				'<button type="button" class="nura-plan__later" data-plan-close>' + esc(t('later', 'Not now')) + '</button>' +
				'<p class="nura-plan__hours">' + esc(fill(t('hours', 'Our team replies %1$s (East Africa Time).'), [t('hoursText', 'Mon-Sat 8:00-20:00')])) + '</p></footer>' +
				'</div>';
			document.body.appendChild(modal);
			each(modal.querySelectorAll('[data-plan-close]'), function (c) { c.addEventListener('click', close); });
			each(modal.querySelectorAll('.nura-plan__wk'), function (b) {
				b.addEventListener('click', function () { setWeeks(parseInt(b.getAttribute('data-weeks'), 10)); update(); render(); });
			});
			each(modal.querySelectorAll('[data-f]'), function (el) {
				el.addEventListener('input', function () { fields[el.getAttribute('data-f')] = el.value.trim(); render(); });
				el.addEventListener('change', function () { fields[el.getAttribute('data-f')] = el.value.trim(); render(); });
			});
			modal.querySelector('[data-p="send"]').addEventListener('click', function (e) {
				var ag = modal.querySelector('[data-nura-agree]');
				if (ag && !ag.checked) {
					e.preventDefault();
					var lb = ag.closest('.nura-plan__agree'); lb.classList.add('is-required'); ag.focus();
					setTimeout(function () { lb.classList.remove('is-required'); }, 1600);
					return;
				}
				save(); setTimeout(close, 300);
			});
			modal.querySelector('[data-nura-agree]').addEventListener('change', function () { modal.querySelector('[data-p="send"]').classList.toggle('is-locked', !this.checked); });
			modal.querySelector('[data-p="send"]').classList.add('is-locked');
			modal.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') { close(); return; }
				if (e.key !== 'Tab') { return; }
				var f = modal.querySelectorAll('button, a[href], input, select');
				if (!f.length) { return; }
				var first = f[0], last = f[f.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			});
		}
		function q(k) { return modal.querySelector('[data-p="' + k + '"]'); }
		function render() {
			var p = plan(current(), depPct, weeksOn()), opts = options();
			var im = q('img');
			if (img) { im.src = img; im.removeAttribute('hidden'); } else { im.setAttribute('hidden', ''); }
			q('name').textContent = name;
			q('opts').textContent = opts.join(' / ');
			q('sku').textContent = sku ? 'SKU ' + sku : '';
			q('price').textContent = ksh(p.price);
			q('dep').textContent = ksh(p.dep) + ' (' + depPct + '%)';
			q('wk').textContent = ksh(p.wk) + ' x ' + p.weeks;
			q('tot').textContent = ksh(p.price);
			q('sched').innerHTML = p.rows.map(function (r) {
				return '<li' + (r.today ? ' class="is-today"' : '') + '><span class="nura-plan__dt">' + esc(r.today ? t('today', 'Today') + ', ' + day(r.date) : day(r.date)) + '</span><span class="nura-plan__what">' + esc(r.label) + '</span><span class="nura-plan__amt">' + esc(ksh(r.amt)) + '</span></li>';
			}).join('');
			each(modal.querySelectorAll('.nura-plan__wk'), function (b) {
				var on = parseInt(b.getAttribute('data-weeks'), 10) === p.weeks;
				b.classList.toggle('is-on', on); b.setAttribute('aria-checked', on ? 'true' : 'false');
			});
			q('send').href = waLink(message(p, fields));
		}
		function open(e) {
			if (e) { e.preventDefault(); }
			if (!modal) { build(); }
			lastFocus = document.activeElement;
			fields.ref = makeRef();
			render();
			modal.removeAttribute('hidden');
			document.documentElement.classList.add('nura-plan-open');
			requestAnimationFrame(function () { modal.classList.add('is-open'); modal.querySelector('.nura-plan__panel').focus(); });
		}
		function close() {
			if (!modal || modal.hasAttribute('hidden')) { return; }
			modal.classList.remove('is-open');
			document.documentElement.classList.remove('nura-plan-open');
			setTimeout(function () { modal.setAttribute('hidden', ''); }, 220);
			if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
		}
		cta.addEventListener('click', open);
	});

	/* Booking page: grey out times that are fully booked (NURA Desk). */
	(function () {
		var date = document.getElementById('nb-date'), time = document.getElementById('nb-time');
		if (!date || !time || !L.slots || !window.fetch) { return; }
		date.addEventListener('change', function () {
			var v = date.value;
			Array.prototype.forEach.call(time.options, function (o) { if (o.value) { o.disabled = false; o.textContent = o.textContent.replace(/ - fully booked$/, ''); } });
			if (!v) { return; }
			fetch(L.slots + (L.slots.indexOf('?') > -1 ? '&' : '?') + 'date=' + encodeURIComponent(v), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
				if (!res || !res.full) { return; }
				Array.prototype.forEach.call(time.options, function (o) {
					if (o.value && res.full.indexOf(o.value) > -1) { o.disabled = true; o.textContent += ' - fully booked'; if (time.value === o.value) { time.value = ''; } }
				});
			}).catch(function () {});
		});
	})();

	/* TikTok: load the player only when tapped (keeps product pages fast). */
	each(document.querySelectorAll('[data-nura-tt]'), function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-nura-tt');
			if (!/^\d{8,25}$/.test(id)) { return; }
			var f = document.createElement('iframe');
			f.src = 'https://www.tiktok.com/embed/v2/' + id;
			f.title = btn.getAttribute('aria-label') || 'TikTok video';
			f.allow = 'encrypted-media; fullscreen; picture-in-picture';
			f.setAttribute('allowfullscreen', '');
			f.className = 'nura-tt__frame';
			btn.parentNode.replaceChild(f, btn);
		});
	});

	/* Copy referral code. */
	each(document.querySelectorAll('[data-nura-copy-btn]'), function (b) {
		b.addEventListener('click', function () {
			var el = b.parentNode.querySelector('[data-nura-copy]');
			if (!el || !navigator.clipboard) { return; }
			navigator.clipboard.writeText(el.textContent.trim()).then(function () {
				var x = b.textContent; b.textContent = t('copied', 'Copied');
				setTimeout(function () { b.textContent = x; }, 1600);
			});
		});
	});
})();
