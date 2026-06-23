<?php
/**
 * Determina se per un ordine il recesso e' esercitabile tramite la funzione
 * digitale: finestra temporale (default 14 gg), stato ordine, esclusioni
 * (art. 59), e data di decorrenza dell'obbligo (19/06/2026).
 *
 * IMPORTANTE: l'art. 54-bis NON crea il diritto di recesso, lo rende solo
 * esercitabile online dove gia' esiste. Le esclusioni dell'art. 59 (beni su
 * misura, sigillati e aperti, deperibili, ecc.) restano valide: vanno gestite
 * qui o tramite i filtri previsti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Eligibility {

	/**
	 * Giorni della finestra di recesso. Filtrabile.
	 * NB: l'art. 53 puo' estendere fino a 12 mesi se l'informativa
	 * precontrattuale e' carente; in quel caso aumentare via filtro.
	 */
	public static function withdrawal_days( $order = null ) {
		$days = (int) get_option( 'ddr_window_days', 14 );
		return (int) apply_filters( 'ddr_withdrawal_days', $days, $order );
	}

	/**
	 * Stati ordine per cui la funzione e' attiva. Filtrabile.
	 */
	public static function eligible_statuses() {
		$default = array( 'processing', 'completed', 'on-hold' );
		return apply_filters( 'ddr_eligible_statuses', $default );
	}

	/**
	 * Data di decorrenza (inizio della finestra di 14 gg).
	 * Per i beni decorre dalla consegna; WooCommerce non traccia nativamente
	 * la consegna, quindi si usa, in ordine: data completamento, data
	 * pagamento, data creazione. Sovrascrivibile via filtro per chi traccia
	 * la consegna con un meta dedicato.
	 *
	 * @return int|null timestamp
	 */
	public static function start_timestamp( $order ) {
		$ts = null;

		if ( $order->get_date_completed() ) {
			$ts = $order->get_date_completed()->getTimestamp();
		} elseif ( $order->get_date_paid() ) {
			$ts = $order->get_date_paid()->getTimestamp();
		} elseif ( $order->get_date_created() ) {
			$ts = $order->get_date_created()->getTimestamp();
		}

		return apply_filters( 'ddr_order_start_timestamp', $ts, $order );
	}

	/**
	 * Timestamp di scadenza del recesso.
	 */
	public static function deadline_timestamp( $order ) {
		$start = self::start_timestamp( $order );
		if ( ! $start ) {
			return null;
		}
		return $start + ( self::withdrawal_days( $order ) * DAY_IN_SECONDS );
	}

	/**
	 * Data di scadenza formattata per l'utente.
	 */
	public static function deadline_label( $order ) {
		$ts = self::deadline_timestamp( $order );
		if ( ! $ts ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ), $ts );
	}

	/**
	 * L'ordine contiene solo prodotti esclusi dal recesso?
	 * Esclusione per prodotto via meta `_ddr_excluso` = 'yes', oppure via
	 * categorie elencate nell'opzione ddr_excluded_cats, oppure via filtro.
	 */
	public static function is_excluded_by_products( $order ) {
		$all_excluded = true;
		$has_items    = false;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$has_items = true;
			if ( ! self::is_product_excluded( $product ) ) {
				$all_excluded = false;
			}
		}

		if ( ! $has_items ) {
			return false;
		}

		return (bool) apply_filters( 'ddr_order_all_excluded', $all_excluded, $order );
	}

	/**
	 * Un singolo prodotto e' escluso dal recesso (art. 59)?
	 * Per meta `_ddr_excluso` = 'yes' o per categoria in `ddr_excluded_cats`.
	 */
	public static function is_product_excluded( $product ) {
		if ( ! $product ) {
			return false;
		}
		if ( 'yes' === $product->get_meta( '_ddr_excluso' ) ) {
			return true;
		}
		$excluded_cats = (array) get_option( 'ddr_excluded_cats', array() );
		if ( $excluded_cats ) {
			$cats = $product->get_category_ids();
			if ( array_intersect( $cats, array_map( 'intval', $excluded_cats ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Item ancora recedibili dell'ordine: per ogni riga, quantita' acquistata
	 * meno quantita' gia' recessa in richieste precedenti non annullate,
	 * escludendo i prodotti non recedibili (art. 59).
	 *
	 * @return array<int,array> Mappa line_item_id => {
	 *     line_item_id, product_id, name, qty_purchased, qty_withdrawn, qty_available
	 * }. Solo righe con qty_available > 0.
	 */
	public static function recedible_items( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}
		$withdrawn = DDR_DB::withdrawn_quantities( $order->get_id() );
		$out       = array();

		foreach ( $order->get_items() as $line_item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product || self::is_product_excluded( $product ) ) {
				continue;
			}
			$purchased = (int) $item->get_quantity();
			$already   = isset( $withdrawn[ $line_item_id ] ) ? (int) $withdrawn[ $line_item_id ] : 0;
			$available = max( 0, $purchased - $already );
			if ( $available <= 0 ) {
				continue;
			}
			$out[ $line_item_id ] = array(
				'line_item_id'  => (int) $line_item_id,
				'product_id'    => (int) $product->get_id(),
				'name'          => $item->get_name(),
				'qty_purchased' => $purchased,
				'qty_withdrawn' => $already,
				'qty_available' => $available,
			);
		}

		return apply_filters( 'ddr_recedible_items', $out, $order );
	}

	/**
	 * Data minima di conclusione contratto per cui scatta l'obbligo (19/06/2026).
	 * Di default NON blocca gli ordini precedenti (li mostra comunque), ma il
	 * professionista puo' attivare il blocco se vuole limitare la funzione ai
	 * soli ordini soggetti all'obbligo.
	 */
	public static function applicability_ok( $order ) {
		if ( 'yes' !== get_option( 'ddr_enforce_cutoff', 'no' ) ) {
			return true;
		}
		$cutoff  = strtotime( '2026-06-19 00:00:00' );
		$created = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
		return $created >= $cutoff;
	}

	/**
	 * Valutazione completa.
	 *
	 * @return array { bool eligible, string reason }
	 */
	public static function evaluate( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array(
				'eligible' => false,
				'reason'   => __( 'Ordine non trovato.', 'diritto-di-recesso' ),
			);
		}

		if ( ! self::applicability_ok( $order ) ) {
			return array(
				'eligible' => false,
				'reason'   => __( 'Per questo ordine non è prevista la funzione di recesso online.', 'diritto-di-recesso' ),
			);
		}

		if ( ! in_array( $order->get_status(), self::eligible_statuses(), true ) ) {
			return array(
				'eligible' => false,
				'reason'   => __( 'Lo stato di questo ordine non consente il recesso online in questo momento.', 'diritto-di-recesso' ),
			);
		}

		if ( self::is_excluded_by_products( $order ) ) {
			return array(
				'eligible' => false,
				'reason'   => __( 'I prodotti di questo ordine sono esclusi dal diritto di recesso ai sensi dell’art. 59 del Codice del Consumo.', 'diritto-di-recesso' ),
			);
		}

		$deadline = self::deadline_timestamp( $order );
		if ( $deadline && current_time( 'timestamp' ) > $deadline ) {
			return array(
				'eligible' => false,
				'reason'   => sprintf(
					/* translators: %s data */
					__( 'Il termine per il recesso (%s) è scaduto.', 'diritto-di-recesso' ),
					self::deadline_label( $order )
				),
			);
		}

		// Recesso parziale + richieste multiple: deve restare almeno una riga
		// con quantita' ancora recedibile.
		if ( ! self::recedible_items( $order ) ) {
			return array(
				'eligible' => false,
				'reason'   => __( 'Per questo ordine il recesso è già stato esercitato su tutti i prodotti disponibili.', 'diritto-di-recesso' ),
			);
		}

		// Permette override finale (es. logiche custom del cliente).
		$override = apply_filters( 'ddr_order_eligible', null, $order );
		if ( false === $override ) {
			return array(
				'eligible' => false,
				'reason'   => __( 'Recesso non disponibile per questo ordine.', 'diritto-di-recesso' ),
			);
		}

		return array(
			'eligible' => true,
			'reason'   => '',
		);
	}
}
