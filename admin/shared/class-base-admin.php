<?php
/**
 * Base Admin Class
 *
 * Provides common admin functionality for all admin classes.
 * This class contains reusable methods for:
 * - Capability checking
 * - Admin notices
 * - Nonce verification
 * - Redirects with messages
 * - URL helpers
 *
 * @package AA_Customers
 * @subpackage Admin/Shared
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Base_Admin
 *
 * Base class for all admin controllers in the AA Customers plugin.
 */
abstract class AA_Customers_Base_Admin {

	/**
	 * Check if current user has required capability
	 *
	 * @param string $capability The capability to check. Default 'manage_options'.
	 * @return bool True if user has capability.
	 */
	protected function check_capability( $capability = 'manage_options' ) {
		return current_user_can( $capability );
	}

	/**
	 * Verify nonce and die if invalid
	 *
	 * @param string $nonce_field The nonce field name.
	 * @param string $nonce_action The nonce action.
	 * @return void Dies if nonce verification fails.
	 */
	protected function verify_nonce( $nonce_field, $nonce_action ) {
		if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed', 'aa-customers' ) );
		}
	}

	/**
	 * Render admin notice
	 *
	 * @param string $message The message to display.
	 * @param string $type The notice type: 'success', 'error', 'warning', 'info'. Default 'success'.
	 * @param bool   $is_dismissible Whether the notice is dismissible. Default true.
	 * @return void
	 */
	protected function render_admin_notice( $message, $type = 'success', $is_dismissible = true ) {
		$class = 'notice notice-' . esc_attr( $type );
		if ( $is_dismissible ) {
			$class .= ' is-dismissible';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Display admin notices based on URL parameters
	 *
	 * @param array $success_messages Array of success message keys and texts.
	 * @param array $error_messages Array of error message keys and texts.
	 * @return void
	 */
	protected function display_admin_notices( $success_messages = array(), $error_messages = array() ) {
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );

			if ( isset( $success_messages[ $message ] ) ) {
				$this->render_admin_notice( $success_messages[ $message ], 'success' );
			}
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );

			if ( isset( $error_messages[ $error ] ) ) {
				$this->render_admin_notice( $error_messages[ $error ], 'error' );
			}
		}
	}

	/**
	 * Redirect to admin page with message
	 *
	 * @param string $page The admin page slug.
	 * @param string $message The message key to pass in URL.
	 * @param array  $extra_args Additional URL parameters.
	 * @return void
	 */
	protected function redirect_with_message( $page, $message, $extra_args = array() ) {
		$args = array_merge( array( 'page' => $page, 'message' => $message ), $extra_args );
		$url  = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect to admin page with error
	 *
	 * @param string $page The admin page slug.
	 * @param string $error The error key to pass in URL.
	 * @param array  $extra_args Additional URL parameters.
	 * @return void
	 */
	protected function redirect_with_error( $page, $error, $extra_args = array() ) {
		$args = array_merge( array( 'page' => $page, 'error' => $error ), $extra_args );
		$url  = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Get current admin URL
	 *
	 * @param array $args Additional query args to add.
	 * @return string The current admin URL with optional additional args.
	 */
	protected function get_current_admin_url( $args = array() ) {
		$page      = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$base_args = array( 'page' => $page );
		$url_args  = array_merge( $base_args, $args );
		return add_query_arg( $url_args, admin_url( 'admin.php' ) );
	}

	/**
	 * Sanitize form data recursively
	 *
	 * @param mixed $data The data to sanitize.
	 * @return mixed Sanitized data.
	 */
	protected function sanitize_form_data( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'sanitize_form_data' ), $data );
		}

		return sanitize_text_field( $data );
	}

	/**
	 * Check if current admin page matches
	 *
	 * @param string $page_slug The page slug to check against.
	 * @return bool True if current page matches.
	 */
	protected function is_current_admin_page( $page_slug ) {
		return isset( $_GET['page'] ) && $_GET['page'] === $page_slug;
	}

	/**
	 * Get sanitized GET parameter
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default Default value if parameter not set.
	 * @return mixed The sanitized parameter value or default.
	 */
	protected function get_param( $key, $default = '' ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Get sanitized POST parameter
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default Default value if parameter not set.
	 * @return mixed The sanitized parameter value or default.
	 */
	protected function post_param( $key, $default = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		if ( is_array( $_POST[ $key ] ) ) {
			return $this->sanitize_form_data( wp_unslash( $_POST[ $key ] ) );
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Enqueue WordPress media uploader
	 *
	 * @return void
	 */
	protected function enqueue_media_uploader() {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Render nonce field
	 *
	 * @param string $action The nonce action.
	 * @param string $name The nonce field name. Default 'aa_customers_nonce'.
	 * @return void
	 */
	protected function render_nonce_field( $action, $name = 'aa_customers_nonce' ) {
		wp_nonce_field( $action, $name );
	}

	/**
	 * Check if we're on a specific admin page hook
	 *
	 * @param string $hook The hook suffix to check.
	 * @param string $page_identifier Part of the page identifier.
	 * @return bool True if hook matches the page identifier.
	 */
	protected function is_admin_hook( $hook, $page_identifier ) {
		return false !== strpos( $hook, $page_identifier );
	}
}
