/* global BB_Public, jQuery */
(function ($) {
    'use strict';

    // ── Stato ──────────────────────────────────────────────
    let quantita  = 1;
    let importo   = BB_Public.importi[0] || 25;
    let perMe     = false;

    // ── Helper: aggiorna display importo e totale ──────────
    function aggiornaTotale() {
        const tot = importo * quantita;
        const fmt = (n) => 'CHF ' + n.toLocaleString('de-CH', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + '.–';
        $('#bb-totale-display').text(fmt(tot));
    }

    // ── Importo buttons ────────────────────────────────────
    $(document).on('change', '.bb-importo-radio', function () {
        importo = parseInt($(this).val(), 10);
        $('.bb-importo-btn').removeClass('selected');
        $(this).closest('.bb-importo-btn').addClass('selected');
        aggiornaTotale();
    });

    // ── Quantità ───────────────────────────────────────────
    $('#bb-qty-minus').on('click', function () {
        if (quantita > 1) {
            quantita--;
            $('#bb-qty').val(quantita);
            aggiornaTotale();
            renderDestinatari();
        }
    });
    $('#bb-qty-plus').on('click', function () {
        if (quantita < 10) {
            quantita++;
            $('#bb-qty').val(quantita);
            aggiornaTotale();
            renderDestinatari();
        }
    });

    // ── Per chi ────────────────────────────────────────────
    $(document).on('change', '.bb-perchi-radio', function () {
        perMe = $(this).val() === 'me';
        $('.bb-perchi-btn').removeClass('selected');
        $(this).closest('.bb-perchi-btn').addClass('selected');
        renderDestinatari();
    });

    // ── Metodo pagamento ───────────────────────────────────
    $(document).on('change', '.bb-metodo-radio', function () {
        $('.bb-metodo-btn').removeClass('selected');
        $(this).closest('.bb-metodo-btn').addClass('selected');
        aggiornaNotaMetodo($(this).val());
    });

    function aggiornaNotaMetodo(metodo) {
        const note = {
            fattura: 'Riceverai una fattura via email con le istruzioni per il pagamento tramite bonifico bancario (IBAN / QR code). I buoni saranno inviati dopo la ricezione del pagamento.',
            stripe:  'Sarai reindirizzato alla pagina di pagamento sicura di Stripe dove potrai pagare con carta di credito/debito o Twint.',
            paypal:  'Sarai reindirizzato a PayPal per completare il pagamento in modo sicuro.'
        };
        const $note = $('#bb-metodo-note');
        if (note[metodo]) {
            $note.text(note[metodo]).addClass('visible');
        } else {
            $note.text('').removeClass('visible');
        }
    }

    // ── Render destinatari ─────────────────────────────────
    function renderDestinatari() {
        if (perMe) {
            $('#bb-porme-block').show();
            $('#bb-altri-blocks').empty();
            $('#bb-msg-uguale-wrap').hide();
            // Pre-compila email con quella dell'acquirente
            const emailAcq = $('#acq_email').val();
            if (emailAcq && !$('#me_email').val()) {
                $('#me_email').val(emailAcq);
            }
            const nomeAcq = $.trim($('#acq_nome').val() + ' ' + $('#acq_cognome').val());
            if (nomeAcq.length > 1 && !$('#me_dest_nome').val()) {
                $('#me_dest_nome').val(nomeAcq);
            }
        } else {
            $('#bb-porme-block').hide();
            $('#bb-altri-blocks').empty();

            for (let i = 1; i <= quantita; i++) {
                const block = buildDestBlock(i);
                $('#bb-altri-blocks').append(block);
                initDateChange(i);
            }

            if (quantita > 1) {
                $('#bb-msg-uguale-wrap').show();
            } else {
                $('#bb-msg-uguale-wrap').hide();
                $('#bb-msg-comune-wrap').hide();
                $('#bb-msg-uguale').prop('checked', false);
            }
        }
    }

    // ── Costruisce blocco destinatario da template ─────────
    function buildDestBlock(idx) {
        const tpl = document.getElementById('bb-dest-template');
        if (!tpl) return $('');

        let html = tpl.innerHTML.replace(/__IDX__/g, idx);
        const $block = $(html);

        // Titolo
        if (quantita > 1) {
            $block.find('.bb-dest-header').text('Buono #' + idx);
        } else {
            $block.find('.bb-dest-header').text('Destinatario');
        }
        return $block;
    }

    // ── Aggiorna orari quando cambia data ──────────────────
    function initDateChange(idx) {
        $(document).on('change', 'input[name="data_consegna_' + idx + '"]', function () {
            const selectedDate = $(this).val();
            const today        = new Date().toISOString().split('T')[0];
            updateTimeSlots($('select[name="orario_consegna_' + idx + '"]'), selectedDate === today);
        });
    }

    $(document).on('change', '#me_data', function () {
        const selectedDate = $(this).val();
        const today        = new Date().toISOString().split('T')[0];
        updateTimeSlots($('#me_orario'), selectedDate === today);
    });

    function updateTimeSlots($select, isToday) {
        const now     = new Date();
        const current = $select.val();
        $select.empty().append('<option value="">Ora</option>');

        for (let h = 0; h < 24; h++) {
            for (let m of [0, 30]) {
                const slotStr = pad(h) + ':' + pad(m);
                if (isToday) {
                    const slotMinutes = h * 60 + m;
                    const nowMinutes  = now.getHours() * 60 + now.getMinutes();
                    // Mostra solo orari futuri (>= ora corrente + 30 min)
                    if (slotMinutes <= nowMinutes) continue;
                }
                const $opt = $('<option>').val(slotStr).text(slotStr);
                if (slotStr === current) $opt.prop('selected', true);
                $select.append($opt);
            }
        }
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    // ── Checkbox "messaggio uguale" ────────────────────────
    $(document).on('change', '#bb-msg-uguale', function () {
        const checked = $(this).is(':checked');
        if (checked) {
            $('#bb-msg-comune-wrap').show();
            // Nascondi i singoli campi messaggio
            $('#bb-altri-blocks .bb-msg-field').hide();
        } else {
            $('#bb-msg-comune-wrap').hide();
            $('#bb-altri-blocks .bb-msg-field').show();
        }
    });

    // Pre-compila email acquirente → destinatario se stessi
    $(document).on('blur', '#acq_email', function () {
        if (perMe && !$('#me_email').val()) {
            $('#me_email').val($(this).val());
        }
    });
    $(document).on('blur', '#acq_nome, #acq_cognome', function () {
        if (perMe && !$('#me_dest_nome').val()) {
            const nome = $.trim($('#acq_nome').val() + ' ' + $('#acq_cognome').val());
            if (nome.length > 1) $('#me_dest_nome').val(nome);
        }
    });

    // ── Validazione form ───────────────────────────────────
    $('#bb-form').on('submit', function (e) {
        let valid = true;

        // Acquirente
        const required_acq = ['acq_nome', 'acq_cognome', 'acq_indirizzo', 'acq_cap', 'acq_localita', 'acq_email', 'acq_telefono'];
        required_acq.forEach(function (f) {
            const $el = $('#' + f);
            if (!$el.val().trim()) {
                showFieldError($el, BB_Public.i18n.campo_required);
                $el.addClass('bb-invalid');
                valid = false;
            } else {
                $el.removeClass('bb-invalid').addClass('bb-valid');
                clearFieldError($el);
            }
        });

        // Email acquirente
        const emailAcq = $('#acq_email').val().trim();
        if (emailAcq && !isValidEmail(emailAcq)) {
            showFieldError($('#acq_email'), BB_Public.i18n.email_invalid);
            $('#acq_email').addClass('bb-invalid');
            valid = false;
        }

        // Destinatari email (per altri)
        if (!perMe) {
            for (let i = 1; i <= quantita; i++) {
                const $email = $('input[name="dest_email_' + i + '"]');
                if ($email.length) {
                    const v = $email.val().trim();
                    if (!v || !isValidEmail(v)) {
                        showFieldError($email, BB_Public.i18n.email_invalid);
                        $email.addClass('bb-invalid');
                        valid = false;
                    }
                }
            }
        } else {
            // Per me: email destinatario obbligatoria
            const $meEmail = $('#me_email');
            if (!$meEmail.val().trim() || !isValidEmail($meEmail.val().trim())) {
                showFieldError($meEmail, BB_Public.i18n.email_invalid);
                $meEmail.addClass('bb-invalid');
                valid = false;
            }
        }

        if (!valid) {
            e.preventDefault();
            const $first = $('.bb-invalid:first');
            if ($first.length) {
                $('html, body').animate({ scrollTop: $first.offset().top - 100 }, 300);
            }
            return;
        }

        // Prima del submit: se per_me, copia email e nome nel campo "me_email" visibile
        if (perMe) {
            if (!$('#me_email').val()) {
                $('#me_email').val($('#acq_email').val());
            }
        }

        $('#bb-submit').text(BB_Public.i18n.sending).prop('disabled', true);
    });

    // ── Helpers ────────────────────────────────────────────
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.trim());
    }

    function showFieldError($input, msg) {
        clearFieldError($input);
        $('<span class="bb-field-error">' + $('<span>').text(msg).html() + '</span>').insertAfter($input);
    }

    function clearFieldError($input) {
        $input.closest('.bb-field').find('.bb-field-error').remove();
    }

    // ── Autocomplete Nominatim ─────────────────────────────
    const NOMINATIM = 'https://nominatim.openstreetmap.org/search';
    let acTimer = null;
    let acDropdown = null;

    function closeDropdowns() {
        $('.bb-autocomplete-dropdown').remove();
        acDropdown = null;
    }

    function initNominatim() {
        const $ind = $('#acq_indirizzo');
        if (!$ind.length) return;
        $ind.wrap('<div class="bb-autocomplete-wrap"></div>');

        $ind.on('input', function () {
            const val = $(this).val().trim();
            clearTimeout(acTimer);
            if (val.length < 3) { closeDropdowns(); return; }

            acTimer = setTimeout(function () {
                $.getJSON(NOMINATIM, {
                    q: val, format: 'json', addressdetails: 1, limit: 6,
                    countrycodes: 'ch,it,de,fr,at', 'accept-language': 'it'
                }).done(function (results) {
                    closeDropdowns();
                    if (!results.length) return;

                    const $wrap = $ind.parent();
                    const $drop = $('<div class="bb-autocomplete-dropdown"></div>');

                    results.forEach(function (r) {
                        const addr  = r.address || {};
                        const road  = addr.road || addr.pedestrian || addr.footway || '';
                        const num   = addr.house_number || '';
                        const cap   = addr.postcode || '';
                        const city  = addr.city || addr.town || addr.village || addr.municipality || '';
                        const paese = (addr.country_code || '').toUpperCase();

                        const strada = road + (num ? ' ' + num : '');
                        const label  = [strada, cap, city, paese].filter(Boolean).join(', ');
                        if (!label) return;

                        $('<div class="bb-autocomplete-item"></div>').text(label)
                            .on('mousedown', function (ev) {
                                ev.preventDefault();
                                $ind.val(strada || r.display_name.split(',')[0]);
                                if (cap)  $('#acq_cap').val(cap);
                                if (city) $('#acq_localita').val(city);
                                closeDropdowns();
                            }).appendTo($drop);
                    });

                    if ($drop.children().length) {
                        $wrap.append($drop);
                        acDropdown = $drop;
                    }
                });
            }, 400);
        });

        $(document).on('click', function (ev) {
            if (!$(ev.target).closest('.bb-autocomplete-wrap').length) closeDropdowns();
        });
    }

    initNominatim();

    // ── Init ───────────────────────────────────────────────
    aggiornaTotale();
    renderDestinatari();

    // Imposta nota metodo di default
    const metodoDefault = $('input[name="metodo_pagamento"]:checked').val();
    if (metodoDefault) aggiornaNotaMetodo(metodoDefault);

}(jQuery));
