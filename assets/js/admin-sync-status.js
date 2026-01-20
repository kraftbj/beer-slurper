/**
 * @file admin-sync-status.js
 * @description Beer Slurper Admin Sync Status JavaScript.
 *              Handles the "Sync Now" button functionality on the plugin settings page,
 *              allowing administrators to manually trigger an Untappd data synchronization.
 *
 * @requires beerSlurperSyncStatus - Localized object provided by WordPress via wp_localize_script().
 *                                   Contains:
 *                                   - ajaxurl {string} WordPress AJAX endpoint URL
 *                                   - nonce {string} Security nonce for AJAX verification
 *                                   - strings {Object} Localized UI strings (syncing, syncComplete, syncError)
 */

/**
 * Immediately Invoked Function Expression (IIFE) wrapper.
 *
 * @description Encapsulates the sync status functionality in a private scope to prevent
 *              global namespace pollution and ensure variables do not conflict with
 *              other scripts on the page.
 */
(function() {
	'use strict';

	/**
	 * Initializes the sync button functionality when the DOM is fully loaded.
	 *
	 * @description Sets up the click event handler for the sync button. Locates the
	 *              sync button and message display elements, then attaches the
	 *              click handler if both elements exist.
	 */
	document.addEventListener('DOMContentLoaded', function() {
		var syncButton = document.getElementById('beer-slurper-sync-now');
		var messageSpan = document.getElementById('beer-slurper-sync-message');

		if (!syncButton || !messageSpan) {
			return;
		}

		/**
		 * Handles the sync button click event.
		 *
		 * @param {Event} e - The click event object.
		 * @description Prevents the default button action, disables the button to prevent
		 *              double-clicks, updates the UI to show syncing state, then initiates
		 *              an AJAX request to trigger the sync operation. On completion,
		 *              restores the button state and displays success or error messages.
		 */
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
			/**
			 * Parses the fetch response as JSON.
			 *
			 * @param {Response} response - The fetch API Response object.
			 * @returns {Promise<Object>} Promise resolving to the parsed JSON data.
			 */
			.then(function(response) {
				return response.json();
			})
			/**
			 * Handles successful AJAX response.
			 *
			 * @param {Object} data - The parsed JSON response from the server.
			 * @param {boolean} data.success - Whether the sync operation succeeded.
			 * @param {Object} [data.data] - Additional response data.
			 * @param {string} [data.data.message] - Error message if sync failed.
			 * @description Restores the button to its original state and displays
			 *              appropriate success or error message. On success, reloads
			 *              the page after 1.5 seconds to display updated sync statistics.
			 */
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
			/**
			 * Handles fetch errors (network failures, etc.).
			 *
			 * @param {Error} error - The error object from the failed fetch request.
			 * @description Restores the button to its original state and displays
			 *              the error message to the user with error styling.
			 */
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
