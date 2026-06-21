<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_Frontend_Admin
 *
 * Shortcode [bb_admin_buoni] – pannello amministrativo accessibile
 * dal frontend a tutti gli utenti con la capability `bb_gestione_buoni`
 * (ruoli: Amministratore, Segretariato, Comitato, Cassiere).
 *
 * Le azioni (segna pagato, annulla, invia buoni, richiamo) avvengono via
 * AJAX con verifica nonce + capability.
 */
class BB_Frontend_Admin {

    public static function init(): void {
        add_shortcode( 'bb_admin_buoni', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX (loggati)
        add_action( 'wp_ajax_bb_fa_set_stato',       [ __CLASS__, 'ajax_set_stato' ] );
        add_action( 'wp_ajax_bb_fa_invia_buoni',     [ __CLASS__, 'ajax_invia_buoni' ] );
        add_action( 'wp_ajax_bb_fa_invia_richiamo',  [ __CLASS__, 'ajax_invia_richiamo' ] );
        add_action( 'wp_ajax_bb_fa_delete',          [ __CLASS__, 'ajax_delete' ] );
        add_action( 'wp_ajax_bb_fa_rigenera_pdf',    [ __CLASS__, 'ajax_rigenera_pdf' ] );
        add_action( 'wp_ajax_bb_fa_get_slots',       [ __CLASS__, 'ajax_get_slots' ] );
    }

    // ── Assets (solo quando lo shortcode è nella pagina) ──────────────────────
    public static function enqueue_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( ! has_shortcode( $post->post_content, 'bb_admin_buoni' ) ) return;
        if ( ! BB_Roles::current_user_can() ) return;

        wp_enqueue_style(
            'bb-frontend-admin',
            BB_PLUGIN_URL . 'assets/css/frontend-admin.css',
            [],
            BB_VERSION
        );
        wp_enqueue_script(
            'bb-frontend-admin',
            BB_PLUGIN_URL . 'assets/js/frontend-admin.js',
            [ 'jquery' ],
            BB_VERSION,
            true
        );
        wp_localize_script( 'bb-frontend-admin', 'BB_FA', [
            'nonce'   => wp_create_nonce( 'bb_frontend_admin' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'role'    => BB_Roles::get_current_role(),
            'is_admin'=> current_user_can( 'manage_options' ) ? '1' : '0',
            'i18n'    => [
                'confirm_pagato'   => __( 'Contrassegnare come pagato? Verranno inviati i buoni ai destinatari.', 'botega-buoni' ),
                'confirm_annulla'  => __( 'Annullare questo ordine?', 'botega-buoni' ),
                'confirm_delete'   => __( 'Eliminare definitivamente questo ordine?', 'botega-buoni' ),
                'confirm_buoni'    => __( 'Inviare i buoni a tutti i destinatari?', 'botega-buoni' ),
                'confirm_richiamo' => __( 'Inviare il richiamo di pagamento all\'acquirente?', 'botega-buoni' ),
                'loading'          => __( 'Elaborazione…', 'botega-buoni' ),
                'done'             => __( 'Operazione completata.', 'botega-buoni' ),
                'error'            => __( 'Si è verificato un errore.', 'botega-buoni' ),
            ],
        ] );
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────
    public static function shortcode( array $atts = [] ): string {
        if ( ! BB_Roles::current_user_can() ) {
            if ( ! is_user_logged_in() ) {
                return '<p class="bb-fa-warn">' . sprintf(
                    wp_kses(
                        __( '<a href="%s">Accedi</a> per gestire i buoni regalo.', 'botega-buoni' ),
                        [ 'a' => [ 'href' => [] ] ]
                    ),
                    esc_url( wp_login_url( get_permalink() ) )
                ) . '</p>';
            }
            return '<p class="bb-fa-warn">' . esc_html__( 'Non hai i permessi per accedere a questa sezione.', 'botega-buoni' ) . '</p>';
        }

        // Routing interno: ?bb_fa_view=<id>
        $view_id = (int) ( $_GET['bb_fa_view'] ?? 0 );

        ob_start();
        if ( $view_id > 0 ) {
            $ordine = BB_Database::get_ordine( $view_id );
            if ( $ordine ) {
                self::render_view( $ordine );
            } else {
                echo '<p class="bb-fa-warn">' . esc_html__( 'Ordine non trovato.', 'botega-buoni' ) . '</p>';
            }
        } else {
            self::render_list();
        }
        return ob_get_clean();
    }

    // ── Render: lista ordini ──────────────────────────────────────────────────
    private static function render_list(): void {
        $page     = max( 1, (int) ( $_GET['bb_fa_pg'] ?? 1 ) );
        $stato    = sanitize_key( $_GET['bb_fa_stato'] ?? '' );
        $search   = sanitize_text_field( $_GET['bb_fa_s'] ?? '' );
        $per_page = 20;

        $data   = BB_Database::get_all( [
            'per_page' => $per_page,
            'paged'    => $page,
            'stato'    => $stato,
            'search'   => $search,
        ] );

        $ordini       = $data['rows'];
        $total        = $data['total'];
        $total_pages  = $data['total_pages'];
        $stati        = BB_Database::get_stati();
        $metodi       = BB_Database::get_metodi();
        $current_url  = get_permalink() ?: home_url( '/' );

        include BB_PLUGIN_DIR . 'templates/frontend-admin-list.php';
    }

    // ── Render: dettaglio ordine ───────────────────────────────────────────────
    private static function render_view( object $ordine ): void {
        $buoni        = BB_Database::get_buoni( $ordine->id );
        $pdfs         = BB_Database::get_pdfs_by_ordine( $ordine->id );
        $stati        = BB_Database::get_stati();
        $metodi       = BB_Database::get_metodi();
        $stati_invio  = BB_Database::get_stati_invio();
        $current_url  = get_permalink() ?: home_url( '/' );
        $back_url     = remove_query_arg( 'bb_fa_view', $current_url );

        include BB_PLUGIN_DIR . 'templates/frontend-admin-view.php';
    }

    // ── AJAX: cambia stato ────────────────────────────────────────────────────
    public static function ajax_set_stato(): void {
        self::check_ajax();
        $id    = (int) ( $_POST['id'] ?? 0 );
        $stato = sanitize_key( $_POST['stato'] ?? '' );
        if ( ! $id || ! in_array( $stato, [ 'pagato', 'annullato' ], true ) ) {
            wp_send_json_error( 'Parametri non validi.' );
        }

        $ordine = BB_Database::get_ordine( $id );
        if ( ! $ordine ) wp_send_json_error( 'Ordine non trovato.' );

        BB_Database::update_ordine( $id, [ 'stato_pagamento' => $stato ] );

        if ( $stato === 'pagato' && $ordine->stato_pagamento !== 'pagato' ) {
            $ordine_fresh = BB_Database::get_ordine( $id );
            BB_Email::invia_conferma_acquirente( $ordine_fresh );
            BB_Email::invia_tutti_buoni( $ordine_fresh );
        }

        wp_send_json_success( [ 'stato' => $stato ] );
    }

    // ── AJAX: invia buoni ─────────────────────────────────────────────────────
    public static function ajax_invia_buoni(): void {
        self::check_ajax();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $ordine = $id ? BB_Database::get_ordine( $id ) : null;
        if ( ! $ordine ) wp_send_json_error( 'Ordine non trovato.' );

        $result = BB_Email::invia_tutti_buoni( $ordine );
        wp_send_json_success( $result );
    }

    // ── AJAX: invia richiamo ──────────────────────────────────────────────────
    public static function ajax_invia_richiamo(): void {
        self::check_ajax();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $ordine = $id ? BB_Database::get_ordine( $id ) : null;
        if ( ! $ordine ) wp_send_json_error( 'Ordine non trovato.' );

        $ok = BB_Email::invia_richiamo_acquirente( $ordine );
        $ok ? wp_send_json_success() : wp_send_json_error( 'Invio fallito.' );
    }

    // ── AJAX: elimina ordine ──────────────────────────────────────────────────
    public static function ajax_delete(): void {
        self::check_ajax();
        // Solo amministratori possono eliminare
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato.' );
        }
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'ID mancante.' );
        BB_Database::delete_ordine( $id );
        wp_send_json_success();
    }

    // ── AJAX: rigenera PDF ────────────────────────────────────────────────────
    public static function ajax_rigenera_pdf(): void {
        self::check_ajax();
        $ordine_id = (int) ( $_POST['ordine_id'] ?? 0 );
        $tipo      = sanitize_key( $_POST['tipo'] ?? 'buono' );
        $buono_id  = (int) ( $_POST['buono_id'] ?? 0 ) ?: null;

        $pdf = BB_PDF_Manager::genera( $tipo, $ordine_id, $buono_id );
        if ( $pdf ) {
            // URL di download tramite endpoint REST publico (serve file dal server)
            $url = add_query_arg( [
                'bb_fa_pdf' => $pdf->id,
                'bb_nonce'  => wp_create_nonce( 'bb_fa_pdf_' . $pdf->id ),
            ], get_permalink() ?: home_url( '/' ) );
            wp_send_json_success( [ 'url' => $url ] );
        } else {
            wp_send_json_error( 'Generazione PDF fallita.' );
        }
    }

    // ── Serve PDF da frontend ─────────────────────────────────────────────────
    public static function maybe_serve_pdf(): void {
        if ( empty( $_GET['bb_fa_pdf'] ) ) return;
        if ( ! BB_Roles::current_user_can() ) return;

        $pdf_id = (int) $_GET['bb_fa_pdf'];
        $nonce  = sanitize_text_field( $_GET['bb_nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'bb_fa_pdf_' . $pdf_id ) ) return;

        BB_PDF_Manager::serve( $pdf_id );
    }

    // ── Guard AJAX ────────────────────────────────────────────────────────────
    private static function check_ajax(): void {
        check_ajax_referer( 'bb_frontend_admin', 'nonce' );
        if ( ! BB_Roles::current_user_can() ) {
            wp_send_json_error( 'Non autorizzato.', 403 );
        }
    }
}
