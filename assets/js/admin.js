/**
 * AA Customers Admin JavaScript
 *
 * @package AA_Customers
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin functionality
	 */
	function init() {
		// Toggle password visibility
		initPasswordToggles();

		// Confirm dialogs
		initConfirmDialogs();
	}

	/**
	 * Password field visibility toggles
	 */
	function initPasswordToggles() {
		$('input[type="password"]').each(function() {
			var $input = $(this);
			var $toggle = $('<button type="button" class="button button-secondary" style="margin-left: 5px;">Show</button>');

			$toggle.on('click', function() {
				if ($input.attr('type') === 'password') {
					$input.attr('type', 'text');
					$toggle.text('Hide');
				} else {
					$input.attr('type', 'password');
					$toggle.text('Show');
				}
			});

			$input.after($toggle);
		});
	}

	/**
	 * Confirmation dialogs for destructive actions
	 */
	function initConfirmDialogs() {
		$('.aa-customers-confirm').on('click', function(e) {
			var message = $(this).data('confirm') || 'Are you sure?';
			if (!confirm(message)) {
				e.preventDefault();
				return false;
			}
		});
	}

	/**
	 * AJAX helper
	 *
	 * @param {string} action - AJAX action name
	 * @param {object} data - Additional data to send
	 * @returns {Promise}
	 */
	window.aaCustomersAjax = function(action, data) {
		return $.ajax({
			url: aaCustomers.ajaxUrl,
			type: 'POST',
			data: $.extend({
				action: action,
				nonce: aaCustomers.nonce
			}, data)
		});
	};

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
