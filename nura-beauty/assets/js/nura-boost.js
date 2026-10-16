/* NURA boost (v1.60.0): Lipa Mdogo Mdogo calculator, TikTok tap-to-play, copy referral code. */
(function () {
	'use strict';
	var L = window.nuraBoost || {};
	function ksh(n) { return 'KSh ' + Math.ceil(n).toLocaleString('en-KE'); }
	function fill(s, a) { return String(s).replace(/%(\d)\$[sd]/g, function (m, n) { var v = a[parseInt(n, 10) - 1]; return v === undefined ? '' : v; }); }

	/* Lipa Mdogo Mdogo. */
	Array.prototype.forEach.call(document.querySelectorAll('[data-nura-lmm]'), function (box) {
		var price = parseFloat(box.getAttribute('data-price')) || 0;
		var depPct = parseFloat(box.getAttribute('data-deposit')) || 30;
		var wa = box.getAttribute('data-wa') || '';
		var name = box.getAttribute('data-name') || '';
		var url = box.getAttribute('data-url') || '';
		var lead = box.querySelector('[data-nura-lmm-lead]');
		var cta = box.querySelector('[data-nura-lmm-cta]');
		var form = document.querySelector('form.variations_form');
		function current() {
			var v = form && form.querySelector('.woocommerce-variation-price ins .amount, .woocommerce-variation-price .amount');
			if (v) { var n = parseFloat(v.textContent.replace(/[^0-9.]/g, '')); if (n > 0) { return n; } }
			return price;
		}
		function update() {
			var on = box.querySelector('.nura-lmm__wk.is-on');
			var weeks = on ? parseInt(on.getAttribute('data-weeks'), 10) || 4 : 4;
			var p = current(), dep = Math.ceil(p * depPct / 100), wk = Math.ceil((p - dep) / weeks);
			if (lead) { lead.textContent = fill(L.lead || 'Pay %1$s deposit, then %2$s a week for %3$s weeks.', [ksh(dep), ksh(wk), weeks]); }
			if (cta && /^https:\/\/(wa\.me|api\.whatsapp\.com)\//.test(wa)) {
				var msg = fill(L.waMsg || 'Hi NURA, I would like to buy %1$s on Lipa Mdogo Mdogo: %2$s deposit, then %3$s a week for %4$s weeks. %5$s', [name, ksh(dep), ksh(wk), weeks, url]);
				cta.href = wa.split('?')[0] + '?text=' + encodeURIComponent(msg);
			}
		}
		Array.prototype.forEach.call(box.querySelectorAll('.nura-lmm__wk'), function (b) {
			b.addEventListener('click', function () {
				Array.prototype.forEach.call(box.querySelectorAll('.nura-lmm__wk'), function (x) { x.classList.remove('is-on'); x.setAttribute('aria-checked', 'false'); });
				b.classList.add('is-on'); b.setAttribute('aria-checked', 'true'); update();
			});
		});
		if (form) {
			form.addEventListener('change', function () { setTimeout(update, 80); });
			if (window.jQuery) { window.jQuery(form).on('found_variation reset_data', function () { setTimeout(update, 80); }); }
		}
		update();
	});

	/* TikTok: load the player only when tapped (keeps product pages fast). */
	Array.prototype.forEach.call(document.querySelectorAll('[data-nura-tt]'), function (btn) {
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
	Array.prototype.forEach.call(document.querySelectorAll('[data-nura-copy-btn]'), function (b) {
		b.addEventListener('click', function () {
			var el = b.parentNode.querySelector('[data-nura-copy]');
			if (!el || !navigator.clipboard) { return; }
			navigator.clipboard.writeText(el.textContent.trim()).then(function () {
				var t = b.textContent; b.textContent = L.copied || 'Copied';
				setTimeout(function () { b.textContent = t; }, 1600);
			});
		});
	});
})();
