<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_PDF_Manager
 * Genera PDF buono regalo e fattura.
 * Usa mPDF (dal vendor di botega-plugin o proprio vendor).
 */
class BB_PDF_Manager {

    // ── Cartella upload ───────────────────────────────────────────────────────
    public static function upload_dir(): array {
        $base = wp_upload_dir();
        $dir  = $base['basedir'] . '/botega-buoni/';
        $url  = $base['baseurl'] . '/botega-buoni/';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            file_put_contents( $dir . '.htaccess', "Options -Indexes\nDeny from all\n" );
            file_put_contents( $dir . 'index.php', '<?php // silence' );
        }
        return [ 'dir' => $dir, 'url' => $url ];
    }

    // ── Carica autoload ───────────────────────────────────────────────────────
    private static bool $vendor_loaded = false;

    public static function load_vendor(): bool {
        if ( self::$vendor_loaded ) return true;
        // 1. Vendor proprio
        $own = BB_PLUGIN_DIR . 'vendor/autoload.php';
        if ( file_exists( $own ) ) {
            require_once $own;
            self::$vendor_loaded = true;
            return true;
        }
        // 2. Vendor di botega-plugin (se attivo)
        if ( defined( 'BA_PLUGIN_DIR' ) ) {
            $shared = BA_PLUGIN_DIR . 'vendor/autoload.php';
            if ( file_exists( $shared ) ) {
                require_once $shared;
                self::$vendor_loaded = true;
                return true;
            }
        }
        // 3. Cerca nella cartella plugins standard
        $common = WP_PLUGIN_DIR . '/botega-plugin/vendor/autoload.php';
        if ( file_exists( $common ) ) {
            require_once $common;
            self::$vendor_loaded = true;
            return true;
        }
        error_log( '[BB PDF] vendor/autoload.php non trovato.' );
        return false;
    }

    // ── Genera PDF e salva ────────────────────────────────────────────────────
    /**
     * @param  string $tipo  'buono' | 'fattura'
     * @param  int    $ordine_id
     * @param  int|null $buono_id  richiesto per tipo='buono'
     * @return object|false  record PDF dal DB oppure false
     */
    public static function genera( string $tipo, int $ordine_id, ?int $buono_id = null ): object|false {
        $ordine = BB_Database::get_ordine( $ordine_id );
        if ( ! $ordine ) return false;

        $buono = null;
        if ( $tipo === 'buono' && $buono_id ) {
            $buono = BB_Database::get_buono( $buono_id );
            if ( ! $buono ) return false;
        }

        $html     = self::build_html( $tipo, $ordine, $buono );
        $pdf_data = self::html_to_pdf( $html );

        $dirs  = self::upload_dir();
        $nome  = self::nome_file( $tipo, $ordine, $buono );
        if ( $pdf_data === null ) {
            $nome = preg_replace( '/\.pdf$/i', '.html', $nome );
        }
        $percorso = $dirs['dir'] . $nome;
        $url      = $dirs['url'] . $nome;

        file_put_contents( $percorso, $pdf_data ?? $html );
        $dim    = (int) filesize( $percorso );
        $pdf_id = BB_Database::save_pdf( $ordine_id, $buono_id, $tipo, $nome, $percorso, $url, $dim );

        return $pdf_id ? BB_Database::get_pdf( $pdf_id ) : false;
    }

    // ── Nome file ─────────────────────────────────────────────────────────────
    public static function nome_file( string $tipo, object $ordine, ?object $buono = null ): string {
        $label = $tipo === 'buono' ? 'BuonoRegalo' : 'Fattura';
        $nome  = preg_replace( '/[^A-Za-z0-9]/', '', ucwords( str_replace( '-', ' ', sanitize_title(
            $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome
        ) ) ) );
        $ref   = preg_replace( '/[^A-Za-z0-9]/', '', $ordine->ordine_ref );
        $n     = $buono ? '_' . $buono->numero : '';
        $dt    = date( 'Ymd_His' );
        return "{$nome}_{$label}_{$ref}{$n}_{$dt}.pdf";
    }

    // ── Build HTML ────────────────────────────────────────────────────────────
    public static function build_html( string $tipo, object $ordine, ?object $buono = null ): string {
        ob_start();
        if ( $tipo === 'buono' ) {
            include BB_PLUGIN_DIR . 'templates/pdf-buono.php';
        } else {
            include BB_PLUGIN_DIR . 'templates/pdf-fattura.php';
        }
        return ob_get_clean();
    }

    // ── HTML → PDF ────────────────────────────────────────────────────────────
    public static function html_to_pdf( string $html ): ?string {
        if ( ! self::load_vendor() ) return null;

        $html = ltrim( $html, "\xEF\xBB\xBF" );
        if ( ! mb_check_encoding( $html, 'UTF-8' ) ) {
            $html = mb_convert_encoding( $html, 'UTF-8', 'UTF-8' );
        }

        // ── mPDF ─────────────────────────────────────────────────────────────
        if ( class_exists( 'Mpdf\Mpdf' ) ) {
            $tmp = self::upload_dir()['dir'] . 'tmp/';
            if ( ! file_exists( $tmp ) ) wp_mkdir_p( $tmp );
            if ( ! is_writable( $tmp ) ) $tmp = sys_get_temp_dir();
            try {
                $prev = error_reporting( E_ERROR | E_PARSE );
                $mpdf = new \Mpdf\Mpdf([
                    'format'      => 'A4',
                    'orientation' => 'P',
                    'margin_top'  => 0, 'margin_bottom' => 0,
                    'margin_left' => 0, 'margin_right'  => 0,
                    'tempDir'     => $tmp,
                ]);
                $mpdf->WriteHTML( $html );
                $pdf = $mpdf->Output( '', 'S' );
                error_reporting( $prev );
                return $pdf;
            } catch ( \Exception $e ) {
                error_reporting( $prev ?? E_ALL );
                error_log( '[BB PDF] mPDF: ' . $e->getMessage() );
            }
        }

        // ── dompdf ────────────────────────────────────────────────────────────
        if ( class_exists( 'Dompdf\Dompdf' ) ) {
            try {
                $font_dir = BB_PLUGIN_DIR . 'vendor/dompdf/lib/fonts/';
                if ( defined( 'BA_PLUGIN_DIR' ) && ! file_exists( $font_dir ) ) {
                    $font_dir = BA_PLUGIN_DIR . 'vendor/dompdf/lib/fonts/';
                }
                $options = new \Dompdf\Options();
                $options->set( 'defaultFont', 'Helvetica' );
                $options->set( 'isRemoteEnabled', false );
                $options->set( 'tempDir', sys_get_temp_dir() );
                if ( file_exists( $font_dir ) ) {
                    $options->set( 'fontDir', $font_dir );
                    $options->set( 'fontCache', $font_dir );
                }
                $dompdf = new \Dompdf\Dompdf( $options );
                $dompdf->loadHtml( $html, 'UTF-8' );
                $dompdf->setPaper( 'A4', 'portrait' );
                $dompdf->render();
                return $dompdf->output();
            } catch ( \Exception $e ) {
                error_log( '[BB PDF] dompdf: ' . $e->getMessage() );
            }
        }

        error_log( '[BB PDF] Nessun motore PDF disponibile.' );
        return null;
    }

    // ── Serve download ────────────────────────────────────────────────────────
    public static function serve( int $pdf_id ): void {
        $pdf = BB_Database::get_pdf( $pdf_id );
        if ( ! $pdf ) wp_die( __( 'File non trovato.', 'botega-buoni' ) );

        if ( ! file_exists( $pdf->percorso ) ) {
            $ordine = BB_Database::get_ordine( $pdf->ordine_id );
            if ( $ordine ) {
                $buono = $pdf->buono_id ? BB_Database::get_buono( (int) $pdf->buono_id ) : null;
                $html  = self::build_html( $pdf->tipo, $ordine, $buono );
                $data  = self::html_to_pdf( $html ) ?? $html;
                file_put_contents( $pdf->percorso, $data );
            } else {
                wp_die( __( 'File non trovato.', 'botega-buoni' ) );
            }
        }

        $mime = str_ends_with( strtolower( $pdf->nome_file ), '.pdf' ) ? 'application/pdf' : 'text/html';
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . $pdf->nome_file . '"' );
        header( 'Content-Length: ' . filesize( $pdf->percorso ) );
        readfile( $pdf->percorso );
        exit;
    }

    // ── Logo base64 ───────────────────────────────────────────────────────────
    public static function logo_b64(): string {
        // Cerca logo nel plugin corrente
        $own = BB_PLUGIN_DIR . 'assets/img/logo.png';
        if ( file_exists( $own ) ) {
            return 'data:image/png;base64,' . base64_encode( file_get_contents( $own ) );
        }
        // Fallback: logo di botega-plugin
        if ( defined( 'BA_PLUGIN_DIR' ) ) {
            $shared = BA_PLUGIN_DIR . 'assets/img/logo.png';
            if ( file_exists( $shared ) ) {
                return 'data:image/png;base64,' . base64_encode( file_get_contents( $shared ) );
            }
        }
        $common = WP_PLUGIN_DIR . '/botega-plugin/assets/img/logo.png';
        if ( file_exists( $common ) ) {
            return 'data:image/png;base64,' . base64_encode( file_get_contents( $common ) );
        }
        return '';
    }

    // ── QR code per fattura (Swiss SPC) ──────────────────────────────────────
    public static function qr_code_fattura_b64( object $ordine ): string {
        // Cerca phpqrcode in vendor condivisi
        $candidates = [
            BB_PLUGIN_DIR  . 'vendor/phpqrcode/phpqrcode.php',
        ];
        if ( defined( 'BA_PLUGIN_DIR' ) ) {
            $candidates[] = BA_PLUGIN_DIR . 'vendor/phpqrcode/phpqrcode.php';
        }
        $candidates[] = WP_PLUGIN_DIR . '/botega-plugin/vendor/phpqrcode/phpqrcode.php';

        $lib = '';
        foreach ( $candidates as $c ) {
            if ( file_exists( $c ) ) { $lib = $c; break; }
        }
        if ( ! $lib ) return '';

        require_once $lib;
        if ( ! class_exists( 'QRcode' ) ) return '';

        $clean = fn( string $s, int $max = 70 ) =>
            mb_substr( preg_replace( '/[\r\n\t]/', ' ', trim( $s ) ), 0, $max );

        $totale = (float) $ordine->importo_totale;
        $imp    = $totale > 0 ? number_format( $totale, 2, '.', '' ) : '';
        $iban   = 'CH4880808003701046947';
        $ref    = preg_replace( '/[^A-Za-z0-9]/', '', $ordine->ordine_ref );

        $deb_nome = $clean( trim( $ordine->acquirente_cognome . ' ' . $ordine->acquirente_nome ) );
        $deb_adr1 = $clean( $ordine->acquirente_indirizzo );
        $deb_adr2 = $clean( trim( $ordine->acquirente_cap . ' ' . $ordine->acquirente_localita ) );

        $lines = [
            'SPC', '0200', '1', $iban,
            'K',
            $clean( 'Societa cooperativa La Botega da la Lavizzara' ),
            $clean( 'Via Cantonale 6' ),
            $clean( '6694 Prato-Sornico' ),
            '', '', 'CH',
            '', '', '', '', '', '', '',
            $imp, 'CHF',
            'K', $deb_nome, $deb_adr1, $deb_adr2, '', '', 'CH',
            'NON', '',
            $clean( 'Buono regalo - ' . $ref, 140 ),
            'EPD',
        ];
        $spc = implode( "\r\n", $lines ) . "\r\n";

        try {
            ob_start();
            @QRcode::png( $spc, false, 'M', 10, 2 );
            $png = ob_get_clean();
            if ( ! $png ) return '';

            if ( function_exists( 'imagecreatefromstring' ) ) {
                $img = @imagecreatefromstring( $png );
                if ( $img ) {
                    $w = imagesx( $img );
                    $h = imagesy( $img );
                    $cx = (int) floor( ( $w - $w * 0.2 ) / 2 );
                    $cy = (int) floor( ( $h - $h * 0.2 ) / 2 );
                    $cw = (int) floor( $w * 0.2 );
                    $ch = (int) floor( $h * 0.2 );
                    $white = imagecolorallocate( $img, 255, 255, 255 );
                    $red   = imagecolorallocate( $img, 220, 0, 0 );
                    imagefilledrectangle( $img, $cx, $cy, $cx + $cw, $cy + $ch, $white );
                    // Croce
                    $arm_w = (int) floor( $cw * 0.36 );
                    $arm_h = (int) floor( $ch * 0.6 );
                    imagefilledrectangle( $img, $cx + ( $cw - $arm_w ) / 2, $cy + ( $ch - $arm_h ) / 2,
                        $cx + ( $cw + $arm_w ) / 2, $cy + ( $ch + $arm_h ) / 2, $red );
                    imagefilledrectangle( $img, $cx + ( $cw - $arm_h ) / 2, $cy + ( $ch - $arm_w ) / 2,
                        $cx + ( $cw + $arm_h ) / 2, $cy + ( $ch + $arm_w ) / 2, $red );
                    ob_start();
                    imagepng( $img );
                    $png = ob_get_clean();
                    imagedestroy( $img );
                }
            }
            return 'data:image/png;base64,' . base64_encode( $png );
        } catch ( \Throwable $e ) {
            error_log( '[BB QR] ' . $e->getMessage() );
            return '';
        }
    }

    // ── Formattazione CHF ─────────────────────────────────────────────────────
    public static function fmt_chf( float $amount ): string {
        return BB_Database::fmt_chf( $amount );
    }
}
