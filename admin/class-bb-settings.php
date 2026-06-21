<?php
defined( 'ABSPATH' ) || exit;

class BB_Settings {

    public static function page(): void {
        include BB_PLUGIN_DIR . 'templates/page-settings.php';
    }

    public static function handle_save(): void {
        if ( ! check_admin_referer( 'bb_save_settings', 'bb_nonce' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $fields = [
            'bb_email_mittente_nome',
            'bb_email_mittente_email',
            'bb_email_admin',
            'bb_email_fattura_corpo',
            'bb_email_conferma_corpo',
            'bb_email_buono_corpo',
            'bb_email_richiamo_corpo',
            'bb_smtp_abilitato',
            'bb_smtp_host',
            'bb_smtp_port',
            'bb_smtp_user',
            'bb_smtp_secure',
            // Metodi pagamento
            'bb_metodo_fattura',
            'bb_metodo_stripe',
            'bb_metodo_paypal',
            // Stripe
            'bb_stripe_pub_key',
            'bb_stripe_secret_key',
            'bb_stripe_webhook_secret',
            'bb_stripe_twint',
            // PayPal
            'bb_paypal_client_id',
            'bb_paypal_sandbox',
            // Google Maps
            'bb_google_maps_key',
        ];

        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                update_option( $f, sanitize_textarea_field( $_POST[ $f ] ) );
            } elseif ( in_array( $f, [ 'bb_smtp_abilitato', 'bb_metodo_fattura', 'bb_metodo_stripe',
                'bb_metodo_paypal', 'bb_stripe_twint', 'bb_paypal_sandbox' ], true ) ) {
                update_option( $f, '0' );
            }
        }

        // Password SMTP – solo se non vuota
        if ( ! empty( $_POST['bb_smtp_pass'] ) ) {
            update_option( 'bb_smtp_pass', sanitize_text_field( $_POST['bb_smtp_pass'] ) );
        }
        // Secret PayPal – solo se non vuota
        if ( ! empty( $_POST['bb_paypal_secret'] ) ) {
            update_option( 'bb_paypal_secret', sanitize_text_field( $_POST['bb_paypal_secret'] ) );
        }

        BB_Admin::set_notice( 'success', __( 'Impostazioni salvate.', 'botega-buoni' ) );
        wp_safe_redirect( admin_url( 'admin.php?page=botega-buoni-settings' ) );
        exit;
    }
}
