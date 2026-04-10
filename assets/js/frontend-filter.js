(function ($) {
	'use strict';

	var cfg = window.WYMM || {};
	var i18n = cfg.i18n || {};
	var MAKES_CACHE_KEY = 'wymm_makes_' + (cfg.version || '1');

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data._wpnonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function placeholder(text) {
		return $('<option>').attr('value', '').text(text);
	}

	function readMakesCache() {
		try {
			var raw = window.sessionStorage && window.sessionStorage.getItem(MAKES_CACHE_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function writeMakesCache(list) {
		try {
			if (window.sessionStorage) {
				window.sessionStorage.setItem(MAKES_CACHE_KEY, JSON.stringify(list));
			}
		} catch (e) {
			/* quota or private-mode — ignore */
		}
	}

	function fillMakes($make, list) {
		$make.empty().append(placeholder(i18n.make || 'Make'));
		list.forEach(function (m) {
			$make.append($('<option>').val(m.slug).text(m.name));
		});
	}

	function showFormError($form, message) {
		var $err = $form.find('.wymm-error');
		if (!$err.length) {
			$err = $('<div class="wymm-error" role="alert" style="color:#b32d2e;margin:6px 0;"></div>');
			$form.prepend($err);
		}
		$err.text(message);
		clearTimeout($err.data('timer'));
		$err.data('timer', setTimeout(function () { $err.text(''); }, 5000));
	}

	$('.wymm-filter').each(function () {
		var $form = $(this);
		var $make = $form.find('.wymm-f-make');
		var $model = $form.find('.wymm-f-model');
		var $year = $form.find('.wymm-f-year');

		var cached = readMakesCache();
		if (cached) {
			fillMakes($make, cached);
		} else {
			ajax('wymm_get_makes').done(function (r) {
				if (r && r.success) {
					fillMakes($make, r.data);
					writeMakesCache(r.data);
				} else {
					$make.empty().append(placeholder(i18n.error || '(error)'));
				}
			});
		}

		$make.on('change', function () {
			var slug = $(this).val();
			$model.prop('disabled', true).empty().append(placeholder(i18n.loading || 'Loading\u2026'));
			$year.prop('disabled', true).empty().append(placeholder(i18n.year || 'Year'));
			if (!slug) { $model.empty().append(placeholder(i18n.model || 'Model')); return; }
			ajax('wymm_get_models', { make: slug }).done(function (r) {
				$model.empty().append(placeholder(i18n.model || 'Model'));
				if (r && r.success) {
					r.data.forEach(function (x) { $model.append($('<option>').val(x.slug).text(x.name)); });
					$model.prop('disabled', false);
				}
			});
		});

		$model.on('change', function () {
			var makeSlug = $make.val();
			var modelSlug = $(this).val();
			$year.prop('disabled', true).empty().append(placeholder(i18n.loading || 'Loading\u2026'));
			if (!makeSlug || !modelSlug) { $year.empty().append(placeholder(i18n.year || 'Year')); return; }
			ajax('wymm_get_years', { make: makeSlug, model: modelSlug }).done(function (r) {
				$year.empty().append(placeholder(i18n.year || 'Year'));
				if (r && r.success) {
					r.data.forEach(function (y) { $year.append($('<option>').val(y).text(y)); });
					$year.prop('disabled', false);
				}
			});
		});

		$form.on('submit', function (e) {
			if (!$make.val() || !$model.val() || !$year.val()) {
				e.preventDefault();
				showFormError($form, i18n.selectAll || 'Please select year, make, and model.');
			}
		});
	});
})(jQuery);
