<?php
/**
 * Blazer Six Gist oEmbed
 *
 * @package BlazerSix\GistoEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones <gary@garyjones.co.uk>
 * @copyright Copyright (c) 2012, Blazer Six, Inc.
 * @license GPL-2.0+
 */

/**
 * Class for displaying a custom panel on the debug bar with information about
 * Gist shortcodes used in a post.
 *
 * @package BlazerSix\GistoEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones <gary@garyjones.co.uk>
 */
class Blazer_Six_Gist_oEmbed_Debug_Bar_Panel extends Debug_Bar_Panel {
	/** @var object Logger object. */
	protected $logger;

	/**
	 * Assign properties, and call parent constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param object $logger
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
		parent::__construct();
	}

	/**
	 * Initialize the panel and set its title.
	 *
	 * @since 1.1.0
	 */
	public function init() {
		$this->title( __( 'Gist oEmbed', 'blazersix-gist-oembed' ) );
	}

	/**
	 * Make the panel visible, only if there is something to display.
	 *
	 * @since 1.1.0
	 */
	public function prerender() {
		$logs = $this->logger->get_logs();
		$this->set_visible( ! empty( $logs ) );
	}

	/**
	 * Request the log from the logger class and display it in the custom debug
	 * bar panel.
	 *
	 * @since 1.1.0
	 */
	public function render() {
		$logs = $this->logger->get_logs();
		foreach ( $logs as $log_id => $gist ) {
			echo '<div class="b6go-gist-debug">';
			foreach ( $gist as $entry ) {
				// Don't wpautop tabular data, as it adds <br> between line number spans.
				echo ( false === strpos( $entry['message'], '<table' ) ) ? wpautop ( $entry['message'] ) : $entry['message'];
			}
			echo '</div>';
		}
		?>
		<style type="text/css">
		.b6go-gist-debug { margin: 2em 0; padding: 10px; background: #e8e8e8;}
		#querylist .b6go-gist-debug .gist .gist-file .gist-data .line_data pre {
			overflow: auto;
			word-wrap: normal;
			-moz-tab-size: 4;
			-o-tab-size: 4;
			tab-size: 4;}
		.b6go-gist-debug .gist .gist-file .gist-data .line_numbers span {font-size: 12px;}
		#querylist .b6go-gist-debug h2 {border: 0; float: none; font-size: 22px; text-align: left; margin: 0 !important; padding-left: 0;}
		</style>
		<?php
	}
}
