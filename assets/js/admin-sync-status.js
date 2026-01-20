/**
 * Beer Slurper Admin Sync Status JavaScript
 *
 * Handles the Sync Now button functionality on the settings page.
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		var syncButton = document.getElementById('beer-slurper-sync-now');
		var messageSpan = document.getElementById('beer-slurper-sync-message');

		if (!syncButton || !messageSpan) {
			return;
		}

		syncButton.addEventListener('click', function(e) {
			e.preventDefault();

			// Prevent double-clicks
			if (syncButton.disabled) {
				return;
			}

			// Set loading state
			syncButton.disabled = true;
			var originalText = syncButton.textContent;
			syncButton.textContent = beerSlurperSyncStatus.strings.syncing;
			messageSpan.textContent = '';
			messageSpan.style.color = '';

			// Make AJAX request
			var formData = new FormData();
			formData.append('action', 'beer_slurper_sync_now');
			formData.append('nonce', beerSlurperSyncStatus.nonce);

			fetch(beerSlurperSyncStatus.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				// Reset button state
				syncButton.disabled = false;
				syncButton.textContent = originalText;

				if (data.success) {
					messageSpan.textContent = beerSlurperSyncStatus.strings.syncComplete;
					messageSpan.style.color = '#00a32a';

					// Reload the page after a short delay to show updated stats
					setTimeout(function() {
						window.location.reload();
					}, 1500);
				} else {
					messageSpan.textContent = beerSlurperSyncStatus.strings.syncError + ' ' + (data.data && data.data.message ? data.data.message : 'Unknown error');
					messageSpan.style.color = '#d63638';
				}
			})
			.catch(function(error) {
				// Reset button state
				syncButton.disabled = false;
				syncButton.textContent = originalText;

				messageSpan.textContent = beerSlurperSyncStatus.strings.syncError + ' ' + error.message;
				messageSpan.style.color = '#d63638';
			});
		});
	});
})();
