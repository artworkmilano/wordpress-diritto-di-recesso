# Diritto di Recesso 54-bis — by Artwork

Plugin **WooCommerce** gratuito per il **recesso digitale** conforme all'**art. 54-bis del Codice del Consumo** (D.Lgs. 209/2025), obbligo applicabile ai contratti conclusi online dal **19 giugno 2026**.

Realizzato e mantenuto da **[Artwork](https://artworkstudios.it)**.

> ⚖️ **Nota legale:** è uno strumento tecnico, **non** sostituisce un parere legale. Rende esercitabile online il recesso dove già esiste; non lo crea. Vanno adeguate separatamente le condizioni generali di vendita e l'informativa precontrattuale (art. 49 c.1 lett. h).

## Funzionalità

- **Punto d'accesso unico**: pagina pubblica `/recesso` (shortcode `[diritto_recesso]`), link nel footer, pulsante in "I miei ordini" e tab dedicato nell'area account.
- **Recesso parziale**: selezione di prodotti e quantità; più richieste possibili nel tempo finché restano quantità recedibili.
- **Valido anche per ospiti** senza account (verifica via numero ordine + email, con link di conferma una tantum).
- **Doppia conferma** (dichiarazione → conferma) e **avviso di ricevimento su supporto durevole** via email, con **ricevuta stampabile** (stampa/salva PDF dal browser).
- **Audit trail** in tabella dedicata, gestione **stati richiesta** ed **export CSV** dal backend.
- Controllo finestra 14 giorni, stati ordine, **esclusioni art. 59** (per prodotto/categoria), IP del cliente anche dietro proxy/CDN.
- **Auto-aggiornamento integrato** dalle release GitHub: nessun plugin esterno richiesto.

## Requisiti

- WordPress ≥ 6.0, PHP ≥ 7.4, WooCommerce ≥ 7.0.

## Installazione

1. Scarica lo **zip** dall'ultima [release](https://github.com/artworkmilano/wordpress-diritto-di-recesso/releases).
2. WordPress → Plugin → Aggiungi nuovo → Carica plugin → seleziona lo zip → Attiva.
3. All'attivazione vengono creati la pagina `/recesso` e la tabella audit. Configura i parametri in **WooCommerce → Diritto di Recesso → Impostazioni**.

## Aggiornamenti

Il plugin si aggiorna **da solo**: controlla l'ultima release di questo repo e mostra "aggiornamento disponibile" nel backend WordPress, come qualsiasi plugin. Nessun tool aggiuntivo.

## Sviluppo

Filtri/azioni disponibili in [`readme.txt`](readme.txt). Contributi e segnalazioni via [issue](https://github.com/artworkmilano/wordpress-diritto-di-recesso/issues).

## Licenza

[GPL-2.0-or-later](LICENSE) — © Artwork, [artworkstudios.it](https://artworkstudios.it)
