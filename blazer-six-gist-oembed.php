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
 * @todo Feed support: link directly to post, directly to Gist, or wrap in iframe?
 * @todo Cache the stylesheet locally.
 */

/**
 * Load the plugin when plugins are loaded.
 */
add_action( 'plugins_loaded', array( 'Blazer_Six_Gist_oEmbed', 'instance' ) );

/**
 * The main plugin class.
 *
 * @since 1.0.0
 */
class Blazer_Six_Gist_oEmbed {
	/**
	 * @access private
	 * @var Blazer_Six_Gist_oEmbed
	 */
	private static $instance;
	
	/**
	 * Basic log for debug messages.
	 *
	 * @access protected
	 * @var array
	 */
	protected $debug_log = array();
	
	/**
	 * Key for grouping debug messages by shortcode.
	 *
	 * @access protected
	 * @var int
	 */
	protected $debug_log_key = 0;
	
	/**
	 * Toggle to short-circuit shortcode output and expire its corresponding
	 * transient so output can be regenerated the next time it is run.
	 *
	 * @access protected
	 * @var bool
	 */
	protected $expire_transients = false;
	
	/**
	 * Main Blazer_Six_Gist_oEmbed instance.
	 *
	 * @since 1.1.0
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
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
	 * @since 1.1.0
	 */
	private function __construct() {
		// File matching is maintained for backward compatibility, but won't work for the new Gist "bookmark" URLs.
		wp_embed_register_handler( 'gist', '#(https://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', array( $this, 'wp_embed_handler' ) );
		add_shortcode( 'gist', array( $this, 'shortcode' ) );
		
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'post_updated', array( $this, 'expire_gist_transients' ), 10, 3 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_filter( 'debug_bar_panels', array( $this, 'add_debug_bar_panel' ) );
		}
	}
	
	/**
	 * Register the Gist stylesheet so it can be embedded once.
	 *
	 * @since 1.0.0
	 */
	public function init() {
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
	public function wp_embed_handler( $matches, $attr, $url, $rawattr ) {
		$shortcode = '[gist';
		
		if ( isset( $matches[2] ) && ! empty( $matches[2] ) ) {
			$shortcode .= ' id="' . esc_attr( $matches[2] ) . '"';
		}
		
		// For backward compatibility.
		if ( isset( $matches[3] ) && ! empty( $matches[3] ) ) {
			$shortcode .= ' file="' . esc_attr( $matches[3] ) . '"';
		}
		
		$shortcode .= ']';
		
		return $shortcode;
	}
	
	/**
	 * Gist shortcode.
	 *
	 * Works with secret Gists, too.
	 *
	 * Shortcode attributes:
	 *
	 * - id - The Gist id (found in the URL). The only required attribute.
	 * - embed_stylesheet - Whether the external stylesheet should be enqueued for output in the footer.
	 *     * If the footer is too late, set to false and enqueue the 'github-gist' style before 'wp_head'.
	 *     * Any custom styles should be added to the theme's stylesheet.
	 * - file - Name of a specific file in a Gist.
	 * - highlight - Comma-separated list of line numbers to highlight.
	 *     * Ranges can be specified. Ex: 2,4,6-10,12
	 * - highlight_color - Background color of highlighted lines.
	 *     * To change it globally, hook into the filter any supply a different color.
	 * - lines - A range of lines to limit the Gist to.
	 *     * Suited for single file Gists or shortcodes using the 'file' attribute.
	 * - show_line_numbers - Whether line numbers should be displayed.
	 * - show_meta - Whether the trailing meta information in default Gist embeds should be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attr Attributes of the shortcode.
	 * @return string HTML content to display the Gist.
	 */
	public function shortcode( $attr ) {
		global $post;
		
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
		
		// Sanitize attributes.
		$attr = shortcode_atts( $defaults, $attr );
		$attr['embed_stylesheet'] = $this->shortcode_bool( $attr['embed_stylesheet'] );
		$attr['show_line_numbers'] = $this->shortcode_bool( $attr['show_line_numbers'] );
		$attr['show_meta'] = $this->shortcode_bool( $attr['show_meta'] );
		$attr['highlight'] = $this->parse_highlight_arg( $attr['highlight'] );
		$attr['lines'] = $this->parse_line_number_arg( $attr['lines'] );
		
		// Short-circuit the shortcode output and just expire the transient.
		// This is set to true when posts are updated.
		if ( $this->expire_transients ) {
			$this->expire_gist_transient( $attr );
			return;
		}
		
		if ( ! empty( $attr['id'] ) ) {
			$url = 'https://gist.github.com/' . $attr['id'];
			$json_url = $url . '.json';
		}
		
		// Bail if the JSON endpoint couldn't be determined.
		if ( ! isset( $json_url ) ) {
			return '';
		}
		
		if ( $json_url && isset( $post->ID ) ) {
			$html = $this->get_gist_html( $json_url, $attr );
			
			if ( '{{unknown}}' === $html ) {
				return $wp_embed->maybe_make_link( $url );
			}
			
			// If there was a result, return it.
			if ( $html ) {
				if ( $attr['embed_stylesheet'] ) {
					wp_enqueue_style( 'github-gist' );
				}
				
				$html = apply_filters( 'blazersix_gist_embed_html', $html, $url, $attr, $post->ID );
				
				$this->debug_log( $attr );
				$this->debug_log( $html );
				$this->debug_log_key ++;
				
				return $html;
			}
		}
		
		return '';
	}
	
	/**
	 * Helper method to determine if a shortcode attribute is true or false.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int|bool $var Attribute value.
	 * @return bool
	 */
	public function shortcode_bool( $var ) {
		$falsey = array( 'false', '0', 'no', 'n' );
		return ( ! $var || in_array( strtolower( $var ), $falsey ) ) ? false : true;
	}
	
	/**
	 * Parses and expands the shortcode 'highlight' attribute and returns it
	 * in a usable format.
	 *
	 * @since 1.1.0
	 *
	 * @param string $line_numbers Comma-separated list of line numbers and ranges.
	 * @return array List of line numbers.
	 */
	public function parse_highlight_arg( $line_numbers ) {
		if ( empty( $line_numbers ) ) {
			return null;
		}
		
		// Determine which lines should be highlighted.
		$highlight = explode( ',', $line_numbers );
		
		// Convert any ranges.
		foreach ( $highlight as $key => $num ) {
			if ( false !== strpos( $num, '-' ) ) {
				unset( $highlight[ $key ] );
				
				$range = explode( '-', $num );
				$highlight += range( $range[0], $range[1] );
			}
		}
		
		return array_unique( $highlight );
	}
	
	/**
	 * Parses the shortcode 'lines' attribute into min and max values.
	 *
	 * @since 1.1.0
	 *
	 * @param string $line_numbers Range of line numbers separated by a dash.
	 * @return array Array with min and max line numbers.
	 */
	public function parse_line_number_arg( $line_numbers ) {
		if ( empty( $line_numbers ) ) {
			return array( 'min' => 0, 'max' => 0 );
		}
		
		if ( false === strpos( $line_numbers, '-' ) ) {
			$range = array_fill_keys( array( 'min', 'max' ), absint( $line_numbers ) );
		} else {
			$numbers = array_map( 'absint', explode( '-', $line_numbers ) );
			
			$range = array(
				'min' => $numbers[0],
				'max' => $numbers[1]
			);
		}
		
		return $range;
	}
	
	/**
	 * Retrieve Gist HTML.
	 *
	 * Gist HTML can come from one of three different sources:
	 * - Remote JSON endpoint.
	 * - Transient.
	 * - Post meta cache.
	 *
	 * When a Gist is intially requested, the HTML is fetched from the JSON
	 * endpoint and cached in a post meta field. It is then processed to limit
	 * line numbers, highlight specific lines, and add a few extra classes as
	 * style hooks. The processed HTML is then stored in a transient using a
	 * hash of the shortcodes attributes for the key.
	 *
	 * On subsequent requests, the HTML is fetched from the transient until it
	 * expires, then it is requested from the remote URL again.
	 *
	 * In the event the HTML can't be fetched from the remote endpoint and the
	 * transient is expired, the HTML is retrieved from the post meta backup.
	 *
	 * This algorithm allows Gist HTML to stay in sync with any changes GitHub
	 * may make to their markup, while providing a local cache for faster
	 * retrieval and a backup in case GitHub can't be reached.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url The JSON endpoint for the Gist.
	 * @param array $args List of shortcode attributes.
	 * @return string Gist HTML or {{unknown}} if it couldn't be determined.
	 */
	public function get_gist_html( $url, $args ) {
		global $post;
		
		// Add a specific file from a Gist to the URL.
		if ( ! empty( $args['file'] ) ) {
			$url = add_query_arg( 'file', urlencode( $args['file'] ), $url );
		}
		
		$post_meta_key = '_gist_embed_' . md5( $url );
		$transient_key = 'gist_embed_' . $this->shortcode_hash( 'gist', $args );
		
		$html = get_transient( $transient_key );
		
		// Retrieve html from Gist JSON endpoint.
		if ( empty( $html ) ) {
			$json = $this->fetch_gist( $url );
			
			if ( ! empty( $json->div ) ) {
				$html = $json->div;
			}
			
			// Update the stylesheet reference.
			if ( ! empty( $json->stylesheet ) ) {
				update_option( 'blazersix_gist_embed_stylesheet', $json->stylesheet );
			}
			
			// Failures are cached, too. Update the post to attempt to fetch again.
			$html = ( $html ) ? $html : '{{unknown}}';
			$transient_expire = 60 * 60 * 24;
			
			if ( '{{unknown}}' != $html ) {
				// Update the post meta fallback.
				// @link http://core.trac.wordpress.org/ticket/21767
				update_post_meta( $post->ID, $post_meta_key, addslashes( $html ) );
				$html = $this->process_gist_html( $html, $args );
				$this->debug_log( '<p class="source">' . __( '<strong>Output Source:</strong> Remote Request', 'blazersix-gist-oembed' ) . '</p>' );
			} elseif ( $fallback = get_post_meta( $post->ID, $post_meta_key, true ) ) {
				// Return the fallback instead of {{unknown}}
				$html = $this->process_gist_html( $fallback, $args );
				
				// Cache the fallback for an hour.
				$transient_expire = 60 * 60;
				$this->debug_log( '<p class="source">' . __( '<strong>Output Source:</strong> Post Meta Fallback', 'blazersix-gist-oembed' ) . '</p>' );
			} else {
				$this->debug_log( '<strong style="color: #ee0000">' . __( 'Remote call and transient failed and fallback was empty.', 'blazersix-gist-oembed' ) . '</strong>' );
			}
			
			// Cache the processed HTML.
			set_transient( $transient_key, $html, $transient_expire );
		} else {
			$this->debug_log( '<p class="source">' . __( '<strong>Output Source:</strong> Transient Cache', 'blazersix-gist-oembed' ) . '</p>' );
		}
		
		$this->debug_log( '<strong>' . __( 'JSON Endpoint:', 'blazersix-gist-oembed' ) . '</strong> ' . $url );
		$this->debug_log( '<strong>' . __( 'Post Meta Cache Key:', 'blazersix-gist-oembed' ) . '</strong> ' . $post_meta_key );
		$this->debug_log( '<strong>' . __( 'Transient Key:', 'blazersix-gist-oembed' ) . '</strong> ' . $transient_key );
		
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
	public function fetch_gist( $url ) {
		$this->debug_log( '<strong>' . __( 'Doing remote request:', 'blazersix-gist-oembed' ) . '</strong><br>' . $url );
		
		$response = wp_remote_get( $url, array( 'sslverify' => false ) );
		
		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}
		
		return false;
	}
	
	/**
	 * Process the HTML returned from a Gist's JSON endpoint based on settings
	 * passed through the shortcode.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html HTML from the Gist's JSON endpoint.
	 * @param array $args List of shortcode attributes.
	 * @return string Modified HTML.
	 */
	public function process_gist_html( $html, $args ) {
		// Remove the line number cell if it has been disabled.
		if ( ! $args['show_line_numbers'] ) {
			$html = preg_replace( '#<td class="line_numbers">.*?</td>#s', '', $html );
		}
		
		// Remove the meta section if it has been disabled.
		if ( ! $args['show_meta'] ) {
			$html = preg_replace( '#<div class="gist-meta">.*?</div>#s', '', $html );
		}
		
		$lines_pattern = '#(<td class="line_data"[^>]+>)(.+?)</td>#s';
		preg_match( $lines_pattern, $html, $lines_matches );
		
		if( ! empty( $lines_matches[2] ) ) {
			// Restrict the line number display if a range has been specified.
			if ( $args['show_line_numbers'] && $args['lines']['min'] && $args['lines']['max'] ) {
				$html = $this->limit_gist_line_numbers( $html, $args['lines'] );
			}
			
			if ( ! empty( $args['highlight'] ) ) {
				// Flip to use isset() when looping through the lines.
				$highlight = array_flip( $args['highlight'] );
			}
			
			// Extract and cleanup the individual lines from the Gist HTML into an array for processing.
			$lines = trim( $lines_matches[2] );
			$lines = preg_split( '#</pre>[\s]*<pre>#', substr( $lines, 5, strlen( $lines ) - 6 ) );
			
			foreach ( $lines as $key => $line ) {
				// Remove lines if they're not in the specified range and continue.
				if ( ( $args['lines']['min'] && $key < $args['lines']['min'] - 1 ) || ( $args['lines']['max'] && $key > $args['lines']['max'] - 1 ) ) {
					unset( $lines[ $key ] );
					continue;
				}
				
				// Add classes for styling.
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
			
			$replacement = $lines_matches[1] . join( "\n", $lines ) . '</td>';
			$html = preg_replace( $lines_pattern, $replacement, $html, 1 );
		}
		
		return $html;
	}
	
	/**
	 * Removes line numbers from the Gist's HTML that fall outside the
	 * supplied range.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html HTML from the Gist's JSON endpoint.
	 * @param array $range Array of min and max values.
	 * @return string Modified HTML.
	 */
	public function limit_gist_line_numbers( $html, $range ) {
		// Limit the line numbers that should show.
		$line_num_pattern = '#(<td class="line_numbers">)(.*?)</td>#s';
		
		preg_match( $line_num_pattern, $html, $line_num_matches );
		
		if ( $line_num_matches[2] ) {
			$line_numbers = array_slice( explode( "\n", trim( $line_num_matches[2] ) ), $range['min'] - 1, $range['max'] - $range['min'] + 1 );
			
			$replacement = $line_num_matches[1] . join( "\n", $line_numbers ) . '</td>';
			$html = preg_replace( $line_num_pattern, $replacement, $html, 1 );
		}
		
		return $html;
	}
	
	/**
	 * Removes transients associated with Gists embedded in a post.
	 *
	 * Retrieves the keys of meta data associated with a post and deletes any
	 * transients with a matching embed key.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Post ID.
	 * @param WP_Post $post_after Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 */
	public function expire_gist_transients( $post_id, $post_after, $post_before ) {
		$this->expire_transients = true;
		
		// Run the shorcodes to clear associated transients.
		do_shortcode( $post_after->post_content );
		do_shortcode( $post_before->post_content );
	}
	
	/**
	 * Expire the transient associated with a particular shortcode so its HTML
	 * will be regenerated the next time it is requested.
	 *
	 * @since 1.1.0
	 *
	 * @param array $args List of shortcode attributes.
	 */
	public function expire_gist_transient( $args ) {
		$key = 'gist_embed_' . $this->shortcode_hash( 'gist', $args );
		set_transient( $key, null, -1 );
	}
	
	/**
	 * Get the debug log property.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function get_debug_log() {
		return $this->debug_log;
	}
	
	/**
	 * Simple debug logger.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value A value to log for the current shortcode.
	 */
	protected function debug_log( $value ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->debug_log[ $this->debug_log_key ][] = $value;
		}
	}
	
	/**
	 * Sort a shortcode's attributes by name and hash it for use as a cache
	 * key.
	 *
	 * @since 1.1.0
	 */
	protected function shortcode_hash( $tag, $args ) {
		ksort( $args );
		return md5( $tag . '_' . serialize( $args ) );
	}
	
	/**
	 * Add a custom panel to the debug bar.
	 *
	 * @since 1.1.0
	 *
	 * @param array $panels List of panels.
	 * @return array
	 */
	public function add_debug_bar_panel( $panels ) {
		if ( ! class_exists( 'Blazer_Six_Gist_oEmbed_Debug_Bar_Panel' ) ) {
			include( plugin_dir_path( __FILE__ ) . 'class-blazer-six-gist-oembed-debug-bar-panel.php' );
			$panels[] = new Blazer_Six_Gist_oEmbed_Debug_Bar_Panel();
		}
		
		return $panels;
	}
}