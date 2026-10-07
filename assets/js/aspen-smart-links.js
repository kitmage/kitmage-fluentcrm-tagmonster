(function () {
	'use strict';

	function getLoadingText() {
		if (typeof window.AspenSmartLinks === 'object' && window.AspenSmartLinks && window.AspenSmartLinks.loadingText) {
			return String(window.AspenSmartLinks.loadingText);
		}
		return 'Loading...';
	}

	function handleSubmit(event) {
		var form = event.target;
		if (!form || !form.getAttribute) {
			return;
		}
		if (form.getAttribute('data-aspen-smart-links') !== '1') {
			return;
		}

		var button = form.querySelector('button[type="submit"], input[type="submit"]');
		if (button) {
			if (button.disabled) {
				return;
			}
			button.disabled = true;
			button.setAttribute('aria-disabled', 'true');

			var loadingText = getLoadingText();
			if (button.tagName && button.tagName.toLowerCase() === 'button') {
				button.textContent = loadingText;
			} else if (typeof button.value === 'string') {
				button.value = loadingText;
			}
		}

		var externalUrl = form.getAttribute('data-aspen-external-url');
		if (externalUrl) {
			try {
				window.open(externalUrl, '_blank', 'noopener,noreferrer');
			} catch (e) {
				// Best effort only.
			}
		}
	}

	document.addEventListener('submit', handleSubmit, true);
})();
