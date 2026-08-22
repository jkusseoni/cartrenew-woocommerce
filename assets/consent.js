jQuery(function ($) {
	'use strict';

	var $box = $('#cartrenew-consent-box');

	// WooCommerce Blocks (Cart block) renders the cart via React after page
	// load, so the target container may not exist yet — poll briefly for it.
	var blockSelectors = [
		'.wp-block-woocommerce-cart-totals-block',
		'.wp-block-woocommerce-cart-order-summary-block',
		'.wc-block-cart__totals-title'
	];
	var classicSelectors = [
		'.cart-collaterals',
		'.woocommerce-cart-form'
	];

	function tryRelocate(attempt) {
		if (!$box.length) {
			return;
		}

		// If a classic PHP hook already rendered the box in position (not the
		// hidden footer copy), don't inject a second one.
		if ($box.css('display') !== 'none') {
			bindHandlers($box);
			return;
		}

		var $target = null;
		for (var i = 0; i < blockSelectors.length; i++) {
			var $found = $(blockSelectors[i]).first();
			if ($found.length) {
				$target = $found;
				break;
			}
		}
		if (!$target) {
			for (var j = 0; j < classicSelectors.length; j++) {
				var $c = $(classicSelectors[j]).first();
				if ($c.length) {
					$target = $c;
					break;
				}
			}
		}

		if ($target) {
			$box.insertBefore($target).show();
			bindHandlers($box);
			return;
		}

		// Cart DOM (block version) still loading — retry a few times.
		if (attempt < 20) {
			setTimeout(function () { tryRelocate(attempt + 1); }, 250);
		} else {
			// Last resort: append near the end of the page body so the form
			// isn't lost entirely, even if placement isn't ideal.
			$box.appendTo('body').show();
			bindHandlers($box);
		}
	}

	function bindHandlers($scope) {
		var $phone = $scope.find('#cartrenew_phone');
		var $consent = $scope.find('#cartrenew_consent');
		var $status = $scope.find('#cartrenew-consent-status');
		var saveTimer = null;

		function normalizePhone(raw) {
			var digits = raw.replace(/\D/g, '');
			if (digits.length === 10) {
				return '91' + digits;
			}
			if (digits.length === 11 && digits.charAt(0) === '0') {
				return '91' + digits.substring(1);
			}
			return digits;
		}

		function save() {
			$.post(CartRenewConsent.ajax_url, {
				action: 'cartrenew_save_consent',
				nonce: CartRenewConsent.nonce,
				phone: normalizePhone($phone.val() || ''),
				consent: $consent.is(':checked') ? 'yes' : 'no'
			}).done(function () {
				$status.text('Saved');
				setTimeout(function () { $status.text(''); }, 1500);
			});
		}

		function debouncedSave() {
			clearTimeout(saveTimer);
			saveTimer = setTimeout(save, 600);
		}

		$phone.off('input').on('input', debouncedSave);
		$consent.off('change').on('change', save);
	}

	tryRelocate(0);
});
