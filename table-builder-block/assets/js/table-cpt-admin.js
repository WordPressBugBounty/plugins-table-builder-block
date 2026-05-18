(function () {
	var timer = 0;
	var toastElement;
	var i18n = window.tablekitCptAdmin || {};

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
