<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BB_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'ordine',
            'plural'   => 'ordini',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'cb'              => '<input type="checkbox">',
            'ordine_ref'      => __( 'Rif. Ordine', 'botega-buoni' ),
            'acquirente'      => __( 'Acquirente', 'botega-buoni' ),
            'importo_totale'  => __( 'Importo', 'botega-buoni' ),
            'quantita'        => __( 'Q.tà', 'botega-buoni' ),
            'metodo_pagamento'=> __( 'Metodo', 'botega-buoni' ),
            'stato_pagamento' => __( 'Stato', 'botega-buoni' ),
            'data_creazione'  => __( 'Data', 'botega-buoni' ),
            'azioni'          => __( 'Azioni', 'botega-buoni' ),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'ordine_ref'     => [ 'ordine_ref', false ],
            'importo_totale' => [ 'importo_totale', false ],
            'data_creazione' => [ 'data_creazione', true ],
        ];
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( $item->$column_name ?? '' );
    }

    protected function column_cb( $item ): string {
        return '<input type="checkbox" name="ordine_ids[]" value="' . (int) $item->id . '">';
    }

    protected function column_ordine_ref( $item ): string {
        $url = admin_url( 'admin.php?page=botega-buoni&action=view&id=' . $item->id );
        return '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $item->ordine_ref ) . '</a></strong>';
    }

    protected function column_acquirente( $item ): string {
        return esc_html( $item->acquirente_cognome . ' ' . $item->acquirente_nome )
            . '<br><span style="color:#666;font-size:11px">' . esc_html( $item->acquirente_email ) . '</span>';
    }

    protected function column_importo_totale( $item ): string {
        return '<strong>' . esc_html( BB_Database::fmt_chf( (float) $item->importo_totale ) ) . '</strong>'
            . '<br><span style="color:#666;font-size:11px">' . (int) $item->quantita . ' × '
            . esc_html( BB_Database::fmt_chf( (float) $item->importo ) ) . '</span>';
    }

    protected function column_quantita( $item ): string {
        return esc_html( (string) (int) $item->quantita );
    }

    protected function column_metodo_pagamento( $item ): string {
        $metodi = BB_Database::get_metodi();
        return esc_html( $metodi[ $item->metodo_pagamento ] ?? $item->metodo_pagamento );
    }

    protected function column_stato_pagamento( $item ): string {
        $stati  = BB_Database::get_stati();
        $label  = $stati[ $item->stato_pagamento ] ?? $item->stato_pagamento;
        $colors = [
            'pagato'       => '#28a745',
            'inviato'      => '#007bff',
            'sospeso'      => '#fd7e14',
            'pending'      => '#6c757d',
            'annullato'    => '#dc3545',
            'errore_invio' => '#dc3545',
        ];
        $color = $colors[ $item->stato_pagamento ] ?? '#888';
        return '<span class="bb-badge" style="background:' . $color . '">' . esc_html( $label ) . '</span>';
    }

    protected function column_data_creazione( $item ): string {
        return date_i18n( 'd.m.Y', strtotime( $item->data_creazione ) )
            . '<br><span style="color:#888;font-size:11px">'
            . date_i18n( 'H:i', strtotime( $item->data_creazione ) ) . '</span>';
    }

    protected function column_azioni( $item ): string {
        $view_url  = admin_url( 'admin.php?page=botega-buoni&action=view&id=' . $item->id );
        $paid_url  = wp_nonce_url(
            admin_url( 'admin.php?page=botega-buoni&bb_action=set_pagato&id=' . $item->id ),
            'bb_admin_action', 'bb_nonce'
        );
        $ann_url   = wp_nonce_url(
            admin_url( 'admin.php?page=botega-buoni&bb_action=set_annullato&id=' . $item->id ),
            'bb_admin_action', 'bb_nonce'
        );

        $html  = '<div style="white-space:nowrap;display:flex;align-items:center;gap:4px;">';
        $html .= '<a href="' . esc_url( $view_url ) . '" class="button button-small">'
               . __( 'Dettaglio', 'botega-buoni' ) . '</a>';

        if ( ! in_array( $item->stato_pagamento, [ 'pagato', 'annullato' ], true ) ) {
            $html .= '<a href="' . esc_url( $paid_url ) . '" class="button button-small bb-btn-success">'
                   . __( '✓ Pagato', 'botega-buoni' ) . '</a>';
        }
        $html .= '<button class="button button-small bb-btn-delete" data-id="' . (int) $item->id . '">'
               . __( '🗑', 'botega-buoni' ) . '</button>';
        $html .= '</div>';

        return $html;
    }

    public function prepare_items(): void {
        $per_page = 25;
        $paged    = (int) ( $_GET['paged'] ?? 1 );
        $orderby  = sanitize_key( $_GET['orderby'] ?? 'data_creazione' );
        $order    = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) );
        $stato    = sanitize_key( $_GET['stato'] ?? '' );
        $search   = sanitize_text_field( $_GET['s'] ?? '' );

        $data = BB_Database::get_all( [
            'per_page' => $per_page,
            'paged'    => $paged,
            'orderby'  => $orderby,
            'order'    => $order,
            'stato'    => $stato,
            'search'   => $search,
        ] );

        $this->items = $data['rows'];
        $this->set_pagination_args( [
            'total_items' => $data['total'],
            'per_page'    => $per_page,
            'total_pages' => $data['total_pages'],
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function no_items(): void {
        esc_html_e( 'Nessun ordine trovato.', 'botega-buoni' );
    }
}
