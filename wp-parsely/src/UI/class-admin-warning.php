<?php
/**
 * UI: Parse.ly wp-admin warning class
 *
 * @package Parsely
 * @since   3.0.0
 */

declare(strict_types=1);

namespace Parsely\UI;

use Parsely\Parsely;

/**
 * Conditionally renders a warning message on wp-admin.
 *
 * @since 3.0.0
 */
final class Admin_Warning {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Registers admin warning.
	 *
	 * @since 3.0.0
	 */
	public function run(): void {
		add_action( 'admin_notices', array( $this, 'display_admin_warning' ) );
	}

	/**
	 * Displays the admin warning if needed.
	 */
	public function display_admin_warning(): void {
		if ( ! $this->should_display_admin_warning() ) {
			return;
		}

		$message = sprintf(
		/* translators: %s: Plugin settings page URL */
			__( '<strong>The Parse.ly plugin is not active.</strong> You need to <a href="%s">provide your Parse.ly Dash Site ID</a> before things get cooking.', 'wp-parsely' ),
			esc_url( Parsely::get_settings_url() )
		);
		?>
		<div id="wp-parsely-site-id-error-notice" class="notice notice-error"><p><?php echo wp_kses_post( $message ); ?></p></div>
		<?php
	}

	/**
	 * Returns whether the admin display warning should be displayed.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True if the admin warning should be displayed.
	 */
	private function should_display_admin_warning(): bool {
		if ( is_network_admin() ) {
			return false;
		}

		return $this->parsely->site_id_is_missing();
	}
}
