=== Diritto di Recesso 54-bis ===
Contributors: artwork
Tags: woocommerce, recesso, diritto di recesso, codice del consumo, 54-bis
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recesso digitale conforme all'art. 54-bis del Codice del Consumo (D.Lgs. 209/2025) per WooCommerce. Obbligo applicabile ai contratti conclusi online dal 19 giugno 2026.

== Description ==

Diritto di Recesso 54-bis aggiunge al tuo store WooCommerce un punto d'accesso unico per esercitare online il diritto di recesso, come previsto dal nuovo art. 54-bis del Codice del Consumo (D.Lgs. 209/2025), in vigore per i contratti conclusi online dal 19 giugno 2026.

La funzione e' utilizzabile anche dagli ospiti senza account: l'utente inserisce numero d'ordine ed email di fatturazione, conferma la titolarita', invia la dichiarazione di recesso e riceve l'avviso di ricevimento su supporto durevole con data e ora.

= Cosa fa =
* Multilingua: italiano, inglese, francese, spagnolo, tedesco.
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
= 1.8.0 =
* Plugin multilingua: traduzioni complete in inglese, francese, spagnolo e tedesco (oltre all'italiano).
* Fix: corretti due titoli di sezione nell'admin che mostravano '&amp;'.

= 1.7.3 =
* I pulsanti ereditano il font del tema; hover con sola opacità (testo leggibile anche su fondo scuro).
* Sicurezza: neutralizzata la CSV formula injection nell'export; header anti-clickjacking sul frame della modale.

= 1.7.2 =
* Nuova spunta per mostrare/nascondere la voce "Diritto di recesso" nel menu dell'area account.

= 1.7.1 =
* Fix layout: il pulsante di recesso nella tabella "I miei ordini" ora e' un blocco contenuto nella cella (niente overflow su nessun tema).
* Nuovo CTA nella pagina di dettaglio ordine (larghezza piena, theme-safe).
* Nuova spunta per attivare/disattivare il pulsante nella lista ordini.

= 1.7.0 =
* Raggio dei bordi globale e attiva/disattiva ombra dalle impostazioni.
* Pannello impostazioni riorganizzato in sezioni (Recesso, Punto di accesso, Aspetto, Ricevuta & PDF, Notifiche & rimborsi, Avanzate).
* Fix: immagini prodotto visibili anche nella modale; pulsante di chiusura senza sfondo; titolo che eredita il font dei titoli del tema.

= 1.6.0 =
* Apertura del flusso di recesso in finestra modale (overlay) opzionale, con colore overlay personalizzabile. Il flusso gira in un frame senza tema, pulito.

= 1.5.0 =
* Schermata di selezione prodotti in stile shop: miniatura, nome e prezzo per ogni prodotto, con stepper di quantita' (-/+).

= 1.4.2 =
* Dall'area account, il pulsante "Recedi" porta direttamente alla selezione dei prodotti (salta il form per i clienti loggati).
* Lista ordini e richieste: etichetta "Ordine #..." piu' chiara.

= 1.4.1 =
* Logo PDF: supporto SVG con conversione automatica (Imagick) quando il server lo consente; altrimenti avviso e fallback. Risoluzione consigliata mostrata nell'admin.

= 1.4.0 =
* Ricevuta in PDF (generatore interno, nessuna libreria di terzi) con logo: pulsante "Scarica PDF" e allegato all'email di avviso ricevimento.
* Logo del PDF: caricabile dalle impostazioni, altrimenti logo email WooCommerce, logo del sito o nome del negozio.

= 1.3.1 =
* Icona del CTA senza cerchietto di sfondo e colorata (segue accento/testo); toggle per mostrarla o nasconderla.
* Stile del link nel footer selezionabile: link testuale (default, discreto), pulsante o badge.

= 1.3.0 =
* Colori personalizzabili: accento, sfondo e testo del pulsante.
* Shortcode [diritto_recesso_link] (stili pill/button/link) per inserire il pulsante ovunque.
* Opzione per agganciare la voce di recesso a una posizione di menu del tema.

= 1.2.0 =
* Email automatica al cliente al cambio di stato della richiesta (in lavorazione/completata/annullata).
* Rimborso WooCommerce opzionale con ripristino stock quando la richiesta diventa "completata" (off di default; non movimenta denaro via gateway).

= 1.1.2 =
* Link rapidi "Impostazioni" e "Testi legali" nella riga del plugin.

= 1.1.1 =
* Pannello "Aggiornamenti" nelle impostazioni: mostra versione installata vs ultima su GitHub e pulsante "Controlla aggiornamenti adesso".

= 1.1.0 =
* Testo del pulsante di recesso personalizzabile dalle impostazioni.
* Nuova sezione "Testi legali" con editor WYSIWYG e testi predefiniti (clausola di recesso, modulo tipo, esclusioni art. 59) compilati con i dati del negozio; riutilizzabili via shortcode [ddr_recesso_condizioni], [ddr_recesso_modulo], [ddr_recesso_esclusioni].

= 1.0.0 =
* Prima release pubblica a marchio Artwork (https://artworkstudios.it).
