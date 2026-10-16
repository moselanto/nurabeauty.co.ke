/* NURA Desk admin (NURA Experience 1.57.0) */
(function ($) {
	'use strict';
	var C = window.NURAX_DESK || {};
	function ksh(n) { return 'KSh ' + Math.round(Number(n) || 0).toLocaleString('en-KE'); }

	/* Confirm before destructive status changes. */
	$(document).on('submit', 'form[data-nd-confirm]', function (e) {
		if (!window.confirm($(this).attr('data-nd-confirm'))) { e.preventDefault(); }
	});

	/* Void a payment: ask for a reason. */
	$(document).on('submit', 'form[data-nd-void]', function (e) {
		var why = window.prompt(C.confirmVoid || 'Void this payment? Reason:');
		if (why === null) { e.preventDefault(); return; }
		$(this).find('input[name=reason]').val(why);
	});

	/* Quick amount chips. */
	$(document).on('click', '[data-nd-amt]', function () {
		$(this).closest('form').find('input[name=amount]').val($(this).attr('data-nd-amt')).trigger('focus');
	});

	/* Copy statement link. */
	$(document).on('click', '[data-nd-copy]', function () {
		var b = $(this), v = b.attr('data-nd-copy'), t = b.text();
		var done = function () { b.text('Link copied'); setTimeout(function () { b.text(t); }, 1600); };
		if (navigator.clipboard) { navigator.clipboard.writeText(v).then(done, function () { window.prompt('Copy this link:', v); }); }
		else { window.prompt('Copy this link:', v); }
	});

	/* Print. */
	$(document).on('click', '[data-nd-print]', function () { window.print(); });

	/* M-Pesa codes in capitals. */
	$(document).on('input', 'input[name=reference], input[name=init_reference]', function () {
		var p = this.selectionStart; this.value = this.value.toUpperCase(); try { this.setSelectionRange(p, p); } catch (x) {}
	});

	/* Plan form: product price lookup + live schedule preview. */
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
