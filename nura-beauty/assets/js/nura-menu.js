/* NURA menu (v1.59.2): dropdowns that work with mouse, touch and keyboard.
 * Mouse: opens on hover (CSS), click follows the link as before.
 * Touch (tablets, touch laptops): first tap opens the dropdown, second tap follows the link.
 * Keyboard: Tab opens via focus, Escape closes. Tap/click outside closes. */
(function () {
	'use strict';
	var desktop = window.matchMedia('(min-width: 861px)');
	var noHover = window.matchMedia('(hover: none)');
	var items = Array.prototype.slice.call(document.querySelectorAll(
		'.nura-nav .nura-mega > li.has-mega, .nura-nav .nura-mega > li.menu-item-has-children, .nura-nav > ul > li.menu-item-has-children'
	));
	if (!items.length) { return; }
	function link(li) { for (var i = 0; i < li.children.length; i++) { if (li.children[i].tagName === 'A') { return li.children[i]; } } return null; }
	function setOpen(li, open) {
		li.classList.toggle('is-open', open);
		var a = link(li); if (a) { a.setAttribute('aria-expanded', open ? 'true' : 'false'); }
	}
	function closeAll(except) { items.forEach(function (li) { if (li !== except) { setOpen(li, false); } }); }
	items.forEach(function (li) {
		var a = link(li); if (!a) { return; }
		a.setAttribute('aria-haspopup', 'true');
		a.setAttribute('aria-expanded', 'false');
		li.addEventListener('touchstart', function () { li._nuraTouch = Date.now(); }, { passive: true });
		a.addEventListener('click', function (e) {
			if (!desktop.matches) { return; }
			var touch = noHover.matches || (li._nuraTouch && Date.now() - li._nuraTouch < 800);
			if (touch && !li.classList.contains('is-open')) {
				e.preventDefault();
				closeAll(li);
				setOpen(li, true);
			}
		});
		li.addEventListener('mouseenter', function () { if (desktop.matches && !noHover.matches) { a.setAttribute('aria-expanded', 'true'); } });
		li.addEventListener('mouseleave', function () { if (desktop.matches && !noHover.matches) { setOpen(li, false); } });
		li.addEventListener('focusout', function (e) { if (!li.contains(e.relatedTarget)) { setOpen(li, false); } });
		li.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { setOpen(li, false); a.focus(); a.blur(); }
		});
	});
	document.addEventListener('click', function (e) { if (!e.target.closest || !e.target.closest('.nura-nav')) { closeAll(null); } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(null); } });
})();
