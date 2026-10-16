/* NURA Desk admin (NURA Experience 1.59.0) - interactive layer. */
(function ($) {
	'use strict';
	var C = window.NURAX_DESK || {};
	var $d = $(document);
	function ksh(n) { return 'KSh ' + Math.round(Number(n) || 0).toLocaleString('en-KE'); }

	/* ---------- toasts ---------- */
	var $toasts = $('<div class="nd-toasts" role="status" aria-live="polite"></div>').appendTo('body');
	function toast(msg, tone) {
		var $t = $('<div class="nd-toast"></div>').addClass(tone ? 'nd-toast--' + tone : '').text(msg).appendTo($toasts);
		setTimeout(function () { $t.addClass('is-in'); }, 10);
		setTimeout(function () { $t.removeClass('is-in'); setTimeout(function () { $t.remove(); }, 300); }, 3200);
	}

	/* ---------- server calls ---------- */
	function call(op, keys, extra) {
		var data = $.extend({ action: 'nurax_desk', nonce: C.dnonce, op: op, keys: keys }, extra || {});
		return $.post(C.ajax, data);
	}

	/* Re-draw counters, tabs, tables and lists without a full page reload. */
	function refresh() {
		$('.nd-wrap').addClass('is-busy');
		return $.get(window.location.href).done(function (html) {
			var $n = $('<div>').append($.parseHTML(html));
			['.nd-tabs', '.nd-kpis', '.nd-toolbar .nd-pills', '.nd-table-wrap', '.nd-pager', '.nd-empty', '.nd-grid2', '.nd-remhero', '.nd-head__actions'].forEach(function (sel) {
				var $old = $('.nd-wrap ' + sel), $new = $n.find(sel);
				if ($old.length && $new.length && $old.length === $new.length) { $old.each(function (i) { $(this).replaceWith($new.eq(i)); }); }
			});
			bulkUpdate();
		}).always(function () { $('.nd-wrap').removeClass('is-busy'); });
	}

	/* ---------- confirm + inline forms ---------- */
	$d.on('submit', 'form[data-nd-confirm]', function (e) {
		if (!window.confirm($(this).attr('data-nd-confirm'))) { e.preventDefault(); e.stopImmediatePropagation(); }
	});
	// Quick status buttons inside tables and lists: post in the background.
	$d.on('submit', '.nd-table form.nd-inline, .nd-list form.nd-inline', function (e) {
		if (e.isDefaultPrevented() || $(this).is('[data-nd-void]')) { return; }
		e.preventDefault();
		var $f = $(this), $b = $f.find('button').prop('disabled', true).addClass('is-loading');
		fetch($f.attr('action'), { method: 'POST', body: new FormData(this), credentials: 'same-origin' })
			.then(function () { toast('Updated', 'ok'); refresh(); })
			.catch(function () { toast('Could not update. Please try again.', 'err'); $b.prop('disabled', false).removeClass('is-loading'); });
	});

	/* ---------- delete ---------- */
	$d.on('click', '[data-nd-del]', function () {
		var key = $(this).attr('data-nd-del'), type = key.split(':')[0];
		var warn = type === 'plan' ? 'Delete this plan and all its payments permanently? Reserved stock is returned. This cannot be undone.' : 'Delete this ' + (type === 'appt' ? 'appointment' : 'order') + ' permanently? This cannot be undone.';
		if (!window.confirm(warn)) { return; }
		var $row = $(this).closest('[data-nd-key], li');
		$row.addClass('is-removing');
		call('delete', [key]).done(function (r) {
			if (r && r.success && r.data.ok) { setTimeout(function () { $row.remove(); refresh(); }, 250); toast('Deleted', 'ok'); }
			else { $row.removeClass('is-removing'); toast('Could not delete.', 'err'); }
		}).fail(function () { $row.removeClass('is-removing'); toast('Could not delete.', 'err'); });
	});

	/* ---------- order paid ---------- */
	$d.on('click', '[data-nd-paid]', function () {
		var ref = window.prompt('Mark this order as paid. M-Pesa code (optional):', '');
		if (ref === null) { return; }
		call('paid', [$(this).attr('data-nd-paid')], { ref: ref.toUpperCase() }).done(function () { toast('Marked as paid', 'ok'); refresh(); });
	});

	/* ---------- reminders ---------- */
	function markDone(key) {
		var $it = $('[data-nd-remitem="' + key + '"]');
		$it.addClass('is-done').find('[data-nd-last]').text('Reminded today');
		var $runner = $('[data-nd-run]');
		if ($runner.length) { $runner.text(runnerLabel()); }
	}
	$d.on('click', 'a[data-nd-remind]', function () {
		var key = $(this).attr('data-nd-remind');
		call('remind_log', [key]).done(function () { markDone(key); toast('Reminder recorded', 'ok'); });
	});
	$d.on('click', '[data-nd-api]', function () {
		var $b = $(this).prop('disabled', true).addClass('is-loading'), key = $b.attr('data-nd-api');
		call('remind_api', [key]).done(function (r) {
			$b.prop('disabled', false).removeClass('is-loading');
			if (r && r.success && r.data.ok) { markDone(key); toast('Sent on WhatsApp', 'ok'); }
			else { toast('Not sent: ' + ((r && r.data && r.data.message) || 'WhatsApp error'), 'err'); }
		});
	});
	$d.on('click', '[data-nd-sendall]', function () {
		var keys = $('[data-nd-remitem]').not('.is-done').map(function () { return $(this).attr('data-nd-remitem'); }).get();
		if (!keys.length) { toast('Everyone has been reminded today.'); return; }
		if (!window.confirm('Send ' + keys.length + ' WhatsApp reminders automatically now?')) { return; }
		var $b = $(this).prop('disabled', true).addClass('is-loading');
		call('remind_api', keys).done(function (r) {
			var ok = r && r.success ? r.data.ok : 0, fail = r && r.success ? r.data.fail : keys.length;
			toast(ok + ' sent' + (fail ? ', ' + fail + ' not sent' : ''), fail ? 'err' : 'ok');
			setTimeout(function () { window.location.reload(); }, 900);
		}).always(function () { $b.prop('disabled', false).removeClass('is-loading'); });
	});

	/* "Remind everyone": open each WhatsApp chat in turn. */
	function pending() { return $('[data-nd-remitem]').not('.is-done').find('a[data-nd-remind]'); }
	function runnerLabel() {
		var n = pending().length;
		if (!n) { return 'All done for today'; }
		var name = pending().first().closest('[data-nd-remitem]').find('.nd-rem__main strong').text();
		return 'Next: ' + name + ' (' + n + ' left)';
	}
	$d.on('click', '[data-nd-run]', function () {
		var $a = pending().first();
		if (!$a.length) { $(this).text('All done for today').prop('disabled', true); return; }
		var $li = $a.closest('[data-nd-remitem]');
		$('html, body').animate({ scrollTop: $li.offset().top - 140 }, 200);
		$li.addClass('is-flash');
		setTimeout(function () { $li.removeClass('is-flash'); }, 1200);
		window.open($a.attr('href'), '_blank', 'noopener');
		$a.trigger('click.ndlog');
		var key = $a.attr('data-nd-remind');
		call('remind_log', [key]).done(function () { markDone(key); });
		$(this).text(runnerLabel());
	});

	/* Preview modal. */
	var $modal = $('[data-nd-modal]');
	$d.on('click', '[data-nd-preview]', function () {
		$modal.find('[data-nd-bubble]').text($(this).attr('data-nd-preview'));
		$modal.prop('hidden', false).addClass('is-open');
		$modal.find('.nd-modal__x').trigger('focus');
	});
	$d.on('click', '[data-nd-close], .nd-modal', function (e) {
		if (e.target !== this && !$(e.target).is('[data-nd-close]')) { return; }
		$modal.removeClass('is-open').prop('hidden', true);
	});
	$d.on('click', '[data-nd-copytext]', function () {
		var t = $modal.find('[data-nd-bubble]').text();
		if (navigator.clipboard) { navigator.clipboard.writeText(t).then(function () { toast('Message copied', 'ok'); }); }
	});
	$d.on('keydown', function (e) {
		if (e.key === 'Escape' && $modal.hasClass('is-open')) { $modal.removeClass('is-open').prop('hidden', true); }
		if (e.key === '/' && !$(e.target).is('input, textarea, select')) {
			var $s = $('.nd-search input[type=search]').first();
			if ($s.length) { e.preventDefault(); $s.trigger('focus'); }
		}
	});

	/* ---------- bulk actions ---------- */
	var ACTIONS = {
		order: [['status:confirmed', 'Confirm'], ['paid', 'Mark paid'], ['status:delivered', 'Delivered'], ['status:cancelled', 'Cancel'], ['delete', 'Delete']],
		appt: [['status:confirmed', 'Confirm'], ['status:completed', 'Completed'], ['status:no_show', 'No-show'], ['status:cancelled', 'Cancel'], ['delete', 'Delete']],
		plan: [['status:cancelled', 'Cancel plans'], ['delete', 'Delete']]
	};
	var $bar = $('<div class="nd-bulk" hidden><span class="nd-bulk__n"></span><span class="nd-bulk__acts"></span><button type="button" class="nd-bulk__x">Clear</button></div>').appendTo('body');
	function bulkUpdate() {
		var $t = $('.nd-table[data-nd-type]');
		if (!$t.length) { $bar.prop('hidden', true); return; }
		var type = $t.attr('data-nd-type'), keys = $t.find('.nd-check:checked').map(function () { return this.value; }).get();
		$t.find('tr[data-nd-key]').each(function () { $(this).toggleClass('is-selected', $(this).find('.nd-check').is(':checked')); });
		if (!keys.length) { $bar.prop('hidden', true).removeClass('is-in'); return; }
		$bar.find('.nd-bulk__n').text(keys.length + ' selected');
		var $acts = $bar.find('.nd-bulk__acts').empty();
		(ACTIONS[type] || []).forEach(function (a) {
			$('<button type="button" class="nd-bulk__btn"></button>').toggleClass('is-danger', a[0] === 'delete').attr('data-op', a[0]).text(a[1]).appendTo($acts);
		});
		$bar.data('keys', keys).data('type', type).prop('hidden', false);
		setTimeout(function () { $bar.addClass('is-in'); }, 10);
	}
	$d.on('change', '[data-nd-all]', function () {
		$(this).closest('table').find('tr[data-nd-key]:visible .nd-check').prop('checked', this.checked);
		bulkUpdate();
	});
	$d.on('change', '.nd-check', bulkUpdate);
	$d.on('click', '.nd-bulk__x', function () { $('.nd-check, [data-nd-all]').prop('checked', false); bulkUpdate(); });
	$d.on('click', '.nd-bulk__btn', function () {
		var op = $(this).attr('data-op'), keys = $bar.data('keys') || [], extra = {};
		if (op === 'delete' && !window.confirm('Delete ' + keys.length + ' records permanently? This cannot be undone.')) { return; }
		if (op === 'status:cancelled' && !window.confirm('Cancel ' + keys.length + ' records?')) { return; }
		if (op.indexOf('status:') === 0) { extra.to = op.split(':')[1]; op = 'status'; }
		$bar.addClass('is-busy');
		call(op, keys, extra).done(function (r) {
			var ok = r && r.success ? r.data.ok : 0;
			toast(ok + ' updated', 'ok');
			$('[data-nd-all]').prop('checked', false);
			refresh();
		}).always(function () { $bar.removeClass('is-busy'); });
	});

	/* ---------- instant filter while typing ---------- */
	var ft;
	$d.on('input', '.nd-search input[type=search]', function () {
		var v = $.trim(this.value).toLowerCase();
		clearTimeout(ft);
		ft = setTimeout(function () {
			var $rows = $('.nd-table tr[data-nd-search]');
			if (!$rows.length) { return; }
			$rows.each(function () { $(this).toggle(!v || $(this).attr('data-nd-search').indexOf(v) > -1); });
			$('.nd-table tr.nd-daysep').each(function () {
				var $n = $(this).nextUntil('.nd-daysep');
				$(this).toggle(!v || $n.filter(':visible').length > 0);
			});
		}, 120);
	});

	/* Row click opens the record (ignores buttons, links, checkboxes). */
	$d.on('click', '.nd-table tr[data-nd-key] td', function (e) {
		if ($(e.target).closest('a, button, input, form, label').length) { return; }
		var $a = $(this).closest('tr').find('a.nd-ref, td a[href*="appt="]').first();
		if ($a.length) { window.location.href = $a.attr('href'); }
	});

	/* ---------- existing helpers ---------- */
	$d.on('submit', 'form[data-nd-void]', function (e) {
		var why = window.prompt(C.confirmVoid || 'Void this payment? Reason:');
		if (why === null) { e.preventDefault(); return; }
		$(this).find('input[name=reason]').val(why);
	});
	$d.on('click', '[data-nd-amt]', function () {
		$(this).closest('form').find('input[name=amount]').val($(this).attr('data-nd-amt')).trigger('focus');
	});
	$d.on('click', '[data-nd-copy]', function () {
		var b = $(this), v = b.attr('data-nd-copy'), t = b.text();
		var done = function () { b.text('Link copied'); setTimeout(function () { b.text(t); }, 1600); };
		if (navigator.clipboard) { navigator.clipboard.writeText(v).then(done, function () { window.prompt('Copy this link:', v); }); }
		else { window.prompt('Copy this link:', v); }
	});
	$d.on('click', '[data-nd-print]', function () { window.print(); });
	$d.on('input', 'input[name=reference], input[name=init_reference], input[name=ref]', function () {
		var p = this.selectionStart; this.value = this.value.toUpperCase(); try { this.setSelectionRange(p, p); } catch (x) {}
	});
	// Fade out the success notice.
	setTimeout(function () { $('.nd-notice.notice-success').slideUp(250); }, 4000);

	/* ---------- plan form: price lookup + live schedule ---------- */
	var form = $('[data-nd-planform]');
	if (!form.length) { return; }
	var price = form.find('[data-nd-price]'), dep = form.find('[data-nd-deposit]'), weeks = form.find('[data-nd-weeks]'), start = form.find('[data-nd-start]'), box = form.find('[data-nd-preview]');
	var depTouched = dep.val() !== '';
	dep.on('input', function () { depTouched = true; });
	function preview() {
		var p = parseFloat(price.val()) || 0, w = Math.max(1, parseInt(weeks.val(), 10) || 1);
		if (!depTouched && p > 0) { dep.val(Math.ceil(p * (parseInt(dep.attr('data-pct'), 10) || C.deposit || 30) / 100)); }
		var d = Math.min(p, Math.max(0, parseFloat(dep.val()) || 0)), bal = Math.max(0, p - d), wk = Math.ceil(bal / w);
		if (!(p > 0)) { box.html(''); return; }
		var s = start.val() ? new Date(start.val() + 'T12:00:00') : new Date();
		var fmt = function (x) { return x.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); };
		var h = '<ol><li><small>Deposit - ' + fmt(s) + '</small><strong>' + ksh(d) + '</strong></li>';
		for (var i = 1; i <= w; i++) {
			var x = new Date(s.getTime()); x.setDate(x.getDate() + 7 * i);
			h += '<li><small>Week ' + i + ' - ' + fmt(x) + '</small><strong>' + ksh(i < w ? wk : bal - wk * (w - 1)) + '</strong></li>';
		}
		box.html(h + '</ol>');
	}
	price.add(dep).add(weeks).add(start).on('input change', preview);
	preview();
	form.find('[data-nd-product]').on('change', function () {
		var id = $(this).val();
		if (!id) { return; }
		$.get(C.ajax, { action: 'nurax_lmm_price', id: id, nonce: C.nonce }).done(function (r) {
			if (!r || !r.success) { return; }
			price.val(Math.round(r.data.price)); depTouched = false;
			form.find('[data-nd-pname]').val(r.data.name);
			if (r.data.options) { form.find('[data-nd-popts]').val(r.data.options); }
			preview();
		});
	});
})(jQuery);

/* NURA Desk 1.60.0 - Point of sale + marketing. */
(function ($) {
	'use strict';
	var $d = $(document);
	function ksh(n) { return 'KSh ' + Math.round(Number(n) || 0).toLocaleString('en-KE'); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function toast(msg, tone) {
		var $w = $('.nd-toasts'); if (!$w.length) { $w = $('<div class="nd-toasts"></div>').appendTo('body'); }
		var $t = $('<div class="nd-toast"></div>').addClass(tone ? 'nd-toast--' + tone : '').text(msg).appendTo($w);
		setTimeout(function () { $t.addClass('is-in'); }, 10);
		setTimeout(function () { $t.removeClass('is-in'); setTimeout(function () { $t.remove(); }, 300); }, 3500);
	}

	/* ================= Sales list actions (also used after a sale) ================= */
	var P = window.NURAX_POS || null;
	if (P) {
		$d.on('click', '[data-pos-email]', function () {
			var $b = $(this), saved = $b.attr('data-email') || '';
			var to = window.prompt(saved ? 'Send to the e-mail used on this order (edit if needed):' : 'No e-mail on this order. Send to:', saved);
			if (to === null) { return; }
			to = $.trim(to);
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(to)) { toast('Please enter a valid e-mail address.', 'err'); return; }
			$b.addClass('is-loading');
			$.post(P.ajax, { action: 'nurax_pos_order', nonce: P.nonce, op: 'email', id: $b.attr('data-pos-email'), to: to }).done(function (r) {
				toast(r && r.data && r.data.message ? r.data.message : (r && r.success ? 'Sent' : 'Not sent'), r && r.success ? 'ok' : 'err');
			}).always(function () { $b.removeClass('is-loading'); });
		});
		$d.on('click', '[data-pos-paid]', function () {
			var $b = $(this), ref = window.prompt('Mark as paid. M-Pesa code (optional):', '');
			if (ref === null) { return; }
			$.post(P.ajax, { action: 'nurax_pos_order', nonce: P.nonce, op: 'paid', id: $b.attr('data-pos-paid'), method: 'mpesa', ref: ref }).done(function (r) {
				if (r && r.success) { toast('Invoice marked paid', 'ok'); setTimeout(function () { window.location.reload(); }, 600); } else { toast('Could not update', 'err'); }
			});
		});
		$d.on('click', '[data-pos-complete]', function () {
			var $b = $(this);
			if (!window.confirm('Mark this order as fulfilled (packed / delivered)?')) { return; }
			$b.addClass('is-loading');
			$.post(P.ajax, { action: 'nurax_pos_order', nonce: P.nonce, op: 'complete', id: $b.attr('data-pos-complete') }).done(function (r) {
				if (r && r.success) { toast(r.data.message, 'ok'); $b.closest('li, tr').fadeOut(250); } else { toast('Could not update', 'err'); $b.removeClass('is-loading'); }
			});
		});
		$d.on('input', '[data-nd-local]', function () {
			var v = $.trim(this.value).toLowerCase();
			$('.nd-table tr[data-nd-search]').each(function () { $(this).toggle(!v || $(this).attr('data-nd-search').indexOf(v) > -1); });
		});
	}

	/* ================= Register ================= */
	var $pos = $('[data-pos]');
	if ($pos.length && P) {
		var cart = [], method = 'mpesa', waOrder = 0, last = null, custTimer, qTimer, cat = 0;
		var KEY = 'nurax_pos_cart';
		function save() { try { sessionStorage.setItem(KEY, JSON.stringify({ cart: cart, cust: cust(), waOrder: waOrder })); } catch (e) {} }
		function cust() { var c = {}; $('[data-pos-c]').each(function () { var k = $(this).attr('data-pos-c'); c[k] = this.type === 'checkbox' ? this.checked : $.trim(this.value); }); return c; }
		function setCust(c) { c = c || {}; $('[data-pos-c=name]').val(c.name || ''); $('[data-pos-c=phone]').val(c.phone ? (String(c.phone).indexOf('+') === 0 ? c.phone : '+' + c.phone) : ''); $('[data-pos-c=email]').val(c.email || ''); $('[data-pos-c=consent]').prop('checked', !!c.consent); $pos.data('uid', c.user_id || 0); }

		function load(q) {
			var $g = $('[data-pos-grid]').addClass('is-busy');
			$.get(P.ajax, { action: 'nurax_pos_search', nonce: P.nonce, q: q || '', cat: cat }).done(function (r) {
				var list = r && r.success ? r.data : [];
				if (!list.length) { $g.html('<p class="pos__hint">No products found. Try another word, or add a custom item.</p>'); return; }
				$g.html(list.map(function (p) {
					var low = p.stock !== null && p.stock <= 2;
					return '<button type="button" class="pos-tile' + (p.in ? '' : ' is-out') + '" data-p=\'' + esc(JSON.stringify(p)) + '\'>' +
						'<span class="pos-tile__img" style="background-image:url(' + esc(p.img) + ')"></span>' +
						'<span class="pos-tile__name">' + esc(p.name) + '</span>' + (p.opt ? '<span class="pos-tile__opt">' + esc(p.opt) + '</span>' : '') +
						'<span class="pos-tile__row"><b>' + ksh(p.price) + '</b>' + (p.stock !== null ? '<em class="' + (low ? 'is-low' : '') + '">' + (p.in ? p.stock + ' left' : 'Out of stock') + '</em>' : (p.in ? '' : '<em class="is-low">Out of stock</em>')) + '</span></button>';
				}).join(''));
			}).always(function () { $g.removeClass('is-busy'); });
		}
		function add(p) {
			var f = null;
			cart.forEach(function (l) { if (!l.custom && l.id === p.id) { f = l; } });
			if (f) { f.qty++; } else { cart.push({ id: p.id, name: p.name, opt: p.opt || '', price: Number(p.price) || 0, qty: Number(p.qty) || 1, img: p.img || '', custom: !!p.custom }); }
			draw(); save();
		}
		function totals() {
			var sub = 0; cart.forEach(function (l) { sub += l.price * l.qty; });
			var dv = Number($('[data-pos-disc]').val()) || 0, pct = $('[data-pos-disctype]').val() === 'pct';
			var disc = Math.min(sub, pct ? Math.round(sub * Math.min(100, dv) / 100) : dv);
			var del = Math.max(0, Number($('[data-pos-del]').val()) || 0);
			return { sub: sub, disc: disc, del: del, total: Math.max(0, sub - disc + del) };
		}
		function draw() {
			var $l = $('[data-pos-lines]');
			if (!cart.length) { $l.html('<li class="pos__empty">Tap a product to add it.</li>'); }
			else {
				$l.html(cart.map(function (l, i) {
					return '<li class="pos-line"><span class="pos-line__main"><strong>' + esc(l.name) + '</strong>' + (l.opt ? '<small>' + esc(l.opt) + '</small>' : '') +
						'<label class="pos-line__price">@ <input type="number" min="0" step="1" value="' + l.price + '" data-i="' + i + '" data-f="price"></label></span>' +
						'<span class="pos-line__qty"><button type="button" data-i="' + i + '" data-d="-1" aria-label="Less">&minus;</button><input type="number" min="1" value="' + l.qty + '" data-i="' + i + '" data-f="qty"><button type="button" data-i="' + i + '" data-d="1" aria-label="More">+</button></span>' +
						'<span class="pos-line__tot">' + ksh(l.price * l.qty) + '</span><button type="button" class="pos-line__x" data-x="' + i + '" aria-label="Remove">&times;</button></li>';
				}).join(''));
			}
			var t = totals();
			$('[data-pos-sub]').text(ksh(t.sub)); $('[data-pos-dsc]').text('- ' + ksh(t.disc)); $('[data-pos-dl]').text(ksh(t.del)); $('[data-pos-total]').text(ksh(t.total));
			$('[data-pos-charge]').prop('disabled', !cart.length).text(cart.length ? 'Complete sale  ·  ' + ksh(t.total) + '  ·  ' + P.methods[method] : 'Add a wig to start the sale');
			var ms = []; if (t.disc > 0) { ms.push('- ' + ksh(t.disc)); } if (t.del > 0) { ms.push('+ ' + ksh(t.del) + ' delivery'); } $('[data-pos-more-sum]').text(ms.join(', '));
			$('[data-pos-invoice]').prop('disabled', !cart.length);
			change();
		}
		function change() {
			var t = totals(), cash = method === 'cash', v = Number($('[data-pos-tender]').val()) || 0;
			$('[data-pos-tender]').prop('hidden', !cash); $('[data-pos-ref]').prop('hidden', cash);
			var $c = $('[data-pos-change]');
			if (cash && v > 0) { $c.prop('hidden', false).toggleClass('is-short', v < t.total).text(v < t.total ? 'Short by ' + ksh(t.total - v) : 'Change to give: ' + ksh(v - t.total)); } else { $c.prop('hidden', true); }
		}
		function reset() {
			cart = []; waOrder = 0; setCust({}); $('[data-pos-disc]').val(0); $('[data-pos-del]').val(0); $('[data-pos-delto], [data-pos-note], [data-pos-ref], [data-pos-tender], [data-pos-cq]').val('');
			try { sessionStorage.removeItem(KEY); } catch (e) {}
			draw();
		}
		function submit(paid) {
			var t = totals(), c = cust();
			if (!cart.length) { return; }
			if (paid && method === 'cash' && (Number($('[data-pos-tender]').val()) || 0) > 0 && (Number($('[data-pos-tender]').val()) || 0) < t.total) { toast('Cash received is less than the total.', 'err'); return; }
			if (!paid && !c.name && !c.phone) { toast('Add the customer name or phone for an invoice.', 'err'); return; }
			var sale = {
				items: cart.map(function (l) { return { id: l.custom ? 0 : l.id, qty: l.qty, price: l.price, name: l.name, custom: l.custom ? 1 : 0 }; }),
				customer: { name: c.name, phone: c.phone, email: c.email, consent: c.consent, user_id: $pos.data('uid') || 0 },
				discount: Number($('[data-pos-disc]').val()) || 0, discount_pct: $('[data-pos-disctype]').val() === 'pct' ? 1 : 0,
				delivery: t.del, delivery_to: $.trim($('[data-pos-delto]').val()), note: $.trim($('[data-pos-note]').val()),
				channel: $('[data-pos-channel]').val(), wa_order: waOrder,
				payment: { method: method, paid: paid ? 1 : 0, ref: $.trim($('[data-pos-ref]').val()), tendered: Number($('[data-pos-tender]').val()) || 0 }
			};
			var $b = $(paid ? '[data-pos-charge]' : '[data-pos-invoice]').prop('disabled', true).addClass('is-loading');
			$.post(P.ajax, { action: 'nurax_pos_sale', nonce: P.nonce, sale: JSON.stringify(sale) }).done(function (r) {
				if (!r || !r.success) { toast((r && r.data && r.data.message) || 'Could not save the sale.', 'err'); return; }
				last = r.data;
				var $m = $('[data-pos-done]');
				$m.find('[data-pos-done-title]').text((r.data.paid ? 'Sale complete' : 'Invoice saved') + ' - #' + r.data.number);
				$m.find('[data-pos-done-amt]').text(ksh(r.data.total) + (r.data.paid ? ' paid' : ' due'));
				$m.find('[data-pos-done-chg]').prop('hidden', !(r.data.change > 0)).text('Change: ' + ksh(r.data.change));
				$m.find('[data-pos-done-receipt]').attr('href', r.data.receipt);
				$m.find('[data-pos-done-invoice]').attr('href', r.data.invoice);
				$m.find('[data-pos-done-wa]').attr('href', r.data.wa || '#').toggle(!!r.data.wa);
				$m.prop('hidden', false).addClass('is-open');
				reset();
			}).fail(function () { toast('Network problem - the sale was not saved. Please try again.', 'err'); })
			.always(function () { $b.removeClass('is-loading'); draw(); });
		}

		/* Events */
		$d.on('click', '.pos-tile', function () { var p = JSON.parse($(this).attr('data-p')); if (!p.in && !window.confirm('This item shows as out of stock. Sell it anyway?')) { return; } add(p); $(this).addClass('is-added'); var b = this; setTimeout(function () { $(b).removeClass('is-added'); }, 400); });
		$d.on('input', '[data-pos-q]', function () { var v = this.value; clearTimeout(qTimer); qTimer = setTimeout(function () { load(v); }, 250); });
		$d.on('keydown', '[data-pos-q]', function (e) { if (e.key === 'Enter') { var $f = $('.pos-tile').first(); if ($f.length) { e.preventDefault(); $f.trigger('click'); this.select(); } } });
		$d.on('click', '[data-pos-cats] button', function () { $('[data-pos-cats] button').removeClass('is-on'); $(this).addClass('is-on'); cat = Number($(this).attr('data-cat')) || 0; $('[data-pos-q]').val(''); load(''); });
		$d.on('click', '.pos-line__qty button', function () { var l = cart[$(this).attr('data-i')]; l.qty = Math.max(1, l.qty + Number($(this).attr('data-d'))); draw(); save(); });
		$d.on('change', '.pos-line input', function () { var l = cart[$(this).attr('data-i')], f = $(this).attr('data-f'); l[f] = f === 'qty' ? Math.max(1, parseInt(this.value, 10) || 1) : Math.max(0, Number(this.value) || 0); draw(); save(); });
		$d.on('click', '[data-x]', function () { cart.splice(Number($(this).attr('data-x')), 1); draw(); save(); });
		$d.on('input change', '[data-pos-disc], [data-pos-disctype], [data-pos-del]', draw);
		$d.on('input', '[data-pos-tender]', change);
		$d.on('click', '[data-pos-methods] button', function () { $('[data-pos-methods] button').removeClass('is-on'); $(this).addClass('is-on'); method = $(this).attr('data-m'); draw(); });
		$d.on('click', '[data-pos-clear]', function () { if (!cart.length || window.confirm('Clear this sale?')) { reset(); } });
		$d.on('click', '[data-pos-charge]', function () { submit(true); });
		$d.on('click', '[data-pos-invoice]', function () { submit(false); });
		$d.on('click', '[data-pos-new]', function () { $('[data-pos-done]').removeClass('is-open').prop('hidden', true); $('[data-pos-q]').trigger('focus'); });
		$d.on('click', '[data-pos-done-email]', function () {
			if (!last) { return; }
			var to = window.prompt('E-mail the ' + (last.paid ? 'receipt' : 'invoice') + ' to' + (last.email ? ' (the customer\'s e-mail on this sale):' : ':'), last.email || '');
			if (!to) { return; }
			to = $.trim(to);
			$.post(P.ajax, { action: 'nurax_pos_order', nonce: P.nonce, op: 'email', id: last.id, to: to }).done(function (r) { toast(r && r.data && r.data.message ? r.data.message : 'Done', r && r.success ? 'ok' : 'err'); });
		});
		$d.on('change input', '[data-pos-c]', save);

		/* Custom item */
		var $cm = $('[data-pos-custom-modal]');
		$d.on('click', '[data-pos-custom]', function () { $cm.prop('hidden', false).addClass('is-open'); $cm.find('[data-pos-cname]').trigger('focus'); });
		$d.on('click', '[data-pos-custom-close]', function () { $cm.removeClass('is-open').prop('hidden', true); });
		$d.on('click', '.pos-custom__quick button', function () { $cm.find('[data-pos-cname]').val($(this).attr('data-q')); $cm.find('[data-pos-cprice]').trigger('focus'); });
		$d.on('click', '[data-pos-cadd]', function () {
			var n = $.trim($cm.find('[data-pos-cname]').val()), pr = Number($cm.find('[data-pos-cprice]').val()) || 0;
			if (!n) { toast('Add a description.', 'err'); return; }
			add({ id: 'c' + Date.now(), name: n, price: pr, custom: true });
			$cm.find('input').val(''); $cm.removeClass('is-open').prop('hidden', true);
		});

		/* Customer search */
		var $cl = $('[data-pos-clist]');
		$d.on('input', '[data-pos-cq]', function () {
			var v = $.trim(this.value); clearTimeout(custTimer);
			if (v.length < 2) { $cl.prop('hidden', true); return; }
			custTimer = setTimeout(function () {
				$.get(P.ajax, { action: 'nurax_pos_customers', nonce: P.nonce, q: v }).done(function (r) {
					var list = r && r.success ? r.data : [];
					var digits = v.replace(/\D/g, ''), isPhone = digits.length >= 9;
					var html = list.map(function (c, i) { return '<li><button type="button" data-ci="' + i + '"><strong>' + esc(c.name || '(no name)') + '</strong><small>' + esc([c.phone ? '+' + c.phone : '', c.email, c.orders ? c.orders + ' orders - ' + ksh(c.spent) : ''].filter(Boolean).join(' - ')) + '</small></button></li>'; }).join('');
					html += '<li><button type="button" data-cnew><strong>+ New customer: ' + esc(v) + '</strong><small>Fill in the details below</small></button></li>';
					$cl.data('list', list).data('q', v).data('phone', isPhone).html(html).prop('hidden', false);
				});
			}, 250);
		});
		$d.on('click', '[data-ci]', function () { var c = $cl.data('list')[$(this).attr('data-ci')]; setCust(c); $cl.prop('hidden', true); $('[data-pos-cq]').val(''); save(); });
		$d.on('click', '[data-cnew]', function () { var v = $cl.data('q'); setCust($cl.data('phone') ? { phone: v } : { name: v }); $cl.prop('hidden', true); $('[data-pos-cq]').val(''); $('[data-pos-c=' + ($cl.data('phone') ? 'name' : 'phone') + ']').trigger('focus'); });
		$d.on('click', function (e) { if (!$(e.target).closest('.pos__cust-search').length) { $cl.prop('hidden', true); } });
		$d.on('keydown', function (e) { if (e.key === '/' && !$(e.target).is('input, textarea, select')) { e.preventDefault(); $('[data-pos-q]').trigger('focus'); } if (e.key === 'F9' && cart.length) { e.preventDefault(); submit(true); } });

		/* Start */
		if (P.pre && P.pre.items) {
			cart = []; P.pre.items.forEach(function (p) { add(p); });
			setCust(P.pre.customer); $('[data-pos-del]').val(P.pre.delivery || 0); $('[data-pos-delto]').val(P.pre.delivery_to || '');
			$('[data-pos-channel]').val('whatsapp'); waOrder = P.pre.wa_order || 0; $('[data-pos-note]').val(P.pre.ref ? 'WhatsApp order ' + P.pre.ref : '');
			toast('WhatsApp order loaded - check and charge.', 'ok');
		} else {
			try { var s = JSON.parse(sessionStorage.getItem(KEY) || 'null'); if (s && s.cart) { cart = s.cart; setCust(s.cust); waOrder = s.waOrder || 0; } } catch (e) {}
		}
		draw(); load('');
	}

	/* ================= Marketing ================= */
	var M = window.NURAX_MKT || null;
	if (M) {
		var $f = $('[data-mkt-form]');
		if ($f.length) {
			var pt;
			var count = function () {
				$.post(M.ajax, { action: 'nurax_mkt', nonce: M.nonce, op: 'count', segment: $f.find('[name=segment]').val(), seg_value: $f.find('[name=seg_value]').val(), channel: M.channel }).done(function (r) {
					var n = r && r.success ? r.data.count : 0;
					$('[data-mkt-count]').text(n + ' ' + (M.channel === 'email' ? 'e-mail' : 'WhatsApp') + ' recipients').data('n', n);
				});
			};
			var preview = function () {
				if (M.channel === 'whatsapp') {
					var t = String($f.find('[name=body]').val() || '').replace(/\{first\}/g, 'Wanjiku').replace(/\{name\}/g, 'Wanjiku Kamau');
					var bt = $f.find('[name=btn_text]').val(), bu = $f.find('[name=btn_url]').val();
					if (bu) { t += '\n\n' + (bt ? bt + ': ' : '') + bu; }
					$('[data-mkt-wa]').text($.trim(t) + '\n\nReply STOP to opt out.');
					return;
				}
				clearTimeout(pt);
				pt = setTimeout(function () {
					var d = { action: 'nurax_mkt', nonce: M.nonce, op: 'preview', products: ($f.find('[data-mkt-prods]').val() || []).join(',') };
					['subject', 'heading', 'body', 'btn_text', 'btn_url', 'image'].forEach(function (k) { d[k] = $f.find('[name=' + k + ']').val() || ''; });
					$.post(M.ajax, d).done(function (r) { if (r && r.success) { var fr = $('[data-mkt-frame]')[0]; if (fr) { fr.srcdoc = r.data.html; } } });
				}, 450);
			};
			$f.on('change', '[name=segment], [name=seg_value]', function () { $('[data-mkt-tag]').prop('hidden', $f.find('[name=segment]').val() !== 'tag'); count(); });
			$f.on('input change', '[data-mkt-in], [data-mkt-prods], [name=btn_text], [name=btn_url]', preview);
			$f.on('click', '[data-mkt-btn]', function (e) {
				var op = $(this).attr('data-mkt-btn');
				if (op === 'send') {
					var n = $('[data-mkt-count]').data('n') || 0;
					if (!n) { e.preventDefault(); toast('Nobody matches this audience yet.', 'err'); return; }
					if (!window.confirm('Send this ' + (M.channel === 'email' ? 'e-mail' : 'WhatsApp message') + ' to ' + n + ' people now?')) { e.preventDefault(); return; }
				}
				$f.find('[data-mkt-do]').val(op);
			});
			$d.on('click', '[data-mkt-media]', function () {
				if (!window.wp || !wp.media) { return; }
				var fr = wp.media({ title: 'Choose banner image', multiple: false, library: { type: 'image' } });
				fr.on('select', function () { var a = fr.state().get('selection').first().toJSON(); $f.find('[data-mkt-img]').val(a.url).trigger('change'); });
				fr.open();
			});
			count(); preview();
		}
		/* Sending progress (e-mail / API). */
		if (M.run) {
			var step = function () {
				$.post(M.ajax, { action: 'nurax_mkt', nonce: M.nonce, op: 'tick', id: M.run }).done(function (r) {
					if (!r || !r.success) { return; }
					var d = r.data, pct = d.total ? Math.round((d.sent + d.failed) / d.total * 100) : 100;
					$('[data-mkt-ring]').css('--p', pct); $('[data-mkt-pct]').text(pct + '%'); $('[data-mkt-sent]').text(d.sent); $('[data-mkt-failed]').text(d.failed);
					$('[data-mkt-status]').text(d.left ? 'Sending' : 'Sent');
					if (d.left) { setTimeout(step, 800); } else { toast('Campaign sent', 'ok'); }
				}).fail(function () { setTimeout(step, 5000); });
			};
			step();
		}
		/* WhatsApp one-by-one. */
		var mark = function (sid) {
			$.post(M.ajax, { action: 'nurax_mkt', nonce: M.nonce, op: 'wa_mark', sid: sid });
			$('[data-mkt-item="' + sid + '"]').addClass('is-done').find('[data-mkt-st]').text('Sent');
		};
		$d.on('click', '[data-mkt-wa-send]', function () { mark($(this).attr('data-mkt-wa-send')); });
		$d.on('click', '[data-mkt-next]', function () {
			var $a = $('[data-mkt-item]').not('.is-done').first().find('[data-mkt-wa-send]');
			if (!$a.length) { toast('Everyone has been messaged.', 'ok'); return; }
			window.open($a.attr('href'), '_blank', 'noopener'); mark($a.attr('data-mkt-wa-send'));
			var left = $('[data-mkt-item]').not('.is-done').length;
			$(this).text(left ? 'Send next (' + left + ' left)' : 'All sent');
		});
	}

	/* ---------- Invoice logo picker (Settings) ---------- */
	$('[data-nd-logo]').each(function () {
		var $w = $(this), $in = $w.find('[data-nd-logo-input]'), $prev = $w.find('[data-nd-logo-prev]'),
			$img = $prev.find('img'), $empty = $prev.find('.nd-logo__empty'), $st = $w.find('[data-nd-logo-status]'),
			$pick = $w.find('[data-nd-logo-pick]'), $rm = $w.find('[data-nd-logo-remove]'), site = $w.data('site') || '', frame;
		function show(url, own) {
			if (url) { $img.attr('src', url).prop('hidden', false); $empty.prop('hidden', true); $prev.addClass('has-img'); }
			else { $img.attr('src', '').prop('hidden', true); $empty.prop('hidden', false); $prev.removeClass('has-img'); }
			$rm.prop('hidden', !own);
			$pick.text(own ? 'Change logo' : 'Upload logo');
			$st.text(own ? 'New logo selected - click Save settings to apply.' : (site ? 'Using your site logo. Click Save settings to apply.' : 'No logo - invoices will show the NURA wordmark.'));
		}
		$pick.on('click', function (e) {
			e.preventDefault();
			if (!window.wp || !wp.media) { var u = window.prompt('Paste the image link of your logo:', $in.val()); if (u) { $in.val(u); show(u, true); } return; }
			if (!frame) {
				frame = wp.media({ title: 'Choose your invoice logo', button: { text: 'Use this logo' }, library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var url = (a.sizes && a.sizes.large ? a.sizes.large.url : a.url);
					if (a.mime === 'image/svg+xml' || !a.sizes) { url = a.url; }
					$in.val(url); show(url, true);
				});
			}
			frame.open();
		});
		$rm.on('click', function (e) { e.preventDefault(); $in.val(''); show(site, false); });
	});
})(jQuery);
