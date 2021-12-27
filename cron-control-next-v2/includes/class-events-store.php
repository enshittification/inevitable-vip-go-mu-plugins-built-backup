<?php
/**
 * Offload cron event storage to a custom table
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * Event's Store class
 */
class Events_Store extends Singleton {
	const TABLE_SUFFIX = 'a8c_cron_control_jobs';

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'a8c_cron_control_db_version';

	const STATUS_PENDING   = 'pending';
	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'complete';
	const ACTIVE_STATUSES  = [ self::STATUS_PENDING, self::STATUS_RUNNING ];
	const ALLOWED_STATUSES = [ self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_COMPLETED ];

	protected function class_init() {
		// Create tables during installation.
		add_action( 'wp_install', array( $this, 'create_table_during_install' ) );
		// TODO: This is deprecated hook, switch to wp_insert_site.
		add_action( 'wpmu_new_blog', array( $this, 'create_tables_during_multisite_install' ) );

		// Remove table when a multisite subsite is deleted.
		add_filter( 'wpmu_drop_tables', array( $this, 'remove_multisite_table' ) );

		// Try to get the table installed.
		if ( ! self::is_installed() ) {
			if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
				// Prime plugin's option before the options table exists.
				add_option( self::DB_VERSION_OPTION, 0, null, false );
			}

			// In limited circumstances, try creating the table.
			add_action( 'shutdown', array( $this, 'maybe_create_table_on_shutdown' ) );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Custom table related methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if events store is ready
	 *
	 * Plugin breaks spectacularly if events store isn't available
	 *
	 * @return bool
	 */
	public static function is_installed() {
		$db_version = (int) get_option( self::DB_VERSION_OPTION );
		return version_compare( $db_version, 0, '>' );
	}

	/**
	 * Build appropriate table name for this site.
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create table during initial install
	 */
	public function create_table_during_install() {
		if ( 'wp_install' !== current_action() ) {
			return;
		}

		$this->_prepare_table();
	}

	/**
	 * Create table when new subsite is added to a multisite
	 *
	 * @param int $bid Blog ID.
	 */
	public function create_tables_during_multisite_install( $bid ) {
		switch_to_blog( $bid );

		if ( ! self::is_installed() ) {
			$this->_prepare_table();
		}

		restore_current_blog();
	}

	/**
	 * For certain requests, create the table on shutdown
	 * Does not include front-end requests
	 */
	public function maybe_create_table_on_shutdown() {
		// TODO: Also let it try to create in CLI automatically, not just is_admin().
		if ( ! is_admin() && ! is_rest_endpoint_request( REST_API::ENDPOINT_LIST ) ) {
			return;
		}

		$this->prepare_table();
	}

	/**
	 * Create table in non-setup contexts, with some protections
	 */
	public function prepare_table() {
		// Table installed.
		if ( self::is_installed() ) {
			return;
		}

		// Nothing to do.
		$current_version = (int) get_option( self::DB_VERSION_OPTION );
		if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
			return;
		}

		// Limit chance of race conditions when creating table.
		$create_lock_set = wp_cache_add( 'a8c_cron_control_creating_table', 1, null, 1 * \MINUTE_IN_SECONDS );
		if ( false === $create_lock_set ) {
			return;
		}

		$this->_prepare_table();
	}

	/**
	 * Create the plugin's DB table when necessary
	 */
	protected function _prepare_table() {
		// Use Core's method of creating/updating tables.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		global $wpdb;

		$table_name = $this->get_table_name();

		// Define schema and create the table.
		$schema = "CREATE TABLE `{$table_name}` (
			`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,

			`timestamp` bigint(20) unsigned NOT NULL,
			`action` varchar(255) NOT NULL,
			`action_hashed` varchar(32) NOT NULL,
			`instance` varchar(32) NOT NULL,

			`args` longtext NOT NULL,
			`schedule` varchar(255) DEFAULT NULL,
			`interval` int unsigned DEFAULT 0,
			`status` varchar(32) NOT NULL DEFAULT 'pending',

			`created` datetime NOT NULL,
			`last_modified` datetime NOT NULL,

			PRIMARY KEY (`ID`),
			UNIQUE KEY `ts_action_instance_status` (`timestamp`, `action` (191), `instance`, `status`),
			KEY `status` (`status`)
		) ENGINE=InnoDB;\n";

		dbDelta( $schema, true );

		// Confirm that the table was created, and set the option to prevent further updates.
		$table_count = count( $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) );

		if ( 1 === $table_count ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}

		// Clear caches now that the table exists.
		self::flush_event_cache();
	}

	/**
	 * Prepare table on demand via CLI
	 */
	public function cli_create_tables() {
		if ( ! defined( 'WP_CLI' ) || ! \WP_CLI ) {
			return;
		}

		$this->_prepare_table();
	}

	/**
	 * When deleting a subsite from a multisite instance, include the plugin's table
	 *
	 * Core only drops its tables
	 *
	 * @param  array $tables_to_drop Array of prefixed table names to drop.
	 * @return array
	 */
	public function remove_multisite_table( $tables_to_drop ) {
		$tables_to_drop[] = $this->get_table_name();
		return $tables_to_drop;
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated (or soon to be) methods for interactions w/ the data store.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Deprecated, unused by the plugin.
	 * Giving time to catch warnings before removing the public method.
	 * @deprecated
	 */
	public function get_option() {
		_deprecated_function( 'Events_Store\get_option', 'pre_get_cron_option' );
		return pre_get_cron_option( false );
	}

	/**
	 * Deprecated, unused by the plugin.
	 * Giving time to catch warnings before removing the public method.
	 * @deprecated
	 */
	public function update_option( $new_value, $old_value ) {
		_deprecated_function( 'Events_Store\update_option', 'pre_update_cron_option' );
		return pre_update_cron_option( $new_value, $old_value );
	}

	/**
	 * Deprecated, unused by the plugin.
	 * Giving time to catch warnings before removing the public method.
	 * @deprecated
	 */
	public function block_creation_if_job_exists( $job ) {
		_deprecated_function( 'Events_Store\block_creation_if_job_exists' );
		return $job;
	}

	/**
	 * Retrieve jobs given a set of parameters
	 *
	 * @deprecated
	 * @param array $args Job arguments to search by.
	 * @return array
	 */
	public function get_jobs( $args ) {
		_deprecated_function( 'Events_Store\get_jobs' );

		// Adjust this method's previous defaults for what our new method expects.
		$adjusted_args = [
			'limit'  => isset( $args['quantity'] ) && is_numeric( $args['quantity'] ) ? $args['quantity'] : 100,
			'page'   => isset( $args['page'] ) && $args['page'] >= 1 ? $args['page'] : 1,
			'status' => $args['status'],
		];

		$jobs = $this->_query_events_raw( $adjusted_args );
		return array_map( array( $this, 'format_job' ), $jobs );
	}

	/**
	 * Retrieve a single event by its ID
	 *
	 * @deprecated
	 * @param int $jid Job ID.
	 * @return object|false
	 */
	public function get_job_by_id( $jid ) {
		_deprecated_function( 'Events_Store\get_job_by_id' );

		// Validate ID.
		$jid = absint( $jid );
		if ( ! $jid ) {
			return false;
		}

		$job = $this->_get_event_raw( $jid );
		if ( ! is_object( $job ) ) {
			return false;
		}

		// This method previously only queried for pending, so we respect that here.
		if ( self::STATUS_PENDING !== $job->status ) {
			return false;
		}

		return $job;
	}

	/**
	 * Retrieve a single event by a combination of a timestamp, instance identifier, and either action or the action's hashed representation
	 *
	 * @deprecated
	 * @param array $attrs Array of event attributes to query by.
	 * @return object|false
	 */
	public function get_job_by_attributes( $attrs ) {
		global $wpdb;

		_deprecated_function( 'Events_Store\get_job_by_attributes' );

		// Validate basic inputs.
		if ( ! is_array( $attrs ) || empty( $attrs ) ) {
			return false;
		}

		if ( ! isset( $attrs['status'] ) || ! self::validate_status( $attrs['status'] ) ) {
			$attrs['status'] = self::STATUS_PENDING;
		}

		// Need a timestamp, an instance, and either an action or its hashed representation.
		if ( ! isset( $attrs['timestamp'] ) || ! isset( $attrs['instance'] ) ) {
			return false;
		} elseif ( ! isset( $attrs['action'] ) && ! isset( $attrs['action_hashed'] ) ) {
			return false;
		}

		// Build the query args, supporting the API this method previously had.
		$adjusted_args = [
			'instance' => $attrs['instance'],
			'status'   => $attrs['status'],
		];

		if ( isset( $attrs['action'] ) ) {
			$adjusted_args['action'] = $attrs['action'];
		} else {
			$adjusted_args['action_hashed'] = $attrs['action_hashed'];
		}

		$jobs = $this->_query_events_raw( $adjusted_args );
		return is_object( $jobs[0] ) ? $this->format_job( $jobs[0] ) : false;
	}

	/**
	 * Standardize formatting and expand serialized data
	 *
	 * @param object $job Job row from DB, in object form.
	 * @return object
	 */
	private function format_job( $job ) {
		if ( ! is_object( $job ) || is_wp_error( $job ) ) {
			return $job;
		}

		$job->ID        = (int) $job->ID;
		$job->timestamp = (int) $job->timestamp;
		$job->interval  = (int) $job->interval;
		$job->args      = maybe_unserialize( $job->args );

		if ( empty( $job->schedule ) ) {
			$job->schedule = false;
		}

		return $job;
	}

	/**
	 * Create or update entry for a given job.
	 *
	 * @deprecated
	 * @param int    $timestamp    Unix timestamp event executes at.
	 * @param string $action       Hook event fires.
	 * @param array  $args         Array of event's schedule, arguments, and interval.
	 * @param bool   $update_id    ID of existing entry to update, rather than creating a new entry.
	 * @param bool   $flush_cache  Whether or not to flush internal caches after creating/updating the event.
	 */
	public function create_or_update_job( $timestamp, $action, $args, $update_id = null, $flush_cache = true ) {
		_deprecated_function( 'Events_Store\create_or_update_job' );

		if ( is_int( $update_id ) && $update_id > 0 ) {
			// Update an existing entry.
			$event = Event::get( $update_id );

			if ( is_null( $event ) ) {
				return;
			}
		} else {
			// Create a new event.
			$event = new Event();
		}

		$event->set_timestamp( $timestamp );
		$event->set_action( $action );
		$event->set_args( $args['args'] );

		if ( ! empty( $args['schedule'] ) && ! empty( $args['interval'] ) ) {
			$event->set_schedule( $args['schedule'], (int) $args['interval'] );
		}

		// Saves the existing one, or creates a new one.
		$event->save();
	}

	/**
	 * Mark an event's entry as completed
	 *
	 * Completed entries will be cleaned up by an internal job
	 *
	 * @deprecated
	 * @param int    $timestamp    Unix timestamp event executes at.
	 * @param string $action       Name of action used when the event is registered (unhashed).
	 * @param string $instance     md5 hash of the event's arguments array, which Core uses to index the `cron` option.
	 * @param bool   $flush_cache  Whether or not to flush internal caches after creating/updating the event.
	 * @return bool
	 */
	public function mark_job_completed( $timestamp, $action, $instance, $flush_cache = true ) {
		_deprecated_function( 'Events_Store\mark_job_completed' );

		$event = Event::find( [
			'timestamp' => $timestamp,
			'action'    => $action,
			'instance'  => $instance,
		] );

		if ( is_null( $event ) ) {
			return false;
		}

		$result = $event->complete();
		return true === $result;
	}

	/**
	 * Set a job post to the "completed" status
	 *
	 * @deprecated
	 * @param int  $job_id       ID of job's record.
	 * @param bool $flush_cache  Whether or not to flush internal caches after creating/updating the event.
	 * @return bool
	 */
	public function mark_job_record_completed( $job_id, $flush_cache = true ) {
		_deprecated_function( 'Events_Store\mark_job_record_completed' );

		$event = Event::get( $job_id );

		$result = false;
		if ( ! is_null( $event ) ) {
			$result = $event->complete();
		}

		return true === $result;
	}

	/**
	 * @deprecated
	 */
	public function flush_internal_caches() {
		_deprecated_function( 'Events_Store\flush_internal_caches' );
		self::flush_event_cache();
	}

	/**
	 * @deprecated
	 */
	public function suspend_event_creation() {
		// No longer needed.
		_deprecated_function( 'Events_Store\suspend_event_creation' );
	}

	/**
	 * @deprecated
	 */
	public function resume_event_creation() {
		// No longer needed.
		_deprecated_function( 'Events_Store\resume_event_creation' );
	}

	/**
	 * Remove entries for non-recurring events that have been run.
	 *
	 * @param bool $count_first Should events be counted before they're deleted.
	 */
	public function purge_completed_events( $count_first = true ) {
		global $wpdb;

		// Skip count if already performed.
		$count = 1;
		if ( $count_first ) {
			if ( property_exists( $wpdb, 'srtm' ) ) {
				$wpdb->srtm = true;
			}

			$count = $this->count_events_by_status( self::STATUS_COMPLETED );
		}

		if ( $count > 0 ) {
			$wpdb->delete(
				$this->get_table_name(),
				array(
					'status' => self::STATUS_COMPLETED,
				)
			);
			self::flush_event_cache();
		}
	}

	/**
	 * Count number of events with a given status
	 *
	 * @param string $status Event status to count.
	 * @return int|false
	 */
	public function count_events_by_status( $status ) {
		global $wpdb;

		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return false;
		}

		// Cannot prepare table name. @codingStandardsIgnoreLine
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$this->get_table_name()} WHERE status = %s", $status ) );
	}

	/*
	|--------------------------------------------------------------------------
	| New event's store methods. The above may be deprecated in the future.
	| But notably, the below is also internal-usage only. See comments about alternatives.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create an event.
	 * For internal use only, please use Event:save() as this method does not validate.
	 *
	 * @param array $row_data The row data used to create the event.
	 * @return int The newly created event ID, 0 if creation failed.
	 */
	public function _create_event( array $row_data ): int {
		global $wpdb;

		if ( empty( $row_data ) ) {
			return 0;
		}

		$result = $wpdb->insert( $this->get_table_name(), $row_data, self::row_formatting( $row_data ) );

		self::flush_event_cache();
		return false === $result ? 0 : $wpdb->insert_id;
	}

	/**
	 * Update an event.
	 * For internal use only, please use Event::save() as this does not validate.
	 *
	 * @param int   $event_id The ID of the event being updated.
	 * @param array $row_data The row data used to update the event.
	 * @return bool True if update was successful, false otherwise.
	 */
	public function _update_event( int $event_id, array $row_data ): bool {
		global $wpdb;

		if ( empty( $event_id ) || empty( $row_data ) ) {
			return 0;
		}

		$where  = [ 'ID' => $event_id ];
		$result = $wpdb->update( $this->get_table_name(), $row_data, $where, self::row_formatting( $row_data ), self::row_formatting( $where ) );

		self::flush_event_cache();
		return false !== $result;
	}

	/**
	 * Get raw event data by an ID.
	 * For internal use only, please use Event::get( $id ).
	 *
	 * Currently no need for caching here really,
	 * the action/instance/timestamp combination is the query that often happens on the FE.
	 * So perhaps room for enhacement there later.
	 *
	 * @param int $id The ID of the event being retrieved.
	 * @return object|null Raw event object if successful, false otherwise.
	 */
	public function _get_event_raw( int $id ): ?object {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		// Cannot prepare table name. @codingStandardsIgnoreLine
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Get raw events data based on various available query args.
	 * For internal use only, please use Event::find( $args ) or Events::query( $args ).
	 *
	 * @param array $args Argument list for the query.
	 * @return array Array of raw event objects.
	 */
	public function _query_events_raw( array $args = [] ): array {
		global $wpdb;

		$valid_args = [
			'action' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'action_hashed' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'args' => [
				'default'    => null,
				'validation' => 'is_array',
			],
			'instance' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'timestamp' => [
				'default'    => null,
				'validation' => fn( $ts ) => self::validate_timestamp( $ts ),
			],
			'schedule' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'status' => [
				'default'    => self::ACTIVE_STATUSES,
				'validation' => fn( $status ) => self::validate_status( $status ),
			],
			'limit' => [
				'default'    => 100,
				'validation' => 'is_int',
			],
			'page' => [
				'default'    => 1,
				'validation' => fn( $page ) => is_int( $page ) && $page >= 1,
			],
			'order' => [
				'default'    => 'ASC',
				'validation' => fn( $order ) => is_string( $order ) && in_array( strtoupper( $order ), [ 'ASC', 'DESC'], true ),
			],
		];

		$parsed_args = wp_parse_args( $args, array_map( fn( $arg ) => $arg['default'], $valid_args ) );

		foreach ( $valid_args as $arg_name => $arg_checks ) {
			if ( $parsed_args[ $arg_name ] !== $arg_checks['default'] ) {
				// The arg was changed from the default, let's validate it.
				if ( ! call_user_func( $arg_checks['validation'], $parsed_args[ $arg_name ] ) ) {
					trigger_error( 'Invalid arguments passed in for the events query', E_USER_NOTICE );
					return [];
				}
			}
		}

		$table = $this->get_table_name();
		$sql = "SELECT * FROM `{$table}` WHERE 1=1";
		$placeholders = [];

		// Timestamp can be:
		if ( ! is_null( $parsed_args['timestamp'] ) ) {
			// 1) A direct integer.
			if ( is_int( $parsed_args['timestamp'] ) ) {
				$sql .= ' AND timestamp = %d';
				$placeholders[] = $parsed_args['timestamp'];
			}

			// 2) Or a request for everything that is "due now".
			if ( 'due_now' === $parsed_args['timestamp'] ) {
				$sql .= ' AND timestamp <= %d';
				$placeholders[] = time();
			}

			// 3) Or a range between two timestamps.
			if ( is_array( $parsed_args['timestamp'] ) ) {
				$sql .= ' AND timestamp >= %d AND timestamp <= %d';
				$placeholders[] = $parsed_args['timestamp']['from'];
				$placeholders[] = $parsed_args['timestamp']['to'];
			}
		}

		if ( ! is_null( $parsed_args['action'] ) ) {
			$sql .= ' AND action = %s';
			$placeholders[] = $parsed_args['action'];
		}

		if ( ! is_null( $parsed_args['action_hashed'] ) ) {
			// TODO: Deprecate this query arg later once all is converted.
			$sql .= ' AND action_hashed = %s';
			$placeholders[] = $parsed_args['action_hashed'];
		}

		if ( ! is_null( $parsed_args['args'] ) ) {
			// Rather than query args directly, convert to the hash so we can utilize index.
			$instance = Event::create_instance_hash( $parsed_args['args'] );
			$sql .= ' AND instance = %s';
			$placeholders[] = $instance;
		} elseif ( ! is_null( $parsed_args['instance'] ) ) {
			// TODO: Deprecate this query arg later once all is converted.
			$sql .= ' AND instance = %s';
			$placeholders[] = $parsed_args['instance'];
		}

		if ( ! is_null( $parsed_args['schedule'] ) ) {
			$sql .= ' AND schedule = %s';
			$placeholders[] = $parsed_args['schedule'];
		}

		$requested_any_status = is_string( $parsed_args['status'] ) ? 'any' === strtolower( $parsed_args['status'] ) : false;
		if ( ! $requested_any_status ) {
			if ( is_array( $parsed_args['status'] ) ) {
				$statuses = array_map( 'strtolower', $parsed_args['status'] );
				$sql .= ' AND status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
				$placeholders = array_merge( $placeholders, $statuses );
			} elseif ( is_string( $parsed_args['status'] ) ) {
				$sql .= ' AND status = %s';
				$placeholders[] = strtolower( $parsed_args['status'] );
			}
		}

		// TODO: adjust for situations where we don't need to sort.
		$sql .= ' ORDER BY timestamp';
		$sql .= strtoupper( $parsed_args['order'] ) === 'ASC' ? ' ASC' : ' DESC';

		// Skip paging/limits if "-1" was passed to get all events.
		if ( $parsed_args['limit'] >= 1 ) {
			$sql .= ' LIMIT %d';
			$placeholders[] = $parsed_args['limit'];

			if ( ! is_null( $parsed_args['page'] ) ) {
				$offset = $parsed_args['limit'] * ( $parsed_args['page'] - 1 );
				if ( $offset > 0 ) {
					$sql .= ' OFFSET %d';
					$placeholders[] = $offset;
				}
			}
		}

		$last_changed = wp_cache_get_last_changed( 'cron-control-events' );
		$query_hash   = sha1( serialize( [ $sql, $placeholders ] ) ) . "::{$last_changed}";

		$results = wp_cache_get( "events::{$query_hash}", 'cron-control-events' );
		if ( false === $results ) {
			// Already prepared @codingStandardsIgnoreLine
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $placeholders ) );
			$results = is_array( $results ) ? $results : [];

			wp_cache_set( "events::{$query_hash}", $results, 'cron-control-events' );
		}

		return $results;
	}

	private static function validate_status( $status ): bool {
		$allowed_string_statuses = array_merge( self::ALLOWED_STATUSES, [ 'any' ] );

		if ( is_string( $status ) && in_array( strtolower( $status ), $allowed_string_statuses, true ) ) {
			return true;
		}

		if ( is_array( $status ) ) {
			$statuses = array_map( 'strtolower', $status );
			return empty( array_diff( $statuses, self::ALLOWED_STATUSES ) );
		}

		return false;
	}

	private static function validate_timestamp( $ts ): bool {
		if ( is_int( $ts ) ) {
			return true;
		}

		if ( is_string( $ts ) ) {
			return 'due_now' === $ts;
		}

		if ( is_array( $ts ) ) {
			return isset( $ts['from'], $ts['to'] ) && is_int( $ts['from'] ) && is_int( $ts['to'] );
		}

		return false;
	}

	private static function row_formatting( array $row ): array {
		$int_formats = [ 'ID', 'interval', 'timestamp' ];

		$formatting = [];
		foreach ( $row as $field => $value ) {
			if ( in_array( $field, $int_formats, true ) ) {
				$formatting[] = '%d';
			} else {
				// Strings for all the rest.
				$formatting[] = '%s';
			}
		}

		return $formatting;
	}

	private static function flush_event_cache() {
		wp_cache_set( 'last_changed', microtime(), 'cron-control-events' );
	}
}

Events_Store::instance();