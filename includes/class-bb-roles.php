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
        return $all[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
    }

    // ── Pagina WP-Admin gestione ruoli ────────────────────────────────────────
    public static function render_page(): void {
        $msg = '';

        if ( isset( $_POST['bb_ruoli_action'] ) && check_admin_referer( 'bb_ruoli', 'bb_ruoli_nonce' ) ) {
            $user_id    = (int) ( $_POST['bb_user_id']  ?? 0 );
            $role_slug  = sanitize_key( $_POST['bb_role_slug'] ?? '' );
            $action     = sanitize_key( $_POST['bb_ruoli_action'] );
            $valid_roles = array_keys( self::roles() );

            $user = $user_id ? get_user_by( 'id', $user_id ) : null;

            if ( $user && in_array( $role_slug, $valid_roles, true ) ) {
                if ( $action === 'aggiungi' ) {
                    $user->add_role( $role_slug );
                    $user->add_cap( self::CAP );
                    $msg = sprintf(
                        '✅ ' . __( '%s aggiunto al ruolo "%s".', 'botega-buoni' ),
                        esc_html( $user->display_name ),
                        esc_html( self::roles()[ $role_slug ] )
                    );
                } elseif ( $action === 'rimuovi' ) {
                    $user->remove_role( $role_slug );
                    // Rimuovi la cap solo se non ha altri ruoli del plugin
                    $still_has_role = false;
                    foreach ( $valid_roles as $r ) {
                        if ( $r !== $role_slug && in_array( $r, $user->roles, true ) ) {
                            $still_has_role = true;
                            break;
                        }
                    }
                    if ( ! $still_has_role ) {
                        $user->remove_cap( self::CAP );
                    }
                    $msg = sprintf(
                        '✅ ' . __( '%s rimosso dal ruolo "%s".', 'botega-buoni' ),
                        esc_html( $user->display_name ),
                        esc_html( self::roles()[ $role_slug ] )
                    );
                }
            }
        }

        $tutti_utenti = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
        include BB_PLUGIN_DIR . 'templates/page-roles.php';
    }
}
