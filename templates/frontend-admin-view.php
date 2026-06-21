<?php
defined( 'ABSPATH' ) || exit;
/**
 * Frontend Admin – Dettaglio ordine
 *
 * @var object $ordine
 * @var array  $buoni
 * @var array  $pdfs
 * @var array  $stati
 * @var array  $metodi
 * @var array  $stati_invio
 * @var string $current_url
 * @var string $back_url
 */

$is_admin    = current_user_can( 'manage_options' );
$color_stato = [
    'pagato'       => '#28a745',
    'inviato'      => '#007bff',
    'sospeso'      => '#fd7e14',
    'pending'      => '#6c757d',
    'annullato'    => '#dc3545',
    'errore_invio' => '#dc3545',
];
$badge_color = $color_stato[ $ordine->stato_pagamento ] ?? '#888';
?>
<div class="bb-fa-wrap" id="bb-fa-view">

    <!-- Header -->
    <div class="bb-fa-header">
        <a href="<?php echo esc_url( $back_url ); ?>" class="bb-fa-btn bb-fa-btn-outline bb-fa-back-btn">
            ← <?php esc_html_e( 'Ordini', 'botega-buoni' ); ?>
        </a>
        <h2 class="bb-fa-title">
            <?php printf( esc_html__( 'Ordine %s', 'botega-buoni' ), esc_html( $ordine->ordine_ref ) ); ?>
        </h2>
        <span class="bb-fa-badge" style="background:<?php echo esc_attr( $badge_color ); ?>">
            <?php echo esc_html( $stati[ $ordine->stato_pagamento ] ?? $ordine->stato_pagamento ); ?>
        </span>
    </div>

    <!-- Messaggi inline (JS) -->
    <div id="bb-fa-msg" class="bb-fa-msg" style="display:none"></div>

    <div class="bb-fa-view-grid">

        <!-- COLONNA PRINCIPALE -->
        <div class="bb-fa-col-main">

            <!-- Acquirente -->
            <div class="bb-fa-card">
                <h3 class="bb-fa-card-title">👤 <?php esc_html_e( 'Acquirente', 'botega-buoni' ); ?></h3>
                <div class="bb-fa-info-grid">
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Nome', 'botega-buoni' ); ?></span>
                    <span><?php echo esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ); ?></span>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Indirizzo', 'botega-buoni' ); ?></span>
                    <span><?php echo esc_html( $ordine->acquirente_indirizzo . ', ' . $ordine->acquirente_cap . ' ' . $ordine->acquirente_localita ); ?></span>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'E-mail', 'botega-buoni' ); ?></span>
                    <span><a href="mailto:<?php echo esc_attr( $ordine->acquirente_email ); ?>"><?php echo esc_html( $ordine->acquirente_email ); ?></a></span>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Telefono', 'botega-buoni' ); ?></span>
                    <span><?php echo esc_html( $ordine->acquirente_telefono ); ?></span>
                </div>
            </div>

            <!-- Buoni -->
            <div class="bb-fa-card">
                <h3 class="bb-fa-card-title">🎁 <?php esc_html_e( 'Buoni inclusi', 'botega-buoni' ); ?></h3>
                <?php if ( $buoni ) : ?>
                <div class="bb-fa-buoni-list">
                    <?php foreach ( $buoni as $b ) :
                        $inv_color = $b->stato_invio === 'inviato' ? '#28a745' : ( $b->stato_invio === 'errore' ? '#dc3545' : '#fd7e14' );
                    ?>
                    <div class="bb-fa-buono-row">
                        <div class="bb-fa-buono-num">
                            #<?php echo (int) $b->numero; ?>
                        </div>
                        <div class="bb-fa-buono-info">
                            <div class="bb-fa-buono-dest">
                                <strong><?php echo esc_html( $b->destinatario_nome ?: '—' ); ?></strong>
                                <span class="bb-fa-buono-email"><?php echo esc_html( $b->destinatario_email ); ?></span>
                            </div>
                            <div class="bb-fa-buono-meta">
                                <?php if ( $b->data_consegna ) : ?>
                                📅 <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $b->data_consegna ) ) ); ?>
                                <?php if ( $b->orario_consegna ) echo esc_html( ' ' . $b->orario_consegna ); ?>
                                <?php endif; ?>
                                &nbsp;
                                <code class="bb-fa-codice"><?php echo esc_html( $b->codice_buono ); ?></code>
                            </div>
                            <?php if ( $b->messaggio ) : ?>
                            <div class="bb-fa-buono-msg">&ldquo;<?php echo esc_html( $b->messaggio ); ?>&rdquo;</div>
                            <?php endif; ?>
                        </div>
                        <div class="bb-fa-buono-stato">
                            <span class="bb-fa-badge bb-fa-badge-sm" style="background:<?php echo esc_attr( $inv_color ); ?>">
                                <?php echo esc_html( $stati_invio[ $b->stato_invio ] ?? $b->stato_invio ); ?>
                            </span>
                            <button class="bb-fa-btn bb-fa-btn-xs bb-fa-action"
                                    data-action="rigenera_pdf"
                                    data-ordine-id="<?php echo (int) $ordine->id; ?>"
                                    data-tipo="buono"
                                    data-buono-id="<?php echo (int) $b->id; ?>">
                                📄
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p class="bb-fa-empty-inline"><?php esc_html_e( 'Nessun buono.', 'botega-buoni' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- PDF disponibili -->
            <?php if ( $pdfs ) : ?>
            <div class="bb-fa-card">
                <h3 class="bb-fa-card-title">📄 <?php esc_html_e( 'Documenti', 'botega-buoni' ); ?></h3>
                <ul class="bb-fa-pdf-list">
                    <?php foreach ( $pdfs as $pdf ) :
                        $dl_url = add_query_arg( [
                            'bb_fa_pdf' => $pdf->id,
                            'bb_nonce'  => wp_create_nonce( 'bb_fa_pdf_' . $pdf->id ),
                        ], $current_url );
                    ?>
                    <li>
                        <span class="bb-fa-pdf-tipo"><?php echo esc_html( $pdf->tipo === 'fattura' ? '🧾 Fattura' : '🎁 Buono' ); ?></span>
                        <a href="<?php echo esc_url( $dl_url ); ?>" target="_blank" class="bb-fa-pdf-link">
                            ⬇ <?php echo esc_html( $pdf->nome_file ); ?>
                        </a>
                        <span class="bb-fa-pdf-data"><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $pdf->data_gen ) ) ); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>

        <!-- COLONNA LATERALE: riepilogo + azioni -->
        <div class="bb-fa-col-side">

            <!-- Riepilogo ordine -->
            <div class="bb-fa-card">
                <h3 class="bb-fa-card-title">📋 <?php esc_html_e( 'Riepilogo', 'botega-buoni' ); ?></h3>
                <div class="bb-fa-info-grid">
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Rif.', 'botega-buoni' ); ?></span>
                    <strong><?php echo esc_html( $ordine->ordine_ref ); ?></strong>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Data', 'botega-buoni' ); ?></span>
                    <span><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $ordine->data_creazione ) ) ); ?></span>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Importo', 'botega-buoni' ); ?></span>
                    <span><?php echo esc_html( BB_Database::fmt_chf( (float) $ordine->importo ) ); ?> × <?php echo (int) $ordine->quantita; ?></span>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Totale', 'botega-buoni' ); ?></span>
                    <strong style="font-size:15px"><?php echo esc_html( BB_Database::fmt_chf( (float) $ordine->importo_totale ) ); ?></strong>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Metodo', 'botega-buoni' ); ?></span>
                    <span><?php echo esc_html( $metodi[ $ordine->metodo_pagamento ] ?? $ordine->metodo_pagamento ); ?></span>
                    <?php if ( $ordine->reminder_count ) : ?>
                    <span class="bb-fa-lbl"><?php esc_html_e( 'Reminder', 'botega-buoni' ); ?></span>
                    <span><?php echo (int) $ordine->reminder_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Azioni -->
            <div class="bb-fa-card">
                <h3 class="bb-fa-card-title">⚙️ <?php esc_html_e( 'Azioni', 'botega-buoni' ); ?></h3>
                <div class="bb-fa-actions" data-ordine-id="<?php echo (int) $ordine->id; ?>">

                    <?php if ( ! in_array( $ordine->stato_pagamento, [ 'pagato', 'annullato' ], true ) ) : ?>
                    <button class="bb-fa-btn bb-fa-btn-success bb-fa-btn-full bb-fa-action"
                            data-action="set_stato"
                            data-id="<?php echo (int) $ordine->id; ?>"
                            data-stato="pagato">
                        ✓ <?php esc_html_e( 'Segna come Pagato + invia buoni', 'botega-buoni' ); ?>
                    </button>
                    <?php endif; ?>

                    <button class="bb-fa-btn bb-fa-btn-full bb-fa-action"
                            data-action="invia_buoni"
                            data-id="<?php echo (int) $ordine->id; ?>">
                        📧 <?php esc_html_e( 'Invia buoni ai destinatari', 'botega-buoni' ); ?>
                    </button>

                    <?php if ( $ordine->metodo_pagamento === 'fattura' ) : ?>
                    <button class="bb-fa-btn bb-fa-btn-full bb-fa-action"
                            data-action="invia_richiamo"
                            data-id="<?php echo (int) $ordine->id; ?>">
                        🔔 <?php esc_html_e( 'Invia richiamo pagamento', 'botega-buoni' ); ?>
                    </button>
                    <?php endif; ?>

                    <button class="bb-fa-btn bb-fa-btn-full bb-fa-action"
                            data-action="rigenera_pdf"
                            data-ordine-id="<?php echo (int) $ordine->id; ?>"
                            data-tipo="fattura">
                        📄 <?php esc_html_e( 'Rigenera fattura PDF', 'botega-buoni' ); ?>
                    </button>

                    <?php if ( ! in_array( $ordine->stato_pagamento, [ 'annullato' ], true ) ) : ?>
                    <button class="bb-fa-btn bb-fa-btn-danger bb-fa-btn-full bb-fa-action"
                            data-action="set_stato"
                            data-id="<?php echo (int) $ordine->id; ?>"
                            data-stato="annullato">
                        ✕ <?php esc_html_e( 'Annulla ordine', 'botega-buoni' ); ?>
                    </button>
                    <?php endif; ?>

                    <?php if ( $is_admin ) : ?>
                    <button class="bb-fa-btn bb-fa-btn-full bb-fa-action bb-fa-btn-outline-danger"
                            data-action="delete"
                            data-id="<?php echo (int) $ordine->id; ?>">
                        🗑 <?php esc_html_e( 'Elimina ordine', 'botega-buoni' ); ?>
                    </button>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

</div><!-- /.bb-fa-wrap -->
