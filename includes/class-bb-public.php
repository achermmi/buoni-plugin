<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_Public
 * Shortcode [bb_buoni_regalo] – form acquisto buoni regalo.
 */
class BB_Public {

    public static function init(): void {
        add_shortcode( 'bb_buoni_regalo', [ __CLASS__, 'shortcode' ] );
        add_action( 'init',               [ __CLASS__, 'handle_post' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ── Assets ────────────────────────────────────────────────────────────────
    public static function enqueue_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'bb_buoni_regalo' ) ) return;

        wp_enqueue_style( 'bb-public', BB_PLUGIN_URL . 'assets/css/public.css', [], BB_VERSION );

        wp_enqueue_script( 'bb-public', BB_PLUGIN_URL . 'assets/js/public.js', [ 'jquery' ], BB_VERSION, true );

        // Metodi di pagamento abilitati
        $metodi_enabled = [];
        foreach ( [ 'fattura', 'stripe', 'paypal' ] as $m ) {
            if ( get_option( 'bb_metodo_' . $m, '1' ) === '1' ) {
                $metodi_enabled[] = $m;
            }
        }

        wp_localize_script( 'bb-public', 'BB_Public', [
            'nonce'           => wp_create_nonce( 'bb_public_form' ),
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'metodi'          => $metodi_enabled,
            'importi'         => array_keys( BB_Database::get_importi() ),
            'stripe_pub_key'  => get_option( 'bb_stripe_pub_key', '' ),
            'paypal_client_id'=> get_option( 'bb_paypal_client_id', '' ),
            'paypal_sandbox'  => get_option( 'bb_paypal_sandbox', '1' ),
            'i18n' => [
                'email_invalid'  => __( 'Inserire un indirizzo e-mail valido.', 'botega-buoni' ),
                'campo_required' => __( 'Campo obbligatorio.', 'botega-buoni' ),
                'sending'        => __( 'Elaborazione in corso…', 'botega-buoni' ),
                'qty_min'        => __( 'La quantità minima è 1.', 'botega-buoni' ),
                'qty_max'        => __( 'La quantità massima è 10.', 'botega-buoni' ),
            ],
        ] );
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────
    public static function shortcode( array $atts = [] ): string {
        // Messaggio di successo
        $success    = ! empty( $_GET['bb_ok'] );
        $cancelled  = ! empty( $_GET['bb_cancelled'] );
        $bb_metodo  = sanitize_key( $_GET['bb_metodo'] ?? '' );
        $bb_ref     = sanitize_text_field( rawurldecode( $_GET['bb_ref'] ?? '' ) );
        $error      = sanitize_text_field( rawurldecode( $_GET['bb_errore'] ?? '' ) );

        // Dati pre-fill acquirente (dopo pagamento, per ripartire con i propri dati)
        $prefill = [];
        if ( ! empty( $_GET['bb_prefill'] ) ) {
            $key = sanitize_key( $_GET['bb_prefill'] );
            $prefill = get_transient( 'bb_prefill_' . $key ) ?: [];
            delete_transient( 'bb_prefill_' . $key );
        }

        ob_start();
        include BB_PLUGIN_DIR . 'templates/public-form.php';
        return ob_get_clean();
    }

    // ── Handler POST ──────────────────────────────────────────────────────────
    public static function handle_post(): void {
        if ( empty( $_POST['bb_action'] ) || $_POST['bb_action'] !== 'acquista_buono' ) return;

        if ( ! wp_verify_nonce( $_POST['bb_nonce'] ?? '', 'bb_public_form' ) ) {
            self::redirect_error( __( 'Sessione scaduta. Ricaricare la pagina e riprovare.', 'botega-buoni' ) );
            return;
        }

        // ── Validazione acquirente ──────────────────────────────────────────
        $errors = [];

        $acq_nome     = sanitize_text_field( $_POST['acq_nome']     ?? '' );
        $acq_cognome  = sanitize_text_field( $_POST['acq_cognome']  ?? '' );
        $acq_indirizzo= sanitize_text_field( $_POST['acq_indirizzo']?? '' );
        $acq_cap      = sanitize_text_field( $_POST['acq_cap']      ?? '' );
        $acq_localita = sanitize_text_field( $_POST['acq_localita'] ?? '' );
        $acq_email    = sanitize_email( $_POST['acq_email']         ?? '' );
        $acq_telefono = sanitize_text_field( $_POST['acq_telefono'] ?? '' );

        if ( empty( $acq_nome ) )      $errors[] = __( 'Il nome è obbligatorio.',      'botega-buoni' );
        if ( empty( $acq_cognome ) )   $errors[] = __( 'Il cognome è obbligatorio.',   'botega-buoni' );
        if ( empty( $acq_indirizzo ) ) $errors[] = __( 'L\'indirizzo è obbligatorio.', 'botega-buoni' );
        if ( empty( $acq_cap ) )       $errors[] = __( 'Il CAP è obbligatorio.',       'botega-buoni' );
        if ( empty( $acq_localita ) )  $errors[] = __( 'La località è obbligatoria.',  'botega-buoni' );
        if ( empty( $acq_email ) )     $errors[] = __( 'L\'e-mail è obbligatoria.',    'botega-buoni' );
        elseif ( ! is_email( $acq_email ) ) $errors[] = __( 'E-mail non valida.', 'botega-buoni' );
        if ( empty( $acq_telefono ) )  $errors[] = __( 'Il telefono è obbligatorio.',  'botega-buoni' );

        // ── Importo e quantità ──────────────────────────────────────────────
        $importi_validi = array_keys( BB_Database::get_importi() );
        $importo  = (int) ( $_POST['importo'] ?? 25 );
        if ( ! in_array( $importo, $importi_validi, true ) ) {
            $importo = 25;
        }
        $quantita = max( 1, min( 10, (int) ( $_POST['quantita'] ?? 1 ) ) );
        $importo_totale = $importo * $quantita;

        // ── Per me / per altri ──────────────────────────────────────────────
        $per_me = ( ( $_POST['per_chi'] ?? 'altri' ) === 'me' ) ? 1 : 0;

        // ── Metodo pagamento ────────────────────────────────────────────────
        $metodo = sanitize_key( $_POST['metodo_pagamento'] ?? 'fattura' );
        $metodi_abilitati = [];
        foreach ( [ 'fattura', 'stripe', 'paypal' ] as $m ) {
            if ( get_option( 'bb_metodo_' . $m, '1' ) === '1' ) $metodi_abilitati[] = $m;
        }
        if ( ! in_array( $metodo, $metodi_abilitati, true ) ) {
            $errors[] = __( 'Metodo di pagamento non valido.', 'botega-buoni' );
        }

        if ( ! empty( $errors ) ) {
            self::redirect_error( implode( ' | ', $errors ) );
            return;
        }

        // ── Crea ordine ─────────────────────────────────────────────────────
        $ordine_id = BB_Database::insert_ordine( [
            'acquirente_nome'      => $acq_nome,
            'acquirente_cognome'   => $acq_cognome,
            'acquirente_indirizzo' => $acq_indirizzo,
            'acquirente_cap'       => $acq_cap,
            'acquirente_localita'  => $acq_localita,
            'acquirente_email'     => $acq_email,
            'acquirente_telefono'  => $acq_telefono,
            'importo'              => $importo,
            'quantita'             => $quantita,
            'importo_totale'       => $importo_totale,
            'per_me'               => $per_me,
            'metodo_pagamento'     => $metodo,
            'stato_pagamento'      => 'sospeso',
        ] );

        if ( ! $ordine_id ) {
            self::redirect_error( __( 'Errore durante il salvataggio. Riprovare.', 'botega-buoni' ) );
            return;
        }

        // ── Crea buoni ──────────────────────────────────────────────────────
        // Dati condivisi messaggio
        $msg_uguale = ! empty( $_POST['messaggio_uguale'] );
        $msg_comune = sanitize_textarea_field( $_POST['messaggio_comune'] ?? '' );

        // Quando per_me=1: destinatario = acquirente per tutti, con unica data/ora/messaggio
        if ( $per_me ) {
            $me_email_raw  = sanitize_email( $_POST['me_email'] ?? '' );
            $dest_email    = ( $me_email_raw && is_email( $me_email_raw ) ) ? $me_email_raw : $acq_email;
            $me_nome_raw   = sanitize_text_field( $_POST['me_dest_nome'] ?? '' );
            $dest_nome     = $me_nome_raw ?: ( $acq_cognome . ' ' . $acq_nome );
            $data_consegna = sanitize_text_field( $_POST['me_data_consegna'] ?? date( 'Y-m-d' ) );
            $orario        = sanitize_text_field( $_POST['me_orario_consegna'] ?? '' );
            $messaggio     = sanitize_textarea_field( $_POST['me_messaggio'] ?? '' );
            $dc_sanitized  = self::sanitize_date( $data_consegna );

            for ( $i = 1; $i <= $quantita; $i++ ) {
                BB_Database::insert_buono( [
                    'ordine_id'          => $ordine_id,
                    'numero'             => $i,
                    'importo'            => $importo,
                    'destinatario_nome'  => $dest_nome,
                    'destinatario_email' => $dest_email,
                    'data_consegna'      => $dc_sanitized,
                    'orario_consegna'    => $orario,
                    'messaggio'          => $messaggio,
                    'stato_invio'        => 'da_inviare',
                ] );
            }
        } else {
            // Per qualcun altro: un set per ogni buono
            for ( $i = 1; $i <= $quantita; $i++ ) {
                $dest_email    = sanitize_email( $_POST[ "dest_email_{$i}" ]    ?? '' );
                $dest_nome     = sanitize_text_field( $_POST[ "dest_nome_{$i}" ] ?? '' );
                $data_consegna = sanitize_text_field( $_POST[ "data_consegna_{$i}" ] ?? date( 'Y-m-d' ) );
                $orario        = sanitize_text_field( $_POST[ "orario_consegna_{$i}" ] ?? '' );
                $messaggio     = $msg_uguale
                    ? $msg_comune
                    : sanitize_textarea_field( $_POST[ "messaggio_{$i}" ] ?? '' );
                $dc_sanitized  = self::sanitize_date( $data_consegna );

                if ( empty( $dest_email ) || ! is_email( $dest_email ) ) {
                    $dest_email = $acq_email; // fallback
                }

                BB_Database::insert_buono( [
                    'ordine_id'          => $ordine_id,
                    'numero'             => $i,
                    'importo'            => $importo,
                    'destinatario_nome'  => $dest_nome,
                    'destinatario_email' => $dest_email,
                    'data_consegna'      => $dc_sanitized,
                    'orario_consegna'    => $orario,
                    'messaggio'          => $messaggio,
                    'stato_invio'        => 'da_inviare',
                ] );
            }
        }

        // ── Elabora pagamento ────────────────────────────────────────────────
        $result = BB_Payment::process( $ordine_id, $metodo );

        if ( ! empty( $result['error'] ) ) {
            // Rollback ordine
            BB_Database::delete_ordine( $ordine_id );
            self::redirect_error( $result['error'] );
            return;
        }

        // Salva dati acquirente nel transient per il pre-fill post-pagamento
        $ordine = BB_Database::get_ordine( $ordine_id );
        if ( $ordine ) {
            $key = wp_generate_password( 16, false );
            set_transient( 'bb_prefill_' . $key, [
                'nome'      => $acq_nome,
                'cognome'   => $acq_cognome,
                'indirizzo' => $acq_indirizzo,
                'cap'       => $acq_cap,
                'localita'  => $acq_localita,
                'email'     => $acq_email,
                'telefono'  => $acq_telefono,
            ], HOUR_IN_SECONDS );

            // Appendi il prefill key al redirect URL se è verso il proprio sito
            if ( ! empty( $result['redirect'] ) && strpos( $result['redirect'], home_url() ) !== false ) {
                $result['redirect'] = add_query_arg( 'bb_prefill', $key, $result['redirect'] );
            } else {
                // Per redirect esterni (Stripe/PayPal): salva key in cookie temporaneo
                setcookie( 'bb_prefill', $key, time() + HOUR_IN_SECONDS, '/', '', is_ssl(), true );
            }
        }

        wp_safe_redirect( $result['redirect'] );
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function redirect_error( string $msg ): void {
        wp_safe_redirect( add_query_arg( 'bb_errore', rawurlencode( $msg ), BB_Pages::get_url() ) );
        exit;
    }

    private static function sanitize_date( string $date ): string {
        $ts = strtotime( $date );
        return $ts ? date( 'Y-m-d', $ts ) : date( 'Y-m-d' );
    }

    // ── Validazione email domain (opzionale) ──────────────────────────────────
    public static function validate_email_domain( string $email ): bool {
        $domain = substr( $email, strpos( $email, '@' ) + 1 );
        if ( function_exists( 'checkdnsrr' ) ) {
            return checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' );
        }
        return true;
    }

    // ── Orari futuri a 30 min ─────────────────────────────────────────────────
    public static function get_time_slots( string $selected_date = '' ): array {
        $today    = wp_date( 'Y-m-d' );
        $is_today = ( ! $selected_date || $selected_date === $today );
        $slots    = [];

        // Minuti attuali nel giorno (timezone WP)
        $now_min   = (int) wp_date( 'H' ) * 60 + (int) wp_date( 'i' );
        // Prossimo slot: arrotondamento ai 30 min successivi
        $start_min = (int) ceil( $now_min / 30 ) * 30 % 1440;

        if ( $is_today ) {
            // Finestra di 24h: da prossimo slot fino al precedente (arrotondamento precedente)
            $end_min = ( $start_min - 30 + 1440 ) % 1440;

            $m = $start_min;
            do {
                $slots[] = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
                $m = ( $m + 30 ) % 1440;
            } while ( $m !== ( $end_min + 30 ) % 1440 );
        } else {
            // Data futura: tutti gli slot del giorno
            for ( $m = 0; $m < 1440; $m += 30 ) {
                $slots[] = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
            }
        }

        return $slots;
    }
}
