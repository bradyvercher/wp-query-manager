<?php
Blazer_Six_WP_Query_Manager_Pro::setup();

class Blazer_Six_WP_Query_Manager_Pro {
	private static $columns;
	
	function setup() {
		$features = array( 'feeds', 'meta-queries', 'tax-queries' );
		foreach( $features as $feature ) {
			if ( file_exists( plugin_dir_path( __FILE__ ) . 'pro/' . $feature . '.php' ) ) {
				include( plugin_dir_path( __FILE__ ) . 'pro/' . $feature . '.php' );
			}
		}
		
		add_action( 'init', array( __CLASS__, 'init' ) );
	}
	
	function init() {
		add_action( 'wp_query_manager_options', array( __CLASS__, 'save_options' ) );
		add_filter( 'wp_query_manager_columns', array( __CLASS__, 'main_table_columns' ) );
		add_action( 'wp_query_manager_load_admin_page', array( __CLASS__, 'load_admin' ) );
		add_action( 'wp_query_manager_column-advanced', array( __CLASS__, 'column_advanced' ), 10, 2 );
	}
	
	function save_options( $options ) {
		if ( isset( $_POST['wp_query_manager'] ) && isset( $options['queries'] ) ) {
			foreach ( $_POST['wp_query_manager'] as $postdata ) {
				if ( ! empty ( $postdata['type'] ) ) {
					$stem = Blazer_Six_WP_Query_Manager_Admin::get_template_stem( $postdata );
					
					if ( isset( $options['queries'][ $stem ] ) && ! empty( $postdata['advanced'] ) ) {
						$options['queries'][ $stem ]['admin_data']['advanced'] = $postdata['advanced'];
						
						$advanced = array();
						wp_parse_str( $postdata['advanced'], $advanced );
						$options['queries'][ $stem ] = wp_parse_args( $options['queries'][ $stem ], $advanced );
					}
				}
			}
		}
		
		return $options;
	}
	
	function main_table_columns( $columns ) {
		$columns['advanced'] = __( 'Advanced' );
		
		return $columns;
	}
	
	function load_admin() {
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );
	}
	
	function column_advanced( $index, $data ) {
		$advanced = ( isset( $data['admin_data']['advanced'] ) ) ? $data['admin_data']['advanced'] : '';
		printf( '<input type="text" name="wp_query_manager[%1$s][advanced]" value="%2$s" class="large-text clear-on-add">', $index, esc_attr( $advanced ) );
	}
}
?>