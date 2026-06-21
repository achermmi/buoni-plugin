<?php
defined( 'ABSPATH' ) || exit;
/**
 * PDF: Fattura ordine buoni regalo
 * @var object $ordine
 * @var object|null $buono  (null per fattura ordine completo)
 */
$logo      = BB_PDF_Manager::logo_b64();
$qr_img    = BB_PDF_Manager::qr_code_fattura_b64( $ordine );
$totale    = (float) $ordine->importo_totale;
$imp       = BB_Database::fmt_chf( $totale );
$imp_num   = number_format( $totale, 2, '.', "'" );
$anno      = date( 'Y' );
$data_fmt  = date_i18n( 'd.m.Y', strtotime( $ordine->data_creazione ) );
$buoni     = BB_Database::get_buoni( (int) $ordine->id );
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Fattura Buoni Regalo</title>
<style>
body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #111; }
.hdr { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #2c3a00; margin-bottom: 7mm; }
.hdr td { padding: 0 0 3mm 0; vertical-align: top; }
.hdr-logo-cell { width: 42mm; }
.hdr-logo-cell img { height: 22mm; width: auto; }
.hdr-org-cell { padding-left: 8mm; }
.hdr-org-name { font-size: 11pt; font-weight: bold; color: #2c3a00; margin-bottom: 1mm; }
.hdr-org-addr { font-size: 8.5pt; color: #444; line-height: 1.7; }
.dest { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
.dest td { vertical-align: top; font-size: 10pt; line-height: 1.6; }
.dest-data { text-align: right; font-size: 9pt; color: #555; width: 45%; }
.titolo { font-size: 12pt; font-weight: bold; color: #2c3a00; border-bottom: 2px solid #C8960A; padding-bottom: 2mm; margin-bottom: 4mm; }
.para { font-size: 9.5pt; line-height: 1.7; color: #333; margin-bottom: 3mm; }
.avviso { background: #fffbea; border-left: 3px solid #C8960A; padding: 2mm 4mm; margin: 3mm 0; font-size: 9pt; color: #6b4c00; }
.riepilogo { margin: 4mm 0; }
.riepilogo-head { background: #2c3a00; color: #d4e000; font-size: 9pt; font-weight: bold; padding: 2mm 4mm; }
.riepilogo-body { border: 1.5px solid #C8960A; border-top: none; background: #F8F0E0; }
.riep { width: 100%; border-collapse: collapse; }
.riep td { padding: 2mm 4mm; font-size: 9pt; border-bottom: 1px solid #e8dcc8; vertical-align: middle; }
.riep td:first-child { color: #555; width: 62%; }
.riep td:last-child { font-weight: bold; color: #2c3a00; text-align: right; }
.riep .totale td { background: #fff8ee; font-size: 10.5pt; padding: 2.5mm 4mm; }
.riep .totale td:last-child { font-size: 12pt; color: #A06828; }
.riep tr:last-child td { border-bottom: none; }
.firma { font-size: 9pt; color: #444; line-height: 1.7; margin-top: 5mm; padding-top: 3mm; border-top: 1px solid #ddd; }
.sep { border: none; border-top: 2px dashed #888; margin: 6mm 0 2mm 0; }
.sep-label { text-align: center; font-size: 7pt; color: #888; margin-bottom: 2mm; letter-spacing: 1px; }
/* QR Bill */
.qrb-outer { width: 100%; border-collapse: collapse; border-top: 1.5px solid #333; }
.qrb-outer td { vertical-align: top; padding: 3mm 4mm 3mm 4mm; font-size: 8pt; }
.qrb-col-ricevuta  { width: 56mm; border-right: 1.5px solid #333; }
.qrb-col-pagamento { width: 68mm; border-right: 1.5px solid #333; text-align: left; }
.qrb-section-title { font-size: 9pt; font-weight: bold; margin-bottom: 3mm; }
.qrb-lbl { font-size: 6.5pt; font-weight: bold; color: #222; margin-top: 2.5mm; margin-bottom: 1mm; }
.qrb-val { font-size: 8pt; line-height: 1.5; }
.qrb-pagabile-box { padding:0; margin:0; line-height:1.3; font-size:8pt; line-height:1.5; }
.qrb-imp-row { margin-top: 2.5mm; }
.qrb-imp-row table { border-collapse: collapse; }
.qrb-imp-row td { vertical-align: bottom; padding: 0; }
.qrb-imp-chf { font-size: 8pt; padding-right: 2mm; white-space: nowrap; }
.qrb-imp-box { font-size: 11pt; font-weight: bold; min-width: 28mm; text-align: left; padding: 0; line-height: 1.2; }
.qrb-accept { font-size: 6.5pt; font-weight: bold; text-align: center; margin-top: 4mm; color: #222; }
.qrb-col-dati .qrb-pagabile-box { font-size: 7pt; }
.qrb-outer td.qrb-col-dati { padding-right: 1mm; }
.page-break { page-break-before: always; }
.sep-label-cedolino { margin-bottom: 8mm; }
.qrb-ref { font-family: monospace; font-size: 7pt; }
.qrb-img { width: 174px; height: 174px; display: block; }
.qrb-img-fallback { width: 174px; height: 174px; border: 1px solid #bbb; }
.qrb-img-fallback td { text-align: center; vertical-align: middle; font-size: 7pt; color: #888; }
@page { margin: 14mm 16mm 12mm 16mm; }
</style>
</head>
<body>

<table class="hdr" cellpadding="0" cellspacing="0">
  <tr>
    <td class="hdr-logo-cell">
      <?php if ( $logo ) : ?>
        <img src="<?php echo $logo; ?>" alt="Logo">
      <?php else : ?>
        <div class="hdr-org-name">La Botega<br><span style="font-size:9pt;font-weight:normal">da la Lavizzara</span></div>
      <?php endif; ?>
    </td>
    <td class="hdr-org-cell">
      <div class="hdr-org-name">Societa cooperativa La Botega da la Lavizzara</div>
      <div class="hdr-org-addr">Casa Moretti 8A &middot; 6694 Prato Sornico<br>info@labotegalavizzara.ch &middot; IBAN: CH48 8080 8003 7010 4694 7</div>
    </td>
  </tr>
</table>

<table class="dest" cellpadding="0" cellspacing="0">
  <tr>
    <td>
      <strong><?php echo esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ); ?></strong><br>
      <?php if ( $ordine->acquirente_indirizzo ) : ?>
        <?php echo esc_html( $ordine->acquirente_indirizzo ); ?><br>
      <?php endif; ?>
      <?php echo esc_html( trim( $ordine->acquirente_cap . ' ' . $ordine->acquirente_localita ) ); ?>
    </td>
    <td class="dest-data">Prato Sornico, <?php echo esc_html( $data_fmt ); ?></td>
  </tr>
</table>

<div class="titolo">Ordine buoni regalo – Rif. <?php echo esc_html( $ordine->ordine_ref ); ?></div>

<div class="para">Gentile <strong><?php echo esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ); ?></strong>,</div>
<div class="para">La ringraziamo per il suo ordine di buoni regalo presso <strong>La Botega da la Lavizzara</strong>.</div>

<div class="avviso">I buoni regalo saranno inviati ai destinatari <strong>non appena ricevuto il pagamento</strong>. Si prega di effettuare il versamento entro <strong>30 giorni</strong>.</div>

<div class="riepilogo">
  <div class="riepilogo-head">RIEPILOGO ORDINE</div>
  <div class="riepilogo-body">
    <table class="riep" cellpadding="0" cellspacing="0">
      <tr><td>Numero ordine</td><td><?php echo esc_html( $ordine->ordine_ref ); ?></td></tr>
      <tr><td>Data ordine</td><td><?php echo esc_html( $data_fmt ); ?></td></tr>
      <tr><td>Importo per buono</td><td><?php echo esc_html( BB_Database::fmt_chf( (float) $ordine->importo ) ); ?></td></tr>
      <tr><td>Quantità buoni</td><td><?php echo (int) $ordine->quantita; ?></td></tr>
      <?php foreach ( $buoni as $b ) : ?>
      <tr>
        <td style="padding-left:8mm;color:#666;">
          Buono #<?php echo (int) $b->numero; ?>
          <?php if ( $b->destinatario_nome ) : ?> – <?php echo esc_html( $b->destinatario_nome ); ?><?php endif; ?>
        </td>
        <td><?php echo esc_html( BB_Database::fmt_chf( (float) $b->importo ) ); ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="totale">
        <td><strong>IMPORTO TOTALE DA VERSARE</strong></td>
        <td><?php echo esc_html( $imp ); ?></td>
      </tr>
    </table>
  </div>
</div>

<div class="para">Il versamento può essere effettuato tramite e-banking con QR code oppure con bonifico bancario.</div>
<div class="para"><strong>IBAN:</strong> CH48 8080 8003 7010 4694 7<br><strong>Causale:</strong> <?php echo esc_html( $ordine->ordine_ref ); ?></div>

<div class="firma">
  <div class="para">Per informazioni: <strong>info@labotegalavizzara.ch</strong></div>
  <br>
  <div class="para">Cordiali saluti,<br><strong>La Botega da la Lavizzara</strong></div>
</div>

<!-- ── CEDOLINO QR ────────────────────────────────────────── -->
<div class="page-break"></div>
<div class="sep-label sep-label-cedolino">- - - CEDOLINO DI PAGAMENTO - - -</div>

<table class="qrb-outer" cellpadding="0" cellspacing="0">
  <tr>
    <!-- RICEVUTA -->
    <td class="qrb-col-ricevuta">
      <div class="qrb-section-title">Ricevuta</div>
      <div class="qrb-lbl">Conto / Pagabile a</div>
      <div class="qrb-val">CH48 8080 8003 7010 4694 7<br>Societa cooperativa La Botega<br>da la Lavizzara<br>Casa Moretti 8A<br>6694 Prato-Sornico</div>
      <div class="qrb-lbl">Riferimento</div>
      <div class="qrb-ref"><?php echo esc_html( $ordine->ordine_ref ); ?></div>
      <div class="qrb-lbl">Pagabile da</div>
      <div class="qrb-pagabile-box"><?php echo esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ); ?></div>
      <?php if ( $ordine->acquirente_indirizzo ) : ?>
      <div class="qrb-pagabile-box"><?php echo esc_html( $ordine->acquirente_indirizzo ); ?></div>
      <?php endif; ?>
      <div class="qrb-pagabile-box"><?php echo esc_html( trim( $ordine->acquirente_cap . ' ' . $ordine->acquirente_localita ) ); ?></div>
      <div class="qrb-imp-row">
        <table cellpadding="0" cellspacing="0">
          <tr>
            <td class="qrb-imp-chf">CHF</td>
            <td class="qrb-imp-box"><?php echo esc_html( $imp_num ); ?></td>
          </tr>
        </table>
      </div>
      <div class="qrb-accept">Sezione di accettazione</div>
    </td>

    <!-- PAGAMENTO (QR) -->
    <td class="qrb-col-pagamento">
      <div class="qrb-section-title">Pagamento</div>
      <?php if ( $qr_img ) : ?>
        <img src="<?php echo $qr_img; ?>" class="qrb-img" alt="QR Bill">
      <?php else : ?>
        <table class="qrb-img-fallback" cellpadding="0" cellspacing="0"><tr><td>QR code<br>non disponibile</td></tr></table>
      <?php endif; ?>
      <div class="qrb-lbl">Valuta</div>
      <div class="qrb-val">CHF</div>
      <div class="qrb-lbl">Importo</div>
      <div class="qrb-val"><?php echo esc_html( $imp_num ); ?></div>
    </td>

    <!-- DATI -->
    <td class="qrb-col-dati">
      <div class="qrb-lbl">Conto / Pagabile a</div>
      <div class="qrb-val">CH48 8080 8003 7010 4694 7<br>Societa cooperativa La Botega<br>da la Lavizzara<br>Casa Moretti 8A<br>6694 Prato-Sornico</div>
      <div class="qrb-lbl">Riferimento</div>
      <div class="qrb-ref"><?php echo esc_html( $ordine->ordine_ref ); ?></div>
      <div class="qrb-lbl">Informazioni aggiuntive</div>
      <div class="qrb-val">Buoni regalo <?php echo (int) $ordine->quantita; ?> x <?php echo esc_html( BB_Database::fmt_chf( (float) $ordine->importo ) ); ?></div>
      <div class="qrb-lbl">Pagabile da</div>
      <div class="qrb-pagabile-box"><?php echo esc_html( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ); ?></div>
      <?php if ( $ordine->acquirente_indirizzo ) : ?>
      <div class="qrb-pagabile-box"><?php echo esc_html( $ordine->acquirente_indirizzo ); ?></div>
      <?php endif; ?>
      <div class="qrb-pagabile-box"><?php echo esc_html( trim( $ordine->acquirente_cap . ' ' . $ordine->acquirente_localita ) ); ?></div>
    </td>
  </tr>
</table>

</body>
</html>
