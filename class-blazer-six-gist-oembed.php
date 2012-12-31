<?php
/**
 * Blazer Six Gist oEmbed
 *
 * @package BlazerSix/GistoEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones <gary@garyjones.co.uk>
 * @copyright Copyright (c) 2012, Blazer Six, Inc.
 * @license GPL-2.0+
 *
 * @todo Feed support: link directly to post, directly to Gist, or wrap in iframe?
 * @todo Cache the style sheet locally.
 */

/**
 * The main plugin class.
 *
 * @package BlazerSix/GistoEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones <gary@garyjones.co.uk>
 */
class Blazer_Six_Gist_oEmbed {
	/** @var object Logger object. */
	protected $logger = null;

	/**
	 * Toggle to short-circuit shortcode output and delete its corresponding
	 * transient so output can be regenerated the next time it is run.
	 *
	 * @var bool
	 */
	protected $delete_shortcode_transients = false;

	/**
	 * Sets a logger instance on the object.
	 *
	 * Since logging is optional, the dependency injection is done via this
	 * method, instead of being required through a constructor.
	 *
	 * Under PSR-1, this method would be called setLogger().
	 *
	 * @see https://github.com/php-fig/log/blob/master/Psr/Log/LoggerAwareInterface.php
	 *
	 * @since 1.1.0
	 *
	 * @param object $logger
	 */
	public function set_logger( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Return logger instance.
	 *
	 * Under PSR-1, this method would be called getLogger().
	 *
	 * @since 1.1.0
	 *
	 * @return object
	 */
	public function get_logger() {
		return $this->$logger;
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
	public function run() {
		// File matching is maintained for backward compatibility, but won't work for the new Gist "bookmark" URLs.
		wp_embed_register_handler( 'gist', '#(https://gist\.github\.com/([a-z0-9]+))(?:\#file_(.*))?#i', array( $this, 'wp_embed_handler' ) );
		add_shortcode( 'gist', array( $this, 'shortcode' ) );

		add_action( 'init', array( $this, 'style' ), 15 );
		add_action( 'post_updated', array( $this, 'delete_gist_transients' ), 10, 3 );
	}

	/**
	 * Register the Gist style sheet so it can be embedded once.
	 *
	 * @since 1.0.0
	 */
	public function style() {
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

		// This attribute added so we can identify if a oembed URL or direct shortcode was used.
		$shortcode .= ' oembed="1"]';

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
	 * - embed_stylesheet - Whether the external style sheet should be enqueued for output in the footer.
	 *     * If the footer is too late, set to false and enqueue the 'github-gist' style before 'wp_head'.
	 *     * Any custom styles should be added to the theme's style sheet.
	 * - file - Name of a specific file in a Gist.
	 * - highlight - Comma-separated list of line numbers to highlight.
	 *     * Ranges can be specified. Ex: 2,4,6-10,12
	 * - highlight_color - Background color of highlighted lines.
	 *     * To change it globally, hook into the filter and supply a different color.
	 * - lines - A range of lines to limit the Gist to.
	 *     * Suited for single file Gists or shortcodes using the 'file' attribute.
	 * - show_line_numbers - Whether line numbers should be displayed.
	 * - show_meta - Whether the trailing meta information in default Gist embeds should be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attr Attributes of the shortcode.
	 *
	 * @return string HTML content to display the Gist.
	 */
	public function shortcode( $attr ) {
		global $post;

		// Rebuild the original shortcode as a string with raw attributes.
		$rawattr = array();
		foreach ( $attr as $key => $value ) {
			if ( 'oembed' != $key ) {
				$rawattr[] = $key . '="' . $value . '"';
			}
		}
		$shortcode = '[gist ' . implode( ' ', $rawattr ) . ']';

		$defaults = apply_filters(
			'blazersix_gist_shortcode_defaults',
			array(
				'embed_stylesheet'  => apply_filters( 'blazersix_gist_embed_stylesheet_default', true ),
				'file'              => '',
				'highlight'         => array(),
				'highlight_color'   => apply_filters( 'blazersix_gist_embed_highlight_color', '#ffffcc' ),
				'id'                => '',
				'lines'             => '',
				'show_line_numbers' => true,
				'show_meta'         => true,
				'oembed'            => 0, // Private use only
			)
		);

		// Sanitize attributes.
		$attr = shortcode_atts( $defaults, $attr );
		$attr['embed_stylesheet']  = $this->shortcode_bool( $attr['embed_stylesheet'] );
		$attr['show_line_numbers'] = $this->shortcode_bool( $attr['show_line_numbers'] );
		$attr['show_meta']         = $this->shortcode_bool( $attr['show_meta'] );
		$attr['highlight']         = $this->parse_highlight_arg( $attr['highlight'] );
		$attr['lines']             = $this->parse_line_number_arg( $attr['lines'] );

		$shortcode_hash = $this->shortcode_hash( 'gist', $attr );

		// Short-circuit the shortcode output and just delete the transient.
		// This is set to true when posts are updated.
		if ( $this->delete_shortcode_transients ) {
			delete_transient( $this->transient_key( $shortcode_hash ) );
			return;
		}

		// Log what we're dealing with - title uses original attributes, but hashed against processed attributes.
		$this->debug_log( '<h2>' . $shortcode . '</h2>', $shortcode_hash );

		// Bail if the ID is not set.
		if ( empty( $attr['id'] ) ) {
			$this->debug_log( __( 'Shortcode did not have a required id attribute.', 'blazer_six_gist_oembed' ), $shortcode_hash );
			return '';
		}

		$url = 'https://gist.github.com/' . $attr['id'];
		$json_url = $url . '.json';

		if ( isset( $post->ID ) ) {
			$html = $this->get_gist_html( $json_url, $attr );

			if ( '{{unknown}}' === $html ) {
				return make_clickable( $url );
			}

			// If there was a result, return it.
			if ( $html ) {
				if ( $attr['embed_stylesheet'] ) {
					wp_enqueue_style( 'github-gist' );
				}

				$html = apply_filters( 'blazersix_gist_embed_html', $html, $url, $attr, $post->ID );

				foreach ( $attr as $key => $value ) {
					$message  = '<strong>' . $key . __(' (shortcode attribute)', 'blazer_six_gist_oembed') . ':</strong> ';
					$message .= is_scalar( $value ) ? $value : print_r( $value, true );
					$this->debug_log( $message, $shortcode_hash );
				}
				$this->debug_log( '<strong>Gist:</strong><br />' . $html, $shortcode_hash );

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
	 *
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
	 *
	 * @return array|null List of line numbers, or null if no line numbers given
	 */
	public function parse_highlight_arg( $line_numbers ) {
		if ( empty( $line_numbers ) ) {
			return null;
		}

		// Determine which lines should be highlighted.
		$highlight = array_map( 'trim', explode( ',', $line_numbers ) );

		// Convert any ranges.
		foreach ( $highlight as $index => $num ) {
			if ( false !== strpos( $num, '-' ) ) {
				unset( $highlight[ $index ] );

				$range = array_map( 'trim', explode( '-', $num ) );
				foreach ( range( $range[0], $range[1] ) as $line ) {
					array_push( $highlight, $line );
				}
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
	 *
	 * @return array Array with min and max line numbers.
	 */
	public function parse_line_number_arg( $line_numbers ) {
		if ( empty( $line_numbers ) ) {
			return array( 'min' => 0, 'max' => 0, );
		}

		if ( false === strpos( $line_numbers, '-' ) ) {
			$range = array_fill_keys( array( 'min', 'max', ), absint( trim( $line_numbers ) ) );
		} else {
			$numbers = array_map( 'absint', array_map( 'trim', explode( '-', $line_numbers ) ) );

			$range = array(
				'min' => $numbers[0],
				'max' => $numbers[1],
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
	 * @param string $url   The JSON endpoint for the Gist.
	 * @param array  $args  List of shortcode attributes.
	 * @param bool   $fetch Whether the Gist's raw HTML should be remotely fetched.
	 *
	 * @return string Gist HTML or {{unknown}} if it couldn't be determined.
	 */
	public function get_gist_html( $url, $args ) {
		global $post;

		// Add a specific file from a Gist to the URL.
		if ( ! empty( $args['file'] ) ) {
			$url = add_query_arg( 'file', urlencode( $args['file'] ), $url );
		}

		$shortcode_hash = $this->shortcode_hash( 'gist', $args );
		$raw_key = '_gist_raw_' . md5( $url );
		$transient_key = $this->transient_key( $shortcode_hash );

		$html = get_transient( $transient_key );

		if ( empty( $html ) ) {
			$html = get_transient( $raw_key );
			$transient_expire = 60 * 60 * 24;

			if ( $html && '{{unknown}}' != $html ) {
				$html = $this->process_gist_html( $html, $args );
				$this->debug_log( __( '<strong>Raw Source:</strong> Transient Cache', 'blazersix-gist-oembed' ), $shortcode_hash );
			} else {
				// Retrieve raw html from Gist JSON endpoint.
				$json = $this->fetch_gist( $url );

				if ( ! empty( $json->div ) ) {
					set_transient( $raw_key, $json->div, $transient_expire );

					// Update the post meta fallback.
					// @link http://core.trac.wordpress.org/ticket/21767
					update_post_meta( $post->ID, $raw_key, addslashes( $json->div ) );

					$html = $this->process_gist_html( $json->div, $args );

					$this->debug_log( __( '<strong>Raw Source:</strong> Remote JSON Endpoint - ', 'blazersix-gist-oembed' ) . $url, $shortcode_hash );
					$this->debug_log( __( '<strong>Output Source:</strong> Processed the raw source.', 'blazersix-gist-oembed' ), $shortcode_hash );
				}

				// Update the style sheet reference.
				if ( ! empty( $json->stylesheet ) ) {
					update_option( 'blazersix_gist_embed_stylesheet', $json->stylesheet );
				}
			}

			// Failures are cached, too. Update the post to attempt to fetch again.
			$html = ( $html ) ? $html : '{{unknown}}';

			if ( '{{unknown}}' == $html && ( $fallback = get_post_meta( $post->ID, $raw_key, true ) ) ) {
				// Return the fallback instead of {{unknown}}
				$html = $this->process_gist_html( $fallback, $args );

				// Cache the fallback for an hour.
				$transient_expire = 60 * 60;

				$this->debug_log( __( '<strong>Raw Source:</strong> Post Meta Fallback', 'blazersix-gist-oembed' ), $shortcode_hash );
				$this->debug_log( __( '<strong>Output Source:</strong> Processed Raw Source', 'blazersix-gist-oembed' ), $shortcode_hash );
			} elseif ( '{{unknown}}' == $html ) {
				$this->debug_log( '<strong style="color: #e00">' . __( 'Remote call and transient failed and fallback was empty.', 'blazersix-gist-oembed' ) . '</strong>', $shortcode_hash );
			}

			// Cache the processed HTML.
			set_transient( $transient_key, $html, $transient_expire );
		} else {
			$this->debug_log( __( '<strong>Output Source:</strong> Transient Cache', 'blazersix-gist-oembed' ), $shortcode_hash );
		}

		$this->debug_log( '<strong>' . __( 'JSON Endpoint:', 'blazersix-gist-oembed' ) . '</strong> ' . $url, $shortcode_hash );
		$this->debug_log( '<strong>' . __( 'Raw Key (Transient & Post Meta):', 'blazersix-gist-oembed' ) . '</strong> ' . $raw_key, $shortcode_hash );
		$this->debug_log( '<strong>' . __( 'Processed Output Key (Transient):', 'blazersix-gist-oembed' ) . '</strong> ' . $transient_key, $shortcode_hash );

		return $html;
	}

	/**
	 * Fetch Gist data from its JSON endpoint.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Gist JSON endpoint.
	 *
	 * @return object|bool Gist JSON object or false.
	 */
	public function fetch_gist( $url ) {
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
	 * @param array  $args List of shortcode attributes.
	 *
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

		if ( ! empty( $lines_matches[2] ) ) {
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
	 * @param string $html  HTML from the Gist's JSON endpoint.
	 * @param array  $range Array of min and max values.
	 *
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
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 */
	public function delete_gist_transients( $post_id, $post_after, $post_before ) {
		$this->delete_shortcode_transients = true;

		// Run the shortcodes to clear associated transients.
		do_shortcode( $post_after->post_content );
		do_shortcode( $post_before->post_content );

		// Delete raw transients whose keys match a post meta fallback.
		$keys = get_post_custom_keys( $post_id );

		if ( $keys ) {
			foreach( $keys as $key ) {
				if ( 0 === strpos( $key, '_gist_raw_' ) ) {
					delete_transient( $key );
				}
			}
		}
	}

	/**
	 * Wrapper for a PSR-3 compatible logger.
	 *
	 * If no logger has been set via the set_logger() method on an instance of
	 * this class, or WP_DEBUG is not enabled, then log messages quietly die
	 * here.
	 *
	 * @since 1.1.0
	 *
	 * @param string $message A message to log for the current shortcode.
	 * @param mixed  $id      An ID under which the message should be grouped.
	 */
	protected function debug_log( $message, $id = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $this->logger ) ) {
			$this->logger->debug( $message, array('key' => $id ) );
		}
	}

	/**
	 * Sort a shortcode's attributes by name and hash it for use as a cache
	 * key and logger message grouping.
	 *
	 * @since 1.1.0
	 */
	protected function shortcode_hash( $tag, $args ) {
		ksort( $args );
		return md5( $tag . '_' . serialize( $args ) );
	}

	/**
	 * Get the transient key.
	 *
	 * @since 1.1.0
	 *
	 * @param string $identifier The identifier part of the key.
	 *
	 * @return string
	 */
	protected function transient_key( $identifier ) {
		return 'gist_html_' . $identifier;
	}
}
