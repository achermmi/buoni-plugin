/* global BB_Admin, jQuery */
(function ($) {
    'use strict';

    // ── Elimina ordine ─────────────────────────────────────
    $(document).on('click', '.bb-btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm(BB_Admin.i18n.confirm_delete)) return;

        $.post(BB_Admin.ajaxurl, {
            action: 'bb_delete_ordine',
            nonce:  BB_Admin.nonce,
            id:     id
        }).done(function () {
            location.reload();
        }).fail(function () {
            alert(BB_Admin.i18n.error);
        });
    });

    // ── Invia buoni ────────────────────────────────────────
    $(document).on('click', '.bb-invia-buoni', function () {
        const id = $(this).data('id');
        if (!confirm(BB_Admin.i18n.confirm_invia)) return;

        const $btn = $(this).prop('disabled', true).text('…');
        $.post(BB_Admin.ajaxurl, {
            action: 'bb_invia_buoni',
            nonce:  BB_Admin.nonce,
            id:     id
        }).done(function (res) {
            if (res.success) {
                const d = res.data;
                alert('Inviati: ' + d.inviati + ' | Errori: ' + d.errori);
                location.reload();
            } else {
                alert(BB_Admin.i18n.error);
            }
        }).fail(function () {
            alert(BB_Admin.i18n.error);
        }).always(function () {
            $btn.prop('disabled', false).text('📧 Invia buoni ai destinatari');
        });
    });

    // ── Invia richiamo ─────────────────────────────────────
    $(document).on('click', '.bb-invia-richiamo', function () {
        const id = $(this).data('id');
        if (!confirm(BB_Admin.i18n.confirm_richiamo)) return;

        const $btn = $(this).prop('disabled', true).text('…');
        $.post(BB_Admin.ajaxurl, {
            action: 'bb_invia_richiamo',
            nonce:  BB_Admin.nonce,
            id:     id
        }).done(function (res) {
            alert(res.success ? BB_Admin.i18n.done : BB_Admin.i18n.error);
        }).fail(function () {
            alert(BB_Admin.i18n.error);
        }).always(function () {
            $btn.prop('disabled', false).text('🔔 Invia richiamo pagamento');
        });
    });

    // ── Rigenera PDF ───────────────────────────────────────
    $(document).on('click', '.bb-rigenera-pdf', function () {
        const $btn     = $(this).prop('disabled', true).text('…');
        const ordineId = $(this).data('ordine-id');
        const tipo     = $(this).data('tipo');
        const buonoId  = $(this).data('buono-id') || 0;

        $.post(BB_Admin.ajaxurl, {
            action:    'bb_rigenera_pdf',
            nonce:     BB_Admin.nonce,
            ordine_id: ordineId,
            tipo:      tipo,
            buono_id:  buonoId
        }).done(function (res) {
            if (res.success && res.data.url) {
                window.open(res.data.url, '_blank');
            } else {
                alert(BB_Admin.i18n.error);
            }
        }).fail(function () {
            alert(BB_Admin.i18n.error);
        }).always(function () {
            $btn.prop('disabled', false).text('📄 PDF');
        });
    });

}(jQuery));
