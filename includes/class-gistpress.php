<?php
/**
 * GistPress
 *
 * @package   GistPress
 * @author    Brady Vercher <brady@blazersix.com>
 * @author    Gary Jones
 * @copyright Copyright (c) 2012, Blazer Six, Inc.
 * @license   GPL-2.0+
 */

/**
 * The main plugin class.
 *
 * @package GistPress
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones
 */
class GistPress {
	/**
	 * Logger object.
	 *
	 * @var object
	 */
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
	 * @param object $logger Logger object.
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
		return $this->logger;
	}

	/**
	 * Set up the plugin.
	 *
	 * Adds a [gist] shortcode to do the bulk of the heavy lifting. An embed
	 * handler is registered to mimic oEmbed functionality, but it relies on
	 * the shortcode for processing.
	 *
	 * Supported formats:
	 *
	 * * Old link: https://gist.github.com/{{id}}#file_{{filename}}
	 * * Old link with username: https://gist.github.com/{{user}}/{{id}}#file_{{filename}}
	 * * New bookmark: https://gist.github.com/{{id}}#file-{{file_slug}}
	 * * New bookmark with username: https://gist.github.com/{{user}}/{{id}}#file-{{sanitized-filename}}
	 *
	 * @since 1.1.0
	 */
	public function run() {
		$oembed_pattern = '#https://gist\.github\.com/(?:.*/)?([a-z0-9]+)(?:\#file([_-])(.*))?#i';
		wp_embed_register_handler( 'gist', $oembed_pattern, array( $this, 'wp_embed_handler' ) );
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
		wp_register_style( 'gistpress', get_option( 'gistpress_stylesheet' ) );
	}

	/**
	 * WP embed handler to generate a shortcode string from a Gist URL.
	 *
	 * Parses Gist URLs for oEmbed support. Returns the value as a shortcode
	 * string to let the shortcode method handle processing. The value
	 * returned also does not have wpautop() applied, which is a must for
	 * source code.
	 *
	 * @since 1.0.0
	 *
	 * @param array $matches Search results against the regex pattern listed in `run()`.
	 * @return string Shortcode.
	 */
	public function wp_embed_handler( array $matches ) {
		$shortcode = '[gist';

		if ( isset( $matches[1] ) && ! empty( $matches[1] ) ) {
			$shortcode .= ' id="' . esc_attr( $matches[1] ) . '"';
		}

		// Make specific to a single file.
		if ( isset( $matches[3] ) && ! empty( $matches[3] ) ) {
			$real_file_name = $this->get_file_name( $matches[3], $matches[2], $matches[1] );
			if ( $real_file_name ) {
				$shortcode .= ' file="' . esc_attr( $real_file_name ) . '"';
			}
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
	 * - lines_start - A number to start counting from for line numbers.
	 * - show_line_numbers - Whether line numbers should be displayed.
	 * - show_meta - Whether the trailing meta information in default Gist embeds should be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @uses GistPress::rebuild_shortcode() Rebuild shortcode string.
	 * @uses GistPress::standardize_attributes() Set defaults and sanitize.
	 * @uses GistPress::shortcode_hash() Get hash of attributes.
	 * @uses GistPress::transient_key() Transient key name.
	 * @uses GistPress::debug_log() Potentially log a debug message.
	 * @uses GistPress::debug_log() Gist retrieval failure string.
	 *
	 * @param array $rawattr Raw attributes of the shortcode.
	 * @return string HTML content to display the Gist.
	 */
	public function shortcode( array $rawattr ) {
		$shortcode = $this->rebuild_shortcode( $rawattr );

		$attr = $this->standardize_attributes( $rawattr );

		$shortcode_hash = $this->shortcode_hash( 'gist', $attr );

		// Short-circuit the shortcode output and just delete the transient.
		// This is set to true when posts are updated.
		if ( $this->delete_shortcode_transients ) {
			delete_transient( $this->transient_key( $shortcode_hash ) );
			delete_transient( $this->gist_files_transient_key( $attr['id'] ) );

			return '';
		}

		// Log what we're dealing with - title uses original attributes, but hashed against processed attributes.
		$this->debug_log( '<h2>' . $shortcode . '</h2>', $shortcode_hash );

		// Bail if the ID is not set.
		if ( empty( $attr['id'] ) ) {
			$this->debug_log( __( 'Shortcode did not have a required id attribute.', 'gistpress' ), $shortcode_hash );
			return '';
		}

		$url = 'https://gist.github.com/' . $attr['id'];
		$json_url = $url . '.json';

		if ( is_feed() ) {
			$html = sprintf( '<a href="%s" target="_blank"><em>%s</em></a>', esc_url( $url ), __( 'View this code snippet on GitHub.', 'gistpress' ) );

			/**
			 * Filter what is shown in feeds.
			 *
			 * @since 2.0.0
			 *
			 * @param string $html Markup to show in feeds.
			 */
			return apply_filters( 'gistpress_feed_html', $html );
		}

		$html = $this->get_gist_html( $json_url, $attr );

		if ( $this->unknown() === $html ) {
			return make_clickable( $url );
		}

		// If there was a result, return it.
		if ( $html ) {
			if ( $attr['embed_stylesheet'] ) {
				wp_enqueue_style( 'gistpress' );
			}

			/**
			 * Filter the output HTML.
			 *
			 * @since 2.0.0
			 *
			 * @param string $html The output HTML.
			 * @param string $url  The URL to the Gist.
			 * @param array  $attr Shortcode attributes, standardized.
			 * @param int    $id   Post ID.
			 */
			$html = apply_filters( 'gistpress_html', $html, $url, $attr, get_the_ID() );

			foreach ( $attr as $key => $value ) {
				$message  = '<strong>' . $key . __( ' (shortcode attribute)', 'gistpress' ) . ':</strong> ';
				$message .= is_scalar( $value ) ? $value : print_r( $value, true );
				$this->debug_log( $message, $shortcode_hash );
			}
			$this->debug_log( '<strong>Gist:</strong><br />' . $html, $shortcode_hash );

			return $html;
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

		return ( ! $var || in_array( strtolower( $var ), $falsey, true ) ) ? false : true;
	}

	/**
	 * Parses and expands the shortcode 'highlight' attribute and returns it
	 * in a usable format.
	 *
	 * @since 1.1.0
	 *
	 * @param string $line_numbers Comma-separated list of line numbers and ranges.
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
	 * @return array Associative array with min and max line numbers.
	 */
	public function parse_line_number_arg( $line_numbers ) {
		if ( empty( $line_numbers ) ) {
			return array( 'min' => 0, 'max' => 0 );
		}

		if ( false === strpos( $line_numbers, '-' ) ) {
			$range = array_fill_keys( array( 'min', 'max' ), absint( trim( $line_numbers ) ) );
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
	 *   Remote JSON endpoint,
	 *   Transient,
	 *   Post meta cache.
	 *
	 * When a Gist is initially requested, the HTML is fetched from the JSON
	 * endpoint and cached in a post meta field. It is then processed to limit
	 * line numbers, highlight specific lines, and add a few extra classes as
	 * style hooks. The processed HTML is then stored in a transient using a
	 * hash of the shortcodes attributes for the key.
	 *
	 * On subsequent requests, the HTML is fetched from the transient until it
	 * expires, then it is requested from the remote URL again.
	 *
	 * In the event the HTML can't be fetched from the remote endpoint and the
	 * transient has expired, the HTML is retrieved from the post meta backup.
	 *
	 * This algorithm allows Gist HTML to stay in sync with any changes GitHub
	 * may make to their markup, while providing a local cache for faster
	 * retrieval and a backup in case GitHub can't be reached.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url   The JSON endpoint for the Gist.
	 * @param array  $args  List of shortcode attributes.
	 * @return string Gist HTML or {{unknown}} if it could not be determined.
	 */
	public function get_gist_html( $url, array $args ) {
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
			// Filter the transient expiration duration
			$transient_expire = apply_filters( 'gistpress_transient_expire', DAY_IN_SECONDS );

			if ( $html && $this->unknown() !== $html ) {
				$html = $this->process_gist_html( $html, $args );
				$this->debug_log( __( '<strong>Raw Source:</strong> Transient Cache', 'gistpress' ), $shortcode_hash );
			} else {
				// Retrieve raw html from Gist JSON endpoint.
				$json = $this->fetch_gist( $url );

				if ( ! empty( $json->div ) ) {
					set_transient( $raw_key, $json->div, $transient_expire );

					// Update the post meta fallback. See http://core.trac.wordpress.org/ticket/21767 for details.
					update_post_meta( get_the_ID(), $raw_key, addslashes( $json->div ) );

					$html = $this->process_gist_html( $json->div, $args );

					$this->debug_log( __( '<strong>Raw Source:</strong> Remote JSON Endpoint - ', 'gistpress' ) . $url, $shortcode_hash );
					$this->debug_log( __( '<strong>Output Source:</strong> Processed the raw source.', 'gistpress' ), $shortcode_hash );
				}

				// Update the style sheet reference.
				if ( ! empty( $json->stylesheet ) ) {
					update_option( 'gistpress_stylesheet', $json->stylesheet );
				}
			}

			// Failures are cached, too. Update the post to attempt to fetch again.
			$html = ( $html ) ? $html : $this->unknown();

			if ( $this->unknown() === $html && ( $fallback = get_post_meta( get_the_ID(), $raw_key, true ) ) ) {
				// Return the fallback instead of the string representing unknown.
				$html = $this->process_gist_html( $fallback, $args );

				// Cache the fallback for an hour.
				// Allow this value to be filterable
				$transient_expire = apply_filters( 'gistpress_transient_expire_fallback', HOUR_IN_SECONDS );

				$this->debug_log( __( '<strong>Raw Source:</strong> Post Meta Fallback', 'gistpress' ), $shortcode_hash );
				$this->debug_log( __( '<strong>Output Source:</strong> Processed Raw Source', 'gistpress' ), $shortcode_hash );
			} elseif ( $this->unknown() === $html ) {
				$this->debug_log( '<strong style="color: #e00;">' . __( 'Remote call and transient failed and fallback was empty.', 'gistpress' ) . '</strong>', $shortcode_hash );
			}

			// Cache the processed HTML.
			set_transient( $transient_key, $html, $transient_expire );
		} else {
			$this->debug_log( __( '<strong>Output Source:</strong> Transient Cache', 'gistpress' ), $shortcode_hash );
		}

		$this->debug_log( '<strong>' . __( 'JSON Endpoint:', 'gistpress' ) . '</strong> ' . $url, $shortcode_hash );
		$this->debug_log( '<strong>' . __( 'Raw Key (Transient & Post Meta):', 'gistpress' ) . '</strong> ' . $raw_key, $shortcode_hash );
		$this->debug_log( '<strong>' . __( 'Processed Output Key (Transient):', 'gistpress' ) . '</strong> ' . $transient_key, $shortcode_hash );

		return $html;
	}

	/**
	 * Fetch Gist data from its JSON endpoint.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Gist JSON endpoint.
	 * @return object|bool Gist JSON object, or false if anything except a HTTP
	 *                     Status code of 200 was received.
	 */
	public function fetch_gist( $url ) {
		$response = wp_remote_get( $url, array( 'sslverify' => false ) );

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
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
	 * @return string Modified HTML.
	 */
	public function process_gist_html( $html, array $args ) {
		// Remove the line number cell if it has been disabled.
		if ( ! $args['show_line_numbers'] ) {
			$html = preg_replace( '#<td id="[^"]*" class="blob-num js-line-number" data-line-number="\d+"></td>#s', '', $html );
		}

		// Remove the meta section if it has been disabled.
		if ( ! $args['show_meta'] ) {
			$html = preg_replace( '#<div class="gist-meta">.*?</div>#s', '', $html );
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return $html;
		}

		// Load the HTML as UTF-8.
		$html = '<?xml encoding="utf-8" ?>' . $html;

		$dom = new DOMDocument();
		$dom->loadHTML( $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );

		$lines = $dom->getElementsByTagName( 'tr' );

		if ( ! empty( $args['highlight'] ) ) {
			// Flip to use isset() when looping through the lines.
			$highlight = array_flip( $args['highlight'] );
		}

		$lines_to_remove = array();
		foreach ( $lines as $key => $line ) {
			// Remove lines if they're not in the specified range and continue.
			if (
				( $args['lines']['min'] && $key < $args['lines']['min'] - 1 ) ||
				( $args['lines']['max'] && $key > $args['lines']['max'] - 1 )
			) {
				$lines_to_remove[] = $line;
				continue;
			}

			// Add classes for styling.
			$classes = array( 'line' );

			if ( isset( $highlight[ $key + 1 ] ) ) {
				$classes[] = 'line-highlight';

				if ( ! empty( $args['highlight_color'] ) ) {
					$style = 'background-color: ' . $args['highlight_color'] . ' !important';

					foreach ( $line->getElementsByTagName( 'td' ) as $cell ) {
						$value = $cell->getAttribute( 'style' );
						$value = empty( $value ) ? $style : $value . ';' . $style;
						$cell->setAttribute( 'style', $value );
					}
				}
			}

			/**
			 * Filter the classes applied to a line of the Gist.
			 *
			 * @since 2.0.0
			 *
			 * @param array $classes List of HTML class values.
			 */
			$classes = apply_filters( 'gistpress_line_classes', $classes );
			$class = ( ! empty( $classes ) && is_array( $classes ) ) ? implode( ' ', $classes ) : '';

			$value = $line->getAttribute( 'class' );
			$value = empty( $value ) ? $class : $value . ' ' . $class;
			$line->setAttribute( 'class', $value );
		}

		foreach ( $lines_to_remove as $line ) {
			$line->parentNode->removeChild( $line );
		}

		// Remove the XML declaration.
		foreach ( $dom->childNodes as $node ) {
			if ( $node instanceof DOMProcessingInstruction ) {
				$dom->removeChild( $node );
				break;
			}
		}

		$html = $dom->saveHTML();

		// Restrict the line number display if a range has been specified.
		if (
			$args['show_line_numbers'] &&
			( ( $args['lines']['min'] && $args['lines']['max'] ) || ! empty( $args['lines_start'] ) )
		) {
			$html = $this->process_gist_line_numbers( $html, $args['lines'], $args['lines_start'] );
		}

		return $html;
	}

	/**
	 * Removes line numbers from the Gist's HTML that fall outside the
	 * supplied range and modifies the starting number if specified.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html  HTML from the Gist's JSON endpoint.
	 * @param array  $range Array of min and max values.
	 * @param int    $start Optional. Line number to start counting at.
	 * @return string Modified HTML.
	 */
	public function process_gist_line_numbers( $html, array $range, $start = null ) {
		$start = empty( $start ) ? absint( $range['min'] ) : absint( $start );

		$dom = new DOMDocument();
		$dom->loadHTML( $html );
		$lines = $dom->getElementsByTagName( 'tr' );

		foreach ( $lines as $i => $line ) {
			$line
				->getElementsByTagName( 'td' )
				->item( 0 )
				->setAttribute( 'data-line-number', $start + $i );
		}

		return $dom->saveHTML();
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
	public function delete_gist_transients( $post_id, WP_Post $post_after, WP_Post $post_before ) {
		$this->delete_shortcode_transients = true;

		// Run the shortcodes to clear associated transients.
		do_shortcode( $GLOBALS['wp_embed']->autoembed( $post_after->post_content ) );
		do_shortcode( $GLOBALS['wp_embed']->autoembed( $post_before->post_content ) );

		// Delete raw transients whose keys match a post meta fallback.
		$keys = get_post_custom_keys( $post_id );

		if ( $keys ) {
			foreach ( $keys as $key ) {
				if ( 0 === strpos( $key, '_gist_raw_' ) ) {
					delete_transient( $key );
				}
			}
		}
	}

	/**
	 * Rebuild the original shortcode as a string with raw attributes.
	 *
	 * @since 1.1.1
	 *
	 * @param array $rawattr Raw attributes => values.
	 * @return string Gist shortcode.
	 */
	protected function rebuild_shortcode( array $rawattr ) {
		$attrs = array();
		foreach ( $rawattr as $key => $value ) {
			if ( 'oembed' !== $key ) {
				$attrs[] = $key . '="' . $value . '"';
			}
		}
		return '[gist ' . implode( ' ', $attrs ) . ']';
	}

	/**
	 * Set defaults and sanitize shortcode attributes and attribute values.
	 *
	 * @since 1.1.1
	 *
	 * @param array $rawattr Associative array of raw attributes => values.
	 * @return array Standardized and sanitized shortcode attributes.
	 */
	protected function standardize_attributes( array $rawattr ) {
		/**
		 * Filter the shortcode attributes defaults.
		 *
		 * @since 2.0.0
		 *
		 * @see standardize_attributes()
		 *
		 * @param array $gistpress_shortcode_defaults {
		 * 	Shortcode attributes defaults.
		 *
		 * 	@type bool   $embed_stylesheet  Filterable value to include style sheet or not. Default is true
		 *                                      to include it.
		 * 	@type string $file              File name within gist. Default is an empty string, indicating
		 *                                      all files.
		 * 	@type array  $highlight         Lines to highlight. Default is empty array, to highlight
		 *                                      no lines.
		 * 	@type string $highlight_color   Filterable hex color code. Default is #ffc.
		 * 	@type string $id                Gist ID. Non-optional.
		 * 	@type string $lines             Number of lines to show. Default is empty string, indicating
		 *                                      all lines in the gist.
		 * 	@type string $lines_start       Which line number to start from. Default is empty string,
		 *                                      indicating line number 1.
		 * 	@type bool   $show_line_numbers Show line numbers or not, default is true, to show line numbers.
		 * 	@type bool   $show_meta         Show meta information or not, default is true, to show
		 *                                      meta information.
		 * }
		 */
		$defaults = apply_filters(
			'gistpress_shortcode_defaults',
			array(

				/**
				 * Filter to include the style sheet or not.
				 *
				 * @since 2.0.0
				 *
				 * @param bool $gistpress_stylesheet_default Include default style sheet or not.
				 *                                           Default is true, to include it.
				 */
				'embed_stylesheet'  => apply_filters( 'gistpress_stylesheet_default', true ),
				'file'              => '',
				'highlight'         => array(),

				/**
				 * Filter highlight color.
				 *
				 * @since 2.0.0
				 *
				 * @param string $gistpress_highlight_color Hex color code for highlighting lines.
				 *                                          Default is `#ffc`.
				 */
				'highlight_color'   => apply_filters( 'gistpress_highlight_color', '#ffc' ),
				'id'                => '',
				'lines'             => '',
				'lines_start'       => '',
				'show_line_numbers' => true,
				'show_meta'         => true,
				'oembed'            => 0, // Private use only.
			)
		);

		// Sanitize attributes.
		$attr = shortcode_atts( $defaults, $rawattr );
		$attr['id']                = preg_replace( '/[^a-z0-9]+/i', '', $attr['id'] );
		$attr['embed_stylesheet']  = $this->shortcode_bool( $attr['embed_stylesheet'] );
		$attr['show_line_numbers'] = $this->shortcode_bool( $attr['show_line_numbers'] );
		$attr['show_meta']         = $this->shortcode_bool( $attr['show_meta'] );
		$attr['highlight']         = $this->parse_highlight_arg( $attr['highlight'] );
		$attr['lines']             = $this->parse_line_number_arg( $attr['lines'] );

		return $attr;
	}

	/**
	 * Try to determine the real file name from a sanitized file name.
	 *
	 * The new Gist "bookmark" URLs point to sanitized file names so that both
	 * hyphen and period in a file name show up as a hyphen e.g. a filename of
	 * foo.bar and foo-bar both appear in the bookmark URL as foo-bar. The
	 * correct original filenames are listed in the JSON data for the overall
	 * Gist, so this method does a call to that, and loops through the listed
	 * file names to see if it can determine which file was meant.
	 *
	 * If a Gist has two files that both resolve to the same sanitized filename,
	 * then we don't have any way to determine which one the other determined,
	 * so we just return the first one we find. If that's incorrect, the author
	 * can use the shortcode approach, which allows a specific file name to be
	 * used.
	 *
	 * @since 2.1.0
	 *
	 * @param  string $sanitized_filename Sanitized filename, such as foo-bar-php.
	 * @param  string $delimiter          Either underscore or hyphen.
	 * @param  string $id                 Gist ID.
	 * @return string                     Filename, or empty string if it couldn't be determined.
	 */
	protected function get_file_name( $sanitized_filename, $delimiter, $id ) {
		// Old style link - filename wasn't actually changed.
		if ( '_' === $delimiter ) {
			return $sanitized_filename;
		}

		// New style bookmark - filename had . replaced with -
		// Means we have to go and look up what the filename could have been.
		$transient_key = $this->gist_files_transient_key( $id );
		$gist_files = get_transient( $transient_key );

		if ( ! $gist_files ) {
			$url = 'https://gist.github.com/' . $id . '.json';
			$json = $this->fetch_gist( $url );

			if ( $json && ! empty( $json->files ) ) {
				$gist_files = $json->files;
				set_transient( $transient_key, $gist_files, WEEK_IN_SECONDS );
			} else {
				set_transient( $transient_key, array(), MINUTE_IN_SECONDS * 15 );
			}
		}

		// If a gist has foo.bar.php and foo-bar.php, then we can't yet
		// determine which was actually wanted, since both give the same
		// bookmark URL. Here, we just return the first one we find.
		if ( ! empty( $gist_files ) ) {
			foreach ( $gist_files as $file ) {
				if ( str_replace( '.', '-', $file ) === $sanitized_filename ) {
					return $file;
				}
			}
		}

		return '';
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
	 * @param mixed  $id      Optional. An ID under which the message should be grouped.
	 */
	protected function debug_log( $message, $id = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $this->logger ) ) {
			$this->logger->debug( $message, array( 'key' => $id ) );
		}
	}

	/**
	 * Sort a shortcode's attributes by name and hash it for use as a cache
	 * key and logger message grouping.
	 *
	 * @since 1.1.0
	 *
	 * @param string $tag  Shortcode tag, used as hash prefix.
	 * @param array  $args Associative array of shortcode attributes.
	 * @return string md5 hash as a 32-character hexadecimal number.
	 */
	protected function shortcode_hash( $tag, array $args ) {
		ksort( $args );
		return md5( $tag . '_' . serialize( $args ) );
	}

	/**
	 * Get the transient key.
	 *
	 * @since 1.1.0
	 *
	 * @param string $identifier The identifier part of the key.
	 * @return string Transient key name.
	 */
	protected function transient_key( $identifier ) {
		return 'gist_html_' . $identifier;
	}

	/**
	 * Get the transient key for a list of a Gist's files.
	 *
	 * @since 2.1.0
	 *
	 * @param string $gist_id The Gist id.
	 * @return string Transient key name.
	 */
	protected function gist_files_transient_key( $gist_id ) {
		return 'gist_files_' . md5( $gist_id );
	}

	/**
	 * String to identify a failure when retrieving a Gist's HTML.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	protected function unknown() {
		return '{{unknown}}';
	}

	/**
	 * Escape a regular expression replacement string.
	 *
	 * @since 2.0.2
	 * @link http://www.procata.com/blog/archives/2005/11/13/two-preg_replace-escaping-gotchas/
	 *
	 * @param string $str String to escape.
	 * @return string
	 */
	public function preg_replace_quote( $str ) {
		return preg_replace( '/(\$|\\\\)(?=\d)/', '\\\\$1', $str );
	}
}
