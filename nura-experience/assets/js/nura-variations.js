/* NURA variation swatches: drive the native <select> so WooCommerce variations
   keep working (price/image/stock), and mirror availability onto the swatches. */
(function ($) {
	'use strict';
	if (typeof $ === 'undefined') { return; }

	function initForm($form) {
		$form.find('.nura-swatches').each(function () {
			var $wrap = $(this);
			var $select = $wrap.nextAll('select').first();
			if (!$select.length) { $select = $wrap.closest('td, .value').find('select').first(); }
			if (!$select.length || $wrap.data('nuraBound')) { return; }
			$wrap.data('nuraBound', true);
			$select.addClass('nura-hidden-select');

			$wrap.on('click', '.nura-swatch', function (e) {
				e.preventDefault();
				if ($(this).hasClass('is-unavailable')) { return; }
				$select.val(String($(this).data('value'))).trigger('change');
			});

			$select.on('change', function () {
				var v = String($select.val());
				$wrap.find('.nura-swatch').each(function () {
					$(this).toggleClass('is-active', String($(this).data('value')) === v);
				});
			});
		});
	}

	function mirrorAvailability($form) {
		$form.find('.nura-swatches').each(function () {
			var $wrap = $(this);
			var $select = $wrap.nextAll('select').first();
			if (!$select.length) { $select = $wrap.closest('td, .value').find('select').first(); }
			if (!$select.length) { return; }
			$wrap.find('.nura-swatch').each(function () {
				var val = String($(this).data('value'));
				var $opt = $select.find('option').filter(function () { return String($(this).val()) === val; });
				var unavailable = $opt.length ? $opt.is(':disabled') : true;
				$(this).toggleClass('is-unavailable', !!unavailable);
			});
		});
	}

	$(function () {
		$('.variations_form').each(function () { initForm($(this)); });
	});

	$(document.body).on('wc_variation_form', function (e) { initForm($(e.target)); });
	$(document.body).on('woocommerce_update_variation_values', function () {
		$('.variations_form').each(function () { mirrorAvailability($(this)); });
	});
	$(document.body).on('reset_data', function (e) {
		var $form = $(e.target).closest('.variations_form');
		if ($form.length) {
			$form.find('.nura-swatch').removeClass('is-active');
		}
	});
})(window.jQuery);
