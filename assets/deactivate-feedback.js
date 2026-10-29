jQuery(function ($) {
	'use strict';

	var cfg = window.CartRenewFeedback || {};
	var i18n = cfg.i18n || {};
	var $link = $('tr[data-plugin="' + cfg.basename + '"] .deactivate a');
	if (!$link.length) {
		return;
	}

	var deactivateUrl = '';

	var css =
		'#cr-fb-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100050;display:none;align-items:center;justify-content:center;padding:16px}' +
		'#cr-fb-modal{background:#fff;border-radius:8px;max-width:460px;width:100%;padding:20px 22px;box-shadow:0 10px 40px rgba(0,0,0,.3);max-height:90vh;overflow:auto}' +
		'#cr-fb-modal h2{margin:0 0 6px;font-size:18px}' +
		'#cr-fb-modal p{margin:6px 0 12px}' +
		'#cr-fb-modal label.cr-fb-reason{display:block;margin:8px 0}' +
		'#cr-fb-modal textarea{width:100%;min-height:70px;margin-top:6px}' +
		'#cr-fb-modal .cr-fb-note{font-size:12px;color:#646970}' +
		'#cr-fb-modal .cr-fb-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}';
	$('<style>').text(css).appendTo('head');

	var $overlay = $('<div id="cr-fb-overlay" role="dialog" aria-modal="true"></div>');
	var $modal = $('<div id="cr-fb-modal"></div>').appendTo($overlay);
	$('<h2>').text(i18n.title).appendTo($modal);
	$('<p>').text(i18n.intro).appendTo($modal);

	$.each(cfg.reasons || {}, function (key, label) {
		var $row = $('<label class="cr-fb-reason"></label>');
		$('<input type="radio" name="cr_fb_reason">').val(key).appendTo($row);
		$row.append(document.createTextNode(' ' + label));
		$modal.append($row);
	});

	var $comment = $('<textarea maxlength="500"></textarea>').attr('placeholder', i18n.placeholder).appendTo($modal);
	$('<p class="cr-fb-note">').text(i18n.privacy).appendTo($modal);

	var $actions = $('<div class="cr-fb-actions"></div>').appendTo($modal);
	var $submit = $('<button type="button" class="button button-primary" disabled></button>').text(i18n.submit).appendTo($actions);
	var $skip = $('<button type="button" class="button"></button>').text(i18n.skip).appendTo($actions);
	var $cancel = $('<button type="button" class="button-link"></button>').text(i18n.cancel).appendTo($actions);

	$overlay.appendTo('body');

	function openModal() {
		$overlay.css('display', 'flex');
	}

	function closeModal() {
		$overlay.hide();
	}

	function deactivate() {
		window.location.href = deactivateUrl;
	}

	$link.on('click', function (e) {
		e.preventDefault();
		deactivateUrl = this.href;
		openModal();
	});

	$overlay.on('change', 'input[name="cr_fb_reason"]', function () {
		$submit.prop('disabled', false);
	});

	$submit.on('click', function () {
		var reason = $overlay.find('input[name="cr_fb_reason"]:checked').val();
		if (!reason) {
			return;
		}
		$submit.add($skip).prop('disabled', true);
		$.post(cfg.ajax_url, {
			action: 'cartrenew_wc_feedback',
			nonce: cfg.nonce,
			reason: reason,
			comment: $comment.val()
		}).always(deactivate);
	});

	$skip.on('click', deactivate);
	$cancel.on('click', closeModal);
	$overlay.on('click', function (e) {
		if (e.target === $overlay[0]) {
			closeModal();
		}
	});
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			closeModal();
		}
	});
});
