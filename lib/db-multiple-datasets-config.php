<?php

namespace Automattic\VIP\DatabaseMultipleDatasetsConfig;

function dataset_callback( $query, $wpdb ) {
	$table   = $wpdb->table;
	$dataset = get_dataset_for_table( $wpdb );
	$wpdb->add_table( $dataset, $table );

	return [ 'dataset' => $dataset ];
}

function get_dataset_for_table( $wpdb ) {
	if ( preg_match( '/^' . $wpdb->base_prefix . '(\d+)_/i', $wpdb->table, $matches ) ) {
		$blog_id = $matches[1];

		return get_dataset_name_for_blog_id( $blog_id );
	}

	return get_primary_dataset();
}

function get_dataset_name_for_blog_id( $blog_id ) {
	global $db_datasets;
	foreach ( $db_datasets as $dataset ) {
		if ( in_array( $blog_id, $dataset['blog_ids'] ) ) {
			return $dataset['name'];
		}
	}

	// If the blog_id is not mapped on $db_datasets, we use the latest dataset
	return get_latest_dataset_name();
}

function get_latest_dataset_name() {
	global $db_datasets;

	$latest = end( $db_datasets );

	if ( isset( $latest['name'] ) ) {
		return $latest['name'];
	}

	trigger_error( 'latest dataset not found', E_USER_ERROR );
}

function get_primary_dataset() {
	global $db_datasets;
	foreach ( $db_datasets as $dataset ) {
		if ( $dataset['primary'] ) {
			return $dataset['name'];
		}
	}

	trigger_error( 'primary dataset not found', E_USER_ERROR );
}
