<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_Roles
 *
 * Gestisce la capability `bb_gestione_buoni` e i ruoli personalizzati:
 *   - bb_segretariato  → "Segretariato"
 *   - bb_comitato      → "Comitato"
 *   - bb_cassiere      → "Cassiere"
 *
 * Tutti e tre i ruoli hanno permessi di Sottoscrittore + `bb_gestione_buoni`.
 * Gli Amministratori ricevono `bb_gestione_buoni` in aggiunta.
 */
class BB_Roles {

    const CAP = 'bb_gestione_buoni';

    // Mappa slug → etichetta
    public static function roles(): array {
        return [
            'bb_segretariato' => __( 'Segretariato – Buoni Regalo', 'botega-buoni' ),
            'bb_comitato'     => __( 'Comitato – Buoni Regalo', 'botega-buoni' ),
            'bb_cassiere'     => __( 'Cassiere – Buoni Regalo', 'botega-buoni' ),
        ];
    }

    // ── Rimuove ruoli legacy (precedenti versioni o altri plugin) ─────────────
    // ── Rimuove ruoli senza prefisso bb_ creati da altri plugin ───────────────
    public static function remove_legacy_roles(): void {
        foreach ( [ 'cassiere', 'comitato', 'segretariato', 'membro_comitato' ] as $slug ) {
            if ( get_role( $slug ) ) {
                // Migra gli utenti al ruolo bb_ equivalente
                foreach ( get_users( [ 'role' => $slug ] ) as $u ) {
                    $u->set_role( 'bb_' . $slug );
                }
                remove_role( $slug );
            }
        }
    }

    // ── Attivazione: crea ruoli + assegna cap agli admin ──────────────────────
    public static function setup(): void {
        // Capability base Sottoscrittore + nostra cap
        $caps = [
            'read'          => true,
            self::CAP       => true,
        ];

        $wp_roles = wp_roles();
        foreach ( self::roles() as $slug => $label ) {
            $role = get_role( $slug );
            if ( ! $role ) {
                add_role( $slug, $label, $caps );
            } else {
                // Aggiorna etichetta se cambiata
                if ( isset( $wp_roles->roles[ $slug ] ) && $wp_roles->roles[ $slug ]['name'] !== $label ) {
                    $wp_roles->roles[ $slug ]['name']          = $label;
                    $wp_roles->role_objects[ $slug ]->name     = $label;
                    update_option( $wp_roles->role_key, $wp_roles->roles );
                }
                // Assicura che la cap sia presente anche su ruoli già esistenti
                if ( ! $role->has_cap( self::CAP ) ) {
                    $role->add_cap( self::CAP );
                }
            }
        }

        // Assegna la cap agli Amministratori
        $admin_role = get_role( 'administrator' );
        if ( $admin_role && ! $admin_role->has_cap( self::CAP ) ) {
            $admin_role->add_cap( self::CAP );
        }
    }

    // ── Disinstallazione: rimuove ruoli + capability ───────────────────────────
    public static function teardown(): void {
        foreach ( array_keys( self::roles() ) as $slug ) {
            remove_role( $slug );
        }

        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->remove_cap( self::CAP );
        }

        // Rimuovi la cap da tutti gli utenti che la possedevano individualmente
        $users = get_users( [ 'capability' => self::CAP ] );
        foreach ( $users as $user ) {
            $user->remove_cap( self::CAP );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Verifica se l'utente corrente ha accesso al plugin. */
    public static function current_user_can(): bool {
        return is_user_logged_in() && current_user_can( self::CAP );
    }

    /** Restituisce la capability (usato in add_menu_page / check). */
    public static function cap(): string {
        return self::CAP;
    }

    /** Restituisce il ruolo principale dell'utente corrente (tra quelli del plugin). */
    public static function get_current_role(): string {
        $user = wp_get_current_user();
        if ( ! $user->ID ) return '';
        foreach ( array_keys( self::roles() ) as $slug ) {
            if ( in_array( $slug, $user->roles, true ) ) return $slug;
        }
        if ( in_array( 'administrator', $user->roles, true ) ) return 'administrator';
        return '';
    }

    /** Restituisce l'etichetta leggibile del ruolo. */
    public static function get_role_label( string $slug ): string {
        $roles = self::roles();
        $all   = array_merge( $roles, [ 'administrator' => __( 'Amministratore', 'botega-buoni' ) ] );
        if ( isset( $all[ $slug ] ) ) return $all[ $slug ];
        // Fallback: etichetta dal registro WP
        $wp_roles = wp_roles()->roles;
        if ( isset( $wp_roles[ $slug ]['name'] ) ) {
            return translate_user_role( $wp_roles[ $slug ]['name'] );
        }
        return ucfirst( str_replace( '_', ' ', $slug ) );
    }

    // ── Ruoli WP che hanno la capability (esclude quelli built-in) ───────────

    /**
     * Restituisce i ruoli del plugin (sempre abilitati, non modificabili).
     */
    public static function plugin_role_slugs(): array {
        return array_keys( self::roles() );
    }

    /**
     * Restituisce i ruoli WP aggiuntivi (non del plugin) che hanno bb_gestione_buoni.
     * Salvato come opzione: array di slug.
     */
    public static function get_extra_roles(): array {
        return (array) get_option( 'bb_extra_roles', [] );
    }

    /**
     * Salva i ruoli aggiuntivi e sincronizza le capability.
     *
     * @param array $new_slugs Slug dei ruoli aggiuntivi da abilitare.
     */
    public static function save_extra_roles( array $new_slugs ): void {
        $plugin_slugs = self::plugin_role_slugs();
        $all_roles    = wp_roles()->roles;

        $new_slugs = array_filter( $new_slugs, static function ( $s ) use ( $all_roles, $plugin_slugs ) {
            // Validi: esistono, non sono ruoli del plugin, non sono administrator
            return isset( $all_roles[ $s ] ) && ! in_array( $s, $plugin_slugs, true ) && $s !== 'administrator';
        } );
        $new_slugs = array_values( array_unique( $new_slugs ) );

        $old_slugs = self::get_extra_roles();

        // Aggiungi cap ai nuovi ruoli
        foreach ( array_diff( $new_slugs, $old_slugs ) as $slug ) {
            $role = get_role( $slug );
            if ( $role ) $role->add_cap( self::CAP );
        }

        // Rimuovi cap dai ruoli de-selezionati
        foreach ( array_diff( $old_slugs, $new_slugs ) as $slug ) {
            $role = get_role( $slug );
            if ( $role ) $role->remove_cap( self::CAP );
        }

        update_option( 'bb_extra_roles', $new_slugs );
    }

    // ── Pagina WP-Admin gestione ruoli ────────────────────────────────────────
    public static function render_page(): void {
        $msg = '';

        if ( isset( $_POST['bb_ruoli_action'] ) && check_admin_referer( 'bb_ruoli', 'bb_ruoli_nonce' ) ) {
            $action = sanitize_key( $_POST['bb_ruoli_action'] );

            // ── Salva ruoli con accesso ───────────────────────────────────
            if ( $action === 'save_roles' ) {
                $selected = isset( $_POST['bb_roles_abilitati'] ) && is_array( $_POST['bb_roles_abilitati'] )
                    ? array_map( 'sanitize_key', $_POST['bb_roles_abilitati'] )
                    : [];
                self::save_extra_roles( $selected );
                $msg = '✅ ' . __( 'Accessi aggiornati.', 'botega-buoni' );
            }

            // ── Aggiungi utente a un ruolo ────────────────────────────────
            if ( $action === 'aggiungi' ) {
                $user_id    = (int) ( $_POST['bb_user_id']   ?? 0 );
                $role_slug  = sanitize_key( $_POST['bb_role_slug'] ?? '' );
                $valid_roles = array_merge( self::plugin_role_slugs(), self::get_extra_roles() );
                $user = $user_id ? get_user_by( 'id', $user_id ) : null;

                if ( $user && in_array( $role_slug, $valid_roles, true ) ) {
                    $user->add_role( $role_slug );
                    $user->add_cap( self::CAP );
                    $msg = sprintf(
                        '✅ ' . __( '%s aggiunto al ruolo "%s".', 'botega-buoni' ),
                        esc_html( $user->display_name ),
                        esc_html( self::get_role_label( $role_slug ) )
                    );
                }
            }

            // ── Rimuovi utente da un ruolo ────────────────────────────────
            if ( $action === 'rimuovi' ) {
                $user_id    = (int) ( $_POST['bb_user_id']   ?? 0 );
                $role_slug  = sanitize_key( $_POST['bb_role_slug'] ?? '' );
                $valid_roles = array_merge( self::plugin_role_slugs(), self::get_extra_roles() );
                $user = $user_id ? get_user_by( 'id', $user_id ) : null;

                if ( $user && in_array( $role_slug, $valid_roles, true ) ) {
                    $user->remove_role( $role_slug );
                    // Rimuovi la cap solo se non ha altri ruoli abilitati
                    $has_other = false;
                    foreach ( $valid_roles as $r ) {
                        if ( $r !== $role_slug && in_array( $r, (array) $user->roles, true ) ) {
                            $has_other = true;
                            break;
                        }
                    }
                    if ( ! $has_other ) {
                        $user->remove_cap( self::CAP );
                    }
                    $msg = sprintf(
                        '✅ ' . __( '%s rimosso dal ruolo "%s".', 'botega-buoni' ),
                        esc_html( $user->display_name ),
                        esc_html( self::get_role_label( $role_slug ) )
                    );
                }
            }
        }

        $tutti_utenti = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
        include BB_PLUGIN_DIR . 'templates/page-roles.php';
    }
}
