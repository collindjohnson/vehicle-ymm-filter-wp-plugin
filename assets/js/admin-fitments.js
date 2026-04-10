(function ($) {
	'use strict';

	var makesCache = null;

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data._wpnonce = WYMM.nonce;
		return $.post(WYMM.ajaxUrl, data);
	}

	function loadMakes($sel) {
		function fill(list) {
			$sel.empty().append('<option value="">Make</option>');
			list.forEach(function (m) {
				$sel.append($('<option>').val(m.slug).text(m.name));
			});
		}
		if (makesCache) { fill(makesCache); return; }
		ajax('wymm_get_makes').done(function (r) {
			if (r.success) { makesCache = r.data; fill(r.data); }
			else { $sel.empty().append('<option value="">(error loading makes)</option>'); }
		}).fail(function () {
			$sel.empty().append('<option value="">(error)</option>');
		});
	}

	function loadModels($editor, makeSlug) {
		var $m = $editor.find('.wymm-model');
		$m.prop('disabled', true).empty().append('<option value="">Loading…</option>');
		$editor.find('.wymm-year').prop('disabled', true).empty().append('<option value="">Year</option>');
		if (!makeSlug) { $m.empty().append('<option value="">Model</option>'); return; }
		ajax('wymm_get_models', { make: makeSlug }).done(function (r) {
			$m.empty().append('<option value="">Model</option>');
			if (r.success) {
				r.data.forEach(function (x) { $m.append($('<option>').val(x.slug).text(x.name)); });
				$m.prop('disabled', false);
			}
		});
	}

	function loadYears($editor, makeSlug, modelSlug) {
		var $y = $editor.find('.wymm-year');
		$y.prop('disabled', true).empty().append('<option value="">Loading…</option>');
		if (!makeSlug || !modelSlug) { $y.empty().append('<option value="">Year</option>'); return; }
		ajax('wymm_get_years', { make: makeSlug, model: modelSlug }).done(function (r) {
			$y.empty().append('<option value="">Year</option>');
			if (r.success) {
				r.data.forEach(function (yr) { $y.append($('<option>').val(yr).text(yr)); });
				$y.prop('disabled', false);
			}
		});
	}

	function addRow($editor, makeSlug, makeName, modelSlug, modelName, year) {
		var field = $editor.data('field');
		var $tbody = $editor.find('.wymm-list tbody');
		var dupe = $tbody.find('tr').filter(function () {
			var $t = $(this);
			return $t.data('make') == makeSlug && $t.data('model') == modelSlug && String($t.data('year')) === String(year);
		});
		if (dupe.length) return;
		var $tr = $('<tr>')
			.attr('data-make', makeSlug)
			.attr('data-model', modelSlug)
			.attr('data-year', year);
		$tr.append($('<td>').text(makeName).append('<input type="hidden" name="' + field + '[make_slug][]" value="' + makeSlug + '">'));
		$tr.append($('<td>').text(modelName).append('<input type="hidden" name="' + field + '[model_slug][]" value="' + modelSlug + '">'));
		$tr.append($('<td>').text(year).append('<input type="hidden" name="' + field + '[year][]" value="' + year + '">'));
		$tr.append($('<td>').append('<button type="button" class="button wymm-remove">Remove</button>'));
		$tbody.append($tr);
	}

	$(document).on('focus', '.wymm-editor .wymm-make', function () {
		var $sel = $(this);
		if ($sel.data('loaded')) return;
		$sel.data('loaded', true);
		loadMakes($sel);
	});

	$(document).on('change', '.wymm-editor .wymm-make', function () {
		var $editor = $(this).closest('.wymm-editor');
		loadModels($editor, $(this).val());
	});

	$(document).on('change', '.wymm-editor .wymm-model', function () {
		var $editor = $(this).closest('.wymm-editor');
		loadYears($editor, $editor.find('.wymm-make').val(), $(this).val());
	});

	$(document).on('click', '.wymm-editor .wymm-add-one', function () {
		var $editor = $(this).closest('.wymm-editor');
		var $mk = $editor.find('.wymm-make');
		var $md = $editor.find('.wymm-model');
		var $yr = $editor.find('.wymm-year');
		if (!$mk.val() || !$md.val() || !$yr.val()) { alert('Select make, model, and year.'); return; }
		addRow($editor, $mk.val(), $mk.find('option:selected').text(), $md.val(), $md.find('option:selected').text(), $yr.val());
	});

	$(document).on('click', '.wymm-editor .wymm-add-range', function () {
		var $editor = $(this).closest('.wymm-editor');
		var $mk = $editor.find('.wymm-make');
		var $md = $editor.find('.wymm-model');
		var from = parseInt($editor.find('.wymm-year-from').val(), 10);
		var to = parseInt($editor.find('.wymm-year-to').val(), 10);
		if (!$mk.val() || !$md.val()) { alert('Select make and model first.'); return; }
		if (!from || !to || from > to) { alert('Enter a valid year range.'); return; }
		for (var y = from; y <= to; y++) {
			addRow($editor, $mk.val(), $mk.find('option:selected').text(), $md.val(), $md.find('option:selected').text(), y);
		}
	});

	$(document).on('click', '.wymm-editor .wymm-remove', function () {
		$(this).closest('tr').remove();
	});
})(jQuery);
