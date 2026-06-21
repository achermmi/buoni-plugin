<?php
defined( 'ABSPATH' ) || exit;

/**
 * BB_Pages
 * Crea automaticamente la pagina pubblica dei buoni regalo.
 */
class BB_Pages {

    const OPTION_PAGE_ID = 'bb_pagina_buoni_id';

    public static function setup(): void {
        $existing_id = (int) get_option( self::OPTION_PAGE_ID, 0 );
        if ( $existing_id > 0 && get_post( $existing_id ) ) return;

        $page_id = wp_insert_post( [
            'post_title'     => __( 'Carta regalo', 'botega-buoni' ),
            'post_name'      => 'carta-regalo',
            'post_content'   => '<!-- wp:shortcode -->[bb_buoni_regalo]<!-- /wp:shortcode -->',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( self::OPTION_PAGE_ID, $page_id );
        }
    }

    public static function get_url(): string {
        $id = (int) get_option( self::OPTION_PAGE_ID, 0 );
        return $id > 0 ? (string) get_permalink( $id ) : home_url( '/carta-regalo/' );
    }

    public static function get_id(): int {
        return (int) get_option( self::OPTION_PAGE_ID, 0 );
    }
}
