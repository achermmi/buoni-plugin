<?php
defined( 'ABSPATH' ) || exit;
/**
 * PDF: Buono Regalo
 * @var object $ordine
 * @var object $buono
 */
$logo     = BB_PDF_Manager::logo_b64();
$imp      = BB_Database::fmt_chf( (float) $buono->importo );
$anno     = date( 'Y' );
$dc_fmt   = $buono->data_consegna
    ? date_i18n( 'd.m.Y', strtotime( $buono->data_consegna ) )
    : date_i18n( 'd.m.Y' );
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Buono Regalo</title>
<style>
@page { size: A5 landscape; margin: 0; }
* { box-sizing: border-box; }
body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background: #fff; }

/* Carta: formato A5 orizzontale */
.card {
  width: 210mm;
  height: 148mm;
  position: relative;
  overflow: hidden;
  background: #fff;
}

/* Bordo decorativo */
.card-border {
  position: absolute;
  inset: 6mm;
  border: 2.5px solid #2c3a00;
  border-radius: 4mm;
  pointer-events: none;
}
.card-border-inner {
  position: absolute;
  inset: 8mm;
  border: 0.8px solid #C8960A;
  border-radius: 3mm;
  pointer-events: none;
}

/* Sfondo sfumato laterale sinistro */
.card-bg {
  position: absolute;
  left: 0; top: 0;
  width: 70mm; height: 148mm;
  background: #2c3a00;
}
.card-bg-pattern {
  position: absolute;
  left: 0; top: 0;
  width: 70mm; height: 148mm;
  background: repeating-linear-gradient(
    45deg,
    rgba(212,224,0,0.05) 0px,
    rgba(212,224,0,0.05) 1px,
    transparent 1px,
    transparent 8px
  );
}

/* Logo area sinistra */
.card-left {
  position: absolute;
  left: 0; top: 0;
  width: 70mm; height: 148mm;
  display: table;
}
.card-left-inner {
  display: table-cell;
  vertical-align: middle;
  text-align: center;
  padding: 10mm 5mm;
}
.card-logo img { max-width: 48mm; max-height: 40mm; }
.card-logo-text {
  margin-top: 4mm;
  color: #d4e000;
  font-size: 9pt;
  font-weight: bold;
  letter-spacing: 0.5mm;
}
.card-logo-sub {
  color: rgba(255,255,255,0.7);
  font-size: 7pt;
  margin-top: 1mm;
}
.card-tagline {
  margin-top: 6mm;
  color: rgba(255,255,255,0.55);
  font-size: 7pt;
  font-style: italic;
  line-height: 1.5;
}

/* Contenuto destra */
.card-right {
  position: absolute;
  left: 70mm;
  top: 0;
  width: 140mm;
  height: 148mm;
  padding: 12mm 12mm 10mm 12mm;
}

/* Titolo "Carta Regalo" */
.cr-label {
  font-size: 8pt;
  font-weight: bold;
  color: #888;
  letter-spacing: 2mm;
  text-transform: uppercase;
  margin-bottom: 1mm;
}
.cr-title {
  font-size: 22pt;
  font-weight: bold;
  color: #2c3a00;
  line-height: 1;
  margin-bottom: 3mm;
}

/* Linea decorativa */
.cr-line {
  width: 100%;
  height: 2px;
  background: linear-gradient(to right, #C8960A, #d4e000, transparent);
  margin-bottom: 4mm;
}

/* Importo */
.cr-importo {
  font-size: 32pt;
  font-weight: bold;
  color: #C8960A;
  line-height: 1;
  margin-bottom: 4mm;
}
.cr-importo-label {
  font-size: 7.5pt;
  color: #888;
  margin-bottom: 4mm;
}

/* Dati destinatario */
.cr-dest-table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
.cr-dest-table td { font-size: 9pt; padding: 1mm 2mm; vertical-align: top; }
.cr-dest-table .lbl { color: #888; width: 22mm; font-size: 7.5pt; }
.cr-dest-table .val { color: #2c3a00; font-weight: bold; }

/* Messaggio */
.cr-msg {
  background: #F8F0E0;
  border-left: 3px solid #C8960A;
  border-radius: 1mm;
  padding: 2mm 3mm;
  font-size: 8pt;
  color: #555;
  font-style: italic;
  line-height: 1.5;
  margin-bottom: 3mm;
  min-height: 9mm;
  max-height: 18mm;
  overflow: hidden;
}

/* Footer */
.cr-footer {
  display: table;
  width: 100%;
}
.cr-footer-row { display: table-row; }
.cr-footer-left, .cr-footer-right { display: table-cell; vertical-align: bottom; }
.cr-footer-right { text-align: right; }
.cr-codice-label { font-size: 6.5pt; color: #888; }
.cr-codice {
  font-family: monospace;
  font-size: 10pt;
  font-weight: bold;
  color: #2c3a00;
  letter-spacing: 1mm;
  background: #f5f5f5;
  padding: 0.5mm 2mm;
  border-radius: 1mm;
}
.cr-scadenza {
  font-size: 7pt;
  color: #888;
  font-style: italic;
}

/* Separatore verticale */
.card-sep {
  position: absolute;
  left: 70mm;
  top: 10mm;
  bottom: 10mm;
  width: 1px;
  background: linear-gradient(to bottom, transparent, #C8960A 20%, #C8960A 80%, transparent);
}
</style>
</head>
<body>
<div class="card">
  <!-- Sfondo verde sinistro -->
  <div class="card-bg"></div>
  <div class="card-bg-pattern"></div>

  <!-- Separatore -->
  <div class="card-sep"></div>

  <!-- Bordi decorativi (sopra tutto il contenuto) -->
  <div class="card-border"></div>
  <div class="card-border-inner"></div>

  <!-- COLONNA SINISTRA -->
  <div class="card-left">
    <div class="card-left-inner">
      <div class="card-logo">
        <?php if ( $logo ) : ?>
        <img src="<?php echo $logo; ?>" alt="La Botega">
        <?php else : ?>
        <div style="color:#d4e000;font-size:14pt;font-weight:bold;">La Botega<br><span style="font-size:9pt;color:rgba(255,255,255,0.7)">da la Lavizzara</span></div>
        <?php endif; ?>
      </div>
      <div class="card-logo-text">LA BOTEGA</div>
      <div class="card-logo-sub">da la Lavizzara</div>
      <div class="card-tagline">Prato Sornico<br>Valle Lavizzara · Ticino<br>labotegalavizzara.ch</div>
    </div>
  </div>

  <!-- COLONNA DESTRA -->
  <div class="card-right">
    <div class="cr-label"><?php esc_html_e( 'CARTA REGALO', 'botega-buoni' ); ?></div>
    <div class="cr-title">Buono<br>Regalo</div>
    <div class="cr-line"></div>

    <div class="cr-importo"><?php echo esc_html( $imp ); ?></div>
    <div class="cr-importo-label"><?php esc_html_e( 'da spendere in negozio o online', 'botega-buoni' ); ?></div>

    <table class="cr-dest-table" cellpadding="0" cellspacing="0">
      <?php if ( $buono->destinatario_nome ) : ?>
      <tr>
        <td class="lbl"><?php esc_html_e( 'Per:', 'botega-buoni' ); ?></td>
        <td class="val"><?php echo esc_html( $buono->destinatario_nome ); ?></td>
      </tr>
      <?php endif; ?>
      <tr>
        <td class="lbl"><?php esc_html_e( 'Data:', 'botega-buoni' ); ?></td>
        <td class="val"><?php echo esc_html( $dc_fmt ); ?></td>
      </tr>
    </table>

    <?php if ( ! empty( $buono->messaggio ) ) : ?>
    <div class="cr-msg">&ldquo;<?php echo esc_html( $buono->messaggio ); ?>&rdquo;</div>
    <?php endif; ?>

    <div class="cr-footer">
      <div class="cr-footer-row">
        <div class="cr-footer-left">
          <div class="cr-scadenza"><?php esc_html_e( 'Il buono regalo non scade mai.', 'botega-buoni' ); ?></div>
        </div>
        <div class="cr-footer-right">
          <div class="cr-codice-label"><?php esc_html_e( 'Codice:', 'botega-buoni' ); ?></div>
          <div class="cr-codice"><?php echo esc_html( $buono->codice_buono ); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
