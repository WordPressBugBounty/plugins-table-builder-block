(function () {
	'use strict';

	var REST_URL  = window.tablekitEditorData.restUrl;
	var NONCE     = window.tablekitEditorData.nonce;
	var ALL_LABEL = window.tablekitEditorData.allLabel;
	var LOADING   = window.tablekitEditorData.loading;
	var WIDGET    = 'tablekit_table';
	var cache     = {};
	var CACHE_TTL = 60000;

	function fetchBlocks(tableId) {
		var entry = cache[tableId];
		if (entry && (Date.now() - entry.ts) < CACHE_TTL) {
			return Promise.resolve(entry.data);
		}
		return fetch(REST_URL + '?table_id=' + encodeURIComponent(tableId), {
			headers: { 'X-WP-Nonce': NONCE }
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				var blocks = Array.isArray(data) ? data : [];
				cache[tableId] = { data: blocks, ts: Date.now() };
				return blocks;
			})
			.catch(function () { return []; });
	}

	function getPanel()  { return document.querySelector('.elementor-panel'); }
	function getSelect() {
		var p = getPanel();
		return p ? p.querySelector('select[data-setting="block_index"]') : null;
	}

	function populateSelect(options, selectedValue) {
		var select = getSelect();
		if (!select) { return; }
		select.innerHTML = '';
		Object.keys(options).forEach(function (val) {
			var opt         = document.createElement('option');
			opt.value       = val;
			opt.textContent = options[val];
			if (String(val) === String(selectedValue)) { opt.selected = true; }
			select.appendChild(opt);
		});
		_settingInternally = true;
		select.dispatchEvent(new Event('change', { bubbles: true }));
		_settingInternally = false;
	}

	function hidePickerRow() {
		var select = getSelect();
		if (select) {
			populateSelect({ '': ALL_LABEL }, '');
			var row = select.closest('.elementor-control');
			if (row) { row.style.display = 'none'; }
		}
	}

	function showPickerRow() {
		var select = getSelect();
		if (select) {
			var row = select.closest('.elementor-control');
			if (row) { row.style.display = ''; }
		}
	}

	var _settingInternally = false;

	function updateBlockPicker(model, tableId, savedBlockIndex) {
		_settingInternally = true;
		model.setSetting('block_index', '');
		_settingInternally = false;

		if (!tableId) {
			hidePickerRow();
			return;
		}

		showPickerRow();
		populateSelect({ '': LOADING }, '');

		fetchBlocks(tableId).then(function (blocks) {
			if (blocks.length < 2) {
				hidePickerRow();
				return;
			}

			var options = { '': ALL_LABEL };
			blocks.forEach(function (block) {
				options[String(block.index)] = block.label;
			});

			populateSelect(options, savedBlockIndex || '');
			showPickerRow();
		});
	}

	function waitForSelect(callback) {
		var panel = getPanel();
		if (!panel) { callback(); return; }
		if (getSelect()) { callback(); return; }

		var observer = new MutationObserver(function () {
			if (getSelect()) {
				observer.disconnect();
				callback();
			}
		});
		observer.observe(panel, { childList: true, subtree: true });
		setTimeout(function () { observer.disconnect(); }, 3000);
	}

	window.elementor.hooks.addAction('panel/open_editor/widget/' + WIDGET, function (panel, model) {

		waitForSelect(function () {
			var initialId = model.getSetting('table_id');
			if (Array.isArray(initialId)) { initialId = initialId[0] || ''; }
			var savedBlock = model.getSetting('block_index') || '';
			updateBlockPicker(model, String(initialId || ''), savedBlock);
		});

		model.off('change:settings', onSettingsChange);
		model.on('change:settings', onSettingsChange);

		jQuery(document).off('change.tablekit-table-picker');
		jQuery(document).on(
			'change.tablekit-table-picker',
			'.elementor-panel select[data-setting="table_id"]',
			function () {
				var newId = jQuery(this).val() || '';
				if (Array.isArray(newId)) { newId = newId[0] || ''; }
				updateBlockPicker(model, String(newId), '');
			}
		);

		jQuery(document).off('change.tablekit-block-picker');
		jQuery(document).on(
			'change.tablekit-block-picker',
			'.elementor-panel select[data-setting="block_index"]',
			function () {
				if (_settingInternally) { return; }
				var val = jQuery(this).val() || '';
				_settingInternally = true;
				model.setSetting('block_index', val);
				_settingInternally = false;
			}
		);

		function onSettingsChange(changedModel) {
			if (_settingInternally) { return; }
			var changed = changedModel.changed || {};
			if (!Object.prototype.hasOwnProperty.call(changed, 'table_id')) { return; }
			var newId = changed.table_id;
			if (Array.isArray(newId)) { newId = newId[0] || ''; }
			updateBlockPicker(model, String(newId || ''), '');
		}
	});

	window.elementor.hooks.addAction('panel/close_editor', function () {
		jQuery(document).off('change.tablekit-table-picker');
		jQuery(document).off('change.tablekit-block-picker');
	});

})();