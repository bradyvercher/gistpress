<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_gist_embed_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_gist_embed_%' OR option_name LIKE '_transient_timeout_gist_embed_%" );
delete_option( 'blazersix_gist_embed_stylesheet' );