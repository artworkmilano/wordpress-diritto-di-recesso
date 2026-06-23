=== Diritto di Recesso per WooCommerce ===
Contributors: artwork
Tags: woocommerce, recesso, diritto di recesso, codice del consumo, 54-bis
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
Stable tag: 1.9.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pulsante di recesso online per WooCommerce conforme all'art. 54-bis del Codice del Consumo (Dir. 2011/83/UE). Recesso parziale e ricevuta durevole.

== Description ==

**Diritto di Recesso per WooCommerce** aggiunge al tuo negozio la funzione digitale di recesso prevista dall'**art. 54-bis del Codice del Consumo** (D.Lgs. 209/2025) e, piu' in generale, dal **diritto di recesso europeo** (Direttiva 2011/83/UE). Dal **19 giugno 2026** gli e-commerce che concludono contratti tramite interfaccia online devono offrire un modo semplice e sempre accessibile per recedere: questo plugin lo fornisce, gratis e pronto all'uso.

Il consumatore trova un **pulsante di recesso** ("Recedere dal contratto qui"), inserisce numero d'ordine ed email, **sceglie i prodotti e le quantita'** da restituire, conferma, e riceve un **avviso di ricevimento su supporto durevole** (email) con il contenuto della dichiarazione, data e ora. Funziona **anche per gli ospiti** senza account.

= Caratteristiche principali =
* **Pulsante di recesso sempre accessibile**: link nel footer, pulsante in "I miei ordini", tab dedicato nell'area account e CTA nel dettaglio ordine. Ogni punto d'accesso e' attivabile o disattivabile.
* **Recesso parziale**: il cliente seleziona singoli prodotti e quantita' (con miniatura e prezzo); piu' richieste nel tempo, finche' restano articoli recedibili.
* **Anche senza account (ospiti)**: verifica con numero ordine + email di fatturazione e link di conferma una tantum via email (anti-abuso).
* **Doppia conferma** (dichiarazione -> conferma), come previsto dalla norma.
* **Avviso di ricevimento su supporto durevole**: email automatica con dichiarazione, prodotti, data e ora, piu' **ricevuta in PDF** (con il tuo logo) e versione stampabile.
* **Gestione lato negozio**: registro (audit trail) delle richieste, stati (ricevuta / in lavorazione / completata / annullata), **export CSV**, nota automatica sull'ordine e notifica email all'amministratore.
* **Rimborso WooCommerce opzionale** con ripristino dello stock alla chiusura della richiesta.
* **Esclusioni art. 59** per prodotto o categoria (beni su misura, sigillati, deperibili...): il pulsante si nasconde quando l'ordine contiene solo prodotti esclusi.
* **Testi legali pronti** (clausola di recesso, modulo tipo, esclusioni) editabili con editor visuale e riutilizzabili via shortcode.
* **Aspetto personalizzabile**: colori, raggio dei bordi, icona, stile del link, apertura in **finestra modale**. Eredita lo stile del tema.
* **Multilingua**: italiano, inglese, francese, spagnolo, tedesco.
* **Compatibile HPOS** (tabelle ordini ad alte prestazioni di WooCommerce).

= Per chi e' =
Negozi **WooCommerce** che devono adeguarsi all'obbligo del pulsante di recesso (art. 54-bis) e, in generale, qualsiasi e-commerce dell'Unione Europea che voglia offrire una procedura di recesso online chiara e tracciabile.

= Conformita' =
Il plugin fornisce la **funzione tecnica**: non sostituisce la consulenza legale e non crea il diritto di recesso, lo rende **esercitabile online** dove gia' esiste. Si raccomanda la validazione del flusso da parte di un legale prima della messa in produzione (vedi sezione Disclaimer).

Realizzato da [Artwork Web Agency](https://artworkstudios.it), agenzia specializzata nella realizzazione di siti e-commerce e WooCommerce.

== Installation ==

1. Dal backend vai su **Plugin > Aggiungi nuovo**, cerca "Diritto di Recesso per WooCommerce" e installa; oppure carica lo zip da **Plugin > Aggiungi nuovo > Carica plugin**.
2. **Attiva** il plugin: vengono creati automaticamente la pagina pubblica /recesso (shortcode `[diritto_recesso]`) e la tabella del registro richieste.
3. Configura i parametri in **WooCommerce > Diritto di Recesso > Impostazioni** (giorni di recesso, punti d'accesso, aspetto, PDF, notifiche, rimborso).
4. Apri **Testi legali** per personalizzare clausola, modulo tipo ed esclusioni (precompilati con i dati del tuo negozio).
5. Consigliato: collega la pagina /recesso nel menu del footer e richiama la clausola nelle Condizioni Generali di Vendita.

Requisiti: WordPress 6.0+, PHP 7.4+, WooCommerce 7.0+.

== Frequently Asked Questions ==

= Il plugin crea il diritto di recesso? =
No. E' uno strumento tecnico che rende **esercitabile online** il recesso dove gia' esiste per legge. Non sostituisce un parere legale; vanno adeguate separatamente le Condizioni Generali di Vendita e l'informativa precontrattuale (art. 49 c.1 lett. h).

= Da quando e' obbligatorio il pulsante di recesso? =
L'art. 54-bis del Codice del Consumo (D.Lgs. 209/2025) si applica ai contratti conclusi online a partire dal **19 giugno 2026**.

= Funziona anche senza account cliente (ospiti)? =
Si. La verifica avviene tramite numero ordine + email di fatturazione; per gli ospiti si aggiunge un link di conferma una tantum via email.

= Si puo' recedere solo da alcuni prodotti dell'ordine? =
Si. Il cliente sceglie i singoli prodotti e le quantita' (recesso parziale) e puo' inviare piu' richieste nel tempo, finche' restano articoli recedibili.

= Come viene fornita la conferma su "supporto durevole"? =
Con un'email automatica di avviso di ricevimento che riporta dichiarazione, prodotti, data e ora, piu' una ricevuta in PDF (con logo) e una versione stampabile.

= Posso escludere alcuni prodotti dal recesso (art. 59)? =
Si, per singolo prodotto o per categoria (es. beni su misura, sigillati, deperibili). Se l'ordine contiene solo prodotti esclusi, il pulsante non viene mostrato.

= Il plugin gestisce i rimborsi? =
Opzionalmente: alla chiusura della richiesta puo' creare un rimborso WooCommerce con ripristino dello stock. L'eventuale restituzione del denaro tramite gateway di pagamento resta a conferma manuale.

= In quante lingue e' disponibile? =
Italiano, inglese, francese, spagnolo e tedesco. Segue automaticamente la lingua del sito.

= Dove posso mostrare il pulsante di recesso? =
Nel footer del sito, nell'area "I miei ordini", nel dettaglio ordine, in un tab dedicato dell'area account, oppure ovunque tramite lo shortcode `[diritto_recesso_link]` o una voce di menu.

= E' davvero gratuito? =
Si: gratuito e open source (licenza GPL-2.0-or-later).

= E' compatibile con HPOS? =
Si, il plugin dichiara la compatibilita' con le tabelle ordini ad alte prestazioni (HPOS) di WooCommerce.

== Filtri per gli sviluppatori ==
* ddr_withdrawal_days( $days, $order )
* ddr_eligible_statuses( $statuses )
* ddr_order_start_timestamp( $ts, $order )   // es. data di consegna reale
* ddr_order_all_excluded( $bool, $order )
* ddr_order_eligible( $override, $order )     // false = blocca
* ddr_resolve_order( $order, $value )         // numerazione personalizzata
* ddr_withdrawal_registered( $request, $order ) // azione post-registrazione
* ddr_after_receipt_sent( $request, $ok )

== Disclaimer ==
Plugin **gratuito** e open source (GPL-2.0-or-later), fornito "cosi' com'e'" ("as is"), SENZA GARANZIE di alcun tipo, esplicite o implicite, incluse garanzie di commerciabilita' o idoneita' a uno scopo specifico.

Il plugin fornisce la funzione **tecnica** per WooCommerce. NON costituisce consulenza legale e NON garantisce la conformita' normativa del sito: si raccomanda la **validazione del flusso da parte di un legale prima della messa in produzione**. La responsabilita' dell'adempimento degli obblighi di legge (in particolare art. 54-bis e art. 49 c.1 lett. h del Codice del Consumo, Direttiva 2011/83/UE), dell'adeguamento dei testi e delle condizioni di vendita, e della corretta configurazione, resta **esclusivamente del titolare del sito**.

Nei limiti massimi consentiti dalla legge applicabile, l'autore [Artwork Web Agency](https://artworkstudios.it) e i contributori NON sono responsabili per danni diretti, indiretti, incidentali o consequenziali, sanzioni, perdite di dati o di profitto, ne' per qualsiasi conseguenza derivante dall'installazione, configurazione o uso del plugin. L'uso del plugin implica l'accettazione di queste condizioni.

I testi legali predefiniti sono un punto di partenza generico (basato sulla Direttiva 2011/83/UE) da adattare alla propria attivita' e giurisdizione; si raccomanda la revisione di un legale.

== Note legali ==
Strumento tecnico, non sostituisce un parere legale. Adeguare separatamente condizioni generali di vendita e informativa precontrattuale (art. 49 c.1 lett. h). La funzione non crea il diritto di recesso: lo rende esercitabile online dove gia' esiste.

== Changelog ==
= 1.9.3 =
* Disclaimer spostato in fondo alle impostazioni e arricchito (nota tecnica + raccomandazione di validazione legale prima della produzione).
* Le citazioni del marchio ora riportano «Artwork Web Agency» con link a artworkstudios.it; Author del plugin aggiornato.

= 1.9.2 =
* Fix: nella pagina "Richieste di recesso" veniva stampata una riga di codice (tag PHP errato). Risolto.

= 1.9.1 =
* Disclaimer/esclusione di responsabilità (gratuito, fornito «così com'è», non sostituisce consulenza legale) nell'header, nel readme e ben visibile nell'admin, tradotto nelle 5 lingue.
* Predisposta la build per WordPress.org (self-updater disattivato: gli aggiornamenti li gestisce WordPress.org).

= 1.9.0 =
* Rinominato "Diritto di Recesso per WooCommerce".
* Impostazioni riorganizzate a schede (tab).
* Colore di default del brand (viola) per accento e pulsante principale.

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
