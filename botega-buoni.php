<?php
/**
 * Plugin Name:       Botega Buoni Regalo
 * Plugin URI:        https://labotegalavizzara.ch
 * Description:       Vendita online di buoni regalo per La Botega da la Lavizzara. Gestisce importi, destinatari, consegna digitale, metodi di pagamento (Fattura, Stripe, PayPal) e notifiche email con PDF allegato.
 * Version:           1.0.0
 * Author:            La Botega da la Lavizzara
 * Author URI:        https://labotegalavizzara.ch
 * License:           GPL v2 or later
 * Text Domain:       botega-buoni
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

defined( 'ABSPATH' ) || exit;

// ── Costanti ─────────────────────────────────────────────────────────────────
define( 'BB_VERSION',     '1.0.0' );
define( 'BB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'BB_PLUGIN_FILE', __FILE__ );

// ── Autoload includes ────────────────────────────────────────────────────────
require_once BB_PLUGIN_DIR . 'includes/class-bb-roles.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-database.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-pages.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-pdf-manager.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-email.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-payment.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-public.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-scheduler.php';
require_once BB_PLUGIN_DIR . 'includes/class-bb-frontend-admin.php';
require_once BB_PLUGIN_DIR . 'admin/class-bb-admin.php';
require_once BB_PLUGIN_DIR . 'admin/class-bb-list-table.php';
require_once BB_PLUGIN_DIR . 'admin/class-bb-settings.php';

// ── Attivazione / Disattivazione ─────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
    BB_Database::install();
    BB_Roles::setup();
    BB_Scheduler::register_cron();
} );

register_deactivation_hook( __FILE__, function () {
    BB_Scheduler::deregister_cron();
} );

// ── i18n ─────────────────────────────────────────────────────────────────────
add_action( 'init', function () {
    load_plugin_textdomain(
        'botega-buoni',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
} );

// ── Rimozione ruoli legacy: gira su wp_loaded (dopo tutti i plugin) ────────
add_action( 'wp_loaded', function () {
    BB_Roles::remove_legacy_roles();
}, 999 );

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    BB_Roles::setup();         // assicura ruoli e cap ad ogni caricamento
    BB_Admin::init();
    BB_Public::init();
    BB_Payment::init();
    BB_Frontend_Admin::init(); // shortcode + AJAX frontend
    BB_Scheduler::init();      // registra listener cron su ogni caricamento
} );

// ── Serve PDF da frontend (prima che l'output parta) ─────────────────────────
add_action( 'template_redirect', function () {
    BB_Frontend_Admin::maybe_serve_pdf();
} );

// ── Pagina pubblica: richiede permalink pronti → init (priority 20) ──────────
add_action( 'init', function () {
    BB_Pages::setup();
}, 20 );
