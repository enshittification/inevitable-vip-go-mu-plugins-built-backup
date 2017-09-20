<?php

/*
 * Plugin Name: Jetpack: VIP Specific Changes
 * Plugin URI: https://github.com/Automattic/vipv2-mu-plugins/blob/master/jetpack-mandatory.php
 * Description: VIP-specific customisations to Jetpack.
 * Author: Automattic
 * Version: 1.0.2
 * License: GPL2+
 */

/**
 * Enable VIP modules required as part of the platform
 */
require_once( __DIR__ . '/jetpack-mandatory.php' );

/**
 * Remove certain modules from the list of those that can be activated
 * Blocks access to certain functionality that isn't compatible with the platform.
 */
add_filter( 'jetpack_get_available_modules', function( $modules ) {
	unset( $modules['photon'] );
	unset( $modules['site-icon'] );
	unset( $modules['protect'] );

	return $modules;
}, 999 );

// Prevent Jetpack version ping-pong when a sandbox has an old version of stacks
if ( true === WPCOM_SANDBOXED ) {
	add_action( 'updating_jetpack_version', function( $new_version, $old_version ) {
		// This is a brand new site with no Jetpack data
		if ( empty( $old_version ) ) {
			return;
		}

		wp_die( sprintf( '😱😱😱 Oh no! Looks like your sandbox is trying to change the version of Jetpack (from %1$s => %2$s). This is probably not a good idea. As a precaution, we\'re killing this request to prevent potentially bad things. Please run `vip stacks update` on your sandbox before doing anything else.', $old_version, $new_version ), 400 );
	}, 0, 2 ); // No need to wait till priority 10 since we're going to die anyway
}

// On production servers, only our machine user can manage the Jetpack connection
if ( true === WPCOM_IS_VIP_ENV && is_admin() ) {
	add_filter( 'map_meta_cap', function( $caps, $cap, $user_id, $args ) {
		switch ( $cap ) {
			case 'jetpack_connect':
			case 'jetpack_reconnect':
			case 'jetpack_disconnect':
				$user = get_userdata( $user_id );
				if ( $user && WPCOM_VIP_MACHINE_USER_LOGIN !== $user->user_login ) {
					return [ 'do_not_allow' ];
				}
				break;
		}

		return $caps;
	}, 10, 4 );
}

function wpcom_vip_did_jetpack_search_query( $query ) {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return;
	}

	global $wp_elasticsearch_queries_log;

	if ( ! isset( $wp_elasticsearch_queries_log ) || ! is_array( $wp_elasticsearch_queries_log ) ) {
		$wp_elasticsearch_queries_log = array();
	}

	$query['backtrace'] = wp_debug_backtrace_summary();

	$wp_elasticsearch_queries_log[] = $query;
}

add_action( 'did_jetpack_search_query', 'wpcom_vip_did_jetpack_search_query' );
