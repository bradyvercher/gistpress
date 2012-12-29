<?php
/**
 * Class for displaying a custom panel on the debug bar with information about
 * Gist shortcodes used in a post.
 *
 * @since 1.1.0
 */
class Blazer_Six_Gist_oEmbed_Debug_Bar_Panel extends Debug_Bar_Panel {
	/**
	 * Initialize the panel and set its title.
	 *
	 * @since 1.1.0
	 */
	public function init(){
		$this->title( __( 'Gist oEmbed', 'blazersix-gist-oembed' ) );
	}

	/**
	 * Make the panel visibile.
	 *
	 * @since 1.1.0
	 */
	public function prerender() {
		$this->set_visible( true );
	}

	/**
	 * Requests the debug log from the Gist oEmbed class and displays it in
	 * the custom debug bar panel.
	 *
	 * @since 1.1.0
	 */
	public function render() {
		$gists = Blazer_Six_Gist_oEmbed::instance();
		
		$log = $gists->get_debug_log();
		
		foreach ( $log as $gist ) {
			echo '<div class="b6go-gist-debug">';
				foreach ( $gist as $entry ) {
					if ( is_scalar( $entry ) ) {
						echo ( false === strpos( $entry, '<table' ) ) ? wpautop ( $entry ) : $entry;
					} elseif ( is_array( $entry ) ) {
						?>
						<table>
							<thead>
								<tr>
									<th><?php _e( 'Attribute', 'blazersix-gist-oembed' ); ?></th>
									<th><?php _e( 'Value', 'blazersix-gist-oembed' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $entry as $key => $value ) : ?>
									<tr>
										<th><?php echo esc_html( $key ); ?></th>
										<td><?php echo ( is_scalar( $value ) ) ? $value : print_r( $value, true ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php
					}
				}
			echo '</div>';
		}
		?>
		<style type="text/css">
		.b6go-gist-debug { margin: 0 0 20px 0; padding: 10px; background: #e8e8e8;}
		.b6go-gist-debug p { margin: 0 0 10px 0;}
		.b6go-gist-debug p.source { padding: 5px; background: #ffffdd;}
		.b6go-gist-debug table { margin: 0 0 10px 0;}
		.b6go-gist-debug td { padding: 3px 5px;}
		.b6go-gist-debug th { padding: 3px 5px; font-weight: bold;}
		.b6go-gist-debug thead th { background: #dfdfdf; border-bottom: 1px solid #ccc;}
		.b6go-gist-debug .gist table { margin: 0;}
		#querylist .b6go-gist-debug .gist .gist-file .gist-data .line_data { padding: 0;}
		#querylist .b6go-gist-debug .gist .gist-file .gist-data .line_data pre { overflow: auto; margin: 0; padding: 0;
			white-space: pre;
			word-wrap: normal;
			-moz-tab-size: 4;
			-o-tab-size: 4;
			tab-size: 4;}
		#querylist .b6go-gist-debug .gist .gist-file .gist-data .line_data .line { padding: 0 0.5em;}
		</style>
		<?php
	}
}