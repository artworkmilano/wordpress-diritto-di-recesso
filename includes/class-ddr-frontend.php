<?php
/**
 * Front-end: punto d'accesso unico alla funzione di recesso.
 *
 * Flusso (macchina a stati nello shortcode [diritto_recesso]):
 *   1. lookup       -> n. ordine + email di fatturazione
 *   2. verifica     -> ospiti: link via email; loggati proprietari: diretto
 *   3. select       -> selezione prodotti e quantita' da recedere (parziale)
 *   4. declaration  -> dichiarazione (art. 54-bis c.2)
 *   5. conferma     -> "Conferma recesso" (c.5)
 *   6. ricevuta     -> avviso su supporto durevole (c.6) + admin + audit trail
 *
 * Il token di flusso (transient, 30') lega i passaggi senza usare sessioni PHP
 * e garantisce che solo chi ha superato la verifica possa confermare.
 *
 * Recesso PARZIALE: il cliente sceglie prodotti e quantita'. Sono possibili piu'
 * richieste nel tempo finche' restano quantita' recedibili (DDR_Eligibility).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Frontend {

	const FLOW_TTL    = 1800; // 30 minuti.
	const ACCOUNT_EP  = 'diritto-recesso'; // endpoint area "Il mio account".

	public static function init() {
		add_shortcode( DDR_SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer_link' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'account_order_action' ), 10, 2 );

		// Modi alternativi di inserire il pulsante: shortcode + voce di menu.
		add_shortcode( 'diritto_recesso_link', array( __CLASS__, 'link_shortcode' ) );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'menu_link' ), 10, 2 );

		// Stream del PDF ricevuta (prima di qualsiasi output HTML).
		add_action( 'template_redirect', array( __CLASS__, 'maybe_stream_pdf' ) );

		// Area "Il mio account": endpoint/tab dedicato.
		add_action( 'init', array( __CLASS__, 'add_account_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ACCOUNT_EP . '_endpoint', array( __CLASS__, 'account_endpoint_content' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'account_query_var' ) );
	}

	public static function assets() {
		// Il badge nel footer compare su tutte le pagine: il CSS va caricato
		// ovunque, non solo sulla pagina /recesso.
		wp_enqueue_style( 'ddr', DDR_URL . 'assets/css/ddr.css', array(), DDR_VERSION );
		wp_register_script( 'ddr', DDR_URL . 'assets/js/ddr.js', array(), DDR_VERSION, true );

		// Colori personalizzabili applicati via CSS inline.
		$accent = self::color( 'ddr_accent', '#ea580c' );
		$btn_bg = self::color( 'ddr_btn_bg', '#1a1a1a' );
		$btn_tx = self::color( 'ddr_btn_text', '#ffffff' );
		$css  = '.ddr-btn-primary{background:' . $btn_bg . ';color:' . $btn_tx . '}';
		$css .= '.ddr-pill{--ddr-accent:' . $accent . '}';
		$css .= '.ddr-link-plain,.ddr-menu-link>a{color:' . $accent . '}';
		wp_add_inline_style( 'ddr', $css );
	}

	/**
	 * Colore esadecimale validato dall'opzione, con fallback.
	 */
	protected static function color( $option, $default ) {
		$val = sanitize_hex_color( (string) get_option( $option, $default ) );
		return $val ? $val : $default;
	}

	/* ---------------------------------------------------------------------
	 * Area "Il mio account": endpoint dedicato
	 * ------------------------------------------------------------------ */

	public static function add_account_endpoint() {
		add_rewrite_endpoint( self::ACCOUNT_EP, EP_ROOT | EP_PAGES );
	}

	public static function account_query_var( $vars ) {
		$vars[ self::ACCOUNT_EP ] = self::ACCOUNT_EP;
		return $vars;
	}

	public static function account_menu_item( $items ) {
		// Inserisce la voce prima di "Esci", se presente.
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
		if ( $logout ) {
			unset( $items['customer-logout'] );
		}
		$items[ self::ACCOUNT_EP ] = __( 'Diritto di recesso', 'diritto-di-recesso' );
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Contenuto del tab: ordini con recesso ancora esercitabile + storico richieste.
	 */
	public static function account_endpoint_content() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		echo '<h3>' . esc_html__( 'Diritto di recesso', 'diritto-di-recesso' ) . '</h3>';
		echo '<p>' . esc_html__( 'Da qui puoi esercitare il diritto di recesso sugli ordini idonei e consultare le richieste già inviate.', 'diritto-di-recesso' ) . '</p>';

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$eligible = array();
		foreach ( $orders as $order ) {
			$eval = DDR_Eligibility::evaluate( $order );
			if ( ! empty( $eval['eligible'] ) ) {
				$eligible[] = $order;
			}
		}

		echo '<h4>' . esc_html__( 'Ordini per cui puoi recedere', 'diritto-di-recesso' ) . '</h4>';
		if ( empty( $eligible ) ) {
			echo '<p>' . esc_html__( 'Nessun ordine attualmente idoneo al recesso online.', 'diritto-di-recesso' ) . '</p>';
		} else {
			echo '<ul class="ddr-account-list">';
			foreach ( $eligible as $order ) {
				$url = ddr_page_url( array( 'order' => $order->get_order_number() ) );
				printf(
					'<li><strong>%1$s</strong> &middot; %2$s &middot; <a class="ddr-btn ddr-btn-primary ddr-btn-sm" href="%3$s">%4$s</a></li>',
					/* translators: %s numero ordine */
					esc_html( sprintf( __( 'Ordine #%s', 'diritto-di-recesso' ), $order->get_order_number() ) ),
					esc_html( wc_format_datetime( $order->get_date_created() ) ),
					esc_url( $url ),
					esc_html__( 'Recedi', 'diritto-di-recesso' )
				);
			}
			echo '</ul>';
		}

		// Storico richieste dell'utente.
		echo '<h4>' . esc_html__( 'Le tue richieste di recesso', 'diritto-di-recesso' ) . '</h4>';
		$mine = array();
		foreach ( $orders as $order ) {
			foreach ( DDR_DB::get_by_order( $order->get_id(), true ) as $req ) {
				$mine[] = $req;
			}
		}
		if ( empty( $mine ) ) {
			echo '<p>' . esc_html__( 'Non hai ancora inviato richieste di recesso.', 'diritto-di-recesso' ) . '</p>';
			return;
		}

		echo '<table class="ddr-account-table shop_table"><thead><tr>';
		echo '<th>' . esc_html__( 'Data', 'diritto-di-recesso' ) . '</th>';
		echo '<th>' . esc_html__( 'Ordine', 'diritto-di-recesso' ) . '</th>';
		echo '<th>' . esc_html__( 'Prodotti', 'diritto-di-recesso' ) . '</th>';
		echo '<th>' . esc_html__( 'Stato', 'diritto-di-recesso' ) . '</th>';
		echo '<th>' . esc_html__( 'Ricevuta', 'diritto-di-recesso' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $mine as $r ) {
			$dt = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['created_at'] );
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td><a href="%5$s" target="_blank" rel="noopener">%6$s</a></td></tr>',
				esc_html( $dt ),
				/* translators: %s numero ordine */
				esc_html( sprintf( __( 'Ordine #%s', 'diritto-di-recesso' ), $r['order_id'] ) ),
				esc_html( self::items_summary( $r['items_data'] ) ),
				esc_html( DDR_DB::status_label( $r['status'] ) ),
				esc_url( ddr_page_url( array( 'ddr_receipt' => $r['receipt_code'] ) ) ),
				esc_html( $r['receipt_code'] )
			);
		}
		echo '</tbody></table>';
	}

	/* ---------------------------------------------------------------------
	 * Punti d'accesso: footer + area ordini
	 * ------------------------------------------------------------------ */

	/**
	 * Link persistente nel footer ("disponibile in maniera continuativa").
	 * Etichetta letterale richiesta dal c.3.
	 */
	public static function footer_link() {
		if ( 'yes' !== get_option( 'ddr_footer_link', 'yes' ) ) {
			return;
		}
		$style = get_option( 'ddr_footer_style', 'link' );
		echo '<div class="ddr-footer-link" style="margin:0 auto;padding:18px 0 28px;width:100%;text-align:center;box-sizing:border-box;">'
			. self::render_link( $style ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — markup gia' sanificato.
			. '</div>';
	}

	/**
	 * Mostra l'icona nel CTA? (opzione, default si).
	 */
	protected static function show_icon() {
		return 'yes' === get_option( 'ddr_btn_icon', 'yes' );
	}

	/**
	 * SVG dell'icona (eredita il colore dal testo via currentColor).
	 */
	protected static function icon_svg() {
		return '<svg class="ddr-ico-svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:-2px;width:1em;height:1em;"><path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg>';
	}

	/**
	 * Rendering unificato del CTA di recesso nei vari stili.
	 *
	 * @param string    $style     link|button|pill
	 * @param string    $label     etichetta (vuoto = ddr_link_label())
	 * @param bool|null $with_icon null = segue l'opzione globale
	 */
	public static function render_link( $style = 'button', $label = '', $with_icon = null ) {
		$label = '' !== $label ? $label : ddr_link_label();
		if ( null === $with_icon ) {
			$with_icon = self::show_icon();
		}
		$url  = ddr_page_url();
		$icon = $with_icon ? self::icon_svg() . ' ' : '';

		if ( 'pill' === $style ) {
			return self::pill_anchor( $label, $with_icon );
		}

		$class = ( 'link' === $style ) ? 'ddr-cta ddr-link-plain' : 'ddr-cta ddr-btn ddr-btn-primary';
		return sprintf(
			'<a class="%1$s" href="%2$s">%3$s<span class="ddr-cta-text">%4$s</span></a>',
			esc_attr( $class ),
			esc_url( $url ),
			$icon,
			esc_html( $label )
		);
	}

	/**
	 * Markup del "pill" (badge) con stili inline resilienti a WP Rocket / temi.
	 */
	protected static function pill_anchor( $label = '', $with_icon = null ) {
		$accent = self::color( 'ddr_accent', '#ea580c' );
		$label  = '' !== $label ? $label : ddr_link_label();
		if ( null === $with_icon ) {
			$with_icon = self::show_icon();
		}

		$pill_style = 'display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:999px;background:#ffffff;color:#1f2937;font-size:14px;font-weight:600;line-height:1;text-decoration:none;border:1px solid rgba(15,23,42,.10);box-shadow:0 1px 2px rgba(15,23,42,.14),0 6px 16px rgba(15,23,42,.18);';
		$ico = $with_icon ? '<span class="ddr-ico" style="display:inline-flex;align-items:center;color:' . esc_attr( $accent ) . ';">' . self::icon_svg() . '</span>' : '';

		return sprintf(
			'<a class="ddr-pill" href="%1$s" style="%2$s">%3$s<span class="ddr-pill-text">%4$s</span></a>',
			esc_url( ddr_page_url() ),
			$pill_style,
			$ico,
			esc_html( $label )
		);
	}

	/**
	 * Shortcode [diritto_recesso_link style="button|link|pill" text="..." icon="yes|no"].
	 * Permette di inserire il pulsante ovunque (pagine, widget, builder).
	 */
	public static function link_shortcode( $atts ) {
		$atts  = shortcode_atts( array( 'style' => 'button', 'text' => '', 'icon' => '' ), $atts, 'diritto_recesso_link' );
		$label = '' !== $atts['text'] ? sanitize_text_field( $atts['text'] ) : '';

		$with_icon = null;
		if ( '' !== $atts['icon'] ) {
			$with_icon = in_array( strtolower( $atts['icon'] ), array( 'yes', 'true', '1' ), true );
		}

		return self::render_link( $atts['style'], $label, $with_icon );
	}

	/**
	 * Aggiunge la voce di recesso a una posizione di menu scelta nelle impostazioni.
	 */
	public static function menu_link( $items, $args ) {
		$location = get_option( 'ddr_menu_location', '' );
		if ( ! $location || empty( $args->theme_location ) || $args->theme_location !== $location ) {
			return $items;
		}
		$items .= sprintf(
			'<li class="menu-item ddr-menu-link"><a href="%s">%s</a></li>',
			esc_url( ddr_page_url() ),
			esc_html( ddr_link_label() )
		);
		return $items;
	}

	/**
	 * Pulsante nella tabella "I miei ordini" per gli utenti loggati.
	 */
	public static function account_order_action( $actions, $order ) {
		$eval = DDR_Eligibility::evaluate( $order );
		if ( empty( $eval['eligible'] ) ) {
			return $actions;
		}
		$actions['ddr'] = array(
			'url'  => ddr_page_url( array( 'order' => $order->get_order_number() ) ),
			'name' => ddr_link_label(),
		);
		return $actions;
	}

	/* ---------------------------------------------------------------------
	 * Shortcode: dispatcher
	 * ------------------------------------------------------------------ */

	public static function render_shortcode() {
		wp_enqueue_script( 'ddr' );

		// Ricevuta stampabile (accesso col codice ricevuta univoco).
		if ( isset( $_GET['ddr_receipt'] ) ) {
			return self::render_receipt_page( sanitize_text_field( wp_unslash( $_GET['ddr_receipt'] ) ) );
		}

		// IMPORTANTE: il POST va gestito PRIMA del token in GET. L'ospite che
		// arriva dal link email resta su un URL con ?ddr_token=...; al submit
		// quel parametro e' ancora presente e, se lo controllassimo prima,
		// ri-mostrerebbe lo step senza mai elaborare il POST.
		if ( isset( $_POST['ddr_action'] ) && check_admin_referer( 'ddr_flow', 'ddr_nonce' ) ) {
			$action = sanitize_key( wp_unslash( $_POST['ddr_action'] ) );
			switch ( $action ) {
				case 'lookup':
					return self::handle_lookup();
				case 'select':
					return self::handle_select();
				case 'declaration':
					return self::handle_declaration();
				case 'confirm':
					return self::handle_confirm();
			}
		}

		// Click sul link email (GET, nessun POST): token in URL -> selezione.
		if ( isset( $_GET['ddr_token'] ) ) {
			$flow = self::get_flow( sanitize_text_field( wp_unslash( $_GET['ddr_token'] ) ) );
			if ( $flow ) {
				return self::step_select( $flow );
			}
			return self::notice( __( 'Il link di verifica non è valido o è scaduto. Ricomincia la procedura.', 'diritto-di-recesso' ), 'error' ) . self::step_lookup();
		}

		// Arrivo con ?order=N (footer/area account): se l'utente loggato è il
		// proprietario dell'ordine ed è eleggibile, salta il lookup e va dritto
		// alla selezione dei prodotti. La verifica titolarità è già garantita
		// dall'autenticazione; il recesso vero resta un POST con nonce.
		if ( isset( $_GET['order'] ) ) {
			$direct = self::maybe_direct_select( sanitize_text_field( wp_unslash( $_GET['order'] ) ) );
			if ( null !== $direct ) {
				return $direct;
			}
		}

		return self::step_lookup();
	}

	/**
	 * Avvio diretto della selezione per il proprietario loggato. Ritorna l'HTML
	 * dello step di selezione, oppure null (si ricade sul lookup normale).
	 */
	protected static function maybe_direct_select( $order_value ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return null;
		}
		$order = self::resolve_order( $order_value );
		if ( ! $order || (int) $order->get_customer_id() !== (int) $user->ID ) {
			return null;
		}
		$eval = DDR_Eligibility::evaluate( $order );
		if ( empty( $eval['eligible'] ) ) {
			return null;
		}
		$token = self::create_flow(
			array(
				'order_id' => $order->get_id(),
				'email'    => $order->get_billing_email(),
				'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			)
		);
		return self::step_select( self::get_flow( $token ) );
	}

	/* ---------------------------------------------------------------------
	 * STEP 1 - Lookup
	 * ------------------------------------------------------------------ */

	protected static function step_lookup( $order_value = '', $email_value = '' ) {
		if ( '' === $order_value && isset( $_GET['order'] ) ) {
			$order_value = sanitize_text_field( wp_unslash( $_GET['order'] ) );
		}
		$user = wp_get_current_user();
		if ( '' === $email_value && $user->exists() ) {
			$email_value = $user->user_email;
		}

		ob_start();
		?>
		<div class="ddr-box">
			<h2 class="ddr-title"><?php esc_html_e( 'Recedere dal contratto', 'diritto-di-recesso' ); ?></h2>
			<p class="ddr-intro"><?php esc_html_e( 'Inserisci il numero d’ordine e l’email usata per l’acquisto. Verificheremo la titolarità e potrai inviare la dichiarazione di recesso.', 'diritto-di-recesso' ); ?></p>
			<form method="post" action="<?php echo esc_url( ddr_page_url() ); ?>" class="ddr-form">
				<?php wp_nonce_field( 'ddr_flow', 'ddr_nonce' ); ?>
				<input type="hidden" name="ddr_action" value="lookup" />
				<p>
					<label for="ddr-order"><?php esc_html_e( 'Numero d’ordine', 'diritto-di-recesso' ); ?></label>
					<input type="text" id="ddr-order" name="ddr_order" value="<?php echo esc_attr( $order_value ); ?>" required />
				</p>
				<p>
					<label for="ddr-email"><?php esc_html_e( 'Email di fatturazione', 'diritto-di-recesso' ); ?></label>
					<input type="email" id="ddr-email" name="ddr_email" value="<?php echo esc_attr( $email_value ); ?>" required />
				</p>
				<p>
					<button type="submit" class="ddr-btn ddr-btn-primary"><?php echo esc_html( ddr_link_label() ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function handle_lookup() {
		$order_input = isset( $_POST['ddr_order'] ) ? sanitize_text_field( wp_unslash( $_POST['ddr_order'] ) ) : '';
		$email_input = isset( $_POST['ddr_email'] ) ? sanitize_email( wp_unslash( $_POST['ddr_email'] ) ) : '';

		$order = self::resolve_order( $order_input );

		// Messaggio volutamente generico: non rivelare se l'ordine esiste.
		$generic_fail = self::notice(
			__( 'Se i dati corrispondono a un ordine valido, riceverai a breve le istruzioni via email.', 'diritto-di-recesso' ),
			'info'
		);

		if ( ! $order || ! $email_input ) {
			return $generic_fail . self::step_lookup( $order_input, $email_input );
		}

		// Verifica corrispondenza email di fatturazione.
		if ( strtolower( $order->get_billing_email() ) !== strtolower( $email_input ) ) {
			return $generic_fail . self::step_lookup( $order_input, $email_input );
		}

		// Eleggibilita' (include il controllo "restano item recedibili").
		$eval = DDR_Eligibility::evaluate( $order );
		if ( empty( $eval['eligible'] ) ) {
			return self::notice( $eval['reason'], 'error' ) . self::step_lookup();
		}

		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$flow_data = array(
			'order_id' => $order->get_id(),
			'email'    => $order->get_billing_email(),
			'name'     => $name,
		);

		$user = wp_get_current_user();
		$is_owner = $user->exists() && (int) $order->get_customer_id() === (int) $user->ID;

		if ( $is_owner ) {
			// Proprietario loggato: niente link, si procede alla selezione.
			$token = self::create_flow( $flow_data );
			return self::step_select( self::get_flow( $token ) );
		}

		// Ospite: invio link di verifica via email.
		$token = self::create_flow( $flow_data );
		$link  = ddr_page_url( array( 'ddr_token' => $token ) );
		DDR_Emails::send_verification_link( $order->get_billing_email(), $name, $order, $link );

		return self::notice(
			__( 'Ti abbiamo inviato un’email con un link per confermare la richiesta di recesso. Controlla la posta (anche lo spam). Il link è valido per 30 minuti.', 'diritto-di-recesso' ),
			'success'
		);
	}

	/* ---------------------------------------------------------------------
	 * STEP 2 - Selezione prodotti e quantita' (recesso parziale)
	 * ------------------------------------------------------------------ */

	protected static function step_select( $flow, $error = '' ) {
		$order = wc_get_order( $flow['order_id'] );
		if ( ! $order ) {
			return self::notice( __( 'Ordine non trovato.', 'diritto-di-recesso' ), 'error' );
		}

		$items = DDR_Eligibility::recedible_items( $order );
		if ( empty( $items ) ) {
			return self::notice( __( 'Per questo ordine non risultano prodotti per cui è ancora possibile recedere.', 'diritto-di-recesso' ), 'info' );
		}

		ob_start();
		if ( $error ) {
			echo self::notice( $error, 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<div class="ddr-box">
			<h2 class="ddr-title"><?php esc_html_e( 'Cosa vuoi restituire', 'diritto-di-recesso' ); ?></h2>
			<p class="ddr-intro"><?php esc_html_e( 'Seleziona i prodotti e le quantità per cui eserciti il recesso. Puoi recedere anche solo per una parte dell’ordine.', 'diritto-di-recesso' ); ?></p>
			<form method="post" action="<?php echo esc_url( ddr_page_url() ); ?>" class="ddr-form ddr-select">
				<?php wp_nonce_field( 'ddr_flow', 'ddr_nonce' ); ?>
				<input type="hidden" name="ddr_action" value="select" />
				<input type="hidden" name="ddr_token" value="<?php echo esc_attr( $flow['token'] ); ?>" />

				<div class="ddr-items">
					<?php
					foreach ( $items as $it ) :
						$cid     = 'ddr-sel-' . (int) $it['line_item_id'];
						$oi      = $order->get_item( $it['line_item_id'] );
						$product = $oi ? $oi->get_product() : null;
						$thumb   = $product ? $product->get_image( 'thumbnail', array( 'class' => 'ddr-item-thumb' ) ) : '';
						$unit    = $oi ? (float) $order->get_item_total( $oi, true ) : 0;
						$price   = $unit ? wc_price( $unit, array( 'currency' => $order->get_currency() ) ) : '';
						?>
						<div class="ddr-item-row">
							<input type="checkbox" class="ddr-item-toggle" id="<?php echo esc_attr( $cid ); ?>" name="ddr_sel[<?php echo esc_attr( $it['line_item_id'] ); ?>]" value="1" />
							<label class="ddr-item-main" for="<?php echo esc_attr( $cid ); ?>">
								<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup WooCommerce. ?>
								<span class="ddr-item-info">
									<span class="ddr-item-name"><?php echo esc_html( $it['name'] ); ?></span>
									<span class="ddr-item-meta">
										<?php echo wp_kses_post( $price ); ?>
										<?php if ( $it['qty_withdrawn'] > 0 ) : ?>
											&middot; <?php
											/* translators: 1: quantita' disponibile 2: gia' recessa */
											printf( esc_html__( 'disponibili %1$d (già recessi %2$d)', 'diritto-di-recesso' ), (int) $it['qty_available'], (int) $it['qty_withdrawn'] );
											?>
										<?php endif; ?>
									</span>
								</span>
							</label>
							<span class="ddr-qty" data-max="<?php echo esc_attr( $it['qty_available'] ); ?>">
								<button type="button" class="ddr-qty-btn ddr-qty-minus" tabindex="-1" aria-label="<?php esc_attr_e( 'Riduci quantità', 'diritto-di-recesso' ); ?>" disabled>&minus;</button>
								<input type="number" class="ddr-item-qty" name="ddr_qty[<?php echo esc_attr( $it['line_item_id'] ); ?>]" value="<?php echo esc_attr( $it['qty_available'] ); ?>" min="1" max="<?php echo esc_attr( $it['qty_available'] ); ?>" inputmode="numeric" disabled />
								<button type="button" class="ddr-qty-btn ddr-qty-plus" tabindex="-1" aria-label="<?php esc_attr_e( 'Aumenta quantità', 'diritto-di-recesso' ); ?>" disabled>+</button>
							</span>
						</div>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="submit" class="ddr-btn ddr-btn-primary"><?php esc_html_e( 'Continua', 'diritto-di-recesso' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function handle_select() {
		$token = isset( $_POST['ddr_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ddr_token'] ) ) : '';
		$flow  = self::get_flow( $token );
		if ( ! $flow ) {
			return self::notice( __( 'Sessione scaduta. Ricomincia la procedura.', 'diritto-di-recesso' ), 'error' ) . self::step_lookup();
		}

		$order = wc_get_order( $flow['order_id'] );
		if ( ! $order ) {
			return self::notice( __( 'Ordine non trovato.', 'diritto-di-recesso' ), 'error' );
		}

		$available = DDR_Eligibility::recedible_items( $order );
		$sel       = isset( $_POST['ddr_sel'] ) && is_array( $_POST['ddr_sel'] ) ? wp_unslash( $_POST['ddr_sel'] ) : array();
		$qty_in    = isset( $_POST['ddr_qty'] ) && is_array( $_POST['ddr_qty'] ) ? wp_unslash( $_POST['ddr_qty'] ) : array();

		$selected = array();
		foreach ( $available as $li => $it ) {
			if ( empty( $sel[ $li ] ) ) {
				continue;
			}
			$qty = isset( $qty_in[ $li ] ) ? (int) $qty_in[ $li ] : 0;
			$qty = max( 1, min( $qty, (int) $it['qty_available'] ) );
			$selected[] = array(
				'line_item_id' => (int) $it['line_item_id'],
				'product_id'   => (int) $it['product_id'],
				'name'         => $it['name'],
				'qty'          => $qty,
			);
		}

		if ( empty( $selected ) ) {
			return self::step_select( $flow, __( 'Seleziona almeno un prodotto da restituire.', 'diritto-di-recesso' ) );
		}

		$flow['items'] = $selected;
		self::update_flow( $token, $flow );

		return self::step_declaration( $flow );
	}

	/* ---------------------------------------------------------------------
	 * STEP 3 - Dichiarazione (c.2)
	 * ------------------------------------------------------------------ */

	protected static function step_declaration( $flow, $reason = '', $name = '' ) {
		$order = wc_get_order( $flow['order_id'] );
		if ( ! $order ) {
			return self::notice( __( 'Ordine non trovato.', 'diritto-di-recesso' ), 'error' );
		}
		if ( empty( $flow['items'] ) ) {
			return self::step_select( $flow );
		}
		if ( '' === $name ) {
			$name = $flow['name'];
		}

		ob_start();
		?>
		<div class="ddr-box">
			<h2 class="ddr-title"><?php esc_html_e( 'Dichiarazione di recesso', 'diritto-di-recesso' ); ?></h2>
			<p class="ddr-intro"><?php esc_html_e( 'Verifica i dati della dichiarazione. Al passaggio successivo potrai confermare il recesso.', 'diritto-di-recesso' ); ?></p>
			<form method="post" action="<?php echo esc_url( ddr_page_url() ); ?>" class="ddr-form">
				<?php wp_nonce_field( 'ddr_flow', 'ddr_nonce' ); ?>
				<input type="hidden" name="ddr_action" value="declaration" />
				<input type="hidden" name="ddr_token" value="<?php echo esc_attr( $flow['token'] ); ?>" />

				<p>
					<label for="ddr-name"><?php esc_html_e( 'Nome e cognome', 'diritto-di-recesso' ); ?></label>
					<input type="text" id="ddr-name" name="ddr_name" value="<?php echo esc_attr( $name ); ?>" required />
				</p>

				<p>
					<label><?php esc_html_e( 'Riferimento ordine', 'diritto-di-recesso' ); ?></label>
					<input type="text" value="<?php echo esc_attr( $order->get_order_number() ); ?>" disabled />
				</p>

				<p>
					<label><?php esc_html_e( 'Prodotti oggetto del recesso', 'diritto-di-recesso' ); ?></label>
					<span class="ddr-readonly"><?php echo esc_html( self::items_summary( $flow['items'] ) ); ?></span>
				</p>

				<p>
					<label><?php esc_html_e( 'Mezzo per l’avviso di ricevimento', 'diritto-di-recesso' ); ?></label>
					<input type="text" value="<?php echo esc_attr( sprintf( __( 'Email: %s', 'diritto-di-recesso' ), $flow['email'] ) ); ?>" disabled />
				</p>

				<p>
					<label for="ddr-reason"><?php esc_html_e( 'Motivazione (facoltativa)', 'diritto-di-recesso' ); ?></label>
					<textarea id="ddr-reason" name="ddr_reason" rows="3"><?php echo esc_textarea( $reason ); ?></textarea>
				</p>

				<p>
					<button type="submit" class="ddr-btn ddr-btn-primary"><?php esc_html_e( 'Procedi alla conferma', 'diritto-di-recesso' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function handle_declaration() {
		$token = isset( $_POST['ddr_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ddr_token'] ) ) : '';
		$flow  = self::get_flow( $token );
		if ( ! $flow ) {
			return self::notice( __( 'Sessione scaduta. Ricomincia la procedura.', 'diritto-di-recesso' ), 'error' ) . self::step_lookup();
		}
		if ( empty( $flow['items'] ) ) {
			return self::step_select( $flow );
		}

		$name   = isset( $_POST['ddr_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ddr_name'] ) ) : $flow['name'];
		$reason = isset( $_POST['ddr_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddr_reason'] ) ) : '';

		// Aggiorna il nome nel flow (confermato dall'utente).
		$flow['name'] = $name;
		self::update_flow( $token, $flow );

		return self::step_confirm( $flow, $name, $reason );
	}

	/* ---------------------------------------------------------------------
	 * STEP 4 - Conferma (c.5)
	 * ------------------------------------------------------------------ */

	protected static function step_confirm( $flow, $name, $reason ) {
		$order = wc_get_order( $flow['order_id'] );
		if ( ! $order ) {
			return self::notice( __( 'Ordine non trovato.', 'diritto-di-recesso' ), 'error' );
		}

		ob_start();
		?>
		<div class="ddr-box">
			<h2 class="ddr-title"><?php esc_html_e( 'Conferma il recesso', 'diritto-di-recesso' ); ?></h2>
			<p class="ddr-intro"><?php esc_html_e( 'Stai per inviare la seguente dichiarazione di recesso. Una volta confermata riceverai l’avviso di ricevimento via email.', 'diritto-di-recesso' ); ?></p>

			<table class="ddr-summary">
				<tr><th><?php esc_html_e( 'Intestatario', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $name ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Ordine', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $order->get_order_number() ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Prodotti', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( self::items_summary( $flow['items'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $flow['email'] ); ?></td></tr>
				<?php if ( $reason ) : ?>
				<tr><th><?php esc_html_e( 'Motivazione', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $reason ); ?></td></tr>
				<?php endif; ?>
			</table>

			<form method="post" action="<?php echo esc_url( ddr_page_url() ); ?>" class="ddr-form">
				<?php wp_nonce_field( 'ddr_flow', 'ddr_nonce' ); ?>
				<input type="hidden" name="ddr_action" value="confirm" />
				<input type="hidden" name="ddr_token" value="<?php echo esc_attr( $flow['token'] ); ?>" />
				<input type="hidden" name="ddr_name" value="<?php echo esc_attr( $name ); ?>" />
				<input type="hidden" name="ddr_reason" value="<?php echo esc_attr( $reason ); ?>" />

				<p class="ddr-ack">
					<label>
						<input type="checkbox" id="ddr-ack" />
						<?php esc_html_e( 'Dichiaro di voler recedere dal contratto relativo ai prodotti sopra indicati.', 'diritto-di-recesso' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="ddr-btn ddr-btn-danger" id="ddr-confirm-btn" disabled><?php esc_html_e( 'Conferma recesso', 'diritto-di-recesso' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function handle_confirm() {
		$token = isset( $_POST['ddr_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ddr_token'] ) ) : '';
		$flow  = self::get_flow( $token );
		if ( ! $flow ) {
			return self::notice( __( 'Sessione scaduta. Ricomincia la procedura.', 'diritto-di-recesso' ), 'error' ) . self::step_lookup();
		}

		$order = wc_get_order( $flow['order_id'] );
		if ( ! $order ) {
			return self::notice( __( 'Ordine non trovato.', 'diritto-di-recesso' ), 'error' );
		}
		if ( empty( $flow['items'] ) ) {
			return self::step_select( $flow );
		}

		// Eleggibilita' al momento della conferma.
		$eval = DDR_Eligibility::evaluate( $order );
		if ( empty( $eval['eligible'] ) ) {
			self::delete_flow( $token );
			return self::notice( $eval['reason'], 'error' );
		}

		// Ri-valida le quantita' selezionate contro il disponibile attuale
		// (anti doppio invio / corsa tra piu' richieste).
		$available = DDR_Eligibility::recedible_items( $order );
		$items     = array();
		foreach ( $flow['items'] as $sel ) {
			$li = (int) $sel['line_item_id'];
			if ( ! isset( $available[ $li ] ) ) {
				continue;
			}
			$qty = min( (int) $sel['qty'], (int) $available[ $li ]['qty_available'] );
			if ( $qty > 0 ) {
				$items[] = array(
					'line_item_id' => $li,
					'product_id'   => (int) $sel['product_id'],
					'name'         => $sel['name'],
					'qty'          => $qty,
				);
			}
		}
		if ( empty( $items ) ) {
			self::delete_flow( $token );
			return self::notice( __( 'I prodotti selezionati non sono più disponibili per il recesso. Ricomincia la procedura.', 'diritto-di-recesso' ), 'error' );
		}

		$name   = isset( $_POST['ddr_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ddr_name'] ) ) : $flow['name'];
		$reason = isset( $_POST['ddr_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddr_reason'] ) ) : '';

		$declaration = array(
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'name'         => $name,
			'email'        => $flow['email'],
			'items'        => $items,
			'statement'    => __( 'Il consumatore dichiara di recedere dal contratto relativo ai prodotti indicati dell’ordine.', 'diritto-di-recesso' ),
		);

		$request = DDR_DB::insert(
			array(
				'order_id'        => $order->get_id(),
				'user_id'         => (int) $order->get_customer_id(),
				'customer_name'   => $name,
				'customer_email'  => $flow['email'],
				'declaration'     => $declaration,
				'items'           => $items,
				'reason'          => $reason,
				'receipt_channel' => 'email',
			)
		);

		if ( ! $request ) {
			return self::notice( __( 'Si è verificato un errore nel salvataggio. Riprova.', 'diritto-di-recesso' ), 'error' );
		}

		// Annotazione sull'ordine + invio email (avviso ricevimento + admin).
		$order->add_order_note(
			sprintf(
				/* translators: 1: codice ricevuta 2: prodotti */
				__( 'Richiesta di recesso online ricevuta (art. 54-bis). Ricevuta: %1$s — %2$s', 'diritto-di-recesso' ),
				$request['receipt_code'],
				self::items_summary( $items )
			)
		);

		DDR_Emails::send_receipt( $request );
		DDR_Emails::notify_admin( $request );

		do_action( 'ddr_withdrawal_registered', $request, $order );

		self::delete_flow( $token );

		return self::step_success( $request, $order );
	}

	/* ---------------------------------------------------------------------
	 * STEP 5 - Esito
	 * ------------------------------------------------------------------ */

	protected static function step_success( $request, $order ) {
		$receipt_url = ddr_page_url( array( 'ddr_receipt' => $request['receipt_code'] ) );
		ob_start();
		?>
		<div class="ddr-box ddr-success">
			<h2 class="ddr-title"><?php esc_html_e( 'Recesso ricevuto', 'diritto-di-recesso' ); ?></h2>
			<p><?php esc_html_e( 'Abbiamo registrato la tua dichiarazione di recesso. Ti abbiamo inviato l’avviso di ricevimento all’indirizzo email indicato, con data e ora della trasmissione.', 'diritto-di-recesso' ); ?></p>
			<p><strong><?php esc_html_e( 'Codice ricevuta:', 'diritto-di-recesso' ); ?></strong> <?php echo esc_html( $request['receipt_code'] ); ?></p>
			<p>
				<a class="ddr-btn ddr-btn-primary" href="<?php echo esc_url( $receipt_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visualizza la ricevuta', 'diritto-di-recesso' ); ?></a>
				<?php if ( 'yes' === get_option( 'ddr_pdf_enable', 'yes' ) ) : ?>
					<a class="ddr-btn" href="<?php echo esc_url( ddr_page_url( array( 'ddr_pdf' => $request['receipt_code'] ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Scarica PDF', 'diritto-di-recesso' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Ricevuta stampabile (supporto durevole consultabile dal cliente)
	 * ------------------------------------------------------------------ */

	protected static function render_receipt_page( $code ) {
		$request = DDR_DB::get_by_receipt( $code );
		if ( ! $request ) {
			return self::notice( __( 'Ricevuta non trovata.', 'diritto-di-recesso' ), 'error' );
		}

		$dt = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $request['created_at'] );

		ob_start();
		?>
		<div class="ddr-box ddr-receipt">
			<h2 class="ddr-title"><?php esc_html_e( 'Avviso di ricevimento del recesso', 'diritto-di-recesso' ); ?></h2>
			<p class="ddr-intro"><?php esc_html_e( 'Documento riepilogativo della dichiarazione di recesso (art. 54-bis Codice del Consumo).', 'diritto-di-recesso' ); ?></p>
			<table class="ddr-summary">
				<tr><th><?php esc_html_e( 'Codice ricevuta', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $request['receipt_code'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Data e ora', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $dt ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Ordine', 'diritto-di-recesso' ); ?></th><td>#<?php echo esc_html( $request['order_id'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Intestatario', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $request['customer_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $request['customer_email'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Prodotti', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( self::items_summary( $request['items_data'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Stato', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( DDR_DB::status_label( $request['status'] ) ); ?></td></tr>
				<?php if ( ! empty( $request['reason'] ) ) : ?>
				<tr><th><?php esc_html_e( 'Motivazione', 'diritto-di-recesso' ); ?></th><td><?php echo esc_html( $request['reason'] ); ?></td></tr>
				<?php endif; ?>
			</table>
			<p class="ddr-no-print">
				<?php if ( 'yes' === get_option( 'ddr_pdf_enable', 'yes' ) ) : ?>
					<a class="ddr-btn ddr-btn-primary" href="<?php echo esc_url( ddr_page_url( array( 'ddr_pdf' => $request['receipt_code'] ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Scarica PDF', 'diritto-di-recesso' ); ?></a>
				<?php endif; ?>
				<button type="button" class="ddr-btn" id="ddr-print"><?php esc_html_e( 'Stampa', 'diritto-di-recesso' ); ?></button>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Genera e invia il PDF della ricevuta se richiesto via ?ddr_pdf=CODICE.
	 */
	public static function maybe_stream_pdf() {
		if ( ! isset( $_GET['ddr_pdf'] ) || 'yes' !== get_option( 'ddr_pdf_enable', 'yes' ) ) {
			return;
		}
		$request = DDR_DB::get_by_receipt( sanitize_text_field( wp_unslash( $_GET['ddr_pdf'] ) ) );
		if ( $request ) {
			DDR_PDF::stream( $request );
		}
	}

	/* ---------------------------------------------------------------------
	 * Helper: risoluzione ordine, flow token, notice, riepilogo item
	 * ------------------------------------------------------------------ */

	/**
	 * Riepilogo leggibile degli item recessi: "Prodotto A ×2, Prodotto B ×1".
	 */
	public static function items_summary( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return '';
		}
		$parts = array();
		foreach ( $items as $it ) {
			$name = isset( $it['name'] ) ? $it['name'] : '';
			$qty  = isset( $it['qty'] ) ? (int) $it['qty'] : 0;
			$parts[] = $qty > 1 ? sprintf( '%s ×%d', $name, $qty ) : $name;
		}
		return implode( ', ', $parts );
	}

	/**
	 * Risolve l'ordine dal valore inserito (numero o id).
	 * Filtrabile per supportare plugin di numerazione personalizzata.
	 */
	protected static function resolve_order( $value ) {
		$value = trim( $value );
		$order = apply_filters( 'ddr_resolve_order', null, $value );
		if ( $order instanceof WC_Order ) {
			return $order;
		}
		if ( is_numeric( $value ) ) {
			$o = wc_get_order( (int) $value );
			if ( $o instanceof WC_Order ) {
				return $o;
			}
		}
		return null;
	}

	protected static function create_flow( $data ) {
		$token = wp_generate_password( 32, false, false );
		$data['token'] = $token;
		set_transient( 'ddr_flow_' . $token, $data, self::FLOW_TTL );
		return $token;
	}

	protected static function get_flow( $token ) {
		if ( ! $token ) {
			return null;
		}
		$data = get_transient( 'ddr_flow_' . $token );
		if ( ! $data ) {
			return null;
		}
		$data['token'] = $token;
		return $data;
	}

	protected static function update_flow( $token, $data ) {
		set_transient( 'ddr_flow_' . $token, $data, self::FLOW_TTL );
	}

	protected static function delete_flow( $token ) {
		delete_transient( 'ddr_flow_' . $token );
	}

	protected static function notice( $message, $type = 'info' ) {
		return sprintf(
			'<div class="ddr-notice ddr-notice-%s">%s</div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
