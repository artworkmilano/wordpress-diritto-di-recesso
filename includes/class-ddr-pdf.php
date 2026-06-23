<?php
/**
 * Generatore PDF interno (nessuna libreria di terzi).
 *
 * Produce un PDF A4 a pagina singola con la ricevuta del recesso: logo (JPEG
 * embedded) o testo, titolo e tabella dei dati. Usa il font standard Helvetica
 * (WinAnsi/CP1252), quindi nessun font da incorporare.
 *
 * Il "core" (build) e' indipendente da WordPress: riceve titolo, righe e un
 * eventuale percorso JPEG del logo, e ritorna i byte del PDF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_PDF {

	/* ===================== Integrazione WordPress ====================== */

	/**
	 * Genera i byte del PDF per una richiesta.
	 */
	public static function generate( $request ) {
		$dt = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $request['created_at'] );

		$rows = array(
			array( __( 'Codice ricevuta', 'diritto-di-recesso' ), $request['receipt_code'] ),
			array( __( 'Data e ora', 'diritto-di-recesso' ), $dt ),
			array( __( 'Ordine', 'diritto-di-recesso' ), '#' . $request['order_id'] ),
			array( __( 'Intestatario', 'diritto-di-recesso' ), $request['customer_name'] ),
			array( __( 'Email', 'diritto-di-recesso' ), $request['customer_email'] ),
			array( __( 'Prodotti', 'diritto-di-recesso' ), self::items_text( $request ) ),
			array( __( 'Stato', 'diritto-di-recesso' ), DDR_DB::status_label( $request['status'] ) ),
		);
		if ( ! empty( $request['reason'] ) ) {
			$rows[] = array( __( 'Motivazione', 'diritto-di-recesso' ), $request['reason'] );
		}

		$shop  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$title = __( 'Avviso di ricevimento del recesso', 'diritto-di-recesso' );
		$note  = __( 'Documento riepilogativo della dichiarazione di recesso ai sensi dell’art. 54-bis del Codice del Consumo. Costituisce avviso di ricevimento su supporto durevole.', 'diritto-di-recesso' );

		$logo = self::logo_jpeg();
		$pdf  = self::build( $shop, $title, $note, $rows, $logo );

		if ( $logo && file_exists( $logo ) ) {
			@unlink( $logo ); // phpcs:ignore -- temporaneo.
		}
		return $pdf;
	}

	/**
	 * Stream del PDF al browser (inline) e stop.
	 */
	public static function stream( $request ) {
		$pdf = self::generate( $request );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename=recesso-' . sanitize_file_name( $request['receipt_code'] ) . '.pdf' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binario PDF.
		exit;
	}

	/**
	 * Scrive il PDF in un file temporaneo (per allegarlo a un'email). Path o ''.
	 */
	public static function tmp_file( $request ) {
		$pdf  = self::generate( $request );
		$base = wp_tempnam( 'ddr-receipt' );
		$path = preg_replace( '/\.tmp$/', '', $base ) . '-' . sanitize_file_name( $request['receipt_code'] ) . '.pdf';
		if ( false === file_put_contents( $path, $pdf ) ) { // phpcs:ignore
			return '';
		}
		if ( $base !== $path && file_exists( $base ) ) {
			@unlink( $base ); // phpcs:ignore
		}
		return $path;
	}

	protected static function items_text( $request ) {
		$items = isset( $request['items_data'] ) ? $request['items_data'] : array();
		if ( empty( $items ) || ! is_array( $items ) ) {
			return __( 'Intero ordine', 'diritto-di-recesso' );
		}
		$parts = array();
		foreach ( $items as $it ) {
			$name    = isset( $it['name'] ) ? $it['name'] : '';
			$qty     = isset( $it['qty'] ) ? (int) $it['qty'] : 0;
			$parts[] = $qty > 1 ? sprintf( '%s x%d', $name, $qty ) : $name;
		}
		return implode( ', ', $parts );
	}

	/**
	 * Risolve il logo e lo converte in JPEG temporaneo. Cascata:
	 *   1) logo PDF caricato (opzione ddr_pdf_logo, attachment id)
	 *   2) logo email di WooCommerce
	 *   3) logo del sito (Custom Logo)
	 *
	 * @return string percorso JPEG temporaneo, oppure '' (fallback testo).
	 */
	protected static function logo_jpeg() {
		$src = '';

		$custom = (int) get_option( 'ddr_pdf_logo', 0 );
		if ( $custom ) {
			$src = get_attached_file( $custom );
		}

		if ( ! $src ) {
			$wc = get_option( 'woocommerce_email_header_image', '' );
			if ( $wc ) {
				$id = attachment_url_to_postid( $wc );
				if ( $id ) {
					$src = get_attached_file( $id );
				}
			}
		}

		if ( ! $src ) {
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				$src = get_attached_file( $logo_id );
			}
		}

		if ( ! $src || ! file_exists( $src ) ) {
			return '';
		}

		// Converte in JPEG (max 600x200, sfondo bianco) via l'editor immagini WP.
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) ) {
			return '';
		}
		$editor->resize( 600, 200, false );
		$tmp   = wp_tempnam( 'ddr-logo' );
		$saved = $editor->save( $tmp . '.jpg', 'image/jpeg' );
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore
		}
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return '';
		}
		return $saved['path'];
	}

	/* ========================= Core PDF (no WP) ======================== */

	/**
	 * Costruisce il PDF. Indipendente da WordPress.
	 *
	 * @param string $shop  nome esercente (fallback se manca il logo).
	 * @param string $title titolo del documento.
	 * @param string $note  nota legale sotto la tabella.
	 * @param array  $rows  righe [ [label, value], ... ].
	 * @param string $logo  percorso a un JPEG, oppure ''.
	 * @return string byte del PDF.
	 */
	public static function build( $shop, $title, $note, $rows, $logo = '' ) {
		$W = 595.28; // A4 in punti.
		$H = 841.89;
		$margin = 56;
		$y = $H - 56;

		$ops = '';

		// Header: logo (immagine) oppure nome negozio (testo).
		$img_obj = null;
		$img_meta = null;
		if ( $logo && file_exists( $logo ) ) {
			$info = @getimagesize( $logo );
			$data = @file_get_contents( $logo );
			if ( $info && $data ) {
				$iw = $info[0];
				$ih = $info[1];
				$disp_w = min( 170, $iw );
				$disp_h = $disp_w * ( $ih / max( 1, $iw ) );
				if ( $disp_h > 70 ) {
					$disp_h = 70;
					$disp_w = $disp_h * ( $iw / max( 1, $ih ) );
				}
				$img_y = $y - $disp_h;
				$ops  .= sprintf( "q %s 0 0 %s %s %s cm /Im1 Do Q\n", self::n( $disp_w ), self::n( $disp_h ), self::n( $margin ), self::n( $img_y ) );
				$y     = $img_y - 26;
				$channels = isset( $info['channels'] ) ? (int) $info['channels'] : 3;
				$img_meta = array( 'w' => $iw, 'h' => $ih, 'data' => $data, 'cs' => ( 1 === $channels ? 'DeviceGray' : ( 4 === $channels ? 'DeviceCMYK' : 'DeviceRGB' ) ), 'cmyk' => ( 4 === $channels ) );
			}
		}
		if ( null === $img_meta ) {
			$ops .= self::text_op( $margin, $y, 17, 'F2', $shop );
			$y   -= 28;
		}

		// Titolo + nota.
		$ops .= self::text_op( $margin, $y, 14, 'F2', $title );
		$y   -= 20;
		foreach ( self::wrap( $note, 92 ) as $line ) {
			$ops .= self::text_op( $margin, $y, 9, 'F1', $line );
			$y   -= 13;
		}
		$y -= 10;

		// Tabella label/valore.
		$label_x = $margin;
		$value_x = $margin + 150;
		foreach ( $rows as $row ) {
			$label = isset( $row[0] ) ? $row[0] : '';
			$value = isset( $row[1] ) ? $row[1] : '';
			$lines = self::wrap( $value, 60 );
			$ops  .= self::text_op( $label_x, $y, 10, 'F2', $label );
			foreach ( $lines as $i => $line ) {
				$ops .= self::text_op( $value_x, $y - ( $i * 13 ), 10, 'F1', $line );
			}
			$y -= max( 18, count( $lines ) * 13 + 5 );
		}

		// Footer.
		$ops .= self::text_op( $margin, 60, 8, 'F1', $shop . '  -  ' . gmdate( 'Y' ) );

		// ---- Assemblaggio oggetti PDF ----
		$objects   = array();
		$objects[] = "<< /Type /Catalog /Pages 2 0 R >>"; // 1
		$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 " . self::n( $W ) . ' ' . self::n( $H ) . "] >>"; // 2

		$xobject = $img_meta ? ' /XObject << /Im1 7 0 R >>' : '';
		$objects[] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >>" . $xobject . " >> /Contents 6 0 R >>"; // 3
		$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>"; // 4
		$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>"; // 5

		$content   = $ops;
		$objects[] = "<< /Length " . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream"; // 6

		if ( $img_meta ) {
			$decode = $img_meta['cmyk'] ? ' /Decode [1 0 1 0 1 0 1 0]' : '';
			$objects[] = "<< /Type /XObject /Subtype /Image /Width " . $img_meta['w'] . " /Height " . $img_meta['h'] . " /ColorSpace /" . $img_meta['cs'] . " /BitsPerComponent 8 /Filter /DCTDecode" . $decode . " /Length " . strlen( $img_meta['data'] ) . " >>\nstream\n" . $img_meta['data'] . "\nendstream"; // 7
		}

		$pdf      = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets  = array();
		$n        = count( $objects );
		for ( $i = 1; $i <= $n; $i++ ) {
			$offsets[ $i ] = strlen( $pdf );
			$pdf          .= $i . " 0 obj\n" . $objects[ $i - 1 ] . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( $n + 1 ) . "\n0000000000 65535 f \n";
		for ( $i = 1; $i <= $n; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . ( $n + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

		return $pdf;
	}

	/* --------------------------- helper PDF ---------------------------- */

	/** Operatore di testo posizionato (matrice assoluta). */
	protected static function text_op( $x, $y, $size, $font, $str ) {
		return sprintf( "BT /%s %d Tf 1 0 0 1 %s %s Tm (%s) Tj ET\n", $font, $size, self::n( $x ), self::n( $y ), self::esc( self::enc( $str ) ) );
	}

	/** Numero PDF con punto decimale e 2 cifre. */
	protected static function n( $v ) {
		return rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );
	}

	/** UTF-8 -> CP1252 (WinAnsi) per i font standard. */
	protected static function enc( $s ) {
		$s = (string) $s;
		if ( function_exists( 'iconv' ) ) {
			$r = @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', $s );
			if ( false !== $r ) {
				return $r;
			}
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $s, 'Windows-1252', 'UTF-8' );
		}
		return $s;
	}

	/** Escape dei caratteri speciali di una stringa PDF. */
	protected static function esc( $s ) {
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', ' ' ), $s );
	}

	/** Word wrap grezzo per colonna (numero massimo di caratteri). */
	protected static function wrap( $text, $max ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array( '' );
		}
		$words = preg_split( '/\s+/', $text );
		$lines = array();
		$cur   = '';
		foreach ( $words as $w ) {
			$try = '' === $cur ? $w : $cur . ' ' . $w;
			if ( strlen( $try ) > $max && '' !== $cur ) {
				$lines[] = $cur;
				$cur     = $w;
			} else {
				$cur = $try;
			}
		}
		if ( '' !== $cur ) {
			$lines[] = $cur;
		}
		return $lines;
	}
}
