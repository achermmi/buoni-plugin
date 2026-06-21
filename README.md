# buoni-plugin

Plugin WordPress per la gestione buoni di **Botega da la Lavizzara**.

## Funzionalità

- Nuovo tipo contenuto **Buoni** nel pannello amministrativo.
- Campi dedicati: codice, valore, destinatario e stato riscattato.
- Shortcode `[bdlv_buoni]` per mostrare i buoni disponibili (non riscattati).
  - Opzionale: `limit` (es. `[bdlv_buoni limit=20]`).

## Installazione

1. Copia la cartella del progetto in `wp-content/plugins/buoni-plugin`.
2. Attiva il plugin da **Plugin** in WordPress.
3. Gestisci i buoni dal menu **Buoni**.

La visualizzazione del valore usa la lingua/locale configurata in WordPress.
