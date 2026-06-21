<?php
defined( 'ABSPATH' ) || exit;
/**
 * WP-Admin: pagina gestione ruoli Botega Buoni
 *
 * @var string $msg          messaggio di feedback (opzionale)
 * @var array  $tutti_utenti tutti gli utenti WP
 */

$plugin_roles = BB_Roles::roles();           // slug => label dei 3 ruoli del plugin
$extra_roles  = BB_Roles::get_extra_roles(); // slug di ruoli WP aggiuntivi abilitati

// Tutti i ruoli con accesso (plugin + extra)
$enabled_slugs = array_merge( array_keys( $plugin_roles ), $extra_roles );

// Tutti i ruoli WP disponibili (escluso administrator, già sempre incluso)
$all_wp_roles = wp_roles()->roles;
?>
<div class="wrap bb-wrap">
<h1><?php esc_html_e( 'Ruoli e accessi – Botega Buoni', 'botega-buoni' ); ?></h1>

<?php if ( ! empty( $msg ) ) : ?>
<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $msg ); ?></p></div>
<?php endif; ?>

<!-- ── SEZIONE 1: Ruoli con accesso ─────────────────────────────────────── -->
<div class="bb-card" style="max-width:860px; margin-bottom:24px;">
    <h2><?php esc_html_e( 'Ruolo destinatario', 'botega-buoni' ); ?></h2>
    <p class="description" style="margin-bottom:16px;">
        <?php esc_html_e( 'Seleziona i ruoli WordPress che possono accedere al pannello di gestione dei buoni regalo. I ruoli del plugin (evidenziati) sono sempre abilitati.', 'botega-buoni' ); ?>
    </p>

    <form method="post">
        <?php wp_nonce_field( 'bb_ruoli', 'bb_ruoli_nonce' ); ?>
        <input type="hidden" name="bb_ruoli_action" value="save_roles">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 32px; max-width:680px; margin-bottom:20px;">
        <?php
        // Ordina i ruoli: prima quelli del plugin, poi il resto alfabeticamente
        $sorted = [];
        foreach ( $all_wp_roles as $slug => $data ) {
            $sorted[ $slug ] = $data['name'];
        }
        asort( $sorted );

        foreach ( $sorted as $slug => $label ) :
            $is_plugin_role = array_key_exists( $slug, $plugin_roles );
            $is_admin       = ( $slug === 'administrator' );
            $is_checked     = $is_plugin_role || $is_admin || in_array( $slug, $extra_roles, true );
            $is_disabled    = $is_plugin_role || $is_admin;
            $style_extra    = $is_plugin_role ? 'font-weight:600; color:#1d2327;' : '';
        ?>
        <label style="display:flex; align-items:center; gap:6px; padding:3px 0; <?php echo esc_attr( $style_extra ); ?>">
            <input type="checkbox"
                   name="bb_roles_abilitati[]"
                   value="<?php echo esc_attr( $slug ); ?>"
                   <?php checked( $is_checked ); ?>
                   <?php disabled( $is_disabled ); ?>>
            <?php echo esc_html( translate_user_role( $label ) ); ?>
            <?php if ( $is_plugin_role ) : ?>
                <span title="<?php esc_attr_e( 'Ruolo del plugin – sempre abilitato', 'botega-buoni' ); ?>" style="font-size:10px; color:#2271b1;">●</span>
            <?php endif; ?>
        </label>
        <?php if ( $is_disabled ) : ?>
        <input type="hidden" name="bb_roles_abilitati[]" value="<?php echo esc_attr( $slug ); ?>">
        <?php endif; ?>
        <?php endforeach; ?>
        </div>

        <button type="submit" class="button button-primary"><?php esc_html_e( 'Salva accessi', 'botega-buoni' ); ?></button>
    </form>
</div>

<!-- ── SEZIONE 2: Utenti per ogni ruolo abilitato ───────────────────────── -->
<h2 style="margin-top:0;"><?php esc_html_e( 'Utenti per ruolo', 'botega-buoni' ); ?></h2>
<p class="description" style="max-width:680px; margin-bottom:20px;">
    <?php esc_html_e( 'Assegna o rimuovi utenti dai ruoli abilitati al plugin.', 'botega-buoni' ); ?>
</p>

<?php
// Costruisci la mappa completa slug => label per i ruoli abilitati (plugin + extra)
$active_roles_map = $plugin_roles;
foreach ( $extra_roles as $slug ) {
    if ( isset( $all_wp_roles[ $slug ] ) ) {
        $active_roles_map[ $slug ] = translate_user_role( $all_wp_roles[ $slug ]['name'] );
    }
}
?>

<?php foreach ( $active_roles_map as $slug => $label ) :
    $membri = get_users( [ 'role' => $slug ] );
?>
<div class="bb-card" style="max-width:860px; margin-bottom:20px;">
    <h3>
        👥 <?php echo esc_html( $label ); ?>
        <small style="font-size:11px; color:#888; font-weight:normal;">
            — <code><?php echo esc_html( $slug ); ?></code>
        </small>
    </h3>

    <!-- Utenti con questo ruolo -->
    <?php if ( $membri ) : ?>
    <table class="widefat striped" style="margin-bottom:16px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Utente', 'botega-buoni' ); ?></th>
                <th><?php esc_html_e( 'Email', 'botega-buoni' ); ?></th>
                <th><?php esc_html_e( 'Azione', 'botega-buoni' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $membri as $m ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $m->display_name ); ?></strong></td>
            <td><?php echo esc_html( $m->user_email ); ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'bb_ruoli', 'bb_ruoli_nonce' ); ?>
                    <input type="hidden" name="bb_user_id"      value="<?php echo (int) $m->ID; ?>">
                    <input type="hidden" name="bb_role_slug"    value="<?php echo esc_attr( $slug ); ?>">
                    <input type="hidden" name="bb_ruoli_action" value="rimuovi">
                    <button type="submit" class="button button-small"
                            onclick="return confirm('<?php printf( esc_attr__( 'Rimuovere %s dal ruolo %s?', 'botega-buoni' ), esc_attr( $m->display_name ), esc_attr( $label ) ); ?>')">
                        ✕ <?php esc_html_e( 'Rimuovi', 'botega-buoni' ); ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p class="description" style="margin-bottom:12px;">
        <?php esc_html_e( 'Nessun utente con questo ruolo.', 'botega-buoni' ); ?>
    </p>
    <?php endif; ?>

    <!-- Aggiungi utente -->
    <form method="post" class="bb-roles-add-form">
        <?php wp_nonce_field( 'bb_ruoli', 'bb_ruoli_nonce' ); ?>
        <input type="hidden" name="bb_role_slug"    value="<?php echo esc_attr( $slug ); ?>">
        <input type="hidden" name="bb_ruoli_action" value="aggiungi">
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <select name="bb_user_id" class="regular-text" required>
                <option value=""><?php esc_html_e( '— Seleziona utente —', 'botega-buoni' ); ?></option>
                <?php foreach ( $tutti_utenti as $u ) : ?>
                <option value="<?php echo (int) $u->ID; ?>">
                    <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary">
                + <?php printf( esc_html__( 'Aggiungi a %s', 'botega-buoni' ), esc_html( $label ) ); ?>
            </button>
        </div>
    </form>
</div>
<?php endforeach; ?>

<!-- INFO ACCESSO FRONTEND -->
<div class="bb-card" style="max-width:860px; border-top:3px solid #d4e000;">
    <h2><?php esc_html_e( 'Accesso dal frontend', 'botega-buoni' ); ?></h2>
    <p>
        <?php esc_html_e( 'Il pannello di gestione è disponibile anche dal frontend tramite lo shortcode:', 'botega-buoni' ); ?>
        <code>[bb_admin_buoni]</code>
    </p>
    <?php
    // Cerca pagine con [bb_admin_buoni]
    $pages_buoni = get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        's'              => 'bb_admin_buoni',
        'posts_per_page' => 5,
    ] );
    // Cerca pagine con [bb_admin_ruoli]
    $pages_ruoli = get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        's'              => 'bb_admin_ruoli',
        'posts_per_page' => 5,
    ] );
    ?>

    <table class="widefat striped" style="margin-bottom:16px;">
        <thead><tr>
            <th><?php esc_html_e( 'Shortcode', 'botega-buoni' ); ?></th>
            <th><?php esc_html_e( 'Accesso', 'botega-buoni' ); ?></th>
            <th><?php esc_html_e( 'Pagine che lo usano', 'botega-buoni' ); ?></th>
        </tr></thead>
        <tbody>
        <tr>
            <td><code>[bb_admin_buoni]</code></td>
            <td><?php esc_html_e( 'Tutti i ruoli abilitati', 'botega-buoni' ); ?></td>
            <td>
            <?php if ( $pages_buoni ) : ?>
                <?php foreach ( $pages_buoni as $pg ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $pg->ID ) ); ?>" target="_blank"><?php echo esc_html( $pg->post_title ); ?></a>
                    (<a href="<?php echo esc_url( get_edit_post_link( $pg->ID ) ); ?>"><?php esc_html_e( 'modifica', 'botega-buoni' ); ?></a>)&nbsp;
                <?php endforeach; ?>
            <?php else : ?>
                <em><?php esc_html_e( 'Nessuna pagina trovata', 'botega-buoni' ); ?></em>
            <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><code>[bb_admin_ruoli]</code></td>
            <td><?php esc_html_e( 'Solo Amministratori', 'botega-buoni' ); ?></td>
            <td>
            <?php if ( $pages_ruoli ) : ?>
                <?php foreach ( $pages_ruoli as $pg ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $pg->ID ) ); ?>" target="_blank"><?php echo esc_html( $pg->post_title ); ?></a>
                    (<a href="<?php echo esc_url( get_edit_post_link( $pg->ID ) ); ?>"><?php esc_html_e( 'modifica', 'botega-buoni' ); ?></a>)&nbsp;
                <?php endforeach; ?>
            <?php else : ?>
                <em><?php esc_html_e( 'Nessuna pagina trovata', 'botega-buoni' ); ?></em>
            <?php endif; ?>
            </td>
        </tr>
        </tbody>
    </table>

    <p class="description">
        <?php esc_html_e( 'Solo gli Amministratori possono eliminare definitivamente un ordine. Gli altri ruoli possono visualizzare, cambiare stato, inviare buoni e richiami.', 'botega-buoni' ); ?>
    </p>
</div>

</div>
