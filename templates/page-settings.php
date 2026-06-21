<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bb-wrap">
<h1><?php esc_html_e( 'Impostazioni – Botega Buoni Regalo', 'botega-buoni' ); ?></h1>

<form method="post">
<?php wp_nonce_field( 'bb_save_settings', 'bb_nonce' ); ?>
<input type="hidden" name="bb_action" value="save_settings">

<div class="bb-settings-grid">
<div class="bb-settings-col">

    <!-- PAGINA PUBBLICA -->
    <div class="bb-card">
        <h2><?php esc_html_e( 'Pagina pubblica', 'botega-buoni' ); ?></h2>
        <?php $page_url = BB_Pages::get_url(); ?>
        <p><?php esc_html_e( 'La pagina dei buoni regalo è accessibile a:', 'botega-buoni' ); ?>
           <a href="<?php echo esc_url( $page_url ); ?>" target="_blank"><code><?php echo esc_url( $page_url ); ?></code></a></p>
        <p class="description"><?php esc_html_e( 'Shortcode:', 'botega-buoni' ); ?> <code>[bb_buoni_regalo]</code></p>
    </div>

    <!-- METODI PAGAMENTO -->
    <div class="bb-card" style="border-top:3px solid #2c3a00;">
        <h2><?php esc_html_e( 'Metodi di pagamento abilitati', 'botega-buoni' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Abilita i metodi di pagamento che vuoi offrire ai clienti.', 'botega-buoni' ); ?></p>
        <?php foreach ( [ 'fattura' => '🏦 Fattura', 'stripe' => '💳 Carta / Twint (Stripe)', 'paypal' => '🅿 PayPal' ] as $k => $lbl ) : ?>
        <div class="bb-field-check">
            <label>
                <input type="checkbox" name="bb_metodo_<?php echo $k; ?>" value="1"
                       <?php checked( get_option( 'bb_metodo_' . $k, '1' ), '1' ); ?>>
                <?php echo esc_html( $lbl ); ?>
            </label>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- STRIPE -->
    <div class="bb-card" style="border-top:3px solid #635bff;">
        <h2>💳 <?php esc_html_e( 'Stripe – Carte di credito / Twint', 'botega-buoni' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Stripe gestisce carte di credito/debito e Twint. Richiede un account Stripe (stripe.com).', 'botega-buoni' ); ?>
        </p>
        <div class="bb-field">
            <label for="bb_stripe_pub_key"><?php esc_html_e( 'Chiave pubblica (pk_live_ o pk_test_)', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_stripe_pub_key" name="bb_stripe_pub_key"
                   value="<?php echo esc_attr( get_option( 'bb_stripe_pub_key', '' ) ); ?>"
                   class="large-text" placeholder="pk_live_…">
        </div>
        <div class="bb-field">
            <label for="bb_stripe_secret_key"><?php esc_html_e( 'Chiave segreta (sk_live_ o sk_test_)', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_stripe_secret_key" name="bb_stripe_secret_key"
                   value="<?php echo esc_attr( get_option( 'bb_stripe_secret_key', '' ) ); ?>"
                   class="large-text" placeholder="sk_live_…">
        </div>
        <div class="bb-field">
            <label for="bb_stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret (whsec_…)', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_stripe_webhook_secret" name="bb_stripe_webhook_secret"
                   value="<?php echo esc_attr( get_option( 'bb_stripe_webhook_secret', '' ) ); ?>"
                   class="large-text" placeholder="whsec_…">
            <p class="description">
                <?php esc_html_e( 'URL webhook da configurare in Stripe:', 'botega-buoni' ); ?>
                <code><?php echo esc_url( rest_url( 'bb/v1/stripe-webhook' ) ); ?></code><br>
                <?php esc_html_e( 'Evento: checkout.session.completed', 'botega-buoni' ); ?>
            </p>
        </div>
        <div class="bb-field-check">
            <label>
                <input type="checkbox" name="bb_stripe_twint" value="1"
                       <?php checked( get_option( 'bb_stripe_twint', '1' ), '1' ); ?>>
                <?php esc_html_e( 'Abilitare Twint via Stripe (richiede che Twint sia attivo nel tuo account Stripe)', 'botega-buoni' ); ?>
            </label>
        </div>
    </div>

    <!-- PAYPAL -->
    <div class="bb-card" style="border-top:3px solid #003087;">
        <h2>🅿 <?php esc_html_e( 'PayPal', 'botega-buoni' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Richiede un account PayPal Business. Crea un\'app su developer.paypal.com per ottenere le credenziali.', 'botega-buoni' ); ?>
        </p>
        <div class="bb-field">
            <label for="bb_paypal_client_id"><?php esc_html_e( 'Client ID', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_paypal_client_id" name="bb_paypal_client_id"
                   value="<?php echo esc_attr( get_option( 'bb_paypal_client_id', '' ) ); ?>"
                   class="large-text">
        </div>
        <div class="bb-field">
            <label for="bb_paypal_secret"><?php esc_html_e( 'Secret (lascia vuoto per non modificare)', 'botega-buoni' ); ?></label>
            <input type="password" id="bb_paypal_secret" name="bb_paypal_secret"
                   value="" class="regular-text" placeholder="••••••••••••">
        </div>
        <div class="bb-field-check">
            <label>
                <input type="checkbox" name="bb_paypal_sandbox" value="1"
                       <?php checked( get_option( 'bb_paypal_sandbox', '1' ), '1' ); ?>>
                <?php esc_html_e( 'Modalità Sandbox (test)', 'botega-buoni' ); ?>
            </label>
        </div>
        <p class="description">
            <?php esc_html_e( 'URL webhook da configurare in PayPal:', 'botega-buoni' ); ?>
            <code><?php echo esc_url( rest_url( 'bb/v1/paypal-webhook' ) ); ?></code>
        </p>
    </div>

</div>
<div class="bb-settings-col">

    <!-- EMAIL -->
    <div class="bb-card">
        <h2><?php esc_html_e( 'Mittente email', 'botega-buoni' ); ?></h2>
        <div class="bb-field">
            <label for="bb_email_mittente_nome"><?php esc_html_e( 'Nome mittente', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_email_mittente_nome" name="bb_email_mittente_nome"
                   value="<?php echo esc_attr( get_option( 'bb_email_mittente_nome', get_bloginfo( 'name' ) ) ); ?>"
                   class="large-text">
        </div>
        <div class="bb-field">
            <label for="bb_email_mittente_email"><?php esc_html_e( 'Email mittente', 'botega-buoni' ); ?></label>
            <input type="email" id="bb_email_mittente_email" name="bb_email_mittente_email"
                   value="<?php echo esc_attr( get_option( 'bb_email_mittente_email', get_option( 'admin_email' ) ) ); ?>"
                   class="regular-text">
        </div>
        <div class="bb-field">
            <label for="bb_email_admin"><?php esc_html_e( 'Email amministratore (per reminder)', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_email_admin" name="bb_email_admin"
                   value="<?php echo esc_attr( get_option( 'bb_email_admin', 'info@labotegalavizzara.ch' ) ); ?>"
                   class="regular-text" placeholder="es. info@esempio.ch, altro@gmail.com">
        </div>
    </div>

    <!-- TEMPLATE EMAIL -->
    <div class="bb-card">
        <h2><?php esc_html_e( 'Template email', 'botega-buoni' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Variabili disponibili: {{nome}}, {{ref}}, {{importo}}, {{totale}}, {{quantita}}, {{metodo}}, {{data}}, {{iban}}', 'botega-buoni' ); ?><br>
        <?php esc_html_e( 'Per email buono: anche {{dest_nome}}, {{codice}}, {{messaggio}}, {{data_consegna}}', 'botega-buoni' ); ?></p>

        <div class="bb-field">
            <label><?php esc_html_e( 'Email fattura (acquirente)', 'botega-buoni' ); ?></label>
            <textarea name="bb_email_fattura_corpo" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'bb_email_fattura_corpo', '' ) ); ?></textarea>
        </div>
        <div class="bb-field">
            <label><?php esc_html_e( 'Email conferma pagamento (acquirente)', 'botega-buoni' ); ?></label>
            <textarea name="bb_email_conferma_corpo" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'bb_email_conferma_corpo', '' ) ); ?></textarea>
        </div>
        <div class="bb-field">
            <label><?php esc_html_e( 'Email buono regalo (destinatario)', 'botega-buoni' ); ?></label>
            <textarea name="bb_email_buono_corpo" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'bb_email_buono_corpo', '' ) ); ?></textarea>
        </div>
        <div class="bb-field">
            <label><?php esc_html_e( 'Email richiamo pagamento (acquirente)', 'botega-buoni' ); ?></label>
            <textarea name="bb_email_richiamo_corpo" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'bb_email_richiamo_corpo', '' ) ); ?></textarea>
        </div>
    </div>

    <!-- SMTP -->
    <div class="bb-card" style="border-top:3px solid #d4e000;">
        <h2><?php esc_html_e( 'Configurazione SMTP', 'botega-buoni' ); ?></h2>
        <div class="bb-field-check">
            <label>
                <input type="checkbox" name="bb_smtp_abilitato" value="1"
                       <?php checked( get_option( 'bb_smtp_abilitato', '0' ), '1' ); ?>>
                <?php esc_html_e( 'Usa SMTP per inviare le email', 'botega-buoni' ); ?>
            </label>
        </div>
        <div class="bb-field">
            <label for="bb_smtp_host"><?php esc_html_e( 'Host SMTP', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_smtp_host" name="bb_smtp_host"
                   value="<?php echo esc_attr( get_option( 'bb_smtp_host', '' ) ); ?>"
                   class="regular-text" placeholder="mail.infomaniak.com">
        </div>
        <div class="bb-field">
            <label for="bb_smtp_port"><?php esc_html_e( 'Porta', 'botega-buoni' ); ?></label>
            <input type="number" id="bb_smtp_port" name="bb_smtp_port"
                   value="<?php echo esc_attr( get_option( 'bb_smtp_port', '587' ) ); ?>"
                   class="small-text">
            <select name="bb_smtp_secure">
                <?php foreach ( [ 'tls' => 'TLS/STARTTLS (587)', 'ssl' => 'SSL (465)', '' => 'Nessuna' ] as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>" <?php selected( get_option( 'bb_smtp_secure', 'tls' ), $v ); ?>><?php echo esc_html( $l ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bb-field">
            <label for="bb_smtp_user"><?php esc_html_e( 'Utente SMTP', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_smtp_user" name="bb_smtp_user"
                   value="<?php echo esc_attr( get_option( 'bb_smtp_user', '' ) ); ?>"
                   class="regular-text">
        </div>
        <div class="bb-field">
            <label for="bb_smtp_pass"><?php esc_html_e( 'Password SMTP (lascia vuoto per non modificare)', 'botega-buoni' ); ?></label>
            <input type="password" id="bb_smtp_pass" name="bb_smtp_pass"
                   value="" class="regular-text" placeholder="••••••••">
        </div>
    </div>

    <!-- GOOGLE MAPS -->
    <div class="bb-card">
        <h2><?php esc_html_e( 'Autocomplete indirizzo', 'botega-buoni' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Il form usa OpenStreetMap (Nominatim) gratuitamente. Per usare Google Places inserire una chiave API.', 'botega-buoni' ); ?></p>
        <div class="bb-field">
            <label for="bb_google_maps_key"><?php esc_html_e( 'Chiave API Google Maps (facoltativa)', 'botega-buoni' ); ?></label>
            <input type="text" id="bb_google_maps_key" name="bb_google_maps_key"
                   value="<?php echo esc_attr( get_option( 'bb_google_maps_key', '' ) ); ?>"
                   class="large-text" placeholder="AIzaSy…">
        </div>
    </div>

</div>
</div><!-- /.bb-settings-grid -->

<?php submit_button( __( 'Salva impostazioni', 'botega-buoni' ), 'primary large' ); ?>
</form>
</div>
