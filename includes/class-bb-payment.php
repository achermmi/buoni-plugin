<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_Payment
 * Gestisce tutti i metodi di pagamento: Fattura, Stripe (carte + Twint), PayPal.
 */
class BB_Payment {

    public static function init(): void {
        // Endpoint REST per webhook Stripe e PayPal
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
        // Return da PayPal dopo approvazione
        add_action( 'init', [ __CLASS__, 'handle_paypal_return' ] );
        // Return da Stripe success
        add_action( 'init', [ __CLASS__, 'handle_stripe_return' ] );
    }

    // ── REST Routes ───────────────────────────────────────────────────────────
    public static function register_rest_routes(): void {
        register_rest_route( 'bb/v1', '/stripe-webhook', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_stripe_webhook' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'bb/v1', '/paypal-webhook', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_paypal_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ── Elabora pagamento dopo submit form ────────────────────────────────────
    /**
     * @return array ['redirect' => url] | ['error' => string]
     */
    public static function process( int $ordine_id, string $metodo ): array {
        $ordine = BB_Database::get_ordine( $ordine_id );
        if ( ! $ordine ) return [ 'error' => __( 'Ordine non trovato.', 'botega-buoni' ) ];

        switch ( $metodo ) {
            case 'fattura':
                return self::process_fattura( $ordine );
            case 'stripe':
                return self::process_stripe( $ordine );
            case 'paypal':
                return self::process_paypal( $ordine );
            default:
                return [ 'error' => __( 'Metodo di pagamento non valido.', 'botega-buoni' ) ];
        }
    }

    // ── FATTURA ───────────────────────────────────────────────────────────────
    private static function process_fattura( object $ordine ): array {
        $stato_email = BB_Email::invia_fattura( $ordine );
        BB_Database::update_ordine( $ordine->id, [
            'stato_pagamento' => $stato_email === 'inviato' ? 'inviato' : 'errore_invio',
        ] );
        $url = add_query_arg( [
            'bb_ok'    => '1',
            'bb_metodo'=> 'fattura',
            'bb_ref'   => rawurlencode( $ordine->ordine_ref ),
        ], BB_Pages::get_url() );
        return [ 'redirect' => $url ];
    }

    // ── STRIPE ────────────────────────────────────────────────────────────────
    private static function process_stripe( object $ordine ): array {
        $secret_key = get_option( 'bb_stripe_secret_key', '' );
        if ( ! $secret_key ) {
            return [ 'error' => __( 'Stripe non configurato. Contattare l\'amministratore.', 'botega-buoni' ) ];
        }

        $amount_cents = (int) round( (float) $ordine->importo_totale * 100 );
        $success_url  = add_query_arg( [
            'bb_stripe_ok'  => '1',
            'bb_ordine_id'  => $ordine->id,
            'bb_nonce'      => wp_create_nonce( 'bb_stripe_return_' . $ordine->id ),
        ], BB_Pages::get_url() );
        $cancel_url = add_query_arg( [
            'bb_cancelled' => '1',
            'bb_ref'       => rawurlencode( $ordine->ordine_ref ),
        ], BB_Pages::get_url() );

        $buoni   = BB_Database::get_buoni( $ordine->id );
        $descrizione = sprintf(
            _n( 'Buono regalo La Botega x%d – %s', 'Buoni regalo La Botega x%d – %s', (int) $ordine->quantita, 'botega-buoni' ),
            (int) $ordine->quantita,
            BB_Database::fmt_chf( (float) $ordine->importo )
        );

        // Payment methods per Stripe: card sempre, twint se abilitato
        $methods = [ 'card' ];
        if ( get_option( 'bb_stripe_twint', '1' ) === '1' ) {
            $methods[] = 'twint';
        }

        $body = [
            'mode'                    => 'payment',
            'success_url'             => $success_url,
            'cancel_url'              => $cancel_url,
            'customer_email'          => $ordine->acquirente_email,
            'client_reference_id'     => $ordine->ordine_ref,
            'metadata[ordine_id]'     => $ordine->id,
            'metadata[ordine_ref]'    => $ordine->ordine_ref,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]'                     => 'chf',
            'line_items[0][price_data][unit_amount]'                  => $amount_cents,
            'line_items[0][price_data][product_data][name]'           => $descrizione,
            'line_items[0][price_data][product_data][description]'    => sprintf(
                __( 'Ordine %s – %d buono/i da %s', 'botega-buoni' ),
                $ordine->ordine_ref, (int) $ordine->quantita,
                BB_Database::fmt_chf( (float) $ordine->importo )
            ),
        ];
        foreach ( $methods as $i => $m ) {
            $body[ "payment_method_types[$i]" ] = $m;
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', [
            'headers'     => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'        => $body,
            'timeout'     => 20,
            'sslverify'   => true,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[BB Stripe] ' . $response->get_error_message() );
            return [ 'error' => __( 'Errore di connessione a Stripe. Riprovare.', 'botega-buoni' ) ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['url'] ) ) {
            $msg = $data['error']['message'] ?? 'Errore Stripe sconosciuto';
            error_log( '[BB Stripe] Session error: ' . $msg );
            return [ 'error' => __( 'Impossibile avviare il pagamento Stripe. Riprovare.', 'botega-buoni' ) ];
        }

        BB_Database::update_ordine( $ordine->id, [
            'gateway_payment_id' => $data['id'],
            'stato_pagamento'    => 'pending',
        ] );

        return [ 'redirect' => $data['url'] ];
    }

    // ── STRIPE: ritorno dopo pagamento ────────────────────────────────────────
    public static function handle_stripe_return(): void {
        if ( empty( $_GET['bb_stripe_ok'] ) || empty( $_GET['bb_ordine_id'] ) ) return;

        $ordine_id = (int) $_GET['bb_ordine_id'];
        $nonce     = sanitize_text_field( $_GET['bb_nonce'] ?? '' );

        if ( ! wp_verify_nonce( $nonce, 'bb_stripe_return_' . $ordine_id ) ) return;

        $ordine = BB_Database::get_ordine( $ordine_id );
        if ( ! $ordine ) return;
        if ( $ordine->stato_pagamento === 'pagato' ) return; // già elaborato

        // Verifica stato sessione Stripe
        $secret_key = get_option( 'bb_stripe_secret_key', '' );
        if ( $secret_key && $ordine->gateway_payment_id ) {
            $response = wp_remote_get(
                'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( $ordine->gateway_payment_id ),
                [
                    'headers'   => [ 'Authorization' => 'Bearer ' . $secret_key ],
                    'timeout'   => 15,
                    'sslverify' => true,
                ]
            );
            if ( ! is_wp_error( $response ) ) {
                $session = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ( $session['payment_status'] ?? '' ) === 'paid' ) {
                    self::complete_order( $ordine );
                }
            }
        }
    }

    // ── STRIPE WEBHOOK ────────────────────────────────────────────────────────
    public static function handle_stripe_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload    = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret     = get_option( 'bb_stripe_webhook_secret', '' );

        if ( $secret ) {
            if ( ! self::stripe_verify_signature( $payload, $sig_header, $secret ) ) {
                return new \WP_REST_Response( [ 'error' => 'Invalid signature' ], 400 );
            }
        }

        $event = json_decode( $payload, true );
        if ( ! $event ) return new \WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );

        if ( $event['type'] === 'checkout.session.completed' ) {
            $session    = $event['data']['object'];
            $session_id = $session['id'] ?? '';
            $ordine     = BB_Database::get_ordine_by_gateway( $session_id );

            if ( $ordine && $ordine->stato_pagamento !== 'pagato' ) {
                self::complete_order( $ordine );
            }
        }
        return new \WP_REST_Response( [ 'received' => true ], 200 );
    }

    // ── PAYPAL ────────────────────────────────────────────────────────────────
    private static function process_paypal( object $ordine ): array {
        $client_id = get_option( 'bb_paypal_client_id', '' );
        $secret    = get_option( 'bb_paypal_secret', '' );
        $sandbox   = get_option( 'bb_paypal_sandbox', '1' ) === '1';

        if ( ! $client_id || ! $secret ) {
            return [ 'error' => __( 'PayPal non configurato. Contattare l\'amministratore.', 'botega-buoni' ) ];
        }

        // 1. Token OAuth
        $token = self::paypal_get_token( $client_id, $secret, $sandbox );
        if ( ! $token ) {
            return [ 'error' => __( 'Errore di autenticazione PayPal. Riprovare.', 'botega-buoni' ) ];
        }

        // 2. Crea ordine PayPal
        $base_url    = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $return_url  = add_query_arg( [
            'bb_paypal_return'   => '1',
            'bb_ordine_id'       => $ordine->id,
            'bb_nonce'           => wp_create_nonce( 'bb_paypal_return_' . $ordine->id ),
        ], BB_Pages::get_url() );
        $cancel_url  = add_query_arg( [
            'bb_cancelled' => '1',
            'bb_ref'       => rawurlencode( $ordine->ordine_ref ),
        ], BB_Pages::get_url() );

        $amount = number_format( (float) $ordine->importo_totale, 2, '.', '' );

        $response = wp_remote_post( $base_url . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'PayPal-Request-Id' => $ordine->ordine_ref . '-' . time(),
            ],
            'body' => json_encode( [
                'intent'         => 'CAPTURE',
                'purchase_units' => [ [
                    'reference_id' => $ordine->ordine_ref,
                    'amount'       => [
                        'currency_code' => 'CHF',
                        'value'         => $amount,
                    ],
                    'description' => sprintf(
                        __( 'Buono regalo La Botega x%d', 'botega-buoni' ),
                        (int) $ordine->quantita
                    ),
                ] ],
                'application_context' => [
                    'return_url'  => $return_url,
                    'cancel_url'  => $cancel_url,
                    'brand_name'  => 'La Botega da la Lavizzara',
                    'locale'      => 'it-CH',
                    'user_action' => 'PAY_NOW',
                ],
            ] ),
            'timeout'   => 20,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[BB PayPal] ' . $response->get_error_message() );
            return [ 'error' => __( 'Errore di connessione a PayPal. Riprovare.', 'botega-buoni' ) ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $approve_url = '';
        foreach ( $data['links'] ?? [] as $link ) {
            if ( $link['rel'] === 'approve' ) {
                $approve_url = $link['href'];
                break;
            }
        }

        if ( ! $approve_url ) {
            error_log( '[BB PayPal] No approve URL: ' . print_r( $data, true ) );
            return [ 'error' => __( 'Impossibile avviare il pagamento PayPal. Riprovare.', 'botega-buoni' ) ];
        }

        BB_Database::update_ordine( $ordine->id, [
            'gateway_payment_id' => $data['id'],
            'stato_pagamento'    => 'pending',
        ] );

        return [ 'redirect' => $approve_url ];
    }

    // ── PAYPAL: ritorno dopo approvazione ─────────────────────────────────────
    public static function handle_paypal_return(): void {
        if ( empty( $_GET['bb_paypal_return'] ) || empty( $_GET['bb_ordine_id'] ) ) return;

        $ordine_id = (int) $_GET['bb_ordine_id'];
        $nonce     = sanitize_text_field( $_GET['bb_nonce'] ?? '' );
        $pp_token  = sanitize_text_field( $_GET['token'] ?? '' );

        if ( ! wp_verify_nonce( $nonce, 'bb_paypal_return_' . $ordine_id ) ) return;
        if ( ! $pp_token ) return;

        $ordine = BB_Database::get_ordine( $ordine_id );
        if ( ! $ordine || $ordine->stato_pagamento === 'pagato' ) return;

        // Cattura il pagamento
        $client_id = get_option( 'bb_paypal_client_id', '' );
        $secret    = get_option( 'bb_paypal_secret', '' );
        $sandbox   = get_option( 'bb_paypal_sandbox', '1' ) === '1';
        $token     = self::paypal_get_token( $client_id, $secret, $sandbox );

        if ( ! $token || ! $ordine->gateway_payment_id ) return;

        $base_url = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $response = wp_remote_post( $base_url . '/v2/checkout/orders/' . rawurlencode( $ordine->gateway_payment_id ) . '/capture', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => '{}',
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) return;

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = $data['status'] ?? '';

        if ( $status === 'COMPLETED' ) {
            self::complete_order( $ordine );
        }
    }

    // ── PAYPAL WEBHOOK ────────────────────────────────────────────────────────
    public static function handle_paypal_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = json_decode( $request->get_body(), true );
        if ( ! $payload ) return new \WP_REST_Response( null, 200 );

        if ( ( $payload['event_type'] ?? '' ) === 'CHECKOUT.ORDER.APPROVED' ) {
            $pp_order_id = $payload['resource']['id'] ?? '';
            if ( $pp_order_id ) {
                $ordine = BB_Database::get_ordine_by_gateway( $pp_order_id );
                if ( $ordine && $ordine->stato_pagamento !== 'pagato' ) {
                    // La cattura viene fatta al ritorno utente; qui solo un log
                    error_log( '[BB PayPal webhook] Order approved: ' . $pp_order_id );
                }
            }
        }
        return new \WP_REST_Response( null, 200 );
    }

    // ── Completa ordine dopo pagamento ────────────────────────────────────────
    public static function complete_order( object $ordine ): void {
        BB_Database::update_ordine( $ordine->id, [ 'stato_pagamento' => 'pagato' ] );
        $ordine_fresh = BB_Database::get_ordine( $ordine->id );
        if ( ! $ordine_fresh ) return;

        // Invia conferma all'acquirente
        BB_Email::invia_conferma_acquirente( $ordine_fresh );
        // Invia buoni ai destinatari
        BB_Email::invia_tutti_buoni( $ordine_fresh );
    }

    // ── Helper PayPal OAuth ───────────────────────────────────────────────────
    private static function paypal_get_token( string $client_id, string $secret, bool $sandbox ): string|false {
        $base_url = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $response = wp_remote_post( $base_url . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ] );
        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['access_token'] ?? false;
    }

    // ── Helper verifica firma Stripe ──────────────────────────────────────────
    private static function stripe_verify_signature( string $payload, string $sig_header, string $secret ): bool {
        $parts    = explode( ',', $sig_header );
        $ts       = 0;
        $v1_sigs  = [];
        foreach ( $parts as $part ) {
            [ $k, $v ] = explode( '=', $part, 2 ) + [ '', '' ];
            if ( $k === 't' ) $ts = (int) $v;
            if ( $k === 'v1' ) $v1_sigs[] = $v;
        }
        if ( ! $ts || empty( $v1_sigs ) ) return false;

        $signed_payload = "$ts.$payload";
        $expected       = hash_hmac( 'sha256', $signed_payload, $secret );
        foreach ( $v1_sigs as $sig ) {
            if ( hash_equals( $expected, $sig ) ) return true;
        }
        return false;
    }
}
