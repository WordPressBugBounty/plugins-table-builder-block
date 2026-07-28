/**
 * Elementor editor integration for the TableKit widget's block picker.
 *
 * @file   Watches the TableKit Elementor widget's "table_id" control and,
 *         whenever it changes, fetches that table's block list from the
 *         tablekit/v1/table-blocks REST route and repopulates the widget's
 *         "block_index" control so the user can target a specific table
 *         block instead of rendering every block in the table.
 * @since  Unknown
 */

(function () {
	'use strict';

	/**
	 * Base REST URL for the table-blocks endpoint, localized from PHP.
	 *
	 * @since Unknown
	 *
	 * @type {string}
	 */
	var REST_URL  = window.tablekitEditorData.restUrl;

	/**
	 * REST nonce used to authenticate the table-blocks fetch, localized from PHP.
	 *
	 * @since Unknown
	 *
	 * @type {string}
	 */
	var NONCE     = window.tablekitEditorData.nonce;

	/**
	 * Label used for the "render every block" option, localized from PHP.
	 *
	 * @since Unknown
	 *
	 * @type {string}
	 */
	var ALL_LABEL = window.tablekitEditorData.allLabel;

	/**
	 * Label shown in the picker while a table's blocks are being fetched, localized from PHP.
	 *
	 * @since Unknown
	 *
	 * @type {string}
	 */
	var LOADING   = window.tablekitEditorData.loading;

	/**
	 * The Elementor widget name this script attaches its panel behavior to.
	 *
	 * @since Unknown
	 *
	 * @type {string}
	 */
	var WIDGET    = 'tablekit_table';

	/**
	 * In-memory cache of fetched block lists, keyed by table ID.
	 *
	 * @since Unknown
	 *
	 * @type {Object}
	 */
	var cache     = {};

	/**
	 * How long a cached block list stays valid, in milliseconds.
	 *
	 * @since Unknown
	 *
	 * @type {number}
	 */
	var CACHE_TTL = 60000;

	/**
	 * Fetches (and caches) the list of table blocks for a given table ID.
	 *
	 * @since Unknown
	 *
	 * @param {string} tableId Post ID of the table to fetch blocks for.
	 * @return {Promise<Array>} Resolves to an array of {index, label, block_name} descriptors,
	 *                          or an empty array on cache miss failure.
	 */
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

	/**
	 * Gets the currently open Elementor panel element.
	 *
	 * @since Unknown
	 *
	 * @return {Element|null} The panel element, or null if not open.
	 */
	function getPanel()  { return document.querySelector('.elementor-panel'); }

	/**
	 * Gets the "block_index" control's select element within the open panel.
	 *
	 * @since Unknown
	 *
	 * @return {Element|null} The select element, or null if not found.
	 */
	function getSelect() {
		var p = getPanel();
		return p ? p.querySelector('select[data-setting="block_index"]') : null;
	}

	/**
	 * Replaces the "block_index" select's options and dispatches a change
	 * event, guarding the model-sync listeners against reacting to it.
	 *
	 * @since Unknown
	 *
	 * @param {Object} options       Option values keyed by block index (or "" for "All").
	 * @param {string} selectedValue Value to mark as selected.
	 * @return {void}
	 */
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

	/**
	 * Hides the "block_index" control's row, after resetting it to "All".
	 *
	 * @since Unknown
	 *
	 * @return {void}
	 */
	function hidePickerRow() {
		var select = getSelect();
		if (select) {
			populateSelect({ '': ALL_LABEL }, '');
			var row = select.closest('.elementor-control');
			if (row) { row.style.display = 'none'; }
		}
	}

	/**
	 * Shows the "block_index" control's row.
	 *
	 * @since Unknown
	 *
	 * @return {void}
	 */
	function showPickerRow() {
		var select = getSelect();
		if (select) {
			var row = select.closest('.elementor-control');
			if (row) { row.style.display = ''; }
		}
	}

	/**
	 * Whether the "block_index" control is currently being updated by this
	 * script (rather than by direct user interaction), used to prevent the
	 * change-event listeners below from reacting to their own updates.
	 *
	 * @since Unknown
	 *
	 * @type {boolean}
	 */
	var _settingInternally = false;

	/**
	 * Refreshes the "block_index" picker for a given table: resets the
	 * setting, shows/hides the row, and repopulates its options from the
	 * table's fetched block list.
	 *
	 * @since Unknown
	 *
	 * @param {Object} model           Elementor widget settings model.
	 * @param {string} tableId         Post ID of the selected table, or an empty string if none.
	 * @param {string} savedBlockIndex Previously saved "block_index" value to restore, if any.
	 * @return {void}
	 */
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

	/**
	 * Waits for the "block_index" select to exist in the DOM (Elementor
	 * renders panel controls asynchronously) before invoking the callback.
	 * Gives up after 3 seconds.
	 *
	 * @since Unknown
	 *
	 * @param {Function} callback Called once the select is found, or after the timeout.
	 * @return {void}
	 */
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

	/**
	 * Sets up the block picker for the TableKit widget's panel: syncs it to
	 * the currently selected table on open, and wires up listeners that keep
	 * it in sync as "table_id"/"block_index" change afterward.
	 *
	 * @since Unknown
	 *
	 * @listens Elementor#panel/open_editor/widget/tablekit_table
	 *
	 * @param {Object} panel The opened widget panel view (unused; kept for hook signature).
	 * @param {Object} model Elementor widget settings model.
	 * @return {void}
	 */
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

		/**
		 * Model "change:settings" handler: refreshes the block picker whenever
		 * the widget's "table_id" setting changes via a means other than the
		 * direct select-element listener above (e.g. dynamic tags, undo/redo).
		 *
		 * @since Unknown
		 *
		 * @listens Backbone.Model#change:settings
		 *
		 * @param {Object} changedModel The settings model that changed.
		 * @return {void}
		 */
		function onSettingsChange(changedModel) {
			if (_settingInternally) { return; }
			var changed = changedModel.changed || {};
			if (!Object.prototype.hasOwnProperty.call(changed, 'table_id')) { return; }
			var newId = changed.table_id;
			if (Array.isArray(newId)) { newId = newId[0] || ''; }
			updateBlockPicker(model, String(newId || ''), '');
		}
	});

	/**
	 * Tears down the table/block picker's jQuery change listeners when the
	 * Elementor panel closes, so they don't leak across widget panels.
	 *
	 * @since Unknown
	 *
	 * @listens Elementor#panel/close_editor
	 *
	 * @return {void}
	 */
	window.elementor.hooks.addAction('panel/close_editor', function () {
		jQuery(document).off('change.tablekit-table-picker');
		jQuery(document).off('change.tablekit-block-picker');
	});

})();