<?php
defined( 'ABSPATH' ) || exit;

class BB_Admin {

    private static string $notice_type = '';
    private static string $notice_msg  = '';

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ __CLASS__, 'handle_actions' ] );
        add_action( 'admin_notices',         [ __CLASS__, 'show_notices' ] );
        add_action( 'wp_ajax_bb_delete_ordine',   [ __CLASS__, 'ajax_delete' ] );
        add_action( 'wp_ajax_bb_invia_buoni',     [ __CLASS__, 'ajax_invia_buoni' ] );
        add_action( 'wp_ajax_bb_invia_richiamo',  [ __CLASS__, 'ajax_invia_richiamo' ] );
        add_action( 'wp_ajax_bb_download_pdf',    [ __CLASS__, 'ajax_download_pdf' ] );
        add_action( 'wp_ajax_bb_rigenera_pdf',    [ __CLASS__, 'ajax_rigenera_pdf' ] );
        add_action( 'admin_post_bb_save_settings', [ 'BB_Settings', 'handle_save' ] );

        // Serve PDF download
        if ( isset( $_GET['bb_dl_pdf'] ) ) {
            add_action( 'admin_init', [ __CLASS__, 'serve_pdf' ] );
        }
    }

    // ── Menu ──────────────────────────────────────────────────────────────────
    public static function register_menus(): void {
        $cap = BB_Roles::cap(); // 'bb_gestione_buoni'

        add_menu_page(
            __( 'Buoni Regalo', 'botega-buoni' ),
            __( 'Buoni Regalo', 'botega-buoni' ),
            $cap,
            'botega-buoni',
            [ __CLASS__, 'page_list' ],
            'dashicons-tickets-alt',
            32
        );
        add_submenu_page(
            'botega-buoni',
            __( 'Tutti gli ordini', 'botega-buoni' ),
            __( 'Tutti gli ordini', 'botega-buoni' ),
            $cap,
            'botega-buoni',
            [ __CLASS__, 'page_list' ]
        );
        add_submenu_page(
            'botega-buoni',
            __( 'Impostazioni', 'botega-buoni' ),
            __( 'Impostazioni', 'botega-buoni' ),
            'manage_options', // solo admin
            'botega-buoni-settings',
            [ 'BB_Settings', 'page' ]
        );
        add_submenu_page(
            'botega-buoni',
            __( 'Ruoli e accessi', 'botega-buoni' ),
            __( '👥 Ruoli e accessi', 'botega-buoni' ),
            'manage_options', // solo admin
            'botega-buoni-roles',
            [ 'BB_Roles', 'render_page' ]
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────
    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'botega-buoni' ) === false && strpos( $hook, 'botega_buoni' ) === false ) return;

        wp_enqueue_style( 'bb-admin', BB_PLUGIN_URL . 'assets/css/admin.css', [], BB_VERSION );
        wp_enqueue_script( 'bb-admin', BB_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], BB_VERSION, true );
        wp_localize_script( 'bb-admin', 'BB_Admin', [
            'nonce'   => wp_create_nonce( 'bb_admin_action' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'i18n'    => [
                'confirm_delete'  => __( 'Eliminare definitivamente questo ordine e tutti i buoni associati?', 'botega-buoni' ),
                'confirm_invia'   => __( 'Inviare i buoni a tutti i destinatari?', 'botega-buoni' ),
                'confirm_richiamo'=> __( 'Inviare il richiamo di pagamento all\'acquirente?', 'botega-buoni' ),
                'done'            => __( 'Operazione completata.', 'botega-buoni' ),
                'error'           => __( 'Si è verificato un errore.', 'botega-buoni' ),
            ],
        ] );
    }

    // ── Handler azioni admin ──────────────────────────────────────────────────
    public static function handle_actions(): void {
        if ( ! BB_Roles::current_user_can() ) return;
        $action = sanitize_key( $_GET['bb_action'] ?? $_POST['bb_action'] ?? '' );
        if ( ! $action ) return;

        switch ( $action ) {
            case 'save_settings':
                BB_Settings::handle_save();
                break;
            case 'set_pagato':
                self::action_set_stato( 'pagato' );
                break;
            case 'set_annullato':
                self::action_set_stato( 'annullato' );
                break;
        }
    }

    private static function action_set_stato( string $stato ): void {
        if ( ! check_admin_referer( 'bb_admin_action', 'bb_nonce' ) ) return;
        if ( ! BB_Roles::current_user_can() ) return;
        $id = (int) ( $_GET['id'] ?? $_POST['id'] ?? 0 );
        if ( ! $id ) return;

        $ordine = BB_Database::get_ordine( $id );
        if ( ! $ordine ) return;

        BB_Database::update_ordine( $id, [ 'stato_pagamento' => $stato ] );

        if ( $stato === 'pagato' && $ordine->stato_pagamento !== 'pagato' ) {
            // Invia conferma acquirente + buoni destinatari
            BB_Email::invia_conferma_acquirente( $ordine );
            BB_Email::invia_tutti_buoni( BB_Database::get_ordine( $id ) );
            self::set_notice( 'success', __( 'Ordine contrassegnato come pagato. Email e buoni inviati.', 'botega-buoni' ) );
        } else {
            self::set_notice( 'success', __( 'Stato aggiornato.', 'botega-buoni' ) );
        }

        $redirect = admin_url( 'admin.php?page=botega-buoni' );
        if ( strpos( wp_get_referer() ?: '', 'action=view' ) !== false ) {
            $redirect = admin_url( 'admin.php?page=botega-buoni&action=view&id=' . $id );
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── AJAX ──────────────────────────────────────────────────────────────────
    public static function ajax_delete(): void {
        check_ajax_referer( 'bb_admin_action', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'No ID' );
        BB_Database::delete_ordine( $id );
        wp_send_json_success();
    }

    public static function ajax_invia_buoni(): void {
        check_ajax_referer( 'bb_admin_action', 'nonce' );
        if ( ! BB_Roles::current_user_can() ) wp_send_json_error( 'Unauthorized', 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $ordine = $id ? BB_Database::get_ordine( $id ) : null;
        if ( ! $ordine ) wp_send_json_error( 'Not found' );
        $result = BB_Email::invia_tutti_buoni( $ordine );
        wp_send_json_success( $result );
    }

    public static function ajax_invia_richiamo(): void {
        check_ajax_referer( 'bb_admin_action', 'nonce' );
        if ( ! BB_Roles::current_user_can() ) wp_send_json_error( 'Unauthorized', 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $ordine = $id ? BB_Database::get_ordine( $id ) : null;
        if ( ! $ordine ) wp_send_json_error( 'Not found' );
        $ok = BB_Email::invia_richiamo_acquirente( $ordine );
        $ok ? wp_send_json_success() : wp_send_json_error( 'Email non inviata' );
    }

    public static function ajax_rigenera_pdf(): void {
        check_ajax_referer( 'bb_admin_action', 'nonce' );
        if ( ! BB_Roles::current_user_can() ) wp_send_json_error( 'Unauthorized', 403 );
        $ordine_id = (int) ( $_POST['ordine_id'] ?? 0 );
        $tipo      = sanitize_key( $_POST['tipo'] ?? 'buono' );
        $buono_id  = (int) ( $_POST['buono_id'] ?? 0 ) ?: null;

        $pdf = BB_PDF_Manager::genera( $tipo, $ordine_id, $buono_id );
        $pdf ? wp_send_json_success( [ 'url' => admin_url( 'admin.php?page=botega-buoni&bb_dl_pdf=' . $pdf->id ) ] )
             : wp_send_json_error( 'PDF generation failed' );
    }

    public static function serve_pdf(): void {
        if ( ! BB_Roles::current_user_can() ) wp_die( 'Unauthorized' );
        $pdf_id = (int) ( $_GET['bb_dl_pdf'] ?? 0 );
        if ( ! $pdf_id ) return;
        BB_PDF_Manager::serve( $pdf_id );
    }

    // ── Notice ────────────────────────────────────────────────────────────────
    public static function set_notice( string $type, string $msg ): void {
        self::$notice_type = $type;
        self::$notice_msg  = $msg;
        set_transient( 'bb_admin_notice_' . get_current_user_id(), [ 'type' => $type, 'msg' => $msg ], 60 );
    }

    public static function show_notices(): void {
        $uid    = get_current_user_id();
        $notice = get_transient( 'bb_admin_notice_' . $uid );
        if ( ! $notice ) return;
        delete_transient( 'bb_admin_notice_' . $uid );
        $class = ( $notice['type'] === 'success' ) ? 'notice-success' : 'notice-error';
        printf( '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr( $class ), esc_html( $notice['msg'] ) );
    }

    // ── Pagine ────────────────────────────────────────────────────────────────
    public static function page_list(): void {
        if ( ! empty( $_GET['action'] ) && $_GET['action'] === 'view' && ! empty( $_GET['id'] ) ) {
            self::page_view( (int) $_GET['id'] );
            return;
        }

        $list  = new BB_List_Table();
        $list->prepare_items();
        ?>
        <div class="wrap bb-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Buoni Regalo – Ordini', 'botega-buoni' ); ?></h1>
            <a href="<?php echo esc_url( BB_Pages::get_url() ); ?>" target="_blank" class="page-title-action">
                🌐 <?php esc_html_e( 'Pagina pubblica', 'botega-buoni' ); ?>
            </a>
            <hr class="wp-header-end">

            <!-- Filtri stato -->
            <ul class="subsubsub">
                <?php
                $stati       = BB_Database::get_stati();
                $stati['']   = __( 'Tutti', 'botega-buoni' );
                $current     = sanitize_key( $_GET['stato'] ?? '' );
                $links       = [];
                foreach ( $stati as $slug => $label ) {
                    $url     = admin_url( 'admin.php?page=botega-buoni' . ( $slug ? '&stato=' . $slug : '' ) );
                    $class   = ( $current === $slug ) ? ' class="current"' : '';
                    $links[] = "<li><a href='" . esc_url( $url ) . "'$class>" . esc_html( $label ) . '</a>';
                }
                echo implode( ' | ', $links );
                ?>
            </ul>

            <form method="get">
                <input type="hidden" name="page" value="botega-buoni">
                <?php $list->search_box( __( 'Cerca', 'botega-buoni' ), 'bb_search' ); ?>
                <?php $list->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function page_view( int $id ): void {
        $ordine = BB_Database::get_ordine( $id );
        if ( ! $ordine ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Ordine non trovato.', 'botega-buoni' ) . '</p></div>';
            return;
        }
        $buoni  = BB_Database::get_buoni( $id );
        $pdfs   = BB_Database::get_pdfs_by_ordine( $id );
        $stati  = BB_Database::get_stati();
        $metodi = BB_Database::get_metodi();
        include BB_PLUGIN_DIR . 'templates/page-view.php';
    }
}
