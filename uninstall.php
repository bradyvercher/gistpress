<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'blazersix_gist_oembed_stylesheet' );

// Delete post meta.
$post_metas = $wpdb->get_results( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE meta_key LIKE '_gist_raw_%'" );
if ( $post_metas ) {
	foreach( $post_metas as $meta ) {
		delete_post_meta( $meta->post_id, $meta->meta_key );
	}
}

// Delete transients.
$transients = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_gist_html_%' OR option_name LIKE '_transient__gist_raw_%'" );
if ( $transients ) {
	foreach ( $transients as $key ) {
		delete_transient( $key );
	}
}