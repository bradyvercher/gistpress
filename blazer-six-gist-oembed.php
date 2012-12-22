<?php
/**
 * Plugin Name: Blazer Six Gist oEmbed
 * Plugin URI: https://github.com/bradyvercher/wp-blazer-six-gist-oembed
 * Description: Gist oEmbed and shortcode support with caching.
 * Version: 1.0.1
 * Author: Blazer Six, Inc.
 * Author URI: http://www.blazersix.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Blazer_Six_Gist_oEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @copyright Copyright (c) 2012, Blazer Six, Inc.
 * @license GPL-2.0+
 *
 * @todo Add support in the shortcode for returning specific line numbers.
 * @todo Shortcode for highlighting specific lines.
 * @todo Feed support: link directly to post, directly to Gist, or wrap in iframe?
 */

/**
 * Load the plugin when plugins are loaded.
 */
add_action( 'plugins_loaded', array( 'Blazer_Six_Gist_oEmbed', 'load' ) );

/**
 * The main plugin class.
 *
 * @since 1.0.0
 */
class Blazer_Six_Gist_oEmbed {
	/**
	 * Set up the plugin.
	 *
	 * Adds a [gist] shortcode to do the bulk of the heavylifting. An embed
	 * handler is registered to mimic oEmbed functionality, but it relies on
	 * the shortcode for processing.
	 *
	 * @since 1.0.0
	 */
	public static function load() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		wp_embed_register_handler( 'gist', '#(https://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', array( __CLASS__, 'wp_embed_handler' ) );
		add_shortcode( 'gist', array( __CLASS__, 'shortcode' ) );
	}
	
	/**
	 * Register the Gist stylesheet so it will only be embedded once.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		wp_register_style( 'github-gist', 'https://gist.github.com/stylesheets/gist/embed.css' );	
	}
	
	/**
	 * WP embed handler to generate a shortcode string from a Gist URL.
	 *
	 * Parses Gist URLs for oEmbed support. Returns the value as a shortcode
	 * string to let the shortcode method handle processing. The value
	 * returned also doesn't have wpautop() applied, which is a must for
	 * source code.
	 *
	 * If a file is specified in the hash of the URL for a multi-file Gist, it
	 * will be picked up and only the single file will be displayed.
	 *
	 * @since 1.0.0
	 */
	public static function wp_embed_handler( $matches, $attr, $url, $rawattr ) {
		$shortcode = '[gist';
		
		if ( isset( $matches[2] ) && ! empty( $matches[2] ) ) {
			$shortcode .= ' id="' . esc_attr( $matches[2] ) . '"';
		}
		
		if ( isset( $matches[3] ) && ! empty( $matches[3] ) ) {
			$shortcode .= ' file="' . esc_attr( $matches[3] ) . '"';
		}
		
		$shortcode .= ']';
		
		return $shortcode; // Allows html to be returned after wpautop().
	}
	
	/**
	 * Gist shortcode.
	 *
	 * Cache functionality mimics WP_Embed so the cache is invalidated
	 * whenever a post is saved. @see WP_Embed->delete_oembed_caches()
	 *
	 * GitHub treats filenames with dashes oddly. If you name a Gist file with
	 * a dash, it will be replaced with an underscore in the link next to the
	 * filename, however, using the replaced version will cause the JSON
	 * endpoint to return all files in the Gist since it doesn't recognize the
	 * file. Just revert the underscore to a dash for oEmbed or the shortcode
	 * to work properly. Or avoid dashes in Gist filenames altogether.
	 *
	 * The $show_line_numbers attribute assumes custom CSS has been added. If
	 * the embedded stylehseet is being used, this value won't do anything
	 * aside from add a class.
	 *
	 * If $embed_stylesheet is set to true, the external stylesheet will be
	 * enqueued and output in the footer. If that's too late, set the default
	 * $embed_stylesheet value to false and enqueue the 'github-gist' style
	 * before wp_head.
	 *
	 * Works with private Gists, too.
	 *
	 * @see WP_Embed->shortcode()
	 *
	 * @since 1.0.0
	 */
	public static function shortcode( $attr, $content = null ) {
		global $post, $wp_embed;
		
		$defaults = apply_filters( 'blazersix_gist_shortcode_defaults', array(
			'embed_stylesheet'  => apply_filters( 'blazersix_gist_embed_stylesheet_default', true ),
			'id'                => '',
			'file'              => '',
			'show_line_numbers' => true,
			'show_meta'         => true
		) );
		
		$attr = shortcode_atts( $defaults, $attr );
		$attr['embed_stylesheet'] = ( ! $attr['embed_stylesheet'] || 'false' === $attr['embed_stylesheet'] || '0' === $attr['embed_stylesheet'] ) ? false : true;
		$attr['show_line_numbers'] = ( ! $attr['show_line_numbers'] || 'false' === $attr['show_line_numbers'] || '0' === $attr['show_line_numbers'] ) ? false : true;
		$attr['show_meta'] = ( ! $attr['show_meta'] || 'false' === $attr['show_meta'] || '0' === $attr['show_meta'] ) ? false : true;
		
		extract( $attr, EXTR_SKIP );
		
		$url = $content;
		if ( ! empty( $id ) ) {
			$url = 'https://gist.github.com/' . $id;
			$json_url = $url . '.json';
		}
		
		// The Gist ID and desired file are picked up from the URL if not passed as shortcode attributes.
		if ( ! empty( $content ) && ( ! isset( $json_url ) || ! isset( $file ) ) ) {
			preg_match( '#(https?://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', $content, $matches );
			
			if ( ! isset( $json_url ) && ! empty( $matches[1] ) ) {
				$json_url = $matches[1] . '.json';
			}
			
			if ( ! isset( $file ) && ! empty( $matches[3] ) ) {
				$file = $matches[3];
			}
		}
		
		// Bail if the JSON endpoint couldn't be determined.
		if ( ! isset( $json_url ) ) {
			return '';
		}
		
		// Add a specific file from a Gist to the URL.
		if ( ! empty( $file ) ) {
			$json_url = add_query_arg( 'file', urlencode( $file ), $json_url );
		}
		
		if ( $json_url && isset( $post->ID ) ) {
			// Check for a cached result (stored in the post meta).
			$cachekey = '_oembed_' . md5( $json_url );
			$html = get_post_meta( $post->ID, $cachekey, true );
			
			// Failures are cached, too. Update the post to attempt to fetch a new copy.
			if ( '{{unknown}}' === $html ) {
				return $wp_embed->maybe_make_link( $url );
			}
			
			// Retrieve html from Gist JSON endpoint.
			if ( empty( $html ) ) {
				$response = wp_remote_get( $json_url, array( 'sslverify' => false ) );
				
				if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
					$json = json_decode( wp_remote_retrieve_body( $response ) );
					if ( ! empty( $json->div ) ) {
						$html = $json->div;
					}
				}
				
				// Cache the result.
				// @link http://core.trac.wordpress.org/ticket/21767
				$cache = ( $html ) ? addslashes( $html ) : '{{unknown}}';
				update_post_meta( $post->ID, $cachekey, $cache );
			}
			
			// If there was a result, return it.
			if ( $html ) {
				if ( $show_line_numbers ) {
					$html = str_replace( 'class="highlight"', 'class="highlight show-line-numbers"', $html );
				}
				
				if ( false === $show_meta ) {
					$html = preg_replace( '#<div class="gist-meta">.*?</div>#ms', '', $html );
				}
				
				if ( $embed_stylesheet ) {
					wp_enqueue_style( 'github-gist' ); // External stylesheet; line numbers won't work.
				}
				
				return apply_filters( 'blazersix_gist_embed_html', $html, $url, $attr, $post->ID );
			}
		}
	}
}