<?php
Blazer_Six_WP_Query_Manager_Admin::setup();

class Blazer_Six_WP_Query_Manager_Admin {
	private static $columns;
	public static $hidden_columns;
	public static $templates;
	
	function setup() {
		add_action( 'init', array( __CLASS__, 'init' ), 11 );
	}
	
	function init() {
		if ( isset( $_POST['button_wp_query_manager_save'] ) ) {
			if ( isset( $_POST['wp_query_manager'] ) && check_admin_referer( 'update-wp-query-manager-options', 'wp_query_manager_options_nonce' ) ) {
				$vars = array( 'meta_query', 'meta_query_relation', 'order', 'orderby', 'posts_per_page', 'tax_query', 'tax_query_relation' );
				
				// clean up the option array and keep it slim
				foreach ( $_POST['wp_query_manager'] as $postdata ) {
					if ( ! empty ( $postdata['type'] ) ) {
						$stem = self::get_template_stem( $postdata );
						
						foreach( $vars as $var ) {
							if ( ! empty( $postdata[ $var ] ) ) {
								$queries[ $stem ][ $var ] = $postdata[ $var ];
							}
						}
						
						if ( isset( $queries[ $stem ] ) ) {
							$queries[ $stem ]['admin_data']['type'] = $postdata['type'];
							
							if ( isset( $postdata[ $postdata['type'] ] ) && ! empty( $postdata[ $postdata['type'] ] ) ) {
								$queries[ $stem ]['admin_data']['filter_value'] = $postdata[ $postdata['type'] ];
							}
						}
					}
				}
			}
			
			$options = array();
			if ( isset( $queries ) ) {
				$options['queries'] = $queries;
			}
			$options = apply_filters( 'wp_query_manager_options', $options );
			
			update_option( 'wp_query_manager', $options );
			wp_safe_redirect( add_query_arg( 'settings-updated', 'true' ) );
			exit;
		}
		
		add_action( 'wp_ajax_wp_query_manager_get_terms', array( __CLASS__, 'ajax_get_terms' ) );
		add_action( 'wp_ajax_wp_query_manager_preferences', array( __CLASS__, 'ajax_save_user_preferences' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}
	
	function admin_menu() {
		$pagehook = add_options_page( __( 'WP Query Manager', 'wp-query-manager' ), __( 'WP Query Manager', 'wp-query-manager' ), 'manage_options', 'wp-query-manager', array( __CLASS__, 'options_page' ) );
		add_action( 'load-' . $pagehook, array( __CLASS__, 'load_options_page' ) );
		
		add_meta_box( 'submitdiv', 'Settings', array( __CLASS__, 'settings_meta_box' ), $pagehook, 'side' );
		add_meta_box( 'bscarddiv', 'Credits', array( __CLASS__, 'bscard_meta_box' ), $pagehook, 'side' );
	}
	
	function load_options_page() {
		$columns = array(
			'template' => __( 'Template', 'wp-query-manager' ),
			'posts-per-page' => __( 'Posts Per Page', 'wp-query-manager' ),
			'orderby' => __( 'Order By', 'wp-query-manager' ),
			'order' => __( 'Order', 'wp-query-manager' )
		);
		self::$columns = apply_filters( 'wp_query_manager_columns', $columns );
		
		
		$user = wp_get_current_user();
		$hidden = get_user_option( 'wp_query_manager_hidden_columns', $user->ID );
		self::$hidden_columns = ( ! $hidden ) ? apply_filters( 'wp_query_manager_default_hidden_columns', array() ) : (array) $hidden;
		
		
		$authors = get_users( 'orderby=display_name&order=DESC&who=authors' );
		$post_types_with_archive = get_post_types( array( 'has_archive' => 1 ), 'objects' );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		
		$templates['archive'] = __( 'Archive', 'wp-query-manager' );
		$templates['author'] = __( 'Author', 'wp-query-manager' );
		$templates['front_page'] = __( 'Front Page', 'wp-query-manager' );
		$templates['home'] = __( 'Home', 'wp-query-manager' );
		if ( $post_types_with_archive ) {
			$templates['post_type'] = __( 'Post Type', 'wp-query-manager' );
			uasort( $post_types_with_archive, array( __CLASS__, 'sort_post_types_by_name' ) );
		}
		$templates['search'] = __( 'Search', 'wp-query-manager' );
		$templates['Date Based']['day'] = __( 'Day', 'wp-query-manager' );
		$templates['Date Based']['month'] = __( 'Month', 'wp-query-manager' );
		$templates['Date Based']['year'] = __( 'Year', 'wp-query-manager' );
		
		if ( $taxonomies ) {
			foreach ( $taxonomies as $name => $taxonomy ) {
				$templates['Taxonomies'][ 'tax_' . $name ] = $taxonomy->labels->singular_name;
			}
		}
		
		self::$templates = array(
			'authors' => $authors,
			'post_types' => $post_types_with_archive,
			'taxonomies' => $taxonomies,
			'templates' => $templates
		);
		
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );
		add_filter( 'screen_settings', array( __CLASS__, 'screen_settings' ), 10, 2 );
		
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'post' );
		wp_enqueue_script( 'wp-query-manager-admin-script', plugins_url( 'admin/admin.js', dirname( __FILE__ ) ), array( 'jquery' ) );
		wp_enqueue_style( 'wp-query-manager-admin-style', plugins_url( 'admin/admin.css', dirname( __FILE__ ) ) );
		
		
		/*get_current_screen()->add_help_tab( array(
			'id' => 'overview',
			'title' => __( 'Overview', 'wp-query-manager' ),
			'content' => '<p>' . 'This is where you\'d get help if I weren\'t lazy.' . '</p>'
		) );*/
		
		do_action( 'wp_query_manager_load_admin_page' );
	}
	
	function screen_settings( $settings, $screen ) {
		$settings.= '<h5>' . __( 'Archive table columns', 'wp-query-manager' ) . '</h5>';
		
		foreach ( self::$columns as $id => $column ) {
			if ( 'template' != $id ) {
				$settings.= sprintf( '<input type="checkbox" id="column-tog-%1$s" value="%2$s"%3$s class="wp-query-manager-column-tog"> <label for="column-tog-%1$s">%4$s</label>',
					esc_attr( $id ),
					esc_attr( $id ),
					checked( in_array( $id, self::$hidden_columns ), false, false ),
					esc_html( $column )
				);
			}
		}
		
		return $settings;
	}
	
	function options_page() {
		?>
		<div class="wrap" id="wp-query-manager">
			<div id="icon-wp-query-manager" class="icon32"><br></div>
			<h2><?php _e( 'WP Query Manager', 'wp-query-manager' ); ?></h2>
			
			<?php if ( isset( $_REQUEST['settings-updated'] ) ) : ?>
				<div class="updated fade"><p><strong><?php _e( 'Settings saved.', 'wp-query-manager' ); ?></strong></p></div>
			<?php endif;?>
			
			<form action="" method="post">
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-<?php echo ( 1 == get_current_screen()->get_columns() ) ? '1' : '2'; ?>">
						
						<div id="post-body-content">
							<?php
							wp_nonce_field( 'update-wp-query-manager-options', 'wp_query_manager_options_nonce' );
							wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
							wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
							do_meta_boxes( 'settings_page_wp-query-manager', 'normal', '' );
							?>
							
							<?php
							$tabs = apply_filters( 'wp_query_manager_admin_tabs', array( 'archives' => __( 'Archives', 'wp-query-manager' ) ) );
							if ( sizeof( $tabs ) > 1 ) :
								?>
								<h3 class="nav-tab-wrapper">
									<?php
									foreach ( $tabs as $id => $tab ) {
										printf( '<a href="#tab-panel-%1$s" class="nav-tab">%2$s</a>', $id, $tab );
									}
									?>
								</h3>
							<?php endif; ?>
							
							<div class="tab-panel tab-panel-active" id="tab-panel-archives">
								<table border="0" cellpadding="0" cellspacing="0" class="wp-query-manager-repeater widefat" id="wp-query-manager-archives">
									<thead>
										<tr>
											<?php
											foreach ( self::$columns as $id => $column ) {
												printf ( '<th class="column-%1$s"%2$s>%3$s</th>',
													esc_attr( $id ),
													self::column_visibility( $id ),
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
								
								<?php do_action( 'wp_query_manager_archives_tab_after' ); ?>
							</div>
							
							<?php do_action( 'wp_query_manager_admin_page' ); ?>
						</div>
						
						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes( 'settings_page_wp-query-manager', 'side', '' ); ?>
						</div>
							
					</div><!--end div#post-body-->
					<br class="clear">
				</div><!--end div#poststuff-->
			</form>
		</div><!--end div.wrap-->
		<?php
	}
	
	function get_rows() {
		$options = get_option( 'wp_query_manager' );
		$options = ( isset( $options['queries'] ) ) ? $options['queries'] : array( array() );
		
		$orderby_args = array(
			'author' => __( 'Author', 'wp-query-manager' ),
			'comment_count' => __( 'Comment Count', 'wp-query-manager' ),
			'date' => __( 'Date', 'wp-query-manager' ),
			'modified' => __( 'Date Modified', 'wp-query-manager' ),
			'menu_order' => __( 'Menu Order', 'wp-query-manager' ),
			'none' => __( 'None', 'wp-query-manager' ),
			'parent' => __( 'Parent ID', 'wp-query-manager' ),
			'ID' => __( 'Post ID', 'wp-query-manager' ),
			'rand' => __( 'Random', 'wp-query-manager' ),
			'title' => __( 'Title', 'wp-query-manager' )
		);
		
		$i = 0;
		foreach ( $options as $key => $data ) :
			$defaults = array(
				'posts_per_page' => '',
				'order' => '',
				'orderby' => '',
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
				<?php foreach( self::$columns as $column_id => $column_name ) : ?>
					
					<td class="column-<?php echo sanitize_key( $column_id ); ?>"<?php echo self::column_visibility( $column_id ); ?>>
					
						<?php if ( 'template' == $column_id ) : ?>
							
							<?php self::get_column_template( $i, $admin_data['type'], $admin_data['filter_value'] ); ?>
							
						<?php elseif ( 'posts-per-page' == $column_id ) : ?>
							
							<input type="text" name="wp_query_manager[<?php echo $i; ?>][posts_per_page]" value="<?php echo esc_attr( $posts_per_page ); ?>" class="small-text clear-on-add">
							
						<?php elseif ( 'orderby' == $column_id ) : ?>
							
							<select name="wp_query_manager[<?php echo $i; ?>][orderby]" class="clear-on-add">
								<option value=""></option>
								<?php
								foreach ( $orderby_args as $arg => $name ) {
									printf( '<option value="%1$s"%2$s>%3$s</option>',
										esc_attr( $arg ),
										selected( $orderby, $arg, false ),
										esc_html( $name )
									);
								}
								?>
							</select>
							
						<?php elseif ( 'order' == $column_id ) : ?>
							
							<select name="wp_query_manager[<?php echo $i; ?>][order]" class="clear-on-add">
								<option value=""></option>
								<option value="DESC" <?php selected( $order, 'DESC' ); ?>>DESC</option>
								<option value="ASC" <?php selected( $order, 'ASC' ); ?>>ASC</option>
							</select>
							
							<!--<a href="" class="view" target="_blank"><img src="<?php echo plugins_url( 'admin/images/icons/link.png', dirname( __FILE__ ) ); ?>" width="16" height="16" alt="<?php esc_attr_e( 'View Page', 'wp-query-manager' ); ?>" title="<?php esc_attr_e( 'View Page', 'wp-query-manager' ); ?>" /></a>-->
						
						<?php else : ?>
							
							<?php do_action( 'wp_query_manager_column-' . $column_id, $i, $data ); ?>
							
						<?php endif; ?>
					
					</td>
					
				<?php endforeach; ?>
				
				<td align="center" valign="middle" style="vertical-align: middle">
					<a class="repeater-remove-item remove-item"><img src="<?php echo plugins_url( 'admin/images/icons/delete.png', dirname( __FILE__ ) ); ?>" width="15" height="16" alt="<?php esc_attr_e( 'Delete Option', 'wp-query-manager' ); ?>" title="<?php esc_attr_e( 'Delete Option', 'wp-query-manager' ); ?>" /></a>
				</td>
			</tr>
			<?php
			$i++;
		endforeach;
	}
	
	function get_column_template( $index, $type, $filter_value, $field_name = 'wp_query_manager' ) {
		$field_name = $field_name . '[' . $index . ']';
		?>
		<select name="<?php echo $field_name; ?>[type]" class="archive-type clear-on-add">
			<?php self::display_dropdown_options( self::$templates['templates'], $type ); ?>
		</select>
		
		<select name="<?php echo $field_name; ?>[author]" class="object-filter filter-author clear-on-add hide-on-add">
			<option value=""></option>
			<?php
			foreach ( self::$templates['authors'] as $author ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>',
					$author->ID,
					selected( $author->ID, $filter_value, false ),
					esc_html( $author->display_name )
				);
			}
			?>
		</select>
		
		<?php if ( self::$templates['post_types'] ) : ?>
			<select name="<?php echo $field_name; ?>[post_type]" class="object-filter filter-post_type clear-on-add hide-on-add">
				<option value=""></option>
				<?php
				foreach ( self::$templates['post_types'] as $type => $post_type ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $type ),
						selected( $type, $filter_value, false ),
						esc_html( $post_type->labels->name )
					);
				}
				?>
			</select>
		<?php endif; ?>
		
		<?php
		if ( self::$templates['taxonomies'] ) :
			foreach ( self::$templates['taxonomies'] as $name => $taxonomy ) {
				if ( $taxonomy->hierarchical ) {
					echo '<select name="' . $field_name . '[tax_' . esc_attr( $name ) . ']" class="object-filter filter-tax_' . esc_attr( $name ) . ' clear-on-add hide-on-add">';
						echo '<option value=""></option>';
						$terms = get_terms( $name );
						foreach ( $terms as $term ) {
							printf( '<option value="%1$s"%2$s>%3$s</option>',
								$term->term_id,
								selected( $term->term_id, $filter_value, false ),
								esc_html( $term->name )
							);
						}
					echo '</select>';
				} else {
					$style = '';
					if ( 'tax_' . $taxonomy->name == $type && ! empty( $filter_value ) && ! is_numeric( $filter_value ) ) {
						$term = get_term_by( 'name', $filter_value, $taxonomy->name );
						if ( ! $term ) {
							$style = ' style="border-color: #ee0000"';
						}
					}
					
					printf( '<input type="text" name="%1$s[tax_%2$s]" value="%3$s" class="object-filter filter-tax_%2$s clear-on-add hide-on-add"%4$s>',
						$field_name,
						esc_attr( $name ),
						esc_attr( $filter_value ),
						$style
					);
				}
			}
		endif;
	}
	
	function bscard_meta_box() {
		$current_user = wp_get_current_user();
		?>
		
		<div class="bscard">
			<h4 class="hndle">
				<a href="http://www.blazersix.com/" target="_blank">
					<img src="<?php echo plugins_url( 'admin/images/blazersix.png', dirname( __FILE__ ) ); ?>" width="50" height="50" alt="Blazer Six" />
					<span><?php _e( 'Built by', 'wp-query-manager' ); ?> <strong>Blazer Six, Inc.</strong></span>
				</a>
			</h4>
			<div class="bscard-inside">
				<ul class="bscard-social">
					<li class="bscard-social-twitter"><a href="http://twitter.com/BlazerSix" target="_blank">@BlazerSix</a></li>
					<li class="bscard-social-facebook"><a href="https://www.facebook.com/pages/Blazer-Six/241713012554129" target="_blank"><?php _e( 'Facebook', 'wp-query-manager' ); ?></a></li>
				</ul>
			</div>
		</div>
		<br class="clear" />
		<?php
	}
	
	function settings_meta_box() {
		?>
		<div id="minor-publishing">
			<div class="misc-pub-section" style="padding-top: 0; padding-bottom: 0">
				<p>
					<strong><?php printf( __( 'Your <a href="%1$s">default settings</a> are:', 'wp-query-manager' ), admin_url( 'options-reading.php' ) ); ?></strong>
				</p>
				<ul>
					<li><?php printf( __( 'Blog pages show: <em>%1$d posts</em>', 'wp-query-manager' ), get_option( 'posts_per_page' ) ); ?></li>
					<li><?php printf( __( 'Feeds show the most recent: <em>%1$d items</em>', 'wp-query-manager' ), get_option( 'posts_per_rss' ) ); ?></li>
					<li><?php printf( __( 'Feeds show: <em>%1$s</em>', 'wp-query-manager' ), ( get_option( 'rss_use_excerpt' ) ) ? __( 'Summary', 'wp-query-manager' ) : __( 'Full text', 'wp-query-manager' ) ); ?></li>
					<li><?php printf( __( 'Feed summary length: <em>%1$d</em>', 'wp-query-manager' ), apply_filters( 'excerpt_length', 55 ) ); ?></li>
				</ul>
			</div>
			<div class="clear"></div>
		</div>
		<div id="major-publishing-actions">
			<div id="publishing-action">
				<input type="submit" name="button_wp_query_manager_save" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'wp-query-manager' ); ?>">
			</div>
			<div class="clear"></div>
		</div>
		<?php
	}
	
	
	/**
	 * Helper Methods
	 */
	
	function column_visibility( $id ) {
		return ( in_array( $id, self::$hidden_columns ) ) ? ' style="display: none"' : '';
	}
	
	function get_template_stem( $data ) {
		if ( isset( $data[ $data['type'] ] ) && ! empty( $data[ $data['type'] ] ) ) {
			// determine if we're working with a non-hierarchical taxonomy type
			if ( false !== strpos( $data['type'], 'tax_' ) && $tax_name = str_replace( 'tax_', '', $data['type'] ) ) {
				$tax = get_taxonomy( $tax_name );
			}
			
			if ( isset( $tax ) && ! empty( $tax ) && ! $tax->hierarchical && ! is_numeric( $data[ $data['type'] ] ) ) {
				$term = get_term_by( 'name', $data[ $data['type'] ], $tax->name );
				if ( $term ) {
					$stem = $data['type'] . '_' . $term->term_id;
				}
			} else {
				$stem = $data['type'] . '_' . $data[ $data['type'] ];
			}
		}
		
		if ( ! isset( $stem ) ) {
			$stem = $data['type'];
		}
		
		return $stem;
	}
	
	function display_dropdown_options( $array, $selected='', $group = false ) {
		if ( is_array( $array ) ) {
			echo ( $group ) ? '<optgroup label="' . esc_attr( $group ) . '">' : '<option value=""></option>';
			
			foreach ( $array as $key => $value ) {
				if ( is_array( $value ) ) {
					self::display_dropdown_options( $value, $selected, $key );
				} else {
					printf( '<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $key ),
						selected( $key, $selected, false ),
						esc_html( $value )
					);
				}
			}
			
			echo ( $group ) ? '</optgroup>' : '';
		}
	}
	
	function sort_post_types_by_name( $a, $b ) {
		if ( $a->labels->name == $b->labels->name ) {
			return 0;
		}
		return ( $a->labels->name < $b->labels->name ) ? -1 : 1;
	}
	
	
	/**
	 * AJAX Methods
	 */
	
	function ajax_get_terms() {
		global $wpdb;
		
		$var = like_escape( stripslashes( $_GET['name'] ) ) . '%';
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT t.name
			FROM $wpdb->terms t
			INNER JOIN $wpdb->term_taxonomy tt ON t.term_id=tt.term_id
			WHERE tt.taxonomy=%s AND t.name LIKE %s
			ORDER BY name ASC", stripslashes($_GET['taxonomy']), $var ) );
		
		echo json_encode( $terms );
		exit;
	}
	
	function ajax_save_user_preferences() {
		check_ajax_referer( 'screen-options-nonce', 'screenoptionnonce' );
		
		$hidden = ( isset( $_POST['hidden'] ) ) ? explode( ',', $_POST['hidden'] ) : array();
		
		if ( ! $user = wp_get_current_user() ) {
			die( '-1' );
		}
		
		if ( is_array( $hidden ) ) {
			update_user_option( $user->ID, 'wp_query_manager_hidden_columns', $hidden, true );
		}
		
		die( '1' );
	}
}
?>