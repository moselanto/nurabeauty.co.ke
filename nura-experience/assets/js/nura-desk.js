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
