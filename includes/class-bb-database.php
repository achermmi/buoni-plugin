<?php
defined( 'ABSPATH' ) || exit;

class BB_Database {

    // ── Nomi tabelle ──────────────────────────────────────────────────────────
    public static function table_ordini(): string  { global $wpdb; return $wpdb->prefix . 'bb_ordini'; }
    public static function table_buoni(): string   { global $wpdb; return $wpdb->prefix . 'bb_buoni'; }
    public static function table_pdf(): string     { global $wpdb; return $wpdb->prefix . 'bb_pdf'; }

    // ── Installazione ─────────────────────────────────────────────────────────
    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_ordini = "CREATE TABLE IF NOT EXISTS " . self::table_ordini() . " (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ordine_ref           VARCHAR(25)     NOT NULL DEFAULT '',
            acquirente_nome      VARCHAR(100)    NOT NULL DEFAULT '',
            acquirente_cognome   VARCHAR(100)    NOT NULL DEFAULT '',
            acquirente_indirizzo VARCHAR(300)    NOT NULL DEFAULT '',
            acquirente_cap       VARCHAR(20)     NOT NULL DEFAULT '',
            acquirente_localita  VARCHAR(200)    NOT NULL DEFAULT '',
            acquirente_email     VARCHAR(200)    NOT NULL DEFAULT '',
            acquirente_telefono  VARCHAR(50)     NOT NULL DEFAULT '',
            importo              DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            quantita             INT UNSIGNED    NOT NULL DEFAULT 1,
            importo_totale       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            per_me               TINYINT(1)      NOT NULL DEFAULT 0,
            metodo_pagamento     VARCHAR(30)     NOT NULL DEFAULT 'fattura',
            stato_pagamento      VARCHAR(30)     NOT NULL DEFAULT 'sospeso',
            gateway_payment_id   VARCHAR(500)    NOT NULL DEFAULT '',
            note                 TEXT            NULL,
            reminder_count       INT UNSIGNED    NOT NULL DEFAULT 0,
            data_ultimo_reminder DATETIME        NULL,
            data_creazione       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_modifica        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_ref    (ordine_ref(25)),
            KEY idx_stato         (stato_pagamento),
            KEY idx_metodo        (metodo_pagamento),
            KEY idx_email         (acquirente_email(100)),
            KEY idx_data          (data_creazione),
            KEY idx_gateway       (gateway_payment_id(100))
        ) $charset;";

        $sql_buoni = "CREATE TABLE IF NOT EXISTS " . self::table_buoni() . " (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ordine_id        BIGINT UNSIGNED NOT NULL,
            numero           INT UNSIGNED    NOT NULL DEFAULT 1,
            codice_buono     VARCHAR(20)     NOT NULL DEFAULT '',
            importo          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
            destinatario_nome  VARCHAR(200)  NOT NULL DEFAULT '',
            destinatario_email VARCHAR(200)  NOT NULL DEFAULT '',
            data_consegna    DATE            NULL,
            orario_consegna  VARCHAR(10)     NOT NULL DEFAULT '',
            messaggio        TEXT            NULL,
            stato_invio      VARCHAR(30)     NOT NULL DEFAULT 'da_inviare',
            data_invio       DATETIME        NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_codice  (codice_buono(20)),
            KEY idx_ordine         (ordine_id),
            KEY idx_stato          (stato_invio)
        ) $charset;";

        $sql_pdf = "CREATE TABLE IF NOT EXISTS " . self::table_pdf() . " (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ordine_id    BIGINT UNSIGNED NOT NULL,
            buono_id     BIGINT UNSIGNED NULL,
            tipo         VARCHAR(30)     NOT NULL DEFAULT 'buono',
            nome_file    VARCHAR(255)    NOT NULL DEFAULT '',
            percorso     VARCHAR(500)    NOT NULL DEFAULT '',
            url          VARCHAR(500)    NOT NULL DEFAULT '',
            dimensione   INT UNSIGNED    NOT NULL DEFAULT 0,
            data_gen     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ordine (ordine_id),
            KEY idx_buono  (buono_id),
            KEY idx_tipo   (tipo)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_ordini );
        dbDelta( $sql_buoni );
        dbDelta( $sql_pdf );

        update_option( 'bb_db_version', BB_VERSION );
    }

    // ── Genera riferimento ordine ─────────────────────────────────────────────
    public static function genera_ref(): string {
        global $wpdb;
        $ym  = date( 'Ym' );
        $max = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table_ordini() . " WHERE ordine_ref LIKE %s",
                'BO-' . $ym . '-%'
            )
        );
        return 'BO-' . $ym . '-' . str_pad( $max + 1, 4, '0', STR_PAD_LEFT );
    }

    // ── Genera codice buono univoco ───────────────────────────────────────────
    public static function genera_codice(): string {
        global $wpdb;
        $t = self::table_buoni();
        do {
            $code = strtoupper( substr( md5( uniqid( '', true ) ), 0, 4 ) . '-'
                              . substr( md5( uniqid( '', true ) ), 0, 4 ) . '-'
                              . substr( md5( uniqid( '', true ) ), 0, 4 ) );
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE codice_buono = %s", $code ) );
        } while ( $exists );
        return $code;
    }

    // ── Inserimento ordine ────────────────────────────────────────────────────
    public static function insert_ordine( array $data ): int|false {
        global $wpdb;
        $data['ordine_ref'] = $data['ordine_ref'] ?? self::genera_ref();
        $ok = $wpdb->insert( self::table_ordini(), $data );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    // ── Inserimento buono ─────────────────────────────────────────────────────
    public static function insert_buono( array $data ): int|false {
        global $wpdb;
        $data['codice_buono'] = $data['codice_buono'] ?? self::genera_codice();
        $ok = $wpdb->insert( self::table_buoni(), $data );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    // ── Get ordine ────────────────────────────────────────────────────────────
    public static function get_ordine( int $id ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_ordini() . " WHERE id = %d", $id
        ) ) ?: null;
    }

    // ── Get ordine by gateway ID ──────────────────────────────────────────────
    public static function get_ordine_by_gateway( string $gateway_id ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_ordini() . " WHERE gateway_payment_id = %s", $gateway_id
        ) ) ?: null;
    }

    // ── Get ordine by ref ─────────────────────────────────────────────────────
    public static function get_ordine_by_ref( string $ref ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_ordini() . " WHERE ordine_ref = %s", $ref
        ) ) ?: null;
    }

    // ── Get buoni per ordine ──────────────────────────────────────────────────
    public static function get_buoni( int $ordine_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table_buoni() . " WHERE ordine_id = %d ORDER BY numero ASC",
            $ordine_id
        ) ) ?: [];
    }

    // ── Get singolo buono ─────────────────────────────────────────────────────
    public static function get_buono( int $id ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_buoni() . " WHERE id = %d", $id
        ) ) ?: null;
    }

    // ── Aggiornamento ordine ──────────────────────────────────────────────────
    public static function update_ordine( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update( self::table_ordini(), $data, [ 'id' => $id ] );
    }

    // ── Aggiornamento buono ───────────────────────────────────────────────────
    public static function update_buono( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update( self::table_buoni(), $data, [ 'id' => $id ] );
    }

    // ── Lista ordini paginata ─────────────────────────────────────────────────
    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $defaults = [
            'per_page'  => 25,
            'paged'     => 1,
            'orderby'   => 'data_creazione',
            'order'     => 'DESC',
            'stato'     => '',
            'metodo'    => '',
            'search'    => '',
        ];
        $args    = wp_parse_args( $args, $defaults );
        $t       = self::table_ordini();
        $where   = '1=1';
        $params  = [];

        if ( $args['stato'] ) {
            $where   .= ' AND stato_pagamento = %s';
            $params[] = $args['stato'];
        }
        if ( $args['metodo'] ) {
            $where   .= ' AND metodo_pagamento = %s';
            $params[] = $args['metodo'];
        }
        if ( $args['search'] ) {
            $where   .= ' AND (acquirente_nome LIKE %s OR acquirente_cognome LIKE %s OR acquirente_email LIKE %s OR ordine_ref LIKE %s)';
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params  = array_merge( $params, [ $s, $s, $s, $s ] );
        }

        $orderby = in_array( $args['orderby'], ['id','data_creazione','importo_totale','stato_pagamento','acquirente_cognome','ordine_ref'], true )
            ? $args['orderby'] : 'data_creazione';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset   = max( 0, ( (int) $args['paged'] - 1 ) * (int) $args['per_page'] );
        $per_page = max( 1, (int) $args['per_page'] );

        $count_sql = "SELECT COUNT(*) FROM $t WHERE $where";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
            : (int) $wpdb->get_var( $count_sql );

        $select_sql = "SELECT * FROM $t WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $row_params = array_merge( $params, [ $per_page, $offset ] );
        $rows = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$row_params ) ) ?: [];

        return [
            'total'      => $total,
            'per_page'   => $per_page,
            'total_pages'=> (int) ceil( $total / $per_page ),
            'rows'       => $rows,
        ];
    }

    // ── Ordini in attesa di reminder ──────────────────────────────────────────
    public static function get_ordini_da_ricordare(): array {
        global $wpdb;
        $t = self::table_ordini();
        // Ordini con metodo=fattura, stato=inviato, creati >30 giorni fa
        $sql = "SELECT * FROM $t
                WHERE metodo_pagamento = 'fattura'
                  AND stato_pagamento IN ('inviato','sospeso')
                  AND data_creazione <= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND (data_ultimo_reminder IS NULL OR data_ultimo_reminder <= DATE_SUB(NOW(), INTERVAL 30 DAY))";
        return $wpdb->get_results( $sql ) ?: [];
    }

    // ── Salva PDF ─────────────────────────────────────────────────────────────
    public static function save_pdf( int $ordine_id, ?int $buono_id, string $tipo, string $nome, string $percorso, string $url, int $dim ): int|false {
        global $wpdb;
        $ok = $wpdb->insert( self::table_pdf(), [
            'ordine_id'  => $ordine_id,
            'buono_id'   => $buono_id,
            'tipo'       => $tipo,
            'nome_file'  => $nome,
            'percorso'   => $percorso,
            'url'        => $url,
            'dimensione' => $dim,
        ] );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    // ── Get PDF ───────────────────────────────────────────────────────────────
    public static function get_pdf( int $id ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_pdf() . " WHERE id = %d", $id
        ) ) ?: null;
    }

    // ── Get PDF by ordine ─────────────────────────────────────────────────────
    public static function get_pdfs_by_ordine( int $ordine_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table_pdf() . " WHERE ordine_id = %d ORDER BY data_gen DESC",
            $ordine_id
        ) ) ?: [];
    }

    // ── Eliminazione ordine (e cascata) ───────────────────────────────────────
    public static function delete_ordine( int $id ): void {
        global $wpdb;
        // Elimina file PDF fisici
        $pdfs = self::get_pdfs_by_ordine( $id );
        foreach ( $pdfs as $pdf ) {
            if ( ! empty( $pdf->percorso ) && file_exists( $pdf->percorso ) ) {
                @unlink( $pdf->percorso );
            }
        }
        $wpdb->delete( self::table_pdf(), [ 'ordine_id' => $id ] );
        $wpdb->delete( self::table_buoni(), [ 'ordine_id' => $id ] );
        $wpdb->delete( self::table_ordini(), [ 'id' => $id ] );
    }

    // ── Enum labels ──────────────────────────────────────────────────────────
    public static function get_stati(): array {
        return [
            'pending'      => __( 'In attesa', 'botega-buoni' ),
            'sospeso'      => __( 'Sospeso', 'botega-buoni' ),
            'inviato'      => __( 'Fattura inviata', 'botega-buoni' ),
            'pagato'       => __( 'Pagato', 'botega-buoni' ),
            'annullato'    => __( 'Annullato', 'botega-buoni' ),
            'errore_invio' => __( 'Errore invio', 'botega-buoni' ),
        ];
    }

    public static function get_metodi(): array {
        return [
            'fattura' => __( 'Fattura', 'botega-buoni' ),
            'stripe'  => __( 'Carta / Twint (Stripe)', 'botega-buoni' ),
            'paypal'  => __( 'PayPal', 'botega-buoni' ),
        ];
    }

    public static function get_importi(): array {
        return [
            25  => 'CHF 25.–',
            50  => 'CHF 50.–',
            100 => 'CHF 100.–',
            150 => 'CHF 150.–',
        ];
    }

    // ── Formattazione CHF ─────────────────────────────────────────────────────
    public static function fmt_chf( float $amount ): string {
        $parts = number_format( $amount, 2, '.', "'" );
        return 'CHF ' . $parts;
    }

    // ── Stato invio buoni ─────────────────────────────────────────────────────
    public static function get_stati_invio(): array {
        return [
            'da_inviare' => __( 'Da inviare', 'botega-buoni' ),
            'inviato'    => __( 'Inviato', 'botega-buoni' ),
            'errore'     => __( 'Errore invio', 'botega-buoni' ),
        ];
    }
}
