/* NURA growth layer (v1.58.0): same-day cut-off countdown + recently viewed. */
(function () {
	'use strict';
	var L = window.nuraGrowth || {};
	function fmt(s, a, b) { return String(s).replace('%1$d', a).replace('%2$d', b).replace('%d', a).replace('%s', a); }

	/* Same-day countdown in Nairobi time (UTC+3, no daylight saving). */
	function nairobiNow() { var d = new Date(); return new Date(d.getTime() + d.getTimezoneOffset() * 60000 + 3 * 3600000); }
	function tick() {
		var els = document.querySelectorAll('[data-nura-cutoff]');
		if (!els.length) { return; }
		var now = nairobiNow();
		els.forEach(function (el) {
			var cut = parseInt(el.getAttribute('data-nura-cutoff'), 10) || 17;
			var end = new Date(now); end.setHours(cut, 0, 0, 0);
			var mins = Math.floor((end - now) / 60000);
			var t = el.querySelector('.nura-promise__text');
			if (!t) { return; }
			if (mins > 0) {
				var h = Math.floor(mins / 60), m = mins % 60;
				var left = h > 0 ? fmt(L.hours || '%1$dh %2$dm', h, m) : fmt(L.minutes || '%dm', m);
				t.textContent = fmt(L.sameDay || 'Order within %s for same-day delivery in Nairobi.', left);
				el.classList.add('is-live');
			} else {
				t.textContent = L.nextDay || 'Order now for next-day delivery in Nairobi.';
				el.classList.remove('is-live');
			}
		});
	}

	/* Recently viewed. */
	var KEY = 'nura_rv', MAX = 12;
	function read() { try { var v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
	function write(v) { try { localStorage.setItem(KEY, JSON.stringify(v.slice(0, MAX))); } catch (e) {} }
	function safeUrl(u) { try { var x = new URL(u, location.href); return x.origin === location.origin ? x.href : ''; } catch (e) { return ''; } }
	function record() {
		var el = document.getElementById('nura-rv-current');
		if (!el) { return null; }
		var cur; try { cur = JSON.parse(el.textContent); } catch (e) { return null; }
		if (!cur || !cur.id) { return null; }
		var list = read().filter(function (x) { return x && x.id !== cur.id; });
		list.unshift({ id: cur.id, name: String(cur.name || ''), url: String(cur.url || ''), img: String(cur.img || ''), price: String(cur.price || '') });
		write(list);
		return cur.id;
	}
	function render(currentId) {
		var rails = document.querySelectorAll('[data-nura-rv]');
		if (!rails.length) { return; }
		var items = read().filter(function (x) { return x && x.id !== currentId && safeUrl(x.url); });
		rails.forEach(function (rail) {
			var track = rail.querySelector('[data-nura-rv-track]');
			if (!track) { return; }
			while (track.firstChild) { track.removeChild(track.firstChild); }
			if (items.length < 2) { rail.hidden = true; return; }
			items.slice(0, 8).forEach(function (x) {
				var a = document.createElement('a'); a.className = 'nura-rv__card'; a.href = safeUrl(x.url);
				var fig = document.createElement('span'); fig.className = 'nura-rv__img';
				var imgUrl = safeUrl(x.img) || (x.img && /^https:\/\//.test(x.img) ? x.img : '');
				if (imgUrl) { var img = document.createElement('img'); img.src = imgUrl; img.alt = x.name; img.loading = 'lazy'; img.width = 300; img.height = 375; fig.appendChild(img); }
				var n = document.createElement('span'); n.className = 'nura-rv__name'; n.textContent = x.name;
				var p = document.createElement('span'); p.className = 'nura-rv__price'; p.textContent = x.price;
				a.appendChild(fig); a.appendChild(n); a.appendChild(p); track.appendChild(a);
			});
			rail.hidden = false;
			var clr = rail.querySelector('[data-nura-rv-clear]');
			if (clr && !clr._nura) { clr._nura = 1; clr.addEventListener('click', function () { write([]); render(currentId); }); }
		});
	}
	function init() { var id = record(); render(id); tick(); setInterval(tick, 30000); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
