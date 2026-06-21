<?php
defined( 'ABSPATH' ) || exit;

class BB_Email {

    // ── Invia fattura all'acquirente ──────────────────────────────────────────
    public static function invia_fattura( object $ordine ): string {
        if ( empty( $ordine->acquirente_email ) ) return 'errore_invio';

        $pdf = BB_PDF_Manager::genera( 'fattura', $ordine->id );

        $subject = sprintf(
            __( 'Ordine buono regalo %s – La Botega da la Lavizzara', 'botega-buoni' ),
            $ordine->ordine_ref
        );
        $body = self::parse( get_option( 'bb_email_fattura_corpo', self::default_fattura_body() ), $ordine );

        $attachments = [];
        if ( $pdf && file_exists( $pdf->percorso ) ) {
            $attachments[] = $pdf->percorso;
        }

        $ok = self::send( $ordine->acquirente_email, $subject, $body, $attachments );
        return $ok ? 'inviato' : 'errore_invio';
    }

    // ── Invia conferma pagamento all'acquirente ────────────────────────────────
    public static function invia_conferma_acquirente( object $ordine ): bool {
        if ( empty( $ordine->acquirente_email ) ) return false;

        $subject = sprintf(
            __( 'Conferma pagamento ordine %s – La Botega da la Lavizzara', 'botega-buoni' ),
            $ordine->ordine_ref
        );
        $body = self::parse( get_option( 'bb_email_conferma_corpo', self::default_conferma_body() ), $ordine );

        return self::send( $ordine->acquirente_email, $subject, $body );
    }

    // ── Invia buono al destinatario ───────────────────────────────────────────
    public static function invia_buono( object $ordine, object $buono ): bool {
        if ( empty( $buono->destinatario_email ) ) return false;

        $pdf = BB_PDF_Manager::genera( 'buono', $ordine->id, $buono->id );

        $subject = sprintf(
            __( 'Hai ricevuto un buono regalo da La Botega da la Lavizzara! 🎁', 'botega-buoni' )
        );
        $body = self::parse_buono(
            get_option( 'bb_email_buono_corpo', self::default_buono_body() ),
            $ordine, $buono
        );

        $attachments = [];
        if ( $pdf && file_exists( $pdf->percorso ) ) {
            $attachments[] = $pdf->percorso;
        }

        $ok = self::send( $buono->destinatario_email, $subject, $body, $attachments );

        BB_Database::update_buono( $buono->id, [
            'stato_invio' => $ok ? 'inviato' : 'errore',
            'data_invio'  => $ok ? current_time( 'mysql' ) : null,
        ] );

        return $ok;
    }

    // ── Invia tutti i buoni di un ordine ──────────────────────────────────────
    public static function invia_tutti_buoni( object $ordine ): array {
        $buoni  = BB_Database::get_buoni( $ordine->id );
        $result = [ 'inviati' => 0, 'errori' => 0 ];
        foreach ( $buoni as $buono ) {
            if ( $buono->stato_invio === 'inviato' ) continue;
            self::invia_buono( $ordine, $buono ) ? $result['inviati']++ : $result['errori']++;
        }
        return $result;
    }

    // ── Invia reminder all'amministratore ─────────────────────────────────────
    public static function invia_reminder_admin( object $ordine ): bool {
        $admin_email = get_option( 'bb_email_admin', get_option( 'admin_email', 'info@labotegalavizzara.ch' ) );
        $subject     = sprintf(
            __( '[Reminder] Pagamento in attesa ordine %s – La Botega', 'botega-buoni' ),
            $ordine->ordine_ref
        );
        $body = sprintf(
            __( "L'ordine %s del %s di %s (%s) per %s (metodo: Fattura) risulta ancora non pagato.\n\nBuoni: %d x CHF %.2f = %s\n\nGestisci l'ordine nel pannello amministrativo:\n%s",
                'botega-buoni' ),
            $ordine->ordine_ref,
            date_i18n( 'd.m.Y', strtotime( $ordine->data_creazione ) ),
            esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ),
            esc_html( $ordine->acquirente_email ),
            BB_Database::fmt_chf( (float) $ordine->importo_totale ),
            (int) $ordine->quantita,
            (float) $ordine->importo,
            BB_Database::fmt_chf( (float) $ordine->importo_totale ),
            admin_url( 'admin.php?page=botega-buoni&action=view&id=' . $ordine->id )
        );

        $ok = self::send( $admin_email, $subject, $body );

        if ( $ok ) {
            BB_Database::update_ordine( $ordine->id, [
                'reminder_count'       => (int) $ordine->reminder_count + 1,
                'data_ultimo_reminder' => current_time( 'mysql' ),
            ] );
        }
        return $ok;
    }

    // ── Invia richiamo pagamento all'acquirente ────────────────────────────────
    public static function invia_richiamo_acquirente( object $ordine ): bool {
        if ( empty( $ordine->acquirente_email ) ) return false;

        $subject = sprintf(
            __( 'Promemoria pagamento ordine %s – La Botega da la Lavizzara', 'botega-buoni' ),
            $ordine->ordine_ref
        );
        $body = self::parse(
            get_option( 'bb_email_richiamo_corpo', self::default_richiamo_body() ),
            $ordine
        );
        // Ri-allega la fattura se disponibile
        $pdfs        = BB_Database::get_pdfs_by_ordine( $ordine->id );
        $attachments = [];
        foreach ( $pdfs as $pdf ) {
            if ( $pdf->tipo === 'fattura' && file_exists( $pdf->percorso ) ) {
                $attachments[] = $pdf->percorso;
                break;
            }
        }
        return self::send( $ordine->acquirente_email, $subject, $body, $attachments );
    }

    // ── Internals ─────────────────────────────────────────────────────────────
    private static function send( string $to, string $subject, string $body, array $attachments = [] ): bool {
        $from_name  = get_option( 'bb_email_mittente_nome',  get_bloginfo( 'name' ) );
        $from_email = get_option( 'bb_email_mittente_email', get_option( 'admin_email' ) );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_name <$from_email>",
        ];
        $html_body = nl2br( $body );

        $use_smtp = get_option( 'bb_smtp_abilitato', '0' ) === '1';
        if ( $use_smtp ) {
            add_action( 'phpmailer_init', [ __CLASS__, 'configure_smtp' ] );
        }
        try {
            $result = wp_mail( $to, $subject, $html_body, $headers, $attachments );
        } catch ( \Throwable $e ) {
            error_log( '[BB Email] ' . $e->getMessage() );
            $result = false;
        }
        if ( $use_smtp ) {
            remove_action( 'phpmailer_init', [ __CLASS__, 'configure_smtp' ] );
        }
        return (bool) $result;
    }

    public static function configure_smtp( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
        $phpmailer->isSMTP();
        $phpmailer->Host       = get_option( 'bb_smtp_host', '' );
        $phpmailer->Port       = (int) get_option( 'bb_smtp_port', 587 );
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = get_option( 'bb_smtp_user', '' );
        $phpmailer->Password   = get_option( 'bb_smtp_pass', '' );
        $phpmailer->SMTPSecure = get_option( 'bb_smtp_secure', 'tls' );
        $phpmailer->SMTPDebug  = 0;
    }

    // ── Placeholder parser ────────────────────────────────────────────────────
    private static function parse( string $tpl, object $ordine ): string {
        $map = [
            '{{nome}}'      => esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ),
            '{{email}}'     => esc_html( $ordine->acquirente_email ),
            '{{ref}}'       => esc_html( $ordine->ordine_ref ),
            '{{importo}}'   => BB_Database::fmt_chf( (float) $ordine->importo ),
            '{{totale}}'    => BB_Database::fmt_chf( (float) $ordine->importo_totale ),
            '{{quantita}}'  => (string) (int) $ordine->quantita,
            '{{metodo}}'    => BB_Database::get_metodi()[ $ordine->metodo_pagamento ] ?? $ordine->metodo_pagamento,
            '{{data}}'      => date_i18n( 'd.m.Y', strtotime( $ordine->data_creazione ) ),
            '{{iban}}'      => 'CH48 8080 8003 7010 4694 7',
            '{{admin_url}}' => admin_url( 'admin.php?page=botega-buoni' ),
        ];
        return str_replace( array_keys( $map ), array_values( $map ), $tpl );
    }

    private static function parse_buono( string $tpl, object $ordine, object $buono ): string {
        $tpl = self::parse( $tpl, $ordine );
        $map = [
            '{{dest_nome}}'    => esc_html( $buono->destinatario_nome ),
            '{{codice}}'       => esc_html( $buono->codice_buono ),
            '{{messaggio}}'    => nl2br( esc_html( $buono->messaggio ?? '' ) ),
            '{{data_consegna}}'=> $buono->data_consegna
                ? date_i18n( 'd.m.Y', strtotime( $buono->data_consegna ) )
                : '',
        ];
        return str_replace( array_keys( $map ), array_values( $map ), $tpl );
    }

    // ── Template predefiniti ──────────────────────────────────────────────────
    private static function default_fattura_body(): string {
        return "Gentile {{nome}},\n\nGrazie per aver acquistato un buono regalo de La Botega da la Lavizzara!\n\nRif. ordine: {{ref}}\nImporto: {{totale}}\n\nIn allegato trovi la fattura con le istruzioni di pagamento tramite bonifico bancario (IBAN: {{iban}}).\n\nIl/i buono/i verrà/ranno inviato/i non appena ricevuto il pagamento.\n\nCordiali saluti,\nLa Botega da la Lavizzara";
    }

    private static function default_conferma_body(): string {
        return "Gentile {{nome}},\n\nConfermiamo la ricezione del pagamento per l'ordine {{ref}} ({{totale}}).\n\nIl/i destinatario/i riceverà/ranno il buono regalo via email.\n\nGrazie mille!\nLa Botega da la Lavizzara";
    }

    private static function default_buono_body(): string {
        return "Caro/a {{dest_nome}},\n\nhai ricevuto un buono regalo de La Botega da la Lavizzara!\n\nTrovi il buono regalo in allegato a questa email.\nCodice buono: {{codice}}\n\n{{messaggio}}\n\nIl buono regalo non scade mai.\n\nCi vediamo a La Botega!\nLa Botega da la Lavizzara";
    }

    private static function default_richiamo_body(): string {
        return "Gentile {{nome}},\n\nti ricordiamo che il pagamento per l'ordine {{ref}} ({{totale}}) risulta ancora in attesa.\n\nPuoi effettuare il versamento tramite bonifico bancario:\nIBAN: {{iban}}\nCausale: {{ref}}\n\nPer informazioni: info@labotegalavizzara.ch\n\nCordiali saluti,\nLa Botega da la Lavizzara";
    }
}
