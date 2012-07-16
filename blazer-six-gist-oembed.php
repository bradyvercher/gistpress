<?php
/*
Plugin Name: Blazer Six Gist oEmbed
Version: 1.0
Plugin URI: https://gist.github.com/3031280
Description: Gist oEmbed and shortcode support with caching.
Author: Blazer Six, Inc.
Author URI: http://www.blazersix.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Alternatives
------------
* oEmbed Gist by Takayuki Miyauchi - http://wordpress.org/extend/plugins/oembed-gist/
* Pretty Cacheable Gists by Zach Tollman - https://gist.github.com/2864688
* Spotify, Rdio, and Gist embeds for WordPress by Alex Mills - https://gist.github.com/2417309

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


add_action( 'plugins_loaded', array( 'Blazer_Six_Gist_oEmbed', 'setup' ) );

class Blazer_Six_Gist_oEmbed {
	function setup() {
		wp_embed_register_handler( 'gist', '#(https://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', array( __CLASS__, 'wp_embed_handler' ) );
		wp_register_style( 'github-gist', 'https://gist.github.com/stylesheets/gist/embed.css' );
		
		add_shortcode( 'gist', array( __CLASS__, 'shortcode' ) );
	}
	
	/**
	 * WP Embed Handler
	 *
	 * Parses Gist URLs for oEmbed support. Returns the value as a shortcode
	 * string to let the shortcode method handle processing. The value returned
	 * also doesn't have wpautop() applied, which is nice for source code.
	 *
	 * If a file is specified in the hash of the URL for a multi-file Gist, it
	 * will be picked up and only the single file will be displayed.
	 */
	function wp_embed_handler( $matches, $attr, $url, $rawattr ) {
		$shortcode = '[gist';
		
		if ( isset( $matches[2] ) && ! empty( $matches[2] ) )
			$shortcode .= ' id="' . esc_attr( $matches[2] ) . '"';
		
		if ( isset( $matches[3] ) && ! empty( $matches[3] ) )
			$shortcode .= ' file="' . esc_attr( $matches[3] ) . '"';
		
		$shortcode .= ']' . $url . '[/gist]';
		
		return $shortcode; // Allows html to be returned after wpautop
	}
	
	/**
	 * Gist Shortcode
	 *
	 * Works with private Gists, too (I think).
	 *
	 * Cache functionality mimics WP_Embed so the cache is invalidated whenever
	 * a post is saved. @see WP_Embed->delete_oembed_caches()
	 *
	 * The $show_line_numbers attribute assumes custom CSS has been added. If
	 * the embedded stylehseet is being used, this value won't do anything aside
	 * from add a class.
	 *
	 * If $embed_stylesheet is set to true, the external stylesheet will be
	 * enqueued and output in the footer. If that's too late, set the default
	 * $embed_stylesheet value to false and enqueue the 'github-gist' style
	 * before wp_head.
	 *
	 * @see WP_Embed->shortcode()
	 */
	function shortcode( $attr, $content = null ) {
		global $post, $wp_embed;
		
		$defaults = apply_filters( 'blazersix_gist_shortcode_defaults', array(
			'embed_stylesheet' => apply_filters( 'blazersix_gist_embed_stylesheet_default', true ),
			'id' => '',
			'file' => '',
			'show_line_numbers' => true,
			'show_meta' => true
		) );
		
		$attr = shortcode_atts( $defaults, $attr );
		$attr['embed_stylesheet'] = ( ! $attr['embed_stylesheet'] || 'false' === $attr['embed_stylesheet'] || '0' === $attr['embed_stylesheet'] ) ? false : true;
		$attr['show_line_numbers'] = ( ! $attr['show_line_numbers'] || 'false' === $attr['show_line_numbers'] || '0' === $attr['show_line_numbers'] ) ? false : true;
		$attr['show_meta'] = ( ! $attr['show_meta'] || 'false' === $attr['show_meta'] || '0' === $attr['show_meta'] ) ? false : true;
		
		extract( $attr, EXTR_SKIP );
		
		if ( ! empty( $id ) )
			$json_url = 'http://gist.github.com/' . $id . '.json';
		
		// The Gist ID and desired file are picked up from the URL if not passed as shortcode attributes
		if ( ! empty( $content ) && ( ! isset( $json_url ) || ! isset( $file ) ) ) {
			preg_match( '#(https://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', $content, $matches );
			
			if ( ! isset( $json_url ) && isset( $matches[1] ) && ! empty( $matches[1] ) )
				$json_url = $matches[1] . '.json';
			
			if ( ! isset( $file ) && isset( $matches[3] ) && ! empty( $matches[3] ) )
				$file = $matches[3];
		}
		
		if ( ! isset( $json_url ) )
			return '';
		
		if ( ! empty( $file ) )
			$json_url = add_query_arg( 'file', urlencode( $file ), $json_url );
		
		if ( $json_url && isset( $post->ID ) ) {
			// Check for a cached result (stored in the post meta)
			$cachekey = '_oembed_' . md5( $json_url );
			$html = get_post_meta( $post->ID, $cachekey, true );
	
			// Failures are cached
			if ( '{{unknown}}' === $html )
				return $wp_embed->maybe_make_link( $url );
			
			// Retrieve html from Gist json endpoint
			if ( empty( $html ) ) {
				$response = wp_remote_get( $json_url, array( 'sslverify' => false ) );
				if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
					$json = json_decode( wp_remote_retrieve_body( $response ) );
					if ( isset( $json->div ) && ! empty( $json->div ) ) {
						$html = $json->div;
					}
				}
		
				// Cache the result
				$cache = ( $html ) ? $html : '{{unknown}}';
				update_post_meta( $post->ID, $cachekey, $cache );
			}
	
			// If there was a result, return it
			if ( $html ) {
				if ( $show_line_numbers )
					$html = str_replace( 'class="highlight"', 'class="highlight show-line-numbers"', $html );
				
				if ( false === $show_meta )
					$html = preg_replace( '#<div class="gist-meta">.*?</div>#ms', '', $html );
				
				if ( $embed_stylesheet )
					wp_enqueue_style( 'github-gist' ); // External stylesheet; line numbers won't work
				
				return apply_filters( 'blazersix_gist_embed_html', $html, $url, $attr, $post->ID );
			}
		}
	}
}
?>