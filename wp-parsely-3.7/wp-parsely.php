<?php
/**
 * Parse.ly
 *
 * @package   Parsely
 * @author    Parse.ly
 * @copyright 2012 Parse.ly
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Parse.ly
 * Plugin URI:        https://www.parse.ly/help/integration/wordpress
 * Description:       This plugin makes it a snap to add Parse.ly tracking code and metadata to your WordPress blog.
 * Version:           3.7.2
 * Author:            Parse.ly
 * Author URI:        https://www.parse.ly
 * Text Domain:       wp-parsely
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/Parsely/wp-parsely
 * Requires PHP:      7.1
 * Requires WP:       5.0.0
 */

declare(strict_types=1);

namespace Parsely;

use Parsely\ContentHelper\Dashboard_Widget;
use Parsely\Endpoints\Analytics_Post_Detail_API_Proxy;
use Parsely\Endpoints\Analytics_Posts_API_Proxy;
use Parsely\Endpoints\GraphQL_Metadata;
use Parsely\Endpoints\Referrers_Post_Detail_API_Proxy;
use Parsely\Endpoints\Related_API_Proxy;
use Parsely\Endpoints\Rest_Metadata;
use Parsely\Integrations\Amp;
use Parsely\Integrations\Facebook_Instant_Articles;
use Parsely\Integrations\Google_Web_Stories;
use Parsely\Integrations\Integrations;
use Parsely\RemoteAPI\Analytics_Post_Detail_API;
use Parsely\RemoteAPI\Analytics_Posts_API;
use Parsely\RemoteAPI\Remote_API_Cache;
use Parsely\RemoteAPI\Referrers_Post_Detail_API;
use Parsely\RemoteAPI\Related_API;
use Parsely\RemoteAPI\WordPress_Cache;
use Parsely\UI\Admin_Bar;
use Parsely\UI\Admin_Columns_Parsely_Stats;
use Parsely\UI\Admin_Warning;
use Parsely\UI\Metadata_Renderer;
use Parsely\UI\Network_Admin_Sites_List;
use Parsely\UI\Plugins_Actions;
use Parsely\UI\Recommended_Widget;
use Parsely\UI\Row_Actions;
use Parsely\UI\Settings_Page;
use Parsely\UI\Site_Health;

require_once __DIR__ . '/src/Utils/utils.php';

if ( class_exists( Parsely::class ) ) {
	return;
}

const PARSELY_VERSION = '3.7.2';
const PARSELY_FILE    = __FILE__;

require_once __DIR__ . '/src/class-parsely.php';
require_once __DIR__ . '/src/class-scripts.php';
require_once __DIR__ . '/src/class-dashboard-link.php';
require_once __DIR__ . '/src/UI/class-admin-bar.php';
require_once __DIR__ . '/src/UI/class-metadata-renderer.php';
require_once __DIR__ . '/src/Endpoints/class-metadata-endpoint.php';
require_once __DIR__ . '/src/Endpoints/class-graphql-metadata.php';

require_once __DIR__ . '/src/class-metadata.php';
require_once __DIR__ . '/src/Metadata/class-metadata-builder.php';
require_once __DIR__ . '/src/Metadata/class-author-archive-builder.php';
require_once __DIR__ . '/src/Metadata/class-category-builder.php';
require_once __DIR__ . '/src/Metadata/class-date-builder.php';
require_once __DIR__ . '/src/Metadata/class-front-page-builder.php';
require_once __DIR__ . '/src/Metadata/class-page-builder.php';
require_once __DIR__ . '/src/Metadata/class-page-for-posts-builder.php';
require_once __DIR__ . '/src/Metadata/class-paginated-front-page-builder.php';
require_once __DIR__ . '/src/Metadata/class-post-builder.php';
require_once __DIR__ . '/src/Metadata/class-tag-builder.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\parsely_initialize_plugin' );
/**
 * Registers the basic classes to initialize the plugin.
 */
function parsely_initialize_plugin(): void {
	$GLOBALS['parsely'] = new Parsely();
	$GLOBALS['parsely']->run();

	if ( class_exists( 'WPGraphQL' ) ) {
		$graphql = new GraphQL_Metadata( $GLOBALS['parsely'] );
		$graphql->run();
	}

	$scripts = new Scripts( $GLOBALS['parsely'] );
	$scripts->run();

	$admin_bar = new Admin_Bar( $GLOBALS['parsely'] );
	$admin_bar->run();

	$metadata_renderer = new Metadata_Renderer( $GLOBALS['parsely'] );
	$metadata_renderer->run();
}

require_once __DIR__ . '/src/UI/class-admin-columns-parsely-stats.php';
require_once __DIR__ . '/src/UI/class-admin-warning.php';
require_once __DIR__ . '/src/UI/class-plugins-actions.php';
require_once __DIR__ . '/src/UI/class-row-actions.php';
require_once __DIR__ . '/src/UI/class-site-health.php';
require_once __DIR__ . '/src/content-helper/dashboard-widget/class-dashboard-widget.php';

add_action( 'admin_init', __NAMESPACE__ . '\\parsely_admin_init_register' );
/**
 * Registers the Parse.ly wp-admin warnings, plugin actions and row actions.
 */
function parsely_admin_init_register(): void {
	$parsely = $GLOBALS['parsely'];

	( new Admin_Warning( $parsely ) )->run();
	( new Plugins_Actions() )->run();
	( new Row_Actions( $parsely ) )->run();
	( new Admin_Columns_Parsely_Stats( $parsely ) )->run();
	( new Site_Health( $parsely ) )->run();
	( new Dashboard_Widget() )->run();
}

require_once __DIR__ . '/src/UI/class-settings-page.php';
require_once __DIR__ . '/src/UI/class-network-admin-sites-list.php';

add_action( 'init', __NAMESPACE__ . '\\parsely_wp_admin_early_register' );
/**
 * Registers the additions the Parse.ly wp-admin settings page and Multisite
 * Network Admin Sites List table.
 */
function parsely_wp_admin_early_register(): void {
	$settings_page = new Settings_Page( $GLOBALS['parsely'] );
	$settings_page->run();

	$network_admin_sites_list = new Network_Admin_Sites_List( $GLOBALS['parsely'] );
	$network_admin_sites_list->run();
}

require_once __DIR__ . '/src/RemoteAPI/interface-cache.php';
require_once __DIR__ . '/src/RemoteAPI/interface-remote-api.php';
require_once __DIR__ . '/src/RemoteAPI/class-remote-api-base.php';
require_once __DIR__ . '/src/RemoteAPI/class-remote-api-cache.php';
require_once __DIR__ . '/src/RemoteAPI/class-related-api.php';
require_once __DIR__ . '/src/RemoteAPI/class-analytics-posts-api.php';
require_once __DIR__ . '/src/RemoteAPI/class-analytics-post-detail-api.php';
require_once __DIR__ . '/src/RemoteAPI/class-referrers-post-detail-api.php';
require_once __DIR__ . '/src/RemoteAPI/class-wordpress-cache.php';

require_once __DIR__ . '/src/Endpoints/class-base-api-proxy.php';
require_once __DIR__ . '/src/Endpoints/class-related-api-proxy.php';
require_once __DIR__ . '/src/Endpoints/class-analytics-posts-api-proxy.php';
require_once __DIR__ . '/src/Endpoints/class-analytics-post-detail-api-proxy.php';
require_once __DIR__ . '/src/Endpoints/class-referrers-post-detail-api-proxy.php';
require_once __DIR__ . '/src/Endpoints/class-rest-metadata.php';

add_action( 'rest_api_init', __NAMESPACE__ . '\\parsely_rest_api_init' );
/**
 * Registers REST Endpoints that act as a proxy to the Parse.ly API.
 * This is needed to get around CORS issues with Firefox.
 *
 * @since 3.2.0
 */
function parsely_rest_api_init(): void {
	$wp_cache = new WordPress_Cache();
	$rest     = new Rest_Metadata( $GLOBALS['parsely'] );
	$rest->run();

	parsely_run_rest_api_endpoint(
		Related_API::class,
		Related_API_Proxy::class,
		$wp_cache
	);

	parsely_run_rest_api_endpoint(
		Analytics_Posts_API::class,
		Analytics_Posts_API_Proxy::class,
		$wp_cache
	);

	parsely_run_rest_api_endpoint(
		Analytics_Post_Detail_API::class,
		Analytics_Post_Detail_API_Proxy::class,
		$wp_cache
	);

	parsely_run_rest_api_endpoint(
		Referrers_Post_Detail_API::class,
		Referrers_Post_Detail_API_Proxy::class,
		$wp_cache
	);
}

require_once __DIR__ . '/src/blocks/recommendations/class-recommendations-block.php';

add_action( 'init', __NAMESPACE__ . '\\init_recommendations_block' );
/**
 * Registers the Recommendations Block.
 */
function init_recommendations_block(): void {
	$recommendations_block = new Recommendations_Block();
	$recommendations_block->run();
}

require_once __DIR__ . '/src/blocks/content-helper/class-content-helper.php';

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\init_content_helper' );
/**
 * Inserts the PCH Editor Sidebar.
 *
 * @since 3.5.0 Moved from Parsely\Scripts\enqueue_block_editor_assets()
 */
function init_content_helper(): void {
	( new Content_Helper() )->run();
}

require_once __DIR__ . '/src/UI/class-recommended-widget.php';

add_action( 'widgets_init', __NAMESPACE__ . '\\parsely_recommended_widget_register' );
/**
 * Registers the Parse.ly Recommended widget.
 */
function parsely_recommended_widget_register(): void {
	register_widget( new Recommended_Widget( $GLOBALS['parsely'] ) );
}

require_once __DIR__ . '/src/Integrations/class-integration.php';
require_once __DIR__ . '/src/Integrations/class-integrations.php';
require_once __DIR__ . '/src/Integrations/class-amp.php';
require_once __DIR__ . '/src/Integrations/class-facebook-instant-articles.php';
require_once __DIR__ . '/src/Integrations/class-google-web-stories.php';

add_action( 'init', __NAMESPACE__ . '\\parsely_integrations' ); // @phpstan-ignore-line
/**
 * Instantiates Integrations collection and registers built-in integrations.
 *
 * @since 2.6.0
 *
 * @param Parsely|string|null $parsely The Parsely object to pass to the integrations.
 * @return Integrations
 */
function parsely_integrations( $parsely = null ): Integrations {
	// If $parsely value is "", then this function is being called by the init
	// hook and we can get the value from $GLOBALS. If $parsely is an instance
	// of the Parsely object, then this function is being called by a test.
	if ( ! is_object( $parsely ) || get_class( $parsely ) !== Parsely::class ) {
		$parsely = $GLOBALS['parsely'];
	}

	$parsely_integrations = new Integrations( $parsely );
	$parsely_integrations->register( 'amp', Amp::class );
	$parsely_integrations->register( 'fbia', Facebook_Instant_Articles::class );
	$parsely_integrations->register( 'webstories', Google_Web_Stories::class );
	$parsely_integrations = apply_filters( 'wp_parsely_add_integration', $parsely_integrations );
	$parsely_integrations->integrate();

	return $parsely_integrations;
}

/**
 * Instantiates and runs the specified API endpoint.
 *
 * @since 3.6.0
 *
 * @param string          $api_class_name The proxy class to instantiate.
 * @param string          $proxy_api_class_name The API proxy class to instantiate and run.
 * @param WordPress_Cache $wp_cache The WordPress cache instance to be used.
 */
function parsely_run_rest_api_endpoint(
	string $api_class_name,
	string $proxy_api_class_name,
	WordPress_Cache &$wp_cache
): void {
	/**
	 * Internal Variable.
	 *
	 * @var RemoteAPI\Remote_API_Base
	 */
	$remote_api       = new $api_class_name( $GLOBALS['parsely'] );
	$remote_api_cache = new Remote_API_Cache( $remote_api, $wp_cache );

	/**
	 * Internal Variable.
	 *
	 * @var Endpoints\Base_API_Proxy
	 */
	$remote_api_proxy = new $proxy_api_class_name( $GLOBALS['parsely'], $remote_api_cache );
	$remote_api_proxy->run();
}
