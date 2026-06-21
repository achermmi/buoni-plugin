<?php
defined( 'ABSPATH' ) || exit;
/**
 * Admin: dettaglio ordine
 * @var object $ordine
 * @var array  $buoni
 * @var array  $pdfs
 * @var array  $stati
 * @var array  $metodi
 */
$stati_invio = BB_Database::get_stati_invio();
$set_paid_url = wp_nonce_url(
    admin_url( 'admin.php?page=botega-buoni&bb_action=set_pagato&id=' . $ordine->id ),
    'bb_admin_action', 'bb_nonce'
);
$set_ann_url = wp_nonce_url(
    admin_url( 'admin.php?page=botega-buoni&bb_action=set_annullato&id=' . $ordine->id ),
    'bb_admin_action', 'bb_nonce'
);
$back_url = admin_url( 'admin.php?page=botega-buoni' );
?>
<div class="wrap bb-wrap">
    <h1>
        <a href="<?php echo esc_url( $back_url ); ?>" class="button">← <?php esc_html_e( 'Ordini', 'botega-buoni' ); ?></a>
        &nbsp;
        <?php printf( esc_html__( 'Ordine %s', 'botega-buoni' ), esc_html( $ordine->ordine_ref ) ); ?>
    </h1>
    <hr class="wp-header-end">

    <div class="bb-view-grid">

        <!-- COLONNA SINISTRA -->
        <div class="bb-view-col-main">

            <!-- Acquirente -->
            <div class="bb-card">
                <h2><?php esc_html_e( 'Acquirente', 'botega-buoni' ); ?></h2>
                <table class="widefat striped">
                    <tbody>
                    <tr><td><strong><?php esc_html_e( 'Nome', 'botega-buoni' ); ?></strong></td>
                        <td><?php echo esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ); ?></td></tr>
                    <tr><td><strong><?php esc_html_e( 'Indirizzo', 'botega-buoni' ); ?></strong></td>
                        <td><?php echo esc_html( $ordine->acquirente_indirizzo . ', ' . $ordine->acquirente_cap . ' ' . $ordine->acquirente_localita ); ?></td></tr>
                    <tr><td><strong><?php esc_html_e( 'E-mail', 'botega-buoni' ); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr( $ordine->acquirente_email ); ?>"><?php echo esc_html( $ordine->acquirente_email ); ?></a></td></tr>
                    <tr><td><strong><?php esc_html_e( 'Telefono', 'botega-buoni' ); ?></strong></td>
                        <td><?php echo esc_html( $ordine->acquirente_telefono ); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Buoni -->
            <div class="bb-card">
                <h2><?php esc_html_e( 'Buoni inclusi', 'botega-buoni' ); ?></h2>
                <?php if ( $buoni ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e( 'Codice', 'botega-buoni' ); ?></th>
                            <th><?php esc_html_e( 'Destinatario', 'botega-buoni' ); ?></th>
                            <th><?php esc_html_e( 'Consegna', 'botega-buoni' ); ?></th>
                            <th><?php esc_html_e( 'Stato', 'botega-buoni' ); ?></th>
                            <th><?php esc_html_e( 'Azioni', 'botega-buoni' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $buoni as $b ) :
                        $color = $b->stato_invio === 'inviato' ? '#28a745' : ( $b->stato_invio === 'errore' ? '#dc3545' : '#fd7e14' );
                    ?>
                    <tr>
                        <td><?php echo (int) $b->numero; ?></td>
                        <td><code><?php echo esc_html( $b->codice_buono ); ?></code></td>
                        <td>
                            <?php echo esc_html( $b->destinatario_nome ); ?><br>
                            <span style="font-size:11px;color:#666"><?php echo esc_html( $b->destinatario_email ); ?></span>
                        </td>
                        <td>
                            <?php echo $b->data_consegna ? esc_html( date_i18n( 'd.m.Y', strtotime( $b->data_consegna ) ) ) : '—'; ?>
                            <?php echo $b->orario_consegna ? esc_html( ' ' . $b->orario_consegna ) : ''; ?>
                        </td>
                        <td><span class="bb-badge" style="background:<?php echo $color; ?>"><?php echo esc_html( $stati_invio[ $b->stato_invio ] ?? $b->stato_invio ); ?></span></td>
                        <td>
                            <button class="button button-small bb-rigenera-pdf"
                                    data-ordine-id="<?php echo (int) $ordine->id; ?>"
                                    data-tipo="buono"
                                    data-buono-id="<?php echo (int) $b->id; ?>">
                                📄 <?php esc_html_e( 'PDF', 'botega-buoni' ); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p><?php esc_html_e( 'Nessun buono trovato.', 'botega-buoni' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- PDF allegati -->
            <?php if ( $pdfs ) : ?>
            <div class="bb-card">
                <h2><?php esc_html_e( 'Documenti PDF', 'botega-buoni' ); ?></h2>
                <table class="widefat striped">
                    <tbody>
                    <?php foreach ( $pdfs as $pdf ) : ?>
                    <tr>
                        <td><?php echo esc_html( $pdf->tipo === 'buono' ? 'Buono #' . ( $pdf->buono_id ? '?' : '' ) : 'Fattura' ); ?></td>
                        <td><?php echo esc_html( $pdf->nome_file ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $pdf->data_gen ) ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=botega-buoni&bb_dl_pdf=' . $pdf->id ) ); ?>" class="button button-small">⬇ Download</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div>

        <!-- COLONNA DESTRA: riepilogo + azioni -->
        <div class="bb-view-col-side">

            <div class="bb-card">
                <h2><?php esc_html_e( 'Riepilogo ordine', 'botega-buoni' ); ?></h2>
                <table class="widefat">
                    <tbody>
                    <tr><td><?php esc_html_e( 'Rif. ordine', 'botega-buoni' ); ?></td>
                        <td><strong><?php echo esc_html( $ordine->ordine_ref ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Data', 'botega-buoni' ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $ordine->data_creazione ) ) ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Importo per buono', 'botega-buoni' ); ?></td>
                        <td><?php echo esc_html( BB_Database::fmt_chf( (float) $ordine->importo ) ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Quantità', 'botega-buoni' ); ?></td>
                        <td><?php echo (int) $ordine->quantita; ?></td></tr>
                    <tr><td><strong><?php esc_html_e( 'Totale', 'botega-buoni' ); ?></strong></td>
                        <td><strong style="font-size:13px"><?php echo esc_html( BB_Database::fmt_chf( (float) $ordine->importo_totale ) ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Metodo', 'botega-buoni' ); ?></td>
                        <td><?php echo esc_html( $metodi[ $ordine->metodo_pagamento ] ?? $ordine->metodo_pagamento ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Stato', 'botega-buoni' ); ?></td>
                        <td>
                            <?php
                            $color_stato = [
                                'pagato' => '#28a745', 'inviato' => '#007bff',
                                'sospeso' => '#fd7e14', 'pending' => '#6c757d',
                                'annullato' => '#dc3545', 'errore_invio' => '#dc3545',
                            ][ $ordine->stato_pagamento ] ?? '#888';
                            ?>
                            <span class="bb-badge" style="background:<?php echo $color_stato; ?>">
                                <?php echo esc_html( $stati[ $ordine->stato_pagamento ] ?? $ordine->stato_pagamento ); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ( $ordine->reminder_count ) : ?>
                    <tr><td><?php esc_html_e( 'Reminder inviati', 'botega-buoni' ); ?></td>
                        <td><?php echo (int) $ordine->reminder_count; ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bb-card">
                <h2><?php esc_html_e( 'Azioni', 'botega-buoni' ); ?></h2>
                <div class="bb-actions-list">

                    <?php if ( ! in_array( $ordine->stato_pagamento, [ 'pagato', 'annullato' ], true ) ) : ?>
                    <a href="<?php echo esc_url( $set_paid_url ); ?>" class="button button-primary bb-btn-full">
                        ✓ <?php esc_html_e( 'Segna come Pagato e invia buoni', 'botega-buoni' ); ?>
                    </a>
                    <?php endif; ?>

                    <button class="button bb-btn-full bb-invia-buoni" data-id="<?php echo (int) $ordine->id; ?>">
                        📧 <?php esc_html_e( 'Invia buoni ai destinatari', 'botega-buoni' ); ?>
                    </button>

                    <?php if ( $ordine->metodo_pagamento === 'fattura' ) : ?>
                    <button class="button bb-btn-full bb-invia-richiamo" data-id="<?php echo (int) $ordine->id; ?>">
                        🔔 <?php esc_html_e( 'Invia richiamo pagamento', 'botega-buoni' ); ?>
                    </button>
                    <?php endif; ?>

                    <button class="button bb-btn-full bb-rigenera-pdf"
                            data-ordine-id="<?php echo (int) $ordine->id; ?>"
                            data-tipo="fattura">
                        📄 <?php esc_html_e( 'Rigenera fattura PDF', 'botega-buoni' ); ?>
                    </button>

                    <?php if ( ! in_array( $ordine->stato_pagamento, [ 'annullato' ], true ) ) : ?>
                    <a href="<?php echo esc_url( $set_ann_url ); ?>" class="button bb-btn-full bb-btn-danger"
                       onclick="return confirm('<?php esc_attr_e( 'Annullare questo ordine?', 'botega-buoni' ); ?>')">
                        ✕ <?php esc_html_e( 'Annulla ordine', 'botega-buoni' ); ?>
                    </a>
                    <?php endif; ?>

                    <button class="button bb-btn-full bb-btn-delete" data-id="<?php echo (int) $ordine->id; ?>">
                        🗑 <?php esc_html_e( 'Elimina ordine', 'botega-buoni' ); ?>
                    </button>

                </div>
            </div>

        </div>
    </div>
</div>
