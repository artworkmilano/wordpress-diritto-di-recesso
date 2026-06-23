<?php
/**
 * Disinstallazione del plugin Diritto di Recesso 54-bis.
 *
 * I dati vengono rimossi SOLO se l'amministratore ha attivato l'opzione
 * "Cancellazione dati" nelle impostazioni. Di default l'audit trail delle
 * richieste di recesso viene CONSERVATO (puo' servire come prova legale).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'ddr_delete_data', 'no' ) ) {
	return;
}

global $wpdb;

// Tabella audit trail.
$table = $wpdb->prefix . 'ddr_richieste';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

// Pagina pubblica del recesso.
$page_id = (int) get_option( 'ddr_page_id', 0 );
if ( $page_id ) {
	wp_delete_post( $page_id, true );
}

// Opzioni.
$options = array(
	'ddr_window_days',
	'ddr_admin_recipients',
	'ddr_footer_link',
	'ddr_enforce_cutoff',
	'ddr_accent',
	'ddr_trust_proxy',
	'ddr_delete_data',
	'ddr_excluded_cats',
	'ddr_page_id',
	'ddr_db_version',
);
foreach ( $options as $opt ) {
	delete_option( $opt );
}
