<?php
/**
 * Botega Buoni Regalo – Uninstall
 * Viene eseguito quando l'amministratore elimina il plugin dalla dashboard.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Carica le classi necessarie (uninstall gira fuori dal boot normale)
require_once plugin_dir_path( __FILE__ ) . 'includes/class-bb-roles.php';

global $wpdb;

// Rimuovi tabelle
$tables = [
    $wpdb->prefix . 'bb_ordini',
    $wpdb->prefix . 'bb_buoni',
    $wpdb->prefix . 'bb_pdf',
];
foreach ( $tables as $t ) {
    $wpdb->query( "DROP TABLE IF EXISTS `$t`" ); // phpcs:ignore
}

// Rimuovi opzioni
$options = [
    'bb_db_version', 'bb_pagina_buoni_id',
    'bb_metodo_fattura', 'bb_metodo_stripe', 'bb_metodo_paypal',
    'bb_stripe_pub_key', 'bb_stripe_secret_key', 'bb_stripe_webhook_secret', 'bb_stripe_twint',
    'bb_paypal_client_id', 'bb_paypal_secret', 'bb_paypal_sandbox',
    'bb_email_mittente_nome', 'bb_email_mittente_email', 'bb_email_admin',
    'bb_email_fattura_corpo', 'bb_email_conferma_corpo', 'bb_email_buono_corpo', 'bb_email_richiamo_corpo',
    'bb_smtp_abilitato', 'bb_smtp_host', 'bb_smtp_port', 'bb_smtp_user', 'bb_smtp_pass', 'bb_smtp_secure',
    'bb_google_maps_key',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Deregistra cron
$ts = wp_next_scheduled( 'bb_check_unpaid_orders' );
if ( $ts ) wp_unschedule_event( $ts, 'bb_check_unpaid_orders' );

// Rimuovi ruoli personalizzati e capability
BB_Roles::teardown();
