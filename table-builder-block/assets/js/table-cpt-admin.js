/**
 * Copy-to-clipboard behavior for the Tables admin list table's shortcode column.
 *
 * @file   Handles the "Copy" button used by TableCPTAdmin's shortcode/child-shortcode
 *         columns: copies the button's shortcode text to the clipboard and shows a
 *         brief toast notification with the result.
 * @since  Unknown
 */

(function () {
	/**
	 * Timeout ID for the currently pending toast auto-hide, if any.
	 *
	 * @since Unknown
	 *
	 * @type {number}
	 */
	var timer = 0;

	/**
	 * Lazily-created toast DOM element, reused across calls.
	 *
	 * @since Unknown
	 *
	 * @type {Element|undefined}
	 */
	var toastElement;

	/**
	 * Localized strings passed from PHP via wp_localize_script().
	 *
	 * @since Unknown
	 *
	 * @type {Object}
	 */
	var i18n = window.tablekitCptAdmin || {};

	/**
	 * Shows a brief toast message, creating the toast element on first use.
	 *
	 * @since Unknown
	 *
	 * @param {string}  message Text to display in the toast.
	 * @param {boolean} ok      Whether to style the toast as success (true) or failure (false).
	 * @return {void}
	 */
	function toast(message, ok) {
		if (!toastElement) {
			toastElement = document.createElement('div');
			toastElement.id = 'tbk-toast';
			document.body.appendChild(toastElement);
		}

		toastElement.textContent = message;
		toastElement.style.background = ok ? '#024b2e' : '#b32d2e';
		toastElement.style.opacity = 1;
		toastElement.style.transform = 'translateX(-50%) translateY(0)';

		clearTimeout(timer);
		timer = setTimeout(function () {
			toastElement.style.opacity = 0;
			toastElement.style.transform = 'translateX(-50%) translateY(-8px)';
		}, 1400);
	}

	/**
	 * Copies text to the clipboard using a hidden textarea and execCommand(),
	 * for browsers/contexts where the async Clipboard API is unavailable.
	 *
	 * @since Unknown
	 *
	 * @param {string} text Text to copy.
	 * @return {boolean} Whether the copy command succeeded.
	 */
	function fallbackCopy(text) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.style.cssText = 'position:fixed;opacity:0';
		document.body.appendChild(textarea);
		textarea.select();

		var copied = document.execCommand('copy');
		document.body.removeChild(textarea);

		return copied;
	}

	/**
	 * Handles clicks on ".tablekit-copy-shortcode" buttons, copying the
	 * button's "data-copy-text" value to the clipboard and toasting the result.
	 *
	 * @since Unknown
	 *
	 * @listens document#click
	 *
	 * @param {MouseEvent} event The click event.
	 * @return {void}
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest('.tablekit-copy-shortcode');
		if (!button) {
			return;
		}

		var text = button.dataset.copyText;
		if (!text) {
			return;
		}

		(navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject())
			.then(function () {
				toast(i18n.copiedText || 'Copied!', true);
			})
			.catch(function () {
				var copied = fallbackCopy(text);
				toast(copied ? (i18n.copiedText || 'Copied!') : (i18n.failedText || 'Copy failed'), copied);
			});
	});
}());
