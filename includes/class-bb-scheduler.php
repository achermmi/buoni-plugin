<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_Scheduler
 * WP-Cron per reminder automatici degli ordini non pagati.
 */
class BB_Scheduler {

    const HOOK = 'bb_check_unpaid_orders';

    public static function register_cron(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK );
        }
        add_action( self::HOOK, [ __CLASS__, 'run' ] );
    }

    public static function deregister_cron(): void {
        $ts = wp_next_scheduled( self::HOOK );
        if ( $ts ) wp_unschedule_event( $ts, self::HOOK );
    }

    /**
     * Avvia anche il listener sull'hook (necessario ad ogni caricamento WordPress
     * dopo la registrazione del cron all'attivazione).
     */
    public static function init(): void {
        add_action( self::HOOK, [ __CLASS__, 'run' ] );
    }

    // ── Esecuzione ────────────────────────────────────────────────────────────
    public static function run(): void {
        $ordini = BB_Database::get_ordini_da_ricordare();
        foreach ( $ordini as $ordine ) {
            BB_Email::invia_reminder_admin( $ordine );
        }
    }
}
