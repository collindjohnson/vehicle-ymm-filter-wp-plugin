(function ($) {
	'use strict';

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data._wpnonce = WYMM.nonce;
		return $.post(WYMM.ajaxUrl, data);
	}

	$('.wymm-filter').each(function () {
		var $form = $(this);
		var $make = $form.find('.wymm-f-make');
		var $model = $form.find('.wymm-f-model');
		var $year = $form.find('.wymm-f-year');

		ajax('wymm_get_makes').done(function (r) {
			$make.empty().append('<option value="">Make</option>');
			if (r.success) {
				r.data.forEach(function (m) {
					$make.append($('<option>').val(m.slug).text(m.name));
				});
			}
		});

		$make.on('change', function () {
			var slug = $(this).val();
			$model.prop('disabled', true).empty().append('<option value="">Loading…</option>');
			$year.prop('disabled', true).empty().append('<option value="">Year</option>');
			if (!slug) { $model.empty().append('<option value="">Model</option>'); return; }
			ajax('wymm_get_models', { make: slug }).done(function (r) {
				$model.empty().append('<option value="">Model</option>');
				if (r.success) {
					r.data.forEach(function (x) { $model.append($('<option>').val(x.slug).text(x.name)); });
					$model.prop('disabled', false);
				}
			});
		});

		$model.on('change', function () {
			var makeSlug = $make.val();
			var modelSlug = $(this).val();
			$year.prop('disabled', true).empty().append('<option value="">Loading…</option>');
			if (!makeSlug || !modelSlug) { $year.empty().append('<option value="">Year</option>'); return; }
			ajax('wymm_get_years', { make: makeSlug, model: modelSlug }).done(function (r) {
				$year.empty().append('<option value="">Year</option>');
				if (r.success) {
					r.data.forEach(function (y) { $year.append($('<option>').val(y).text(y)); });
					$year.prop('disabled', false);
				}
			});
		});

		$form.on('submit', function (e) {
			if (!$make.val() || !$model.val() || !$year.val()) {
				e.preventDefault();
				alert('Please select year, make, and model.');
			}
		});
	});
})(jQuery);
