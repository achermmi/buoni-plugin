<?php
defined( 'ABSPATH' ) || exit;
/**
 * WP-Admin: pagina gestione ruoli Botega Buoni
 *
 * @var string $msg          messaggio di feedback (opzionale)
 * @var array  $tutti_utenti tutti gli utenti WP
 */
$roles_map   = BB_Roles::roles();
?>
<div class="wrap bb-wrap">
<h1><?php esc_html_e( 'Ruoli e accessi – Botega Buoni', 'botega-buoni' ); ?></h1>

<?php if ( ! empty( $msg ) ) : ?>
<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $msg ); ?></p></div>
<?php endif; ?>

<p class="description" style="max-width:680px; margin-bottom:20px;">
    <?php esc_html_e( 'Gli utenti con uno dei ruoli seguenti possono accedere al pannello di gestione dei buoni regalo dal frontend (shortcode [bb_admin_buoni]) e dalla dashboard WordPress, senza avere accesso alle altre impostazioni del sito.', 'botega-buoni' ); ?>
</p>

<!-- TABELLA RUOLI ATTIVI -->
<?php foreach ( $roles_map as $slug => $label ) :
    $membri = get_users( [ 'role' => $slug ] );
?>
<div class="bb-card" style="max-width:860px; margin-bottom:20px;">
    <h2>
        👥 <?php echo esc_html( $label ); ?>
        <small style="font-size:12px;color:#888;font-weight:normal">
            — <code><?php echo esc_html( $slug ); ?></code>
            — cap: <code><?php echo esc_html( BB_Roles::CAP ); ?></code>
        </small>
    </h2>

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
                    <input type="hidden" name="bb_user_id"    value="<?php echo (int) $m->ID; ?>">
                    <input type="hidden" name="bb_role_slug"  value="<?php echo esc_attr( $slug ); ?>">
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
    // Cerca una pagina che usi lo shortcode
    $pages = get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        's'              => 'bb_admin_buoni',
        'posts_per_page' => 5,
    ] );
    if ( $pages ) :
    ?>
    <p><?php esc_html_e( 'Pagine che usano questo shortcode:', 'botega-buoni' ); ?></p>
    <ul>
        <?php foreach ( $pages as $pg ) : ?>
        <li>
            <a href="<?php echo esc_url( get_permalink( $pg->ID ) ); ?>" target="_blank">
                <?php echo esc_html( $pg->post_title ); ?>
            </a>
            — <a href="<?php echo esc_url( get_edit_post_link( $pg->ID ) ); ?>">
                <?php esc_html_e( 'Modifica', 'botega-buoni' ); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else : ?>
    <p class="description">
        <?php esc_html_e( 'Crea una pagina WordPress (es. "Gestione buoni") e inserisci lo shortcode [bb_admin_buoni] nel contenuto. Solo gli utenti con i ruoli abilitati vedranno il pannello.', 'botega-buoni' ); ?>
    </p>
    <?php endif; ?>

    <p class="description" style="margin-top:12px;">
        <?php esc_html_e( 'Accesso per ruolo:', 'botega-buoni' ); ?>
        <strong><?php esc_html_e( 'Amministratore', 'botega-buoni' ); ?></strong> —
        <?php echo esc_html( implode( ' — ', array_values( BB_Roles::roles() ) ) ); ?>
    </p>
    <p class="description">
        <?php esc_html_e( 'Solo gli Amministratori possono eliminare definitivamente un ordine. Gli altri ruoli possono visualizzare, cambiare stato, inviare buoni e richiami.', 'botega-buoni' ); ?>
    </p>
</div>

</div>
