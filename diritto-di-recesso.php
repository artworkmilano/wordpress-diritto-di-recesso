<?php
/**
 * Plugin Name:       Diritto di Recesso 54-bis
 * Plugin URI:        https://artworkstudios.it/diritto-di-recesso
 * Description:       Recesso digitale conforme all'art. 54-bis del Codice del Consumo (D.Lgs. 209/2025) per WooCommerce. Punto d'accesso unico (pagina + link footer) valido anche per ospiti senza account: lookup ordine, doppia conferma, avviso di ricevimento su supporto durevole con data/ora, notifica admin e audit trail.
 * Version:           1.7.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Artwork
 * Author URI:        https://artworkstudios.it
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       diritto-di-recesso
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * Update URI:        https://github.com/artworkmilano/wordpress-diritto-di-recesso
 *
 * Diritto di Recesso 54-bis — by Artwork (https://artworkstudios.it)
 *
 * NOTA LEGALE: questo plugin fornisce gli strumenti tecnici per adempiere
 * all'obbligo dell'art. 54-bis. Non sostituisce un parere legale: le condizioni
 * generali di vendita e l'informativa precontrattuale (art. 49 c.1 lett. h)
 * vanno adeguate separatamente dal professionista.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DDR_VERSION', '1.7.2' );
define( 'DDR_FILE', __FILE__ );
define( 'DDR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DDR_URL', plugin_dir_url( __FILE__ ) );
define( 'DDR_SHORTCODE', 'diritto_recesso' );

// Repo GitHub per l'auto-aggiornamento integrato (formato "owner/repo").
// Vuoto = updater disattivato.
if ( ! defined( 'DDR_GITHUB_REPO' ) ) {
	define( 'DDR_GITHUB_REPO', 'artworkmilano/wordpress-diritto-di-recesso' );
}

/**
 * Auto-aggiornamento dalle release GitHub (nessun plugin esterno richiesto).
 * Caricato sempre, anche senza WooCommerce, cosi' gli update funzionano comunque.
 */
require_once DDR_PATH . 'includes/class-ddr-updater.php';
add_action( 'init', array( 'DDR_Updater', 'init' ) );

/**
 * Verifica che WooCommerce sia attivo.
 */
function ddr_woocommerce_active() {
	return in_array(
		'woocommerce/woocommerce.php',
		apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ),
		true
	) || function_exists( 'WC' );
}

/**
 * Caricamento moduli.
 */
function ddr_bootstrap() {
	if ( ! ddr_woocommerce_active() ) {
		add_action( 'admin_notices', 'ddr_notice_missing_wc' );
		return;
	}

	require_once DDR_PATH . 'includes/class-ddr-db.php';
	require_once DDR_PATH . 'includes/class-ddr-eligibility.php';
	require_once DDR_PATH . 'includes/class-ddr-emails.php';
	require_once DDR_PATH . 'includes/class-ddr-refund.php';
	require_once DDR_PATH . 'includes/class-ddr-pdf.php';
	require_once DDR_PATH . 'includes/class-ddr-legal.php';
	require_once DDR_PATH . 'includes/class-ddr-frontend.php';
	require_once DDR_PATH . 'includes/class-ddr-admin.php';

	DDR_Legal::init();
	DDR_Frontend::init();
	DDR_Admin::init();
}

/**
 * Etichetta editabile del pulsante/link di recesso.
 * Default: "Recedere dal contratto qui" (esempio indicato dall'art. 54-bis c.3).
 */
function ddr_link_label() {
	$label = trim( (string) get_option( 'ddr_link_label', '' ) );
	return '' !== $label ? $label : __( 'Recedere dal contratto qui', 'diritto-di-recesso' );
}
add_action( 'plugins_loaded', 'ddr_bootstrap', 20 );

function ddr_notice_missing_wc() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Diritto di Recesso 54-bis richiede WooCommerce attivo per funzionare.', 'diritto-di-recesso' );
	echo '</p></div>';
}

/**
 * Link rapidi nella riga del plugin (pagina Plugin).
 */
add_filter(
	'plugin_action_links_' . plugin_basename( DDR_FILE ),
	function ( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=ddr-settings' ) ) . '">' . esc_html__( 'Impostazioni', 'diritto-di-recesso' ) . '</a>';
		$legal    = '<a href="' . esc_url( admin_url( 'admin.php?page=ddr-legal' ) ) . '">' . esc_html__( 'Testi legali', 'diritto-di-recesso' ) . '</a>';
		array_unshift( $links, $settings, $legal );
		return $links;
	}
);

/**
 * Traduzioni.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'diritto-di-recesso', false, dirname( plugin_basename( DDR_FILE ) ) . '/languages' );
	}
);

/**
 * URL della pagina di recesso (creata in attivazione, id in opzione).
 */
function ddr_page_url( $args = array() ) {
	$page_id = (int) get_option( 'ddr_page_id', 0 );
	$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
	if ( $args ) {
		$url = add_query_arg( $args, $url );
	}
	return $url;
}

/* -------------------------------------------------------------------------
 * Attivazione: tabella + pagina con shortcode
 * ---------------------------------------------------------------------- */

register_activation_hook(
	__FILE__,
	function () {
		require_once DDR_PATH . 'includes/class-ddr-db.php';
		require_once DDR_PATH . 'includes/class-ddr-frontend.php';
		DDR_DB::create_table();
		ddr_ensure_page();
		// Registra l'endpoint area account e rigenera i permalink.
		DDR_Frontend::add_account_endpoint();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

/**
 * Crea (se manca) la pagina pubblica /recesso con lo shortcode.
 */
function ddr_ensure_page() {
	$existing = (int) get_option( 'ddr_page_id', 0 );
	if ( $existing && 'publish' === get_post_status( $existing ) ) {
		return $existing;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => __( 'Recesso dal contratto', 'diritto-di-recesso' ),
			'post_name'    => 'recesso',
			'post_content' => '[' . DDR_SHORTCODE . ']',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'ddr_page_id', $page_id );
	}
	return $page_id;
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);
