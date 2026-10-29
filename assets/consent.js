jQuery(function ($) {
	'use strict';

	var cfg = window.CartRenewConsent || {};
	var i18n = cfg.i18n || {};
	var isCheckout = cfg.context === 'checkout';
	var $box = $('#cartrenew-consent-box');

	// WooCommerce Blocks render via React after page load, so the target
	// container may not exist yet — poll briefly for it.
	var blockSelectors = isCheckout
		? [
			'.wp-block-woocommerce-checkout-payment-block',
			'.wc-block-checkout__payment-method',
			'.wp-block-woocommerce-checkout-actions-block'
		]
		: [
			'.wp-block-woocommerce-cart-totals-block',
			'.wp-block-woocommerce-cart-order-summary-block',
			'.wc-block-cart__totals-title'
		];
	// Block cart: the items and totals blocks are two columns of one flex row,
	// so inserting the box between them makes it a third column and breaks the
	// layout. Put it at the end of the items column instead.
	var blockCartMain = '.wp-block-woocommerce-cart-items-block, .wc-block-cart__main';
	var classicSelectors = isCheckout
		? ['#payment', '.woocommerce-billing-fields', 'form.checkout']
		: ['.cart-collaterals', '.woocommerce-cart-form'];

	// Checkout fields (classic ids use underscores, Blocks ids use dashes).
	var phoneSelector = '#billing_phone, #billing-phone, #shipping-phone, #shipping_phone';
	var nameSelector = '#billing_first_name, #billing-first_name, #shipping-first_name';
	var countrySelector = '#billing_country, #billing-country, #shipping-country';

	function tryRelocate(attempt) {
		if (!$box.length) {
			return;
		}

		// If a classic PHP hook already rendered the box in position (not the
		// hidden footer copy), don't inject a second one.
		if ($box.css('display') !== 'none') {
			return;
		}

		var $target = null;
		var i;
		if (!isCheckout) {
			var $main = $(blockCartMain).first();
			if ($main.length) {
				$box.appendTo($main).show();
				return;
			}
		}
		for (i = 0; i < blockSelectors.length; i++) {
			var $found = $(blockSelectors[i]).first();
			if ($found.length) {
				$target = $found;
				break;
			}
		}
		if (!$target) {
			for (i = 0; i < classicSelectors.length; i++) {
				var $c = $(classicSelectors[i]).first();
				if ($c.length) {
					$target = $c;
					break;
				}
			}
		}

		if ($target) {
			$box.insertBefore($target).show();
			return;
		}

		// DOM (block version) still loading — retry a few times.
		if (attempt < 20) {
			setTimeout(function () { tryRelocate(attempt + 1); }, 250);
		} else {
			// Last resort: append near the end of the page body so the form
			// isn't lost entirely, even if placement isn't ideal.
			$box.appendTo('body').show();
		}
	}

	function firstValue(selector) {
		var value = '';
		$(selector).each(function () {
			var current = String($(this).val() || '').trim();
			if (current) {
				value = current;
				return false;
			}
		});
		return value;
	}

	// Build digits-only E.164 (no "+"): keep numbers typed with a country code,
	// otherwise prefix the calling code of the billing country (checkout) or the
	// store's base country (cart).
	function normalizePhone(raw, country) {
		var trimmed = String(raw || '').trim();
		var digits = trimmed.replace(/\D/g, '');
		var codes = cfg.calling_codes || {};
		var cc = (country && codes[country]) || cfg.default_cc || '';

		// Already international ("+44 ..." or "0044 ...") — keep as typed.
		if (trimmed.charAt(0) === '+') {
			return digits;
		}
		if (digits.indexOf('00') === 0) {
			return digits.substring(2);
		}

		// National number: drop the trunk "0" and add the country's code.
		digits = digits.replace(/^0+/, '');
		if (cc && digits.length > 0 && digits.length <= 10) {
			return cc + digits;
		}
		return digits;
	}

	var saveTimer = null;

	function flash(text) {
		var $status = $('#cartrenew-consent-status');
		$status.text(text);
		setTimeout(function () { $status.text(''); }, isCheckout && text === i18n.need_phone ? 4000 : 1500);
	}

	function save() {
		var data = {
			action: 'cartrenew_save_consent',
			nonce: cfg.nonce,
			consent: $('#cartrenew_consent').is(':checked') ? 'yes' : 'no'
		};

		if (isCheckout) {
			var rawPhone = firstValue(phoneSelector);
			var name = firstValue(nameSelector);
			if (rawPhone) {
				data.phone = normalizePhone(rawPhone, firstValue(countrySelector));
			} else if (data.consent === 'yes') {
				flash(i18n.need_phone || '');
			}
			if (name) {
				data.name = name;
			}
		} else {
			data.phone = normalizePhone($('#cartrenew_phone').val() || '', '');
		}

		$.post(cfg.ajax_url, data).done(function () {
			if (!(isCheckout && !data.phone && data.consent === 'yes')) {
				flash(i18n.saved || 'Saved');
			}
		});
	}

	function debouncedSave() {
		clearTimeout(saveTimer);
		saveTimer = setTimeout(save, 600);
	}

	// Delegated handlers: the box can be relocated and Blocks re-render inputs.
	$(document).on('change', '#cartrenew_consent', save);
	if (isCheckout) {
		$(document).on('input change blur', phoneSelector + ', ' + nameSelector + ', ' + countrySelector, function () {
			// Only keep the server copy fresh once the shopper has opted in.
			if ($('#cartrenew_consent').is(':checked')) {
				debouncedSave();
			}
		});
	} else {
		$(document).on('input', '#cartrenew_phone', debouncedSave);
	}

	tryRelocate(0);
});
