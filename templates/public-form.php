<?php
defined( 'ABSPATH' ) || exit;
/**
 * Template form pubblico acquisto buoni regalo
 * @var bool   $success
 * @var bool   $cancelled
 * @var string $bb_metodo
 * @var string $bb_ref
 * @var string $error
 * @var array  $prefill
 */

// Riprendi prefill dal cookie (ritorno da Stripe/PayPal)
if ( empty( $prefill ) && ! empty( $_COOKIE['bb_prefill'] ) ) {
    $ck_key  = sanitize_key( $_COOKIE['bb_prefill'] );
    $prefill = get_transient( 'bb_prefill_' . $ck_key ) ?: [];
    if ( $prefill ) {
        delete_transient( 'bb_prefill_' . $ck_key );
        setcookie( 'bb_prefill', '', time() - 3600, '/', '', is_ssl(), true );
    }
}

$p = fn( string $k ) => esc_attr( $prefill[ $k ] ?? '' );

$importi          = BB_Database::get_importi();
$metodi_labels    = BB_Database::get_metodi();
$metodi_abilitati = [];
foreach ( [ 'fattura', 'stripe', 'paypal' ] as $m ) {
    if ( get_option( 'bb_metodo_' . $m, '1' ) === '1' ) $metodi_abilitati[] = $m;
}
$today            = date( 'Y-m-d' );
$slot_oggi        = BB_Public::get_time_slots( $today );
?>
<div class="bb-wrap" id="bb-buoni-wrap">

<?php if ( $success ) : ?>
<!-- ── SUCCESSO ──────────────────────────────────────── -->
<div class="bb-success-box">
    <div class="bb-success-icon">🎁</div>
    <h3><?php esc_html_e( 'Ordine ricevuto!', 'botega-buoni' ); ?></h3>
    <?php if ( $bb_metodo === 'fattura' ) : ?>
    <p><?php printf(
        esc_html__( 'Grazie! La tua fattura per l\'ordine %s è stata inviata alla tua email.', 'botega-buoni' ),
        '<strong>' . esc_html( $bb_ref ) . '</strong>'
    ); ?></p>
    <div class="bb-info-box">
        📧 <?php esc_html_e( 'Trovi la fattura con le istruzioni per il pagamento nell\'email che ti abbiamo inviato.', 'botega-buoni' ); ?><br>
        🎁 <?php esc_html_e( 'I buoni regalo saranno inviati ai destinatari dopo la ricezione del pagamento.', 'botega-buoni' ); ?>
    </div>
    <?php else : ?>
    <p><?php printf(
        esc_html__( 'Il pagamento per l\'ordine %s è stato confermato.', 'botega-buoni' ),
        '<strong>' . esc_html( $bb_ref ) . '</strong>'
    ); ?></p>
    <div class="bb-info-box">
        📧 <?php esc_html_e( 'Hai ricevuto una conferma via email e i buoni sono stati inviati ai destinatari.', 'botega-buoni' ); ?>
    </div>
    <?php endif; ?>
    <a href="<?php echo esc_url( BB_Pages::get_url() . ( ! empty( $_GET['bb_prefill'] ) ? '?bb_prefill=' . esc_attr( $_GET['bb_prefill'] ) : '' ) ); ?>" class="bb-btn bb-btn-secondary">
        🎁 <?php esc_html_e( 'Acquista un altro buono', 'botega-buoni' ); ?>
    </a>
</div>

<?php elseif ( $cancelled ) : ?>
<div class="bb-error-box">
    <strong>⚠️ <?php esc_html_e( 'Pagamento annullato.', 'botega-buoni' ); ?></strong>
    <?php esc_html_e( 'Il pagamento è stato annullato. Puoi riprovare quando vuoi.', 'botega-buoni' ); ?>
</div>

<?php else : ?>

<?php if ( $error ) : ?>
<div class="bb-error-box" role="alert">
    <strong>⚠️</strong> <?php echo esc_html( $error ); ?>
</div>
<?php endif; ?>

<!-- ── FORM ──────────────────────────────────────────── -->
<form method="post" id="bb-form" novalidate
      action="<?php echo esc_url( BB_Pages::get_url() ); ?>">
    <?php wp_nonce_field( 'bb_public_form', 'bb_nonce' ); ?>
    <input type="hidden" name="bb_action" value="acquista_buono">

    <div class="bb-layout">

        <!-- ── COLONNA DESTRA: form ──────────────────────── -->
        <div class="bb-col-form">

             
            <p class="bb-descrizione">
                <?php esc_html_e( 'Non puoi sbagliare con una carta regalo.', 'botega-buoni' ); ?><br>
                <?php esc_html_e( 'Scegli un importo e scrivi un messaggio personalizzato per rendere tuo questo regalo.', 'botega-buoni' ); ?>
            </p>

            <!-- SEZIONE: Dati acquirente -->
            <div class="bb-section">
                <h3 class="bb-section-title"><?php esc_html_e( 'I tuoi dati', 'botega-buoni' ); ?></h3>

                <div class="bb-row-2">
                    <div class="bb-field bb-required">
                        <label for="acq_nome"><?php esc_html_e( 'Nome', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="text" id="acq_nome" name="acq_nome"
                               value="<?php echo $p( 'nome' ); ?>"
                               placeholder="<?php esc_attr_e( 'Nome', 'botega-buoni' ); ?>"
                               required autocomplete="given-name">
                    </div>
                    <div class="bb-field bb-required">
                        <label for="acq_cognome"><?php esc_html_e( 'Cognome', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="text" id="acq_cognome" name="acq_cognome"
                               value="<?php echo $p( 'cognome' ); ?>"
                               placeholder="<?php esc_attr_e( 'Cognome', 'botega-buoni' ); ?>"
                               required autocomplete="family-name">
                    </div>
                </div>

                <div class="bb-field bb-required">
                    <label for="acq_indirizzo"><?php esc_html_e( 'Indirizzo', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                    <input type="text" id="acq_indirizzo" name="acq_indirizzo"
                           value="<?php echo $p( 'indirizzo' ); ?>"
                           placeholder="<?php esc_attr_e( 'Via / Strada…', 'botega-buoni' ); ?>"
                           required autocomplete="address-line1"
                           data-bb-autocomplete="indirizzo">
                </div>

                <div class="bb-row-2">
                    <div class="bb-field bb-required">
                        <label for="acq_cap"><?php esc_html_e( 'CAP', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="text" id="acq_cap" name="acq_cap"
                               value="<?php echo $p( 'cap' ); ?>"
                               placeholder="<?php esc_attr_e( 'CAP', 'botega-buoni' ); ?>"
                               required autocomplete="postal-code" maxlength="10"
                               data-bb-autocomplete="cap">
                    </div>
                    <div class="bb-field bb-required">
                        <label for="acq_localita"><?php esc_html_e( 'Località', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="text" id="acq_localita" name="acq_localita"
                               value="<?php echo $p( 'localita' ); ?>"
                               placeholder="<?php esc_attr_e( 'Comune / Città', 'botega-buoni' ); ?>"
                               required autocomplete="address-level2"
                               data-bb-autocomplete="localita">
                    </div>
                </div>

                <div class="bb-row-2">
                    <div class="bb-field bb-required">
                        <label for="acq_email"><?php esc_html_e( 'E-mail', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="email" id="acq_email" name="acq_email"
                               value="<?php echo $p( 'email' ); ?>"
                               placeholder="<?php esc_attr_e( 'email@esempio.ch', 'botega-buoni' ); ?>"
                               required autocomplete="email">
                    </div>
                    <div class="bb-field bb-required">
                        <label for="acq_telefono"><?php esc_html_e( 'Telefono', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="tel" id="acq_telefono" name="acq_telefono"
                               value="<?php echo $p( 'telefono' ); ?>"
                               placeholder="<?php esc_attr_e( '+41 …', 'botega-buoni' ); ?>"
                               required autocomplete="tel">
                    </div>
                </div>
            </div>

            <!-- SEZIONE: Importo -->
            <div class="bb-section">
                <label class="bb-label-section"><?php esc_html_e( 'Importo', 'botega-buoni' ); ?></label>
                <div class="bb-importo-btns" role="group" aria-label="<?php esc_attr_e( 'Scegli importo', 'botega-buoni' ); ?>">
                    <?php foreach ( $importi as $val => $label ) : ?>
                    <label class="bb-importo-btn<?php echo $val === 25 ? ' selected' : ''; ?>">
                        <input type="radio" name="importo" value="<?php echo (int) $val; ?>"
                               <?php checked( $val, 25 ); ?> class="bb-importo-radio">
                        <?php echo esc_html( $label ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SEZIONE: Quantità -->
            <div class="bb-section bb-section-inline">
                <label class="bb-label-section"><?php esc_html_e( 'Quantità', 'botega-buoni' ); ?></label>
                <div class="bb-qty-wrap">
                    <button type="button" class="bb-qty-btn" id="bb-qty-minus" aria-label="<?php esc_attr_e( 'Diminuisci quantità', 'botega-buoni' ); ?>">−</button>
                    <input type="number" id="bb-qty" name="quantita" value="1" min="1" max="10"
                           class="bb-qty-input" readonly>
                    <button type="button" class="bb-qty-btn" id="bb-qty-plus" aria-label="<?php esc_attr_e( 'Aumenta quantità', 'botega-buoni' ); ?>">+</button>
                </div>
            </div>

            <!-- SEZIONE: Per chi -->
            <div class="bb-section">
                <label class="bb-label-section"><?php esc_html_e( 'Per chi è il buono regalo?', 'botega-buoni' ); ?></label>
                <div class="bb-perchi-btns" role="group">
                    <label class="bb-perchi-btn selected" id="bb-lbl-altri">
                        <input type="radio" name="per_chi" value="altri" checked class="bb-perchi-radio">
                        <?php esc_html_e( 'Per qualcun altro', 'botega-buoni' ); ?>
                    </label>
                    <label class="bb-perchi-btn" id="bb-lbl-me">
                        <input type="radio" name="per_chi" value="me" class="bb-perchi-radio">
                        <?php esc_html_e( 'Per me', 'botega-buoni' ); ?>
                    </label>
                </div>
            </div>

            <!-- SEZIONE: Destinatari (generata dinamicamente da JS) -->
            <div id="bb-destinatari-wrap">

                <!-- Template vuoto per JS clonazione (nascosto) -->
                <template id="bb-dest-template">
                    <div class="bb-section bb-dest-block" data-index="__IDX__">
                        <h4 class="bb-dest-header"></h4><!-- Buono #N – compilato da JS -->

                        <div class="bb-field bb-required">
                            <label><?php esc_html_e( 'Email del destinatario', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                            <input type="email" name="dest_email___IDX__"
                                   placeholder="<?php esc_attr_e( 'email@esempio.ch', 'botega-buoni' ); ?>"
                                   class="bb-dest-email" required>
                        </div>

                        <div class="bb-field">
                            <label><?php esc_html_e( 'Nome del destinatario', 'botega-buoni' ); ?></label>
                            <input type="text" name="dest_nome___IDX__"
                                   placeholder="<?php esc_attr_e( 'Nome e cognome', 'botega-buoni' ); ?>"
                                   class="bb-dest-nome">
                        </div>

                        <div class="bb-row-2">
                            <div class="bb-field">
                                <label><?php esc_html_e( 'Data di consegna', 'botega-buoni' ); ?></label>
                                <input type="date" name="data_consegna___IDX__"
                                       value="<?php echo esc_attr( $today ); ?>"
                                       min="<?php echo esc_attr( $today ); ?>"
                                       class="bb-dest-data">
                            </div>
                            <div class="bb-field">
                                <label><?php esc_html_e( 'Orario della consegna', 'botega-buoni' ); ?></label>
                                <select name="orario_consegna___IDX__" class="bb-dest-orario">
                                    <option value=""><?php esc_html_e( 'Ora', 'botega-buoni' ); ?></option>
                                    <?php foreach ( $slot_oggi as $slot ) : ?>
                                    <option value="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p class="bb-note"><?php esc_html_e( 'Il buono regalo non scade mai.', 'botega-buoni' ); ?></p>

                        <div class="bb-field bb-msg-field">
                            <label><?php esc_html_e( 'Messaggio', 'botega-buoni' ); ?></label>
                            <textarea name="messaggio___IDX__" rows="3"
                                      placeholder="<?php esc_attr_e( 'Scrivi un messaggio personalizzato…', 'botega-buoni' ); ?>"
                                      class="bb-dest-msg"></textarea>
                        </div>
                    </div>
                </template>

                <!-- Blocco "Per me" (single, condiviso) -->
                <div id="bb-porme-block" class="bb-section" style="display:none">
                    <h4 class="bb-dest-header"><?php esc_html_e( 'Dettagli consegna', 'botega-buoni' ); ?></h4>

                    <div class="bb-field bb-required">
                        <label for="me_email"><?php esc_html_e( 'Email destinatario', 'botega-buoni' ); ?> <span class="bb-req">*</span></label>
                        <input type="email" id="me_email" name="me_email"
                               placeholder="<?php esc_attr_e( 'Verrà pre-compilato con la tua email', 'botega-buoni' ); ?>">
                    </div>

                    <div class="bb-field">
                        <label for="me_dest_nome"><?php esc_html_e( 'Nome del destinatario', 'botega-buoni' ); ?></label>
                        <input type="text" id="me_dest_nome" name="me_dest_nome"
                               placeholder="<?php esc_attr_e( 'Tuo nome e cognome', 'botega-buoni' ); ?>">
                    </div>

                    <div class="bb-row-2">
                        <div class="bb-field">
                            <label for="me_data"><?php esc_html_e( 'Data di consegna', 'botega-buoni' ); ?></label>
                            <input type="date" id="me_data" name="me_data_consegna"
                                   value="<?php echo esc_attr( $today ); ?>"
                                   min="<?php echo esc_attr( $today ); ?>">
                        </div>
                        <div class="bb-field">
                            <label for="me_orario"><?php esc_html_e( 'Orario della consegna', 'botega-buoni' ); ?></label>
                            <select id="me_orario" name="me_orario_consegna">
                                <option value=""><?php esc_html_e( 'Ora', 'botega-buoni' ); ?></option>
                                <?php foreach ( $slot_oggi as $slot ) : ?>
                                <option value="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="bb-note"><?php esc_html_e( 'Il buono regalo non scade mai.', 'botega-buoni' ); ?></p>

                    <div class="bb-field">
                        <label for="me_messaggio"><?php esc_html_e( 'Messaggio', 'botega-buoni' ); ?></label>
                        <textarea id="me_messaggio" name="me_messaggio" rows="3"
                                  placeholder="<?php esc_attr_e( 'Scrivi un messaggio personalizzato…', 'botega-buoni' ); ?>"></textarea>
                    </div>
                </div>

                <!-- Container per blocchi "Per qualcun altro" -->
                <div id="bb-altri-blocks"></div>

                <!-- Checkbox messaggio uguale (appare solo con >1 buono e "per altri") -->
                <div id="bb-msg-uguale-wrap" class="bb-section" style="display:none">
                    <label class="bb-check-label">
                        <input type="checkbox" name="messaggio_uguale" id="bb-msg-uguale" value="1">
                        <span class="bb-check-box"></span>
                        <span><?php esc_html_e( 'Messaggio uguale per tutti i buoni', 'botega-buoni' ); ?></span>
                    </label>
                    <div id="bb-msg-comune-wrap" style="display:none" class="bb-field" style="margin-top:12px">
                        <label for="bb-msg-comune"><?php esc_html_e( 'Messaggio comune', 'botega-buoni' ); ?></label>
                        <textarea id="bb-msg-comune" name="messaggio_comune" rows="3"
                                  placeholder="<?php esc_attr_e( 'Messaggio per tutti i buoni…', 'botega-buoni' ); ?>"></textarea>
                    </div>
                </div>

            </div><!-- /#bb-destinatari-wrap -->

            <!-- SEZIONE: Metodo pagamento -->
            <?php if ( count( $metodi_abilitati ) > 1 || ( count( $metodi_abilitati ) === 1 && $metodi_abilitati[0] !== 'fattura' ) ) : ?>
            <div class="bb-section">
                <label class="bb-label-section"><?php esc_html_e( 'Metodo di pagamento', 'botega-buoni' ); ?></label>
                <div class="bb-metodo-btns" role="group">
                    <?php foreach ( $metodi_abilitati as $m ) :
                        $icons = [
                            'fattura' => '🏦',
                            'stripe'  => '💳',
                            'paypal'  => '🅿',
                        ];
                        $labels = [
                            'fattura' => __( 'Fattura', 'botega-buoni' ),
                            'stripe'  => __( 'Carta / Twint', 'botega-buoni' ),
                            'paypal'  => __( 'PayPal', 'botega-buoni' ),
                        ];
                    ?>
                    <label class="bb-metodo-btn<?php echo $m === $metodi_abilitati[0] ? ' selected' : ''; ?>">
                        <input type="radio" name="metodo_pagamento" value="<?php echo esc_attr( $m ); ?>"
                               <?php checked( $m, $metodi_abilitati[0] ); ?> class="bb-metodo-radio">
                        <span class="bb-metodo-icon"><?php echo $icons[ $m ] ?? ''; ?></span>
                        <?php echo esc_html( $labels[ $m ] ?? $m ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Note per metodo -->
                <div id="bb-metodo-note" class="bb-metodo-note"></div>
            </div>
            <?php else : ?>
            <input type="hidden" name="metodo_pagamento" value="<?php echo esc_attr( $metodi_abilitati[0] ?? 'fattura' ); ?>">
            <?php endif; ?>

            <!-- PULSANTE -->
            <div class="bb-section bb-submit-section">
                <div class="bb-totale-riepilogo">
                    <?php esc_html_e( 'Totale:', 'botega-buoni' ); ?>
                    <strong id="bb-totale-display">CHF 25.–</strong>
                </div>
                <button type="submit" id="bb-submit" class="bb-btn bb-btn-primary bb-btn-full">
                    <?php esc_html_e( 'Acquista ora', 'botega-buoni' ); ?>
                </button>
            </div>

        </div><!-- /.bb-col-form -->
    </div><!-- /.bb-layout -->
</form>

<?php endif; ?>
</div><!-- /.bb-wrap -->
