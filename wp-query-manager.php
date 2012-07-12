<?php
/*
Plugin Name: WP Query Manager
Version: 0.1
Plugin URI: http://github.com/bradyvercher/wp-query-manager
Description: An interface for easily modifying WP_Query parameters on archive pages and feeds.
Author: Blazer Six, Inc.
Author URI: http://www.blazersix.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

------------------------------------------------------------------------
Copyright 2012  Blazer Six, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


add_action( 'plugins_loaded', array( 'Blazer_Six_WP_Query_Manager', 'setup' ) );

class Blazer_Six_WP_Query_Manager {
	public static $indices = array();
	public static $options = array();
	
	function setup() {
		load_plugin_textdomain( 'wp-query-manager', false, 'wp-query-manager/languages' );
		
		if ( file_exists( plugin_dir_path( __FILE__ ) . 'wp-query-manager-pro.php' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'wp-query-manager-pro.php' );
		}
		
		if ( is_admin() ) {
			require_once( plugin_dir_path( __FILE__ ) . 'admin/admin.php' );
		}
		
		add_action( 'init', array( __CLASS__, 'init' ) );
	}
	
	function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ), 11 );
	}
	
	function parse_query( $query ) {
		if ( $query->is_main_query() ) {
			self::$indices = self::get_index_stems();
			self::$options = get_option( 'wp_query_manager' );
			
			if ( ! is_admin() && is_array( self::$indices ) ) {
				if ( ! is_feed() && isset( self::$options['queries'] ) ) {
					add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
				} elseif ( is_feed() && isset( self::$options['feed_queries'] ) ) {
					add_filter( 'pre_option_posts_per_rss', array( __CLASS__, 'pre_option_filter' ) );
					add_filter( 'pre_option_rss_use_excerpt', array( __CLASS__, 'pre_option_filter' ) );
					add_filter( 'excerpt_length', array( __CLASS__, 'feed_excerpt_length' ) );
				}
			}
		}
	}
	
	function pre_get_posts( $query ) {
		$opts = ( isset( self::$options['queries'] ) ) ? self::$options['queries'] : array( array() );
		$set_vars = array();
		
		// build the array of query vars
		foreach ( self::$indices as $index ) {
			if ( isset( $opts[ $index ] ) ) {
				$set_vars = wp_parse_args( $set_vars, $opts[ $index ] );
			}
		}
		
		// unset vars that don't pertain to the query
		unset( $set_vars['admin_data'] );
		
		if ( is_archive() && isset( $set_vars['posts_per_page'] ) ) {
			$set_vars['posts_per_archive_page'] = $set_vars['posts_per_page'];
			unset( $set_vars['posts_per_page'] );
		}
		
		foreach( $set_vars as $key => $var ) {
			$query->set( $key, $var );
		}
	}
	
	
	/**
	 * Feeds
	 */
	function pre_option_filter( $option ) {
		$options = self::$options;
		$options = $options['feed_queries'];
		
		// get the option name from the name of the filter
		$option_name = str_replace( 'pre_option_', '', current_filter() );
		
		// loop through the indices and if one has been set, return it's value
		foreach ( self::$indices as $stem ) {
			if ( isset( $options[ $stem ][ $option_name ] ) && '' !== $options[ $stem ][ $option_name ] ) {
				$option = $options[ $stem ][ $option_name ];
				break;
			}
		}
		
		return $option;
	}
	
	function feed_excerpt_length( $length ) {
		$indices = Blazer_Six_WP_Query_Manager::$indices;
		$options = Blazer_Six_WP_Query_Manager::$options;
		
		if ( isset( $options['feed_queries'] ) && ! empty( $indices ) ) {
			foreach( $indices as $stem ) {
				if ( isset( $options['feed_queries'][ $stem ]['excerpt_length'] ) ) {
					$length = $options['feed_queries'][ $stem ]['excerpt_length'];
					break;
				}
			}
		}
		
		return $length;
	}
	
	
	/**
	 * Get index stems
	 */
	function get_index_stems() {
		$indices = array();
		
		// build an array of index stems
		if ( is_search() ) $indices[] = 'search';
		if ( is_home() ) $indices[] = 'home';
		if ( is_front_page() ) $indices[] = 'front_page';
		
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term ) {
				$indices[] = 'tax_' . $term->taxonomy . '_' . $term->term_id;
				$indices[] = 'tax_' . $term->taxonomy;
			}
		}
		
		if ( is_author() ) {
			$author = null;
			if ( ! $author ) {
				$author = get_user_by( 'slug', get_query_var( 'author_name' ) );
				$author = $author->data;
			}
			
			if ( $author ) {
				$indices[] = 'author_' . $author->ID;
			}
			
			$indices[] = 'author';
		}
		
		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			
			$indices[] = 'post_type_' . $post_type;
			$indices[] = 'post_type';
		}
		
		if ( is_day() ) $indices[] = 'day';
		if ( is_month() ) $indices[] = 'month';
		if ( is_year() ) $indices[] = 'year';
		if ( is_archive() ) $indices[] = 'archive';
		
		return $indices;
	}
}


if ( ! function_exists( 'vd' ) ) :
function vd( $var ) {
	echo '<pre style="font-size: 12px; text-align: left">'; print_r( $var ); echo '</pre>';
}
endif;
?>