<?php
defined( 'ABSPATH' ) || exit;
/**
 * Frontend Admin – Lista ordini
 *
 * @var array  $ordini
 * @var int    $total
 * @var int    $total_pages
 * @var int    $page
 * @var int    $per_page
 * @var array  $stati
 * @var array  $metodi
 * @var string $stato      filtro attivo
 * @var string $search
 * @var string $current_url
 */

$color_stato = [
    'pagato'       => '#28a745',
    'inviato'      => '#007bff',
    'sospeso'      => '#fd7e14',
    'pending'      => '#6c757d',
    'annullato'    => '#dc3545',
    'errore_invio' => '#dc3545',
];

$current_role = BB_Roles::get_current_role();
$is_admin     = current_user_can( 'manage_options' );
?>
<div class="bb-fa-wrap" id="bb-fa-list">

    <!-- Header -->
    <div class="bb-fa-header">
        <h2 class="bb-fa-title">
            🎁 <?php esc_html_e( 'Buoni Regalo – Ordini', 'botega-buoni' ); ?>
        </h2>
        <span class="bb-fa-role-badge">
            <?php echo esc_html( BB_Roles::get_role_label( $current_role ) ); ?>
        </span>
    </div>

    <!-- Filtri stato (tab) -->
    <div class="bb-fa-tabs">
        <a href="<?php echo esc_url( remove_query_arg( [ 'bb_fa_stato', 'bb_fa_pg' ], $current_url ) ); ?>"
           class="bb-fa-tab<?php echo $stato === '' ? ' active' : ''; ?>">
            <?php esc_html_e( 'Tutti', 'botega-buoni' ); ?>
            <span class="bb-fa-tab-count"><?php echo (int) $total; ?></span>
        </a>
        <?php foreach ( $stati as $slug => $label ) : ?>
        <a href="<?php echo esc_url( add_query_arg( [ 'bb_fa_stato' => $slug, 'bb_fa_pg' => 1 ], $current_url ) ); ?>"
           class="bb-fa-tab<?php echo $stato === $slug ? ' active' : ''; ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Barra ricerca -->
    <form method="get" class="bb-fa-search-form" action="<?php echo esc_url( $current_url ); ?>">
        <?php
        // Preserva eventuali query param non nostri (es. slug pagina)
        foreach ( $_GET as $k => $v ) {
            if ( ! in_array( $k, [ 'bb_fa_s', 'bb_fa_pg', 's' ], true ) ) {
                echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
            }
        }
        ?>
        <div class="bb-fa-search-wrap">
            <input type="text" name="bb_fa_s" value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Cerca per nome, email, riferimento…', 'botega-buoni' ); ?>"
                   class="bb-fa-search-input">
            <button type="submit" class="bb-fa-btn bb-fa-btn-sm">🔍</button>
            <?php if ( $search ) : ?>
            <a href="<?php echo esc_url( remove_query_arg( 'bb_fa_s', $current_url ) ); ?>"
               class="bb-fa-btn bb-fa-btn-sm bb-fa-btn-outline">✕</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabella ordini -->
    <?php if ( $ordini ) : ?>
    <div class="bb-fa-table-wrap">
        <table class="bb-fa-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Rif.', 'botega-buoni' ); ?></th>
                    <th><?php esc_html_e( 'Acquirente', 'botega-buoni' ); ?></th>
                    <th><?php esc_html_e( 'Importo', 'botega-buoni' ); ?></th>
                    <th><?php esc_html_e( 'Metodo', 'botega-buoni' ); ?></th>
                    <th><?php esc_html_e( 'Stato', 'botega-buoni' ); ?></th>
                    <th><?php esc_html_e( 'Data', 'botega-buoni' ); ?></th>
                    <th><?php esc_html_e( 'Azioni', 'botega-buoni' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $ordini as $o ) :
                $view_url   = add_query_arg( 'bb_fa_view', $o->id, $current_url );
                $badge_color = $color_stato[ $o->stato_pagamento ] ?? '#888';
            ?>
            <tr class="bb-fa-tr" data-id="<?php echo (int) $o->id; ?>">
                <td>
                    <a href="<?php echo esc_url( $view_url ); ?>" class="bb-fa-ref-link">
                        <?php echo esc_html( $o->ordine_ref ); ?>
                    </a>
                </td>
                <td>
                    <strong><?php echo esc_html( $o->acquirente_cognome . ' ' . $o->acquirente_nome ); ?></strong>
                    <br><small><?php echo esc_html( $o->acquirente_email ); ?></small>
                </td>
                <td>
                    <strong><?php echo esc_html( BB_Database::fmt_chf( (float) $o->importo_totale ) ); ?></strong>
                    <br><small><?php echo (int) $o->quantita; ?> × <?php echo esc_html( BB_Database::fmt_chf( (float) $o->importo ) ); ?></small>
                </td>
                <td><?php echo esc_html( $metodi[ $o->metodo_pagamento ] ?? $o->metodo_pagamento ); ?></td>
                <td>
                    <span class="bb-fa-badge" style="background:<?php echo esc_attr( $badge_color ); ?>">
                        <?php echo esc_html( $stati[ $o->stato_pagamento ] ?? $o->stato_pagamento ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $o->data_creazione ) ) ); ?></td>
                <td class="bb-fa-td-actions">
                    <a href="<?php echo esc_url( $view_url ); ?>" class="bb-fa-btn bb-fa-btn-sm">
                        👁 <?php esc_html_e( 'Dettaglio', 'botega-buoni' ); ?>
                    </a>
                    <?php if ( ! in_array( $o->stato_pagamento, [ 'pagato', 'annullato' ], true ) ) : ?>
                    <button class="bb-fa-btn bb-fa-btn-sm bb-fa-btn-success bb-fa-action"
                            data-action="set_stato"
                            data-id="<?php echo (int) $o->id; ?>"
                            data-stato="pagato">
                        ✓ <?php esc_html_e( 'Pagato', 'botega-buoni' ); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginazione -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="bb-fa-pagination">
        <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
            $url_pg = add_query_arg( 'bb_fa_pg', $i, $current_url );
        ?>
        <a href="<?php echo esc_url( $url_pg ); ?>"
           class="bb-fa-pg-btn<?php echo $i === $page ? ' active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        <span class="bb-fa-pg-info">
            <?php printf(
                esc_html__( '%d di %d ordini', 'botega-buoni' ),
                count( $ordini ),
                $total
            ); ?>
        </span>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="bb-fa-empty">
        <?php if ( $search || $stato ) : ?>
        <p>🔍 <?php esc_html_e( 'Nessun ordine corrisponde ai criteri di ricerca.', 'botega-buoni' ); ?></p>
        <a href="<?php echo esc_url( $current_url ); ?>" class="bb-fa-btn bb-fa-btn-outline">
            <?php esc_html_e( 'Mostra tutti', 'botega-buoni' ); ?>
        </a>
        <?php else : ?>
        <p>🎁 <?php esc_html_e( 'Nessun ordine ancora presente.', 'botega-buoni' ); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.bb-fa-wrap -->
