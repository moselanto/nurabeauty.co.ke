/* NURA shop layer: client-side wishlist + mobile filter drawer. */
(function () {
	'use strict';

	var KEY = 'nura_wishlist';

	function read() {
		try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
	}
	function write(list) {
		try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
	}

	function paintBadges() {
		var n = read().length;
		var badges = document.querySelectorAll('.nura-wish-badge');
		for (var i = 0; i < badges.length; i++) {
			if (n > 0) { badges[i].textContent = String(n); badges[i].hidden = false; }
			else { badges[i].hidden = true; }
		}
	}

	function paintHearts() {
		var list = read();
		var hearts = document.querySelectorAll('.nura-wish');
		for (var i = 0; i < hearts.length; i++) {
			var on = list.indexOf(String(hearts[i].getAttribute('data-id'))) > -1;
			hearts[i].setAttribute('aria-pressed', on ? 'true' : 'false');
			hearts[i].classList.toggle('is-on', on);
		}
	}

	function toggleWish(id) {
		var list = read();
		var i = list.indexOf(id);
		if (i > -1) { list.splice(i, 1); } else { list.push(id); }
		write(list);
		paintHearts();
		paintBadges();
	}

	document.addEventListener('click', function (e) {
		var heart = e.target.closest ? e.target.closest('.nura-wish') : null;
		if (heart) {
			e.preventDefault();
			toggleWish(String(heart.getAttribute('data-id')));
			return;
		}
		var toggle = e.target.closest ? e.target.closest('[data-nura-filter-toggle]') : null;
		if (toggle) {
			var sidebar = document.querySelector('[data-nura-sidebar]');
			if (sidebar) {
				var open = sidebar.classList.toggle('is-open');
				document.body.classList.toggle('nura-filter-open', open);
			}
		}
	});

	if (document.readyState !== 'loading') {
		paintHearts(); paintBadges();
	} else {
		document.addEventListener('DOMContentLoaded', function () { paintHearts(); paintBadges(); });
	}
})();
