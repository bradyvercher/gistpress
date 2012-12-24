<?php
/**
 * Plugin Name: Blazer Six Gist oEmbed
 * Plugin URI: https://github.com/bradyvercher/wp-blazer-six-gist-oembed
 * Description: Gist oEmbed and shortcode support with caching.
 * Version: 1.1.0
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
 * @todo Attribute for highlighting specific lines.
 * @todo Feed support: link directly to post, directly to Gist, or wrap in iframe?
 * @todo Add some debugging features.
 * @todo Determine why self closing shortcodes without the slash don't work.
 * @todo Add an uninstall file to removing transients and post meta.
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
	 *
	 */
	private static $debug = false;
	
	/**
	 *
	 */
	private static $debug_log = '';
	
	/**
	 * Set up the plugin.
	 *
	 * Adds a [gist] shortcode to do the bulk of the heavy lifting. An embed
	 * handler is registered to mimic oEmbed functionality, but it relies on
	 * the shortcode for processing.
	 *
	 * Old URL Format: https://gist.github.com/{{id}}#file_{{filename}}
	 * New URL Format: https://gist.github.com/{{id}}#file-{{file_slug}}
	 *
	 * @since 1.0.0
	 */
	public static function load() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'pre_post_update', array( __CLASS__, 'delete_gist_transients' ) );
		
		// File matching is maintained for backward compatibility, but won't work for the new Gist "bookmark" URLs.
		wp_embed_register_handler( 'gist', '#(https://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', array( __CLASS__, 'wp_embed_handler' ) );
		add_shortcode( 'gist', array( __CLASS__, 'shortcode' ) );
	}
	
	/**
	 * Register the Gist stylesheet so it will only be embedded once.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		wp_register_style( 'github-gist', get_option( 'blazersix_gist_embed_stylesheet' ) );	
	}
	
	/**
	 * WP embed handler to generate a shortcode string from a Gist URL.
	 *
	 * Parses Gist URLs for oEmbed support. Returns the value as a shortcode
	 * string to let the shortcode method handle processing. The value
	 * returned also doesn't have wpautop() applied, which is a must for
	 * source code.
	 *
	 * @since 1.0.0
	 */
	public static function wp_embed_handler( $matches, $attr, $url, $rawattr ) {
		$shortcode = '[gist';
		
		if ( isset( $matches[2] ) && ! empty( $matches[2] ) ) {
			$shortcode .= ' id="' . esc_attr( $matches[2] ) . '"';
		}
		
		// For backward compatibility.
		if ( isset( $matches[3] ) && ! empty( $matches[3] ) ) {
			$shortcode .= ' file="' . esc_attr( $matches[3] ) . '"';
		}
		
		$shortcode .= '/]'; // Self-closing shortcode.
		
		return $shortcode;
	}
	
	/**
	 * Gist shortcode.
	 *
	 * Cache functionality mimics WP_Embed so the cache is invalidated
	 * whenever a post is saved. @see WP_Embed->delete_oembed_caches()
	 *
	 * If 'embed_stylesheet' is set to true, the external stylesheet will be
	 * enqueued and output in the footer. If that's too late, set the default
	 * $embed_stylesheet value to false and enqueue the 'github-gist' style
	 * before wp_head.
	 *
	 * Any custom styles should be added to the theme's stylesheet.
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
			'file'              => '',
			'highlight'         => array(),
			'highlight_color'   => apply_filters( 'blazersix_gist_embed_highlight_color', '#ffffcc' ),
			'id'                => '',
			'lines'             => '',
			'show_line_numbers' => true,
			'show_meta'         => true
		) );
		
		// Turn debugging on or off via a shortcode attribute.
		if ( ! empty( $attr['debug'] ) ) {
			self::$debug = self::shortcode_bool( $attr['debug'] );
		}
		
		$attr = shortcode_atts( $defaults, $attr );
		$attr['embed_stylesheet'] = self::shortcode_bool( $attr['embed_stylesheet'] );
		$attr['show_line_numbers'] = self::shortcode_bool( $attr['show_line_numbers'] );
		$attr['show_meta'] = self::shortcode_bool( $attr['show_meta'] );
		
		extract( $attr, EXTR_SKIP );
		
		$url = $content;
		if ( ! empty( $id ) ) {
			$url = 'https://gist.github.com/' . $id;
			$json_url = $url . '.json';
		}
		
		// The Gist ID is picked up from the URL if not passed as a shortcode attribute.
		if ( ! empty( $content ) && ( ! isset( $json_url ) || ! isset( $file ) ) ) {
			// File matching is maintained for backward compatibility, but won't work for the new Gist "bookmark" URLs.
			preg_match( '#(https?://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', $content, $matches );
			
			if ( ! isset( $json_url ) && ! empty( $matches[1] ) ) {
				$json_url = $matches[1] . '.json';
			}
			
			// For backward compatibility.
			if ( empty( $file ) && ! empty( $matches[3] ) ) {
				$file = $matches[3];
			}
		}
		
		// Bail if the JSON endpoint couldn't be determined.
		if ( ! isset( $json_url ) ) {
			return '';
		}
		
		if ( $json_url && isset( $post->ID ) ) {
			$html = self::get_gist_html( $json_url, array( 'file' => $file ) );
			
			if ( '{{unknown}}' === $html ) {
				return $wp_embed->maybe_make_link( $url );
			}
			
			// If there was a result, return it.
			if ( $html ) {
				if ( ! $show_line_numbers ) {
					$html = preg_replace( '#<td class="line_numbers">.*?</td>#s', '', $html );
				}
				
				if ( ! $show_meta ) {
					$html = preg_replace( '#<div class="gist-meta">.*?</div>#s', '', $html );
				}
				
				if ( $embed_stylesheet ) {
					wp_enqueue_style( 'github-gist' );
				}
				
				if ( ! empty( $highlight ) ) {
					$html = self::highlight_lines( $html, $attr );
				}
				
				$html = apply_filters( 'blazersix_gist_embed_html', $html, $url, $attr, $post->ID );
				
				// Append any debug information.
				if ( self::$debug && ! empty( self::$debug_log ) ) {
					$html .= '<div style="padding: 10px; background: #ffffdb">' . self::debug_log() . '</div>';
					self::$debug_log = ''; // Reset the debug log.
				}
				
				return $html;
			}
		}
	}
	
	/**
	 * @todo Ideally, this should be done before being saved as a transient, rather than on display.
	 * @todo Do some work on the initial request to add classes to the HTML some tokens can also be easily added to speed up future replacing.
	 * @todo Limiting by lines might be kinda difficult.
	 */
	public static function highlight_lines( $html, $args ) {
		$pattern = '#(<td class="line_data"[^>]+>)(.+?)</td>#s';
		preg_match( $pattern, $html, $matches );
		
		if( ! empty( $matches[2] ) ) {
			if ( ! empty( $args['lines'] ) ) {
				$min = $max = 0;
				if ( false === strpos( $args['lines'], '-' ) ) {
					$min = $max = absint( $args['lines'] );
				} else {
					list( $min, $max ) = array_map( 'absint', explode( '-', $args['lines'] ) );
				}
				
				// Limit the line numbers that should show.
				if ( $args['show_line_numbers'] ) {
					$line_num_pattern = '#(<td class="line_numbers">)(.*?)</td>#s';
					preg_match( $line_num_pattern, $html, $line_num_matches );
					
					if ( $line_num_matches[2] ) {
						$line_numbers = array_slice( explode( "\n", trim( $line_num_matches[2] ) ), $min - 1, $max - $min + 1 );
						
						$replacement = $line_num_matches[1] . join( "\n", $line_numbers ) . '</td>';
						$html = preg_replace( $line_num_pattern, $replacement, $html, 1 );
					}
				}
			}
			
			if ( ! empty( $args['highlight'] ) ) {
				// Determine which lines should be highlighted.
				$highlight = explode( ',', $args['highlight'] );
				
				// Convert any ranges.
				foreach ( $highlight as $key => $num ) {
					if ( false !== strpos( $num, '-' ) ) {
						unset( $highlight[ $key ] );
						
						$range = explode( '-', $num );
						$highlight += range( $range[0], $range[1] );
					}
				}
				
				// Flip to make unique and to use isset() when looping through the lines.
				$highlight = array_flip( $highlight );
			}
			
			$lines = trim( $matches[2] );
			$lines = preg_split( '#</pre>[\s]*<pre>#', substr( $lines, 5, strlen( $lines ) - 6 ) );
			
			foreach ( $lines as $key => $line ) {
				if ( ( $min && $key < $min - 1 ) || ( $max && $key > $max - 1 ) ) {
					unset( $lines[ $key ] );
					continue;
				}
				
				$classes = array( 'pre-line' );
				$classes[] = ( $key % 2 ) ? 'pre-line-odd' : 'pre-line-even';
				$style = '';
				
				if ( isset( $highlight[ $key + 1 ] ) ) {
					$classes[] = 'pre-line-highlight';
					
					if ( ! empty( $args['highlight_color'] ) ) {
						$style = ' style="background-color: ' . $args['highlight_color'] . ' !important"';
					}
				}
				
				$prepend = '<pre class="' . join( ' ', $classes ) . '"' . $style . '>';
				
				$lines[ $key ] = $prepend . $line . '</pre>';
			}
			
			$replacement = $matches[1] . join( "\n", $lines ) . '</td>';
			$html = preg_replace( $pattern, $replacement, $html, 1 );
		}
		
		return $html;
	}
	
	/**
	 * Helper method to determine if a shortcode attribute should be true or false.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int|bool $var
	 * @return bool
	 */
	public static function shortcode_bool( $var ) {
		$falsey = array( 'false', '0', 'no', 'n' );
		return ( ! $var || in_array( strtolower( $var ), $falsey ) ) ? false : true;
	}
	
	/**
	 * 
	 *
	 * Uses a custom caching and fallback algorithm.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url The JSON endpoint for the Gist.
	 * @param array $args Optional.
	 * @return string Gist HTML or {{unknown}} if it couldn't be determined.
	 */
	public static function get_gist_html( $url, $args = array() ) {
		global $post;
		
		$args = wp_parse_args( $args, array(
			'file' => ''
		) );
		
		// Add a specific file from a Gist to the URL.
		if ( ! empty( $args['file'] ) ) {
			$url = add_query_arg( 'file', urlencode( $args['file'] ), $url );
		}
		
		$cachekey = '_gist_embed_' . md5( $url );
		
		// @todo Need to clear transients when a post is updated.
		$html = get_transient( $cachekey );
		
		// Retrieve html from Gist JSON endpoint.
		if ( empty( $html ) ) {
			$json = self::fetch_gist( $url );
			
			if ( ! empty( $json->div ) ) {
				$html = $json->div;
			}
			
			if ( ! empty( $json->stylesheet ) ) {
				update_option( 'blazersix_gist_embed_stylesheet', $json->stylesheet );
			}
			
			// Failures are cached, too. Update the post to attempt to fetch again.
			$html = ( $html ) ? $html : '{{unknown}}';
			$transient_expire = 60 * 60 * 24;
			
			if ( '{{unknown}}' != $html ) {
				// Update the post meta fallback.
				// @link http://core.trac.wordpress.org/ticket/21767
				update_post_meta( $post->ID, $cachekey, addslashes( $html ) );
			} elseif ( $fallback = get_post_meta( $post->ID, $cachekey, true ) ) {
				// Return the fallback instead of {{unknown}}
				$html = $fallback;
				$transient_expire = 60 * 60; // Check again in an hour.
				
				self::debug_log( 'Output from post meta fallback' );
			} else {
				// Add a post meta value so the transient key can be referenced.
				// This should only happen if the first request for a Gist fails.
				add_post_meta( $post->ID, $cachekey, $html );
				self::debug_log( 'Remote call and transient failed and fallback was empty.' );
			}
			
			// Cache the result.
			set_transient( $cachekey, $html, $transient_expire );
		} else {
			self::debug_log( 'Output from transient cache.' );
		}
		
		return $html;
	}
	
	/**
	 * Fetch Gist data from its JSON endpoint.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Gist JSON endpoint.
	 * @return object|bool Gist JSON object or false.
	 */
	public static function fetch_gist( $url ) {
		self::debug_log( 'Doing remote request: ' . $url );
		
		$response = wp_remote_get( $url, array( 'sslverify' => false ) );
		
		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}
		
		return false;
	}
	
	/**
	 * Removes transients associated with Gists embedded in a post.
	 *
	 * Retrieves the keys of meta data associated with a post and deletes an transients with a matching embed key.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_gist_transients( $post_id ) {
		$keys = get_post_custom_keys( $post_id );
		
		if ( empty( $keys ) ) {
			return;
		}

		foreach( $keys as $key ) {
			if ( 0 === strpos( $key, '_gist_embed_' ) ) {
				// Expire the transient with the matching key.
				// The post meta is preserved as a fallback.
				// The key will likely be reused, so expire the transient instead of deleting it.
				set_transient( $key, null, -1 );
			}
		}
	}
	
	/**
	 * Simple debug logger.
	 *
	 * If a string is passed, it's simply appended to the existing debug log. If a parameter isn't passed, then the existing debug log is returned.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Optional. A debug message.
	 * @return void|string Returns the current log if a value isn't passed for logging.
	 */
	public static function debug_log( $value = null ) {
		if ( self::$debug ) {
			if ( $value ) {
				$prepend = ( empty( self::$debug_log ) ) ? '' : '<br>';
				
				// @todo Account for arrays. Maybe paragraph tags instead.
				self::$debug_log .= $prepend . $value;
			} else {
				return self::$debug_log;
			}
		}
	}
}