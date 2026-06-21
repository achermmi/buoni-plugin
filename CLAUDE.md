# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descrizione

Plugin WordPress per la vendita online di buoni regalo de **La Botega da la Lavizzara**. Gestisce importi fissi (CHF 25/50/100/150), destinatari multipli, consegna digitale programmata, tre metodi di pagamento (Fattura/Stripe/PayPal) e notifiche email con PDF allegato.

- Richiede WordPress 6.0+ e PHP 8.1+
- Dipendenza Composer: `mpdf/mpdf ^8.2` (generazione PDF)
- Prefisso classi: `BB_`, prefisso opzioni/hook/nonce: `bb_`

## Comandi

```bash
# Installare le dipendenze PHP (mPDF)
composer install

# Aggiornare le dipendenze
composer update
```

Non esiste build step, linter configurato né suite di test automatizzati. Il testing avviene direttamente su un'installazione WordPress.

## Architettura

### Entry point

`botega-buoni.php` — carica tutte le classi con `require_once`, registra i hook di attivazione/disattivazione e avvia il plugin via `plugins_loaded`.

### Flusso principale acquisto

1. **`BB_Pages`** crea automaticamente la pagina pubblica `carta-regalo` con lo shortcode `[bb_buoni_regalo]`.
2. **`BB_Public`** gestisce il form pubblico: validazione POST → `BB_Database::insert_ordine()` + `BB_Database::insert_buono()` → `BB_Payment::process()`.
3. **`BB_Payment`** esegue il pagamento:
   - *Fattura*: invia email con PDF fattura, stato `inviato`.
   - *Stripe*: crea Checkout Session, redirect a Stripe; al ritorno o via webhook (`/wp-json/bb/v1/stripe-webhook`) chiama `complete_order()`.
   - *PayPal*: crea ordine PayPal, redirect; al ritorno cattura il pagamento e chiama `complete_order()`.
4. **`BB_Payment::complete_order()`** aggiorna stato a `pagato` → `BB_Email::invia_conferma_acquirente()` + `BB_Email::invia_tutti_buoni()`.
5. **`BB_Email::invia_buono()`** genera il PDF via `BB_PDF_Manager::genera('buono', ...)` e lo allega all'email del destinatario.

### Classi principali

| Classe | File | Responsabilità |
|---|---|---|
| `BB_Database` | `includes/class-bb-database.php` | Tutte le query DB; metodi statici puri. Contiene anche `get_importi()`, `get_stati()`, `get_metodi()`, `fmt_chf()`. |
| `BB_PDF_Manager` | `includes/class-bb-pdf-manager.php` | Genera PDF (mPDF primario, dompdf fallback). I file vengono salvati in `wp-content/uploads/botega-buoni/` protetti da `.htaccess`. |
| `BB_Email` | `includes/class-bb-email.php` | Invio email tramite `wp_mail()` con supporto SMTP opzionale. Template configurabili via opzioni WP con placeholder `{{nome}}`, `{{ref}}`, `{{totale}}`, ecc. |
| `BB_Payment` | `includes/class-bb-payment.php` | Integrazione Fattura, Stripe Checkout, PayPal Orders v2. Webhook REST su `bb/v1/stripe-webhook` e `bb/v1/paypal-webhook`. |
| `BB_Public` | `includes/class-bb-public.php` | Shortcode `[bb_buoni_regalo]` per il form pubblico. |
| `BB_Frontend_Admin` | `includes/class-bb-frontend-admin.php` | Shortcode `[bb_admin_buoni]` — pannello gestione ordini nel frontend, accessibile agli utenti con capability `bb_gestione_buoni`. |
| `BB_Admin` | `admin/class-bb-admin.php` | Pannello WP-Admin: lista ordini, dettaglio, azioni AJAX. |
| `BB_Settings` | `admin/class-bb-settings.php` | Salvataggio opzioni plugin (email, SMTP, gateway, metodi abilitati). |
| `BB_Roles` | `includes/class-bb-roles.php` | Gestisce i ruoli personalizzati e la capability `bb_gestione_buoni`. |
| `BB_Scheduler` | `includes/class-bb-scheduler.php` | WP-Cron giornaliero `bb_check_unpaid_orders` per reminder ordini fattura non pagati dopo 30 giorni. |
| `BB_Pages` | `includes/class-bb-pages.php` | Crea/recupera la pagina pubblica `carta-regalo`. |

### Database

Tre tabelle custom create da `BB_Database::install()` all'attivazione:

- **`{prefix}bb_ordini`** — dati acquirente, importo, metodo/stato pagamento, contatore reminder.
- **`{prefix}bb_buoni`** — uno o più buoni per ordine, con destinatario, data consegna, stato invio (`da_inviare`, `inviato`, `errore`).
- **`{prefix}bb_pdf`** — riferimenti ai file PDF generati (percorso, URL, dimensione).

La cancellazione ordine (`BB_Database::delete_ordine()`) elimina in cascata buoni, record PDF e i file fisici.

### Templates

In `templates/` sono presenti le viste PHP incluse tramite `include BB_PLUGIN_DIR . 'templates/...'`:

- `public-form.php` — form pubblico acquisto buoni
- `pdf-buono.php` / `pdf-fattura.php` — HTML renderizzato in PDF da mPDF
- `page-settings.php` — pagina impostazioni admin
- `page-view.php` — dettaglio ordine in WP-Admin
- `frontend-admin-list.php` / `frontend-admin-view.php` — pannello frontend per i ruoli interni
- `page-roles.php` — gestione ruoli in WP-Admin

### Ruoli e accessi

La capability `bb_gestione_buoni` controlla l'accesso al plugin. I ruoli personalizzati con questa capability sono:

- `bb_segretariato` — Segretariato
- `bb_comitato` — Comitato
- `bb_cassiere` — Cassiere

Gli Amministratori WordPress ricevono automaticamente la capability. L'eliminazione ordini è riservata ai soli `manage_options`.

### Opzioni WordPress rilevanti

Le impostazioni sono salvate con `get_option()`/`update_option()`. Le chiavi principali:

- `bb_stripe_secret_key`, `bb_stripe_pub_key`, `bb_stripe_webhook_secret`, `bb_stripe_twint`
- `bb_paypal_client_id`, `bb_paypal_secret`, `bb_paypal_sandbox`
- `bb_metodo_fattura`, `bb_metodo_stripe`, `bb_metodo_paypal` — abilitano/disabilitano i metodi
- `bb_smtp_*` — configurazione SMTP opzionale
- `bb_email_*_corpo` — corpo email configurabile con placeholder
- `bb_pagina_buoni_id` — ID della pagina pubblica creata automaticamente
