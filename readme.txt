=== Diritto di Recesso 54-bis ===
Contributors: artwork
Tags: woocommerce, recesso, diritto di recesso, codice del consumo, 54-bis
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recesso digitale conforme all'art. 54-bis del Codice del Consumo (D.Lgs. 209/2025) per WooCommerce. Obbligo applicabile ai contratti conclusi online dal 19 giugno 2026.

== Description ==

Diritto di Recesso 54-bis aggiunge al tuo store WooCommerce un punto d'accesso unico per esercitare online il diritto di recesso, come previsto dal nuovo art. 54-bis del Codice del Consumo (D.Lgs. 209/2025), in vigore per i contratti conclusi online dal 19 giugno 2026.

La funzione e' utilizzabile anche dagli ospiti senza account: l'utente inserisce numero d'ordine ed email di fatturazione, conferma la titolarita', invia la dichiarazione di recesso e riceve l'avviso di ricevimento su supporto durevole con data e ora.

= Cosa fa =
* Pagina pubblica unica /recesso (shortcode [diritto_recesso]) valida anche per ospiti senza account.
* Recesso PARZIALE: il cliente sceglie prodotti e quantita' da restituire; piu' richieste possibili nel tempo finche' restano quantita' recedibili.
* Link "Recedere dal contratto qui" nel footer (etichetta letterale c.3) + pulsante nell'area "I miei ordini" + tab dedicato "Diritto di recesso" nell'area account.
* Verifica titolarita': numero ordine + email di fatturazione. Per gli ospiti, link di conferma una tantum via email (gating anti-abuso).
* Doppia conferma: dichiarazione (c.2) -> "Conferma recesso" (c.5).
* Avviso di ricevimento su supporto durevole (email) con contenuto della dichiarazione, prodotti, data e ora (c.6) + ricevuta stampabile (stampa/salva PDF dal browser).
* Notifica all'amministratore.
* Audit trail in tabella dedicata + nota sull'ordine; gestione stato richiesta (ricevuta/in lavorazione/completata/annullata) ed export CSV dall'admin.
* Controllo finestra 14 giorni, stati ordine, esclusioni art. 59 (flag per prodotto / per categoria).
* IP del cliente rilevabile anche dietro proxy/CDN (Cloudflare / X-Forwarded-For), opzionale. Cleanup dati opzionale alla disinstallazione.

== Installation ==
1. Carica la cartella in /wp-content/plugins/ (o installa lo zip).
2. Attiva il plugin: viene creata la pagina /recesso e la tabella audit.
3. WooCommerce > Diritto di Recesso > Impostazioni per i parametri.

== Frequently Asked Questions ==

= Il plugin crea il diritto di recesso? =
No. E' uno strumento tecnico: rende esercitabile online il recesso dove gia' esiste. Non sostituisce un parere legale. Vanno adeguate separatamente le condizioni generali di vendita e l'informativa precontrattuale (art. 49 c.1 lett. h).

= Funziona senza account cliente? =
Si. La verifica avviene tramite numero ordine + email di fatturazione; per gli ospiti si aggiunge un link di conferma via email.

== Filtri per gli sviluppatori ==
* ddr_withdrawal_days( $days, $order )
* ddr_eligible_statuses( $statuses )
* ddr_order_start_timestamp( $ts, $order )   // es. data di consegna reale
* ddr_order_all_excluded( $bool, $order )
* ddr_order_eligible( $override, $order )     // false = blocca
* ddr_resolve_order( $order, $value )         // numerazione personalizzata
* ddr_withdrawal_registered( $request, $order ) // azione post-registrazione
* ddr_after_receipt_sent( $request, $ok )

== Note legali ==
Strumento tecnico, non sostituisce un parere legale. Adeguare separatamente condizioni generali di vendita e informativa precontrattuale (art. 49 c.1 lett. h). La funzione non crea il diritto di recesso: lo rende esercitabile online dove gia' esiste.

== Changelog ==
= 1.1.0 =
* Testo del pulsante di recesso personalizzabile dalle impostazioni.
* Nuova sezione "Testi legali" con editor WYSIWYG e testi predefiniti (clausola di recesso, modulo tipo, esclusioni art. 59) compilati con i dati del negozio; riutilizzabili via shortcode [ddr_recesso_condizioni], [ddr_recesso_modulo], [ddr_recesso_esclusioni].

= 1.0.0 =
* Prima release pubblica a marchio Artwork (https://artworkstudios.it).
