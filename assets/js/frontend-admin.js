/* global BB_FA */
( function () {
	'use strict';

	const cfg = window.BB_FA || {};

	/* ── helpers ─────────────────────────────────────────────── */
	function msg( text, type ) {
		const el = document.getElementById( 'bb-fa-msg' );
		if ( ! el ) return;
		el.textContent = text;
		el.className = 'bb-fa-msg ' + ( type || 'success' );
		el.style.display = 'block';
		el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		if ( type !== 'error' ) {
			setTimeout( () => { el.style.display = 'none'; }, 6000 );
		}
	}

	function ajax( action, data, btn ) {
		const body = new URLSearchParams( { action, nonce: cfg.nonce, ...data } );
		if ( btn ) btn.classList.add( 'loading' );
		return fetch( cfg.ajaxurl, { method: 'POST', body } )
			.then( r => r.json() )
			.finally( () => { if ( btn ) btn.classList.remove( 'loading' ); } );
	}

	function confirm_( key ) {
		return window.confirm( cfg.i18n[ key ] || 'Confermare?' );
	}

	/* ── dispatcher ──────────────────────────────────────────── */
	function handle( btn ) {
		const act  = btn.dataset.action;
		const id   = btn.dataset.id || btn.closest( '[data-ordine-id]' )?.dataset?.ordineId;
		const stato = btn.dataset.stato;

		switch ( act ) {

			case 'set_stato':
				if ( stato === 'annullato' && ! confirm_( 'conf_annulla' ) ) return;
				if ( stato === 'pagato'    && ! confirm_( 'conf_pagato'  ) ) return;
				ajax( 'bb_fa_set_stato', { id, stato }, btn )
					.then( r => {
						if ( r.success ) window.location.reload();
						else msg( r.data || cfg.i18n.err_generic, 'error' );
					} );
				break;

			case 'invia_buoni':
				if ( ! confirm_( 'conf_invia_buoni' ) ) return;
				ajax( 'bb_fa_invia_buoni', { id }, btn )
					.then( r => {
						if ( r.success ) {
							const d = r.data || {};
							msg(
								( cfg.i18n.inviati || 'Inviati: %s – Errori: %e' )
									.replace( '%s', d.inviati || 0 )
									.replace( '%e', d.errori  || 0 ),
								( d.errori > 0 ) ? 'error' : 'success'
							);
						} else {
							msg( r.data || cfg.i18n.err_generic, 'error' );
						}
					} );
				break;

			case 'invia_richiamo':
				if ( ! confirm_( 'conf_richiamo' ) ) return;
				ajax( 'bb_fa_invia_richiamo', { id }, btn )
					.then( r => {
						if ( r.success ) msg( cfg.i18n.ok_richiamo || 'Richiamo inviato.' );
						else msg( r.data || cfg.i18n.err_generic, 'error' );
					} );
				break;

			case 'rigenera_pdf': {
				const ordineId = btn.dataset.ordineId || id;
				const tipo     = btn.dataset.tipo   || 'buono';
				const buonoId  = btn.dataset.buonoId || '';
				ajax( 'bb_fa_rigenera_pdf', { ordine_id: ordineId, tipo, buono_id: buonoId }, btn )
					.then( r => {
						if ( r.success && r.data?.url ) {
							window.open( r.data.url, '_blank' );
							msg( cfg.i18n.ok_pdf || 'PDF generato.' );
						} else {
							msg( r.data || cfg.i18n.err_generic, 'error' );
						}
					} );
				break;
			}

			case 'delete':
				if ( ! confirm_( 'conf_delete' ) ) return;
				ajax( 'bb_fa_delete', { id }, btn )
					.then( r => {
						if ( r.success ) window.location.reload();
						else msg( r.data || cfg.i18n.err_generic, 'error' );
					} );
				break;
		}
	}

	/* ── Event delegation ────────────────────────────────────── */
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.bb-fa-action' );
		if ( ! btn ) return;
		if ( btn.classList.contains( 'loading' ) ) return;
		e.preventDefault();
		handle( btn );
	} );

} )();
