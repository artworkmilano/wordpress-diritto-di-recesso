<?php
/**
 * Rimborso WooCommerce a fronte di un recesso "completato".
 *
 * Crea un rimborso WooCommerce per le righe/quantita' oggetto del recesso, con
 * ripristino dello stock. NON effettua il rimborso tramite gateway di pagamento
 * (nessun movimento di denaro automatico): registra il rimborso e riporta lo
 * stock, lasciando all'esercente l'eventuale restituzione effettiva. Opzionale,
 * disattivato di default.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Refund {

	/**
	 * Elabora il rimborso per una richiesta. Idempotente per richiesta.
	 *
	 * @return bool true se il rimborso e' stato creato.
	 */
	public static function process( $request ) {
		if ( ! function_exists( 'wc_create_refund' ) ) {
			return false;
		}
		$order = wc_get_order( $request['order_id'] );
		if ( ! $order ) {
			return false;
		}

		// Guard anti doppio rimborso per la stessa richiesta.
		$marker = '_ddr_refunded_' . (int) $request['id'];
		if ( 'yes' === $order->get_meta( $marker ) ) {
			return false;
		}

		$items = isset( $request['items_data'] ) ? $request['items_data'] : array();
		if ( empty( $items ) || ! is_array( $items ) ) {
			return false;
		}

		$line_items = array();
		$amount     = 0.0;

		foreach ( $items as $it ) {
			$item_id = isset( $it['line_item_id'] ) ? (int) $it['line_item_id'] : 0;
			$item    = $item_id ? $order->get_item( $item_id ) : null;
			if ( ! $item ) {
				continue;
			}
			$purchased = (int) $item->get_quantity();
			$refund_qty = min( (int) $it['qty'], $purchased );
			if ( $refund_qty < 1 || $purchased < 1 ) {
				continue;
			}
			$prop = $refund_qty / $purchased;

			$refund_total = (float) $item->get_total() * $prop;

			$refund_tax = array();
			$item_taxes = $item->get_taxes();
			if ( ! empty( $item_taxes['total'] ) ) {
				foreach ( $item_taxes['total'] as $tax_id => $tax_amount ) {
					$t = (float) $tax_amount * $prop;
					$refund_tax[ $tax_id ] = wc_format_decimal( $t, '' );
					$amount += $t;
				}
			}

			$line_items[ $item_id ] = array(
				'qty'          => $refund_qty,
				'refund_total' => wc_format_decimal( $refund_total, '' ),
				'refund_tax'   => $refund_tax,
			);
			$amount += $refund_total;
		}

		if ( empty( $line_items ) || $amount <= 0 ) {
			return false;
		}

		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => wc_format_decimal( $amount, '' ),
				'reason'         => sprintf(
					/* translators: %s codice ricevuta */
					__( 'Recesso art. 54-bis – ricevuta %s', 'diritto-di-recesso' ),
					$request['receipt_code']
				),
				'line_items'     => $line_items,
				'restock_items'  => true,
			)
		);

		if ( is_wp_error( $refund ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s messaggio errore */
					__( 'Recesso: rimborso automatico non riuscito (%s).', 'diritto-di-recesso' ),
					$refund->get_error_message()
				)
			);
			return false;
		}

		$order->update_meta_data( $marker, 'yes' );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: 1: importo 2: codice ricevuta */
				__( 'Recesso: rimborso di %1$s registrato con ripristino stock (ricevuta %2$s). Verificare l’eventuale rimborso tramite il gateway di pagamento.', 'diritto-di-recesso' ),
				wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
				$request['receipt_code']
			)
		);

		do_action( 'ddr_refund_created', $request, $refund, $order );

		return true;
	}
}
