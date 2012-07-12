<?php
Blazer_Six_WP_Query_Manager_Admin_Feeds::setup();

class Blazer_Six_WP_Query_Manager_Admin_Feeds {
	private static $columns;
	
	function setup() {
		add_action( 'wp_query_manager_options', array( __CLASS__, 'save_options' ) );
		add_action( 'wp_query_manager_load_admin_page', array( __CLASS__, 'load_admin' ) );
		add_action( 'wp_query_manager_admin_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'wp_query_manager_admin_page', array( __CLASS__, 'feeds_table' ) );
	}
	
	function save_options( $options ) {
		if ( isset( $_POST['wp_query_manager_feeds'] ) ) {
			$vars = array( 'excerpt_length', 'posts_per_rss', 'rss_use_excerpt' );
			
			// clean up the option array and keep it slim
			foreach ( $_POST['wp_query_manager_feeds'] as $data ) {
				if ( ! empty ( $data['type'] ) ) {
					$stem = Blazer_Six_WP_Query_Manager_Admin::get_template_stem( $data );
					
					foreach( $vars as $var ) {
						if ( '' != $data[ $var ] ) {
							$queries[ $stem ][ $var ] = $data[ $var ];
						}
					}
					
					if ( isset( $queries[ $stem ] ) ) {
						$queries[ $stem ]['admin_data']['type'] = $data['type'];
						
						if ( isset( $data[ $data['type'] ] ) && ! empty( $data[ $data['type'] ] ) ) {
							$queries[ $stem ]['admin_data']['filter_value'] = $data[ $data['type'] ];
						}
					}
				}
			}
			
			if ( isset( $queries ) ) {
				$options['feed_queries'] = $queries;
			}
		}
		
		return $options;
	}
	
	function load_admin() {
		self::$columns = array(
			'template' => __( 'Feed' ),
			'posts-per-rss' => __( 'Posts' ),
			'rss-use-excerpt' => __( 'Content' ),
			'rss-excerpt-length' => __( 'Summary Length' )
		);
		
		add_filter( 'screen_settings', array( __CLASS__, 'screen_settings' ), 10, 2 );
	}
	
	function screen_settings( $settings, $screen ) {
		$settings.= '<h5>' . __( 'Feed table columns', 'wp-query-manager' ) . '</h5>';
		
		foreach ( self::$columns as $id => $column ) {
			if ( 'template' != $id ) {
				$settings.= sprintf( '<input type="checkbox" id="column-tog-%1$s" value="%2$s"%3$s class="wp-query-manager-column-tog"> <label for="column-tog-%1$s">%4$s</label>',
					esc_attr( $id ),
					esc_attr( $id ),
					checked( in_array( $id, Blazer_Six_WP_Query_Manager_Admin::$hidden_columns ), false, false ),
					esc_html( $column )
				);
			}
		}
		
		return $settings;
	}
	
	function add_tab( $tabs ) {
		$tabs['feeds'] = __( 'Feeds', 'wp-query-manager' );
		
		return $tabs;
	}
	
	function feeds_table() {
		?>
		<div class="tab-panel" id="tab-panel-feeds">
			<table border="0" cellpadding="0" cellspacing="0" class="wp-query-manager-repeater widefat" id="wp-query-manager-feeds">
				<thead>
					<tr>
						<?php
						foreach ( self::$columns as $id => $column ) {
							printf ( '<th class="column-%1$s"%2$s>%3$s</th>',
								esc_attr( $id ),
								Blazer_Six_WP_Query_Manager_Admin::column_visibility( $id ),
								esc_html( $column )
							);
						}
						?>
						<th width="16"><a class="repeater-add-item add-item"><img src="<?php echo plugins_url( 'admin/images/icons/add.png', dirname( __FILE__ ) ); ?>" width="16" height="16" alt="<?php esc_attr_e( 'Add Item', 'wp-query-manager' ); ?>" title="<?php esc_attr_e( 'Add Option', 'wp-query-manager' ); ?>" /></a></th>
					</tr>
				</thead>
				<tbody class="repeater-items">
					<?php self::get_rows(); ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function get_rows() {
		$options = get_option( 'wp_query_manager' );
		$options = ( isset( $options['feed_queries'] ) ) ? $options['feed_queries'] : array( array() );
		
		$i = 0;
		foreach ( $options as $key => $data ) :
			$defaults = array(
				'excerpt_length' => '',
				'posts_per_rss' => '',
				'rss_use_excerpt' => '',
				'admin_data' => array(
					'type' => '',
					'filter_value' => ''
				)
			);
			
			$data = wp_parse_args( $data, $defaults );
			$data['admin_data'] = wp_parse_args( $data['admin_data'], $defaults['admin_data'] );
			extract( $data );
			?>
			<tr class="repeater-item">
				<td class="column-template">
					<?php Blazer_Six_WP_Query_Manager_Admin::get_column_template( $i, $admin_data['type'], $admin_data['filter_value'], 'wp_query_manager_feeds' ); ?>
				</td>
				<td class="column-posts-per-rss"<?php echo Blazer_Six_WP_Query_Manager_Admin::column_visibility( 'posts-per-rss' ); ?>>
					<input type="text" name="wp_query_manager_feeds[<?php echo $i; ?>][posts_per_rss]" value="<?php echo esc_attr( $posts_per_rss ); ?>" class="small-text clear-on-add">
				</td>
				<td class="column-rss-use-excerpt"<?php echo Blazer_Six_WP_Query_Manager_Admin::column_visibility( 'rss-use-excerpt' ); ?>>
					<select name="wp_query_manager_feeds[<?php echo $i; ?>][rss_use_excerpt]" class="rss-use-excerpt clear-on-add">
						<option value=""></option>
						<option value="0" <?php selected( $rss_use_excerpt, 0 ); ?>>Full text</option>
						<option value="1" <?php selected( $rss_use_excerpt, 1 ); ?>>Summary</option>
					</select>
				</td>
				<td class="column-rss-excerpt-length"<?php echo Blazer_Six_WP_Query_Manager_Admin::column_visibility( 'rss-excerpt-length' ); ?>>
					<input type="text" name="wp_query_manager_feeds[<?php echo $i; ?>][excerpt_length]" value="<?php echo esc_attr( $excerpt_length ); ?>" class="feed-excerpt-length small-text clear-on-add">
					<!--<a href="" class="view" target="_blank"><img src="<?php echo plugins_url( 'admin/images/icons/link.png', dirname( __FILE__ ) ); ?>" width="16" height="16" alt="<?php esc_attr_e( 'View Feed', 'wp-query-manager' ); ?>" title="<?php esc_attr_e( 'View Feed', 'wp-query-manager' ); ?>" /></a>-->
				</td>
				<td align="center" valign="middle" style="vertical-align: middle">
					<a class="repeater-remove-item remove-item"><img src="<?php echo plugins_url( 'admin/images/icons/delete.png', dirname( __FILE__ ) ); ?>" width="15" height="16" alt="<?php esc_attr_e( 'Delete Option', 'wp-query-manager' ); ?>" title="<?php esc_attr_e( 'Delete Option', 'wp-query-manager' ); ?>" /></a>
				</td>
			</tr>
			<?php
			$i++;
		endforeach;
	}
}
?>