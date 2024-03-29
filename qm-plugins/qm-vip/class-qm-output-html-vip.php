<?php

class QM_Output_Html_VIP extends QM_Output_Html {

	public function __construct( \QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 1 );
	}

	public function admin_menu( array $menu ) {
		$menu[] = $this->menu( array(
			'id'    => 'qm-vip',
			'href'  => '#qm-vip',
			'title' => esc_html__( 'VIP', 'query-monitor' ),
		));

		return $menu;
	}

	public function output() {
		$data = $this->collector->get_data();

		$this->before_non_tabular_output();

		// MU-Plugins section
		$this->output_before_section( 'MU-Plugins' );
		$this->output_table_row( 'Branch', 'prod' === $data->mu_plugins['branch'] ? 'production' : $data->mu_plugins['branch'] );
		if ( isset( $data->mu_plugins['commit'] ) && isset( $data->mu_plugins['date'] ) ) {
			$this->output_table_row( 'Last modified', $data->mu_plugins['date'] );
			$this->output_table_row( 'Commit', $data->mu_plugins['commit'], 'https://github.com/automattic/vip-go-mu-plugins/commit/' . $data->mu_plugins['commit'] );
		}
		$this->output_after_section();

		// App section
		$this->output_before_section( 'Application' );
		if ( isset( $data->app['id'] ) ) {
			$this->output_table_row( 'ID', $data->app['id'] );
		}
		if ( isset( $data->app['name'] ) ) {
			$this->output_table_row( 'Name', $data->app['name'] );
		}
		if ( isset( $data->app['branch'] ) ) {
			$this->output_table_row( 'Branch', $data->app['branch'] );
		}
		if ( isset( $data->app['commit'] ) ) {
			$this->output_table_row( 'Commit', $data->app['commit'] );
		}
		if ( isset( $data->app['jetpack'] ) ) {
			$this->output_table_row( 'Jetpack', $data->app['jetpack'] );
		}
		if ( isset( $data->app['es_version'] ) ) {
			$this->output_table_row( 'Elasticsearch', $data->app['es_version'] );
		}
		if ( isset( $data->app['fedramp'] ) ) {
			$this->output_table_row( 'FedRAMP', $data->app['fedramp'] ? 'Yes' : 'No' );
		}
		$this->output_after_section();

		$this->after_non_tabular_output();
	}
	/**
	 * Outputs a table row with a key-value pairing.
	 *
	 * @param string $title Title of table row
	 * @param string $value Value of table row
	 * @param string $link Inline link of table row value
	 */
	public function output_table_row( string $title, string $value, string $link = '' ) {
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $title ) . '</th>';
		echo '<td>';
		if ( ! empty( $link ) ) {
			echo '<a href="' . esc_url( $link ) . '">';
		}
		echo esc_html( $value );
		if ( ! empty( $link ) ) {
			echo '</a>';
		}
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Outputs the beginning of a table section.
	 *
	 * @param string $heading Heading of table
	 */
	public function output_before_section( string $heading ) {
		echo '<section>';
		if ( $heading ) {
			echo '<h3><strong>' . esc_html( $heading ) . '</strong></h3>';
		}
		echo '<table>';
		echo '<thead class="qm-screen-reader-text">';
		echo '<tr>';
		echo '<th scope="col">' . esc_html__( 'Property', 'qm-vip' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Value', 'qm-vip' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
	}

	/**
	 * Outputs the end of a table section.
	 */
	public function output_after_section() {
		echo '</tbody>';
		echo '</table>';
		echo '</section>';
	}
}
