<?php
Blazer_Six_WP_Query_Manager_Tax_Queries::setup();

class Blazer_Six_WP_Query_Manager_Tax_Queries {
	private static $columns;
	
	function setup() {
		add_action( 'wp_query_manager_options', array( __CLASS__, 'save_options' ) );
		add_filter( 'wp_query_manager_columns', array( __CLASS__, 'main_table_columns' ) );
		add_action( 'wp_query_manager_load_admin_page', array( __CLASS__, 'load_admin' ) );
		add_action( 'wp_query_manager_column-tax-query', array( __CLASS__, 'column_tax_query' ), 10, 2 );
		add_action( 'wp_query_manager_archives_tab_after', array( __CLASS__, 'tax_queries_table' ) );
	}
	
	function save_options( $options ) {
		if ( isset( $_POST['wp_query_manager_tax_queries'] ) ) {
			$vars = array( 'field', 'operator', 'taxonomy', 'terms' );
			
			foreach ( $_POST['wp_query_manager_tax_queries'] as $data ) {
				if ( ! empty ( $data['id'] ) ) {
					$id = $data['id'];
					
					foreach( $vars as $var ) {
						if ( ! empty( $data[ $var ] ) ) {
							$tax_queries[ $id ][ $var ] = $data[ $var ];
						}
					}
					
					if ( isset( $tax_queries[ $id ]['terms'] ) && strpos( $tax_queries[ $id ]['terms'], ',' ) ) {
						$tax_queries[ $id ]['terms'] = array_map( 'trim', explode( ',', $tax_queries[ $id ]['terms'] ) );
					}
				}
			}
			
			if ( isset( $tax_queries ) ) {
				$options['tax_queries'] = $tax_queries;
				
				if ( isset( $options['queries'] ) ) {
					foreach( $options['queries'] as $stem => $query ) {
						if ( isset( $query['tax_query'] ) ) {
							$and = strpos( $query['tax_query'], '&' );
							$or = strpos( $query['tax_query'], '|' );
							
							$relation = '';
							if ( $and && $or ) {
								$relation = ( $and < $or ) ? 'AND' : 'OR';
							} elseif ( $and && ! $or ) {
								$relation = 'AND';
							} elseif ( ! $and && $or ) {
								$relation = 'OR';
							}
							
							$ids = array( $query['tax_query'] );
							if ( 'AND' == $relation ) {
								$query['tax_query'] = str_replace( '|', '&', $query['tax_query'] );
								$ids = explode( '&', $query['tax_query'] );
							} elseif ( 'OR' == $relation ) {
								$query['tax_query'] = str_replace( '&', '|', $query['tax_query'] );
								$ids = explode( '|', $query['tax_query'] );
							}
							
							$options['queries'][ $stem ]['tax_query'] = array();
							
							foreach( $ids as $id ) {
								if ( isset( $tax_queries[ $id ] ) ) {
									$options['queries'][ $stem ]['tax_query'][ $id ] = $tax_queries[ $id ];
								}
							}
							
							if ( isset( $options['queries'][ $stem ]['tax_query'] ) && ! empty( $relation ) ) {
								$options['queries'][ $stem ]['tax_query']['relation'] = $relation;
							}
						}
					}
				}
			}
		}
		
		return $options;
	}
	
	function main_table_columns( $columns ) {
		$columns['tax-query'] = __( 'Tax Query', 'wp-query-manager' );
		
		return $columns;
	}
	
	function load_admin() {
		self::$columns = array(
			'tax-query-id' => __( 'ID', 'wp-query-manager' ),
			'tax-taxonomy' => __( 'Taxonomy', 'wp-query-manager' ),
			'tax-field' => __( 'Field', 'wp-query-manager' ),
			'tax-terms' => __( 'Terms', 'wp-query-manager' ),
			'tax-operator' => __( 'Operator', 'wp-query-manager' )
		);
	}
	
	function tax_queries_table() {
		?>
		<div class="wp-query-manager-section" data-column="tax-query"<?php echo Blazer_Six_WP_Query_Manager_Admin::column_visibility( 'tax-query' ); ?>>
			<h3><?php _e( 'Tax Queries', 'wp-query-manager' ); ?></h3>
			<table border="0" cellpadding="0" cellspacing="0" class="wp-query-manager-repeater widefat" id="wp-query-manager-tax-queries">
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
		$options = ( isset( $options['tax_queries'] ) ) ? $options['tax_queries'] : array( array() );
		
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$field_args = array( 'id', 'slug' );
		$operator_args = array( 'IN', 'NOT IN', 'AND' );
		
		$i = 0;
		foreach ( $options as $id => $data ) :
			$defaults = array(
				'taxonomy' => '',
				'field' => '',
				'terms' => '',
				'operator' => ''
			);
			
			$data = wp_parse_args( $data, $defaults );
			extract( $data );
			?>
			<tr class="repeater-item">
				<td class="column-tax-query-id">
					<?php $id = ( ! empty( $id ) ) ? $id : ''; ?>
					<input type="text" name="wp_query_manager_tax_queries[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $id ); ?>" class="medium-text clear-on-add">
				</td>
				<td class="column-tax-taxonomy">
					<select name="wp_query_manager_tax_queries[<?php echo $i; ?>][taxonomy]" class="clear-on-add">
						<?php
						foreach ( $taxonomies as $arg ) {
							printf( '<option value="%1$s"%2$s>%3$s</option>',
								esc_attr( $arg->name ),
								selected( $taxonomy, $arg->name, false ),
								$arg->labels->name
							);
						}
						?>
					</select>
				</td>
				<td class="column-tax-field">
					<select name="wp_query_manager_tax_queries[<?php echo $i; ?>][field]" class="clear-on-add">
						<?php
						foreach ( $field_args as $arg ) {
							printf( '<option value="%1$s"%2$s>%1$s</option>',
								esc_attr( $arg ),
								selected( $field, $arg, false )
							);
						}
						?>
					</select>
				</td>
				<td class="column-tax-terms">
					<input type="text" name="wp_query_manager_tax_queries[<?php echo $i; ?>][terms]" value="<?php echo esc_attr( $terms ); ?>" class="medium-text clear-on-add">
				</td>
				<td class="column-tax-field">
					<select name="wp_query_manager_tax_queries[<?php echo $i; ?>][operator]" class="clear-on-add">
						<?php
						foreach ( $operator_args as $arg ) {
							printf( '<option value="%1$s"%2$s>%1$s</option>',
								esc_attr( $arg ),
								selected( $operator, $arg, false )
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
	
	function column_tax_query( $index, $data ) {
		$tax_query = ( isset( $data['tax_query'] ) ) ? (array) $data['tax_query'] : array();
		
		$sep = '&';
		if ( isset( $tax_query['relation'] ) ) {
			$sep = ( 'AND' == $tax_query['relation'] ) ? '&' : '|';
		}
		unset( $tax_query['relation'] );
		
		$tax_query = join( array_keys( $tax_query ), $sep );
		
		printf( '<input type="text" name="wp_query_manager[%1$d][tax_query]" value="%2$s" class="tax-query medium-text clear-on-add">',
			$index,
			esc_attr( $tax_query )
		);
	}
}
?>