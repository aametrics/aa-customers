/**
 * AA Customers Frontend JavaScript
 *
 * @package AA_Customers
 */

(function($) {
	'use strict';

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		initCheckoutButtons();
		initBillingPortal();
		initProfileEdit();
	});

	/**
	 * Initialize checkout buttons
	 */
	function initCheckoutButtons() {
		$('.aa-checkout-btn').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var productId = $btn.data('product-id');

			if (!productId) {
				alert('Invalid product.');
				return;
			}

			// Disable button and show loading.
			$btn.prop('disabled', true).text('Processing...');

			// Create checkout session.
			$.ajax({
				url: aaCustomersFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aa_customers_create_checkout',
					nonce: aaCustomersFrontend.nonce,
					product_id: productId,
					donation: 0 // Could add donation input here.
				},
				success: function(response) {
					if (response.success && response.data.checkout_url) {
						// Redirect to Stripe checkout.
						window.location.href = response.data.checkout_url;
					} else {
						alert(response.data.message || 'Something went wrong. Please try again.');
						$btn.prop('disabled', false).text('Try Again');
					}
				},
				error: function() {
					alert('Connection error. Please try again.');
					$btn.prop('disabled', false).text('Try Again');
				}
			});
		});
	}

	/**
	 * Initialize billing portal link
	 */
	function initBillingPortal() {
		$('.aa-billing-portal-link').on('click', function(e) {
			e.preventDefault();

			var $link = $(this);
			var originalText = $link.text();

			$link.text('Loading...');

			$.ajax({
				url: aaCustomersFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aa_customers_billing_portal',
					nonce: aaCustomersFrontend.nonce
				},
				success: function(response) {
					if (response.success && response.data.portal_url) {
						window.location.href = response.data.portal_url;
					} else {
						alert(response.data.message || 'Could not access billing portal.');
						$link.text(originalText);
					}
				},
				error: function() {
					alert('Connection error. Please try again.');
					$link.text(originalText);
				}
			});
		});
	}

	/**
	 * Initialize profile edit
	 */
	function initProfileEdit() {
		$('.aa-edit-profile-link').on('click', function(e) {
			e.preventDefault();

			// Show edit profile modal/form.
			// For now, redirect to a profile edit page if it exists.
			var editUrl = $(this).data('edit-url');
			if (editUrl) {
				window.location.href = editUrl;
			} else {
				alert('Profile editing coming soon.');
			}
		});

		// Handle profile form submission.
		$('#aa-profile-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $submit = $form.find('button[type="submit"]');
			var originalText = $submit.text();

			$submit.prop('disabled', true).text('Saving...');

			var formData = $form.serializeArray();
			formData.push({name: 'action', value: 'aa_customers_update_profile'});
			formData.push({name: 'nonce', value: aaCustomersFrontend.nonce});

			$.ajax({
				url: aaCustomersFrontend.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						showNotice('success', response.data.message);
					} else {
						showNotice('error', response.data.message);
					}
					$submit.prop('disabled', false).text(originalText);
				},
				error: function() {
					showNotice('error', 'Connection error. Please try again.');
					$submit.prop('disabled', false).text(originalText);
				}
			});
		});
	}

	/**
	 * Show a notice message
	 */
	function showNotice(type, message) {
		var $notice = $('<div class="aa-notice aa-notice-' + type + '">' + message + '</div>');

		// Remove any existing notices.
		$('.aa-notice').remove();

		// Add notice at top of main content.
		$('.aa-member-dashboard, .aa-join-page, .aa-events-list').prepend($notice);

		// Auto-hide after 5 seconds.
		setTimeout(function() {
			$notice.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}

})(jQuery);
