<?php
Blazer_Six_WP_Query_Manager_Meta_Queries::setup();

class Blazer_Six_WP_Query_Manager_Meta_Queries {
	private static $columns;
	
	function setup() {
		add_action( 'wp_query_manager_options', array( __CLASS__, 'save_options' ) );
		add_filter( 'wp_query_manager_columns', array( __CLASS__, 'main_table_columns' ) );
		add_action( 'wp_query_manager_load_admin_page', array( __CLASS__, 'load_admin' ) );
		add_action( 'wp_query_manager_column-meta-query', array( __CLASS__, 'column_meta_query' ), 10, 2 );
		add_action( 'wp_query_manager_archives_tab_after', array( __CLASS__, 'meta_queries_table' ) );
	}
	
	function save_options( $options ) {
		if ( isset( $_POST['wp_query_manager_meta_queries'] ) ) {
			$vars = array( 'compare', 'key', 'type', 'value' );
			
			foreach ( $_POST['wp_query_manager_meta_queries'] as $data ) {
				if ( ! empty ( $data['id'] ) ) {
					$id = $data['id'];
					
					foreach( $vars as $var ) {
						if ( ! empty( $data[ $var ] ) ) {
							$meta_queries[ $id ][ $var ] = $data[ $var ];
						}
					}
					
					if ( isset( $meta_queries[ $id ]['value'] ) && strpos( $meta_queries[ $id ]['value'], ',' ) ) {
						// only split the value if the compare operator supports arrays
						if ( in_array( $meta_queries[ $id ]['compare'], array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
							$meta_queries[ $id ]['value'] = array_map( 'trim', explode( ',', $meta_queries[ $id ]['value'] ) );
						}
					}
				}
			}
			
			
			
			if ( isset( $meta_queries ) ) {
				$options['meta_queries'] = $meta_queries;
				
				if ( isset( $options['queries'] ) ) {
					foreach( $options['queries'] as $stem => $query ) {
						if ( isset( $query['meta_query'] ) ) {
							$and = strpos( $query['meta_query'], '&' );
							$or = strpos( $query['meta_query'], '|' );
							
							$relation = '';
							if ( $and && $or ) {
								$relation = ( $and < $or ) ? 'AND' : 'OR';
							} elseif ( $and && ! $or ) {
								$relation = 'AND';
							} elseif ( ! $and && $or ) {
								$relation = 'OR';
							}
							
							$ids = array( $query['meta_query'] );
							if ( 'AND' == $relation ) {
								$query['meta_query'] = str_replace( '|', '&', $query['meta_query'] );
								$ids = explode( '&', $query['meta_query'] );
							} elseif ( 'OR' == $relation ) {
								$query['meta_query'] = str_replace( '&', '|', $query['meta_query'] );
								$ids = explode( '|', $query['meta_query'] );
							}
							
							$options['queries'][ $stem ]['meta_query'] = array();
							
							foreach( $ids as $id ) {
								if ( isset( $meta_queries[ $id ] ) ) {
									$options['queries'][ $stem ]['meta_query'][ $id ] = $meta_queries[ $id ];
								}
							}
							
							if ( isset( $options['queries'][ $stem ]['meta_query'] ) && ! empty( $relation ) ) {
								$options['queries'][ $stem ]['meta_query']['relation'] = $relation;
							}
						}
					}
				}
			}
		}
		
		return $options;
	}
	
	function main_table_columns( $columns ) {
		$columns['meta-query'] = __( 'Meta Query', 'wp-query-manager' );
		
		return $columns;
	}
	
	function load_admin() {
		self::$columns = array(
			'meta-query-id' => __( 'ID', 'wp-query-manager' ),
			'meta-key' => __( 'Key', 'wp-query-manager' ),
			'meta-value' => __( 'Value', 'wp-query-manager' ),
			'meta-compare' => __( 'Compare', 'wp-query-manager' ),
			'meta-type' => __( 'Type', 'wp-query-manager' )
		);
	}
	
	function meta_queries_table() {
		?>
		<div class="wp-query-manager-section" data-column="meta-query"<?php echo Blazer_Six_WP_Query_Manager_Admin::column_visibility( 'meta-query' ); ?>>
			<h3><?php _e( 'Meta Queries', 'wp-query-manager' ); ?></h3>
			<table border="0" cellpadding="0" cellspacing="0" class="wp-query-manager-repeater widefat" id="wp-query-manager-meta-queries">
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
		$options = ( isset( $options['meta_queries'] ) ) ? $options['meta_queries'] : array( array() );
		
		$compare_args = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' );
		$type_args = array( 'CHAR', 'BINARY', 'DATE', 'DATETIME', 'DECIMAL', 'NUMERIC', 'SIGNED', 'TIME', 'UNSIGNED' );
		
		$i = 0;
		foreach ( $options as $id => $data ) :
			$defaults = array(
				'key' => '',
				'value' => '',
				'compare' => '=',
				'type' => 'CHAR'
			);
			
			$data = wp_parse_args( $data, $defaults );
			extract( $data );
			?>
			<tr class="repeater-item">
				<td class="column-meta-query-id">
					<?php $id = ( ! empty( $id ) ) ? $id : ''; ?>
					<input type="text" name="wp_query_manager_meta_queries[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $id ); ?>" class="medium-text clear-on-add">
				</td>
				<td class="column-meta-key">
					<input type="text" name="wp_query_manager_meta_queries[<?php echo $i; ?>][key]" value="<?php echo esc_attr( $key ); ?>" class="medium-text clear-on-add">
				</td>
				<td class="column-meta-value">
					<?php $value = ( is_array( $value ) ) ? join( $value, ',' ) : $value; ?>
					<input type="text" name="wp_query_manager_meta_queries[<?php echo $i; ?>][value]" value="<?php echo esc_attr( $value ); ?>" class="medium-text clear-on-add">
				</td>
				<td class="column-meta-compare">
					<select name="wp_query_manager_meta_queries[<?php echo $i; ?>][compare]" class="clear-on-add">
						<?php
						foreach ( $compare_args as $arg ) {
							printf( '<option value="%1$s"%2$s>%1$s</option>',
								esc_attr( $arg ),
								selected( $compare, $arg, false )
							);
						}
						?>
					</select>
				</td>
				<td class="column-meta-type">
					<select name="wp_query_manager_meta_queries[<?php echo $i; ?>][type]" class="clear-on-add">
						<?php
						foreach ( $type_args as $arg ) {
							printf( '<option value="%1$s"%2$s>%1$s</option>',
								esc_attr( $arg ),
								selected( $type, $arg, false )
							);
						}
						?>
					</select>
				</td>
				<td align="center" valign="middle" style="vertical-align: middle">
					<a class="repeater-remove-item remove-item"><img src="<?php echo plugins_url( 'admin/images/icons/delete.png', dirname( __FILE__ ) ); ?>" width="15" height="16" alt="<?php esc_attr_e( 'Delete Option', 'wp-query-manager' ); ?>" title="<?php esc_attr_e( 'Delete Option', 'wp-query-manager' ); ?>" /></a>
				</td>
			</tr>
			<?php
			$i++;
		endforeach;
	}
	
	function column_meta_query( $index, $data ) {
		$meta_query = ( isset( $data['meta_query'] ) ) ? $data['meta_query'] : array();
		
		$sep = '&';
		if ( isset( $meta_query['relation'] ) ) {
			$sep = ( 'AND' == $meta_query['relation'] ) ? '&' : '|';
		}
		unset( $meta_query['relation'] );
		
		$meta_query = join( array_keys( $meta_query ), $sep );
		
		printf( '<input type="text" name="wp_query_manager[%1$d][meta_query]" value="%2$s" class="meta-query medium-text clear-on-add">',
			$index,
			esc_attr( $meta_query )
		);
	}
}
?>