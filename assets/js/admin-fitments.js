(function ($) {
	'use strict';

	var cfg = window.WYMM || {};
	var actions = cfg.actions || {};
	var i18n = cfg.i18n || {};
	var MAX_RANGE = 50;

	var makesCache = null;

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data._wpnonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function showEditorError($editor, message) {
		var $err = $editor.find('.wymm-error');
		if (!$err.length) {
			$err = $('<div class="wymm-error" role="alert" style="color:#b32d2e;margin:6px 0;"></div>');
			$editor.prepend($err);
		}
		$err.text(message);
		clearTimeout($err.data('timer'));
		$err.data('timer', setTimeout(function () { $err.text(''); }, 5000));
	}

	function placeholderOption(text) {
		return $('<option>').attr('value', '').text(text);
	}

	function loadMakes($sel) {
		function fill(list) {
			$sel.empty().append(placeholderOption('Make'));
			list.forEach(function (m) {
				$sel.append($('<option>').val(m.slug).text(m.name));
			});
		}
		if (makesCache) { fill(makesCache); return; }
		ajax(actions.getMakes || 'wymm_admin_get_makes').done(function (r) {
			if (r && r.success) { makesCache = r.data; fill(r.data); }
			else { $sel.empty().append(placeholderOption(i18n.errorLoading || '(error loading makes)')); }
		}).fail(function () {
			$sel.empty().append(placeholderOption(i18n.error || '(error)'));
		});
	}

	function loadModels($editor, makeSlug) {
		var $m = $editor.find('.wymm-model');
		$m.prop('disabled', true).empty().append(placeholderOption(i18n.loading || 'Loading\u2026'));
		$editor.find('.wymm-year').prop('disabled', true).empty().append(placeholderOption(i18n.year || 'Year'));
		if (!makeSlug) { $m.empty().append(placeholderOption(i18n.model || 'Model')); return; }
		ajax(actions.getModels || 'wymm_admin_get_models', { make: makeSlug }).done(function (r) {
			$m.empty().append(placeholderOption(i18n.model || 'Model'));
			if (r && r.success) {
				r.data.forEach(function (x) { $m.append($('<option>').val(x.slug).text(x.name)); });
				$m.prop('disabled', false);
			}
		});
	}

	function loadYears($editor, makeSlug, modelSlug) {
		var $y = $editor.find('.wymm-year');
		$y.prop('disabled', true).empty().append(placeholderOption(i18n.loading || 'Loading\u2026'));
		if (!makeSlug || !modelSlug) { $y.empty().append(placeholderOption(i18n.year || 'Year')); return; }
		ajax(actions.getYears || 'wymm_admin_get_years', { make: makeSlug, model: modelSlug }).done(function (r) {
			$y.empty().append(placeholderOption(i18n.year || 'Year'));
			if (r && r.success) {
				r.data.forEach(function (yr) { $y.append($('<option>').val(yr).text(yr)); });
				$y.prop('disabled', false);
			}
		});
	}

	function addRow($editor, makeSlug, makeName, modelSlug, modelName, year) {
		var field = String($editor.data('field') || '');
		var $tbody = $editor.find('.wymm-list tbody');
		var yearStr = String(year);
		var dupe = $tbody.find('tr').filter(function () {
			var $t = $(this);
			return String($t.data('make')) === String(makeSlug) &&
				String($t.data('model')) === String(modelSlug) &&
				String($t.data('year')) === yearStr;
		});
		if (dupe.length) return;

		var $tr = $('<tr>', {
			'data-make': makeSlug,
			'data-model': modelSlug,
			'data-year': yearStr
		});

		var $tdMake = $('<td>').text(makeName).append(
			$('<input>', { type: 'hidden', name: field + '[make_slug][]', value: makeSlug })
		);
		var $tdModel = $('<td>').text(modelName).append(
			$('<input>', { type: 'hidden', name: field + '[model_slug][]', value: modelSlug })
		);
		var $tdYear = $('<td>').text(yearStr).append(
			$('<input>', { type: 'hidden', name: field + '[year][]', value: yearStr })
		);
		var $tdRemove = $('<td>').append(
			$('<button>', { type: 'button', 'class': 'button wymm-remove' }).text('Remove')
		);

		$tr.append($tdMake).append($tdModel).append($tdYear).append($tdRemove);
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
		if (!$mk.val() || !$md.val() || !$yr.val()) {
			showEditorError($editor, i18n.selectAll || 'Select make, model, and year.');
			return;
		}
		addRow($editor, $mk.val(), $mk.find('option:selected').text(), $md.val(), $md.find('option:selected').text(), $yr.val());
	});

	$(document).on('click', '.wymm-editor .wymm-add-range', function () {
		var $editor = $(this).closest('.wymm-editor');
		var $mk = $editor.find('.wymm-make');
		var $md = $editor.find('.wymm-model');
		var from = parseInt($editor.find('.wymm-year-from').val(), 10);
		var to = parseInt($editor.find('.wymm-year-to').val(), 10);
		if (!$mk.val() || !$md.val()) {
			showEditorError($editor, i18n.selectMakeModel || 'Select make and model first.');
			return;
		}
		if (!from || !to || from > to) {
			showEditorError($editor, i18n.invalidRange || 'Enter a valid year range.');
			return;
		}
		if ((to - from) > MAX_RANGE) {
			showEditorError($editor, i18n.rangeTooLarge || 'Maximum year range is 50 years.');
			return;
		}
		for (var y = from; y <= to; y++) {
			addRow($editor, $mk.val(), $mk.find('option:selected').text(), $md.val(), $md.find('option:selected').text(), y);
		}
	});

	$(document).on('click', '.wymm-editor .wymm-remove', function () {
		$(this).closest('tr').remove();
	});
})(jQuery);
