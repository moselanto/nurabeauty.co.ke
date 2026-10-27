/* NURA "Join The House" - slim slide-in bar, pop-up on request + footer form (NURA Experience 1.79.2: closing it hides everything for 24 hours). */
(function () {
'use strict';
function nuraHouseInit() {
	var C = window.NURAX_SUB || {};
	var d = document, KEY = 'nura_house', email = '';

	function getC(n) { var m = d.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; }
	function setC(n, v, days) { var t = new Date(); t.setTime(t.getTime() + days * 864e5); d.cookie = n + '=' + encodeURIComponent(v) + ';expires=' + t.toUTCString() + ';path=/;SameSite=Lax'; try { localStorage.setItem(n, v + '|' + t.getTime()); } catch (e) {} }
	var SNOOZE = 'nura_house_snooze';
	function snoozed() {
		var until = Number(getC(SNOOZE)) || 0;
		try { until = Math.max(until, Number(localStorage.getItem(SNOOZE)) || 0, Number(sessionStorage.getItem(SNOOZE)) || 0); } catch (e) {}
		return until > Date.now();
	}
	function snooze() {
		var h = Math.max(1, Number(C.hours) || 24), until = Date.now() + h * 36e5;
		d.cookie = SNOOZE + '=' + until + ';max-age=' + Math.round(h * 3600) + ';path=/;SameSite=Lax';
		try { localStorage.setItem(SNOOZE, String(until)); sessionStorage.setItem(SNOOZE, String(until)); } catch (e) {}
	}
	function state() {
		var c = getC(KEY); if (c === 'sub') { return c; }
		try { var l = (localStorage.getItem(KEY) || '').split('|'); if (l[0] === 'sub' && Number(l[1]) > Date.now()) { return 'sub'; } } catch (e) {}
		return snoozed() ? 'x' : '';
	}
	function track(where) {
		try { if (window.gtag) { window.gtag('event', 'sign_up', { method: 'newsletter_' + where }); } } catch (e) {}
		try { if (window.fbq) { window.fbq('track', 'Lead', { content_name: 'Join The House' }); } } catch (e) {}
		try { if (window.ttq) { window.ttq.track('Subscribe'); } } catch (e) {}
	}
	function post(data) {
		var fd = new FormData(); fd.append('action', 'nurax_subscribe');
		Object.keys(data).forEach(function (k) { if (Array.isArray(data[k])) { data[k].forEach(function (v) { fd.append(k + '[]', v); }); } else { fd.append(k, data[k]); } });
		return fetch(C.ajax || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }

	/* ---------- Footer "Join The House" form ---------- */
	Array.prototype.forEach.call(d.querySelectorAll('.nura-subscribe-form'), function (f) {
		f.addEventListener('submit', function (e) {
			var inp = f.querySelector('input[type=email]'), v = inp ? inp.value.trim() : '';
			if (!window.fetch || !window.FormData) { return; }
			e.preventDefault();
			if (!validEmail(v)) { inp.focus(); inp.setCustomValidity('Please enter a valid e-mail address.'); inp.reportValidity(); inp.setCustomValidity(''); return; }
			var btn = f.querySelector('button'); if (btn) { btn.disabled = true; btn.textContent = '...'; }
			var hp = f.querySelector('[name=nura_hp]');
			post({ email: v, website: hp ? hp.value : '' }).then(function (r) {
				var ok = r && r.success, msg = d.createElement('p');
				msg.className = 'nhs-foot-msg' + (ok ? '' : ' is-err');
				msg.textContent = ok ? ((r.data && r.data.code) ? 'Welcome to the house! Your code: ' + r.data.code + ' (also sent to your inbox).' : 'Welcome to the house! Check your inbox.') : ((r && r.data && r.data.message) || 'Please try again.');
				if (ok) { f.replaceWith(msg); setC(KEY, 'sub', 365); track('footer'); hideAll(); } else { f.appendChild(msg); if (btn) { btn.disabled = false; btn.textContent = 'Subscribe'; } }
			}).catch(function () { f.submit(); });
		});
	});

	/* ---------- Pop-up ---------- */
	var box = d.querySelector('[data-nhs]'), tab = d.querySelector('[data-nhs-tab]'), bar = d.querySelector('[data-nhs-bar]');
	function hideAll() { if (box) { box.hidden = true; } if (tab) { tab.hidden = true; } if (bar) { bar.hidden = true; d.documentElement.classList.remove('nhs-bar-on'); } }
	if (!box || !C.popup) { return; }
	var card = box.querySelector('.nhs__card'), opened = false, lastFocus = null;

	function step(n) { Array.prototype.forEach.call(box.querySelectorAll('[data-nhs-step]'), function (s) { s.classList.toggle('is-on', s.getAttribute('data-nhs-step') === String(n)); }); }
	function busy() { return !!d.querySelector('.nwo.is-open, .nura-plan.is-open') || d.documentElement.classList.contains('nwo-lock') || d.documentElement.classList.contains('nura-plan-open'); }
	function open(force) {
		if (opened && !force) { return; }
		if (C.debug && window.console) { console.log('[NURA House] open', { force: !!force, state: state(), busy: busy() }); }
		if (!force && state()) { return; }
		if (!force && busy()) { setTimeout(function () { open(false); }, 8000); return; }
		opened = true; lastFocus = d.activeElement;
		box.hidden = false; d.documentElement.classList.add('nhs-lock');
		requestAnimationFrame(function () { box.classList.add('is-open'); var i = box.querySelector('[name=name]'); setTimeout(function () { (i || card).focus({ preventScroll: true }); }, 250); });
		if (tab) { tab.hidden = true; }
	}
	function close(remember) {
		box.classList.remove('is-open'); d.documentElement.classList.remove('nhs-lock');
		setTimeout(function () { box.hidden = true; }, 260);
		if (remember && state() !== 'sub') { snooze(); if (tab) { tab.hidden = true; tab.classList.remove('is-in'); } }
		if (lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
	}

	box.addEventListener('click', function (e) { if (e.target.closest('[data-nhs-close]')) { close(true); } });
	d.addEventListener('keydown', function (e) {
		if (box.hidden) { return; }
		if (e.key === 'Escape') { close(true); return; }
		if (e.key === 'Tab') {
			var f = Array.prototype.filter.call(card.querySelectorAll('a[href],button,input,select,textarea'), function (x) { return x.offsetParent !== null && x.tabIndex !== -1; });
			if (!f.length) { return; }
			if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); } else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		}
	});
	if (tab) {
		tab.addEventListener('click', function () { open(true); });
		// 1.79.2: the tab no longer slides in by itself; while snoozed nothing appears.
	}

	var f1 = box.querySelector('[data-nhs-form]'), f2 = box.querySelector('[data-nhs-more]'), err = box.querySelector('[data-nhs-err]');
	f1.addEventListener('submit', function (e) {
		e.preventDefault(); err.hidden = true;
		var v = f1.email.value.trim();
		if (!validEmail(v)) { err.textContent = 'Please enter a valid e-mail address.'; err.hidden = false; f1.email.focus(); return; }
		var b = f1.querySelector('.nhs__btn'); b.disabled = true; b.classList.add('is-busy');
		post({ name: f1.name.value.trim(), email: v, website: f1.website.value }).then(function (r) {
			b.disabled = false; b.classList.remove('is-busy');
			if (!r || !r.success) { err.textContent = (r && r.data && r.data.message) || 'Please try again.'; err.hidden = false; return; }
			email = v; setC(KEY, 'sub', 365); track('popup');
			if (r.data && r.data.code) { box.querySelector('[data-nhs-codeval]').textContent = r.data.code; box.querySelector('[data-nhs-code]').hidden = false; box.querySelector('[data-nhs-donetext]').textContent = 'Your welcome code is below and in your inbox. Use it at checkout within 30 days.'; }
			if (r.data && r.data.returning) { box.querySelector('[data-nhs-donetext]').textContent = 'You were already on the list - we have updated your details.'; }
			step(2);
		}).catch(function () { b.disabled = false; b.classList.remove('is-busy'); err.textContent = 'Network problem. Please try again.'; err.hidden = false; });
	});
	f2.addEventListener('submit', function (e) {
		e.preventDefault();
		var love = Array.prototype.map.call(f2.querySelectorAll('input[name="love[]"]:checked'), function (x) { return x.value; });
		var ph = f2.phone.value.trim();
		if (ph || love.length) { post({ email: email, phone: ph, love: love, more: 1 }); }
		step(3);
	});
	box.querySelector('[data-nhs-skip]').addEventListener('click', function () { step(3); });
	var cp = box.querySelector('[data-nhs-copy]');
	if (cp) { cp.addEventListener('click', function () { var t = box.querySelector('[data-nhs-codeval]').textContent; if (navigator.clipboard) { navigator.clipboard.writeText(t).then(function () { cp.textContent = 'Copied'; }); } }); }

	if (C.preview) { if (!snoozed()) { setTimeout(function () { open(true); }, 800); } return; }

	/* ---------- Slim slide-in bar (1.78.0) ----------
	 * The centred pop-up no longer opens by itself (it covered product buttons).
	 * New visitors only get a slim bar at the bottom, and only from their second
	 * page view or after reading most of the page. The full pop-up opens only when
	 * she taps the "Join The House" tab. */
	function showBar() {
		if (!bar || state() || !bar.hidden) { return; }
		if (busy() || (box && !box.hidden)) { setTimeout(showBar, 8000); return; }
		bar.hidden = false; d.documentElement.classList.add('nhs-bar-on');
		requestAnimationFrame(function () { bar.classList.add('is-in'); });
		if (tab) { tab.hidden = true; }
	}
	function hideBar(remember) {
		if (!bar) { return; }
		bar.classList.remove('is-in'); d.documentElement.classList.remove('nhs-bar-on');
		setTimeout(function () { bar.hidden = true; }, 320);
		if (remember && state() !== 'sub') { snooze(); if (tab) { tab.hidden = true; tab.classList.remove('is-in'); } }
	}
	if (bar) {
		bar.querySelector('[data-nhs-barclose]').addEventListener('click', function () { hideBar(true); });
		var bf = bar.querySelector('[data-nhs-barform]'), bm = bar.querySelector('[data-nhs-barmsg]');
		bf.addEventListener('submit', function (e) {
			e.preventDefault(); bm.hidden = true; bm.classList.remove('is-err');
			var v = bf.email.value.trim();
			if (!validEmail(v)) { bm.textContent = 'Please enter a valid e-mail address.'; bm.classList.add('is-err'); bm.hidden = false; bf.email.focus(); return; }
			var bb = bf.querySelector('button'); bb.disabled = true;
			post({ email: v, website: bf.website.value }).then(function (r) {
				bb.disabled = false;
				if (!r || !r.success) { bm.textContent = (r && r.data && r.data.message) || 'Please try again.'; bm.classList.add('is-err'); bm.hidden = false; return; }
				setC(KEY, 'sub', 365); track('bar');
				bm.textContent = (r.data && r.data.code) ? 'Welcome to the house! Your code: ' + r.data.code + ' (also in your inbox).' : 'Welcome to the house! Check your inbox.';
				bf.hidden = true; bm.hidden = false;
				setTimeout(function () { hideBar(false); }, 9000);
			}).catch(function () { bb.disabled = false; bm.textContent = 'Network problem. Please try again.'; bm.classList.add('is-err'); bm.hidden = false; });
		});
	}

	if (state()) { return; }
	var pv = 0;
	try { pv = (parseInt(localStorage.getItem('nura_house_pv'), 10) || 0) + 1; localStorage.setItem('nura_house_pv', String(pv)); } catch (e) { pv = 1; }
	if (pv >= 2) { setTimeout(showBar, (C.delay || 4) * 1000); }
	window.addEventListener('scroll', function onS() {
		var h = d.documentElement, p = (h.scrollTop || d.body.scrollTop) / Math.max(1, h.scrollHeight - h.clientHeight) * 100;
		if (p >= (C.scroll || 60)) { window.removeEventListener('scroll', onS); showBar(); }
	}, { passive: true });
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', nuraHouseInit); } else { nuraHouseInit(); }
})();
