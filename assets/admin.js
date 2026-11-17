/* Adschi Consultation Popup - admin (requests CRM). @author Mohammad Babaei | https://adschi.com */
(function () {
    'use strict';

    var t = window.acpAdmin || {};

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    }

    /** Styled confirmation instead of window.confirm(). */
    function ask(message) {
        return new Promise(function (resolve) {
            var back = el('div', 'acp-modal-backdrop');
            var box = el('div', 'acp-modal');
            box.setAttribute('role', 'alertdialog');
            box.setAttribute('aria-modal', 'true');
            box.appendChild(el('div', 'acp-modal-title', t.confirmTitle || 'Please confirm'));
            box.appendChild(el('div', 'acp-modal-text', message));
            var actions = el('div', 'acp-modal-actions');
            var no = el('button', 'button', t.no || 'No');
            var yes = el('button', 'button button-primary', t.yes || 'Yes');
            no.type = yes.type = 'button';
            actions.appendChild(no);
            actions.appendChild(yes);
            box.appendChild(actions);
            back.appendChild(box);
            document.body.appendChild(back);
            requestAnimationFrame(function () { back.classList.add('is-open'); });
            yes.focus();
            function close(v) {
                document.removeEventListener('keydown', key, true);
                back.remove();
                resolve(v);
            }
            function key(e) {
                if (e.key === 'Escape') { close(false); }
            }
            document.addEventListener('keydown', key, true);
            yes.addEventListener('click', function () { close(true); });
            no.addEventListener('click', function () { close(false); });
            back.addEventListener('mousedown', function (e) { if (e.target === back) { close(false); } });
        });
    }

    document.addEventListener('click', function (e) {
        // Buttons with data-acp-confirm: ask first, then repeat the click (keeps form="..." and the submitter).
        var c = e.target.closest('[data-acp-confirm]');
        if (c) {
            if (c.dataset.acpOk) {
                delete c.dataset.acpOk;
            } else {
                e.preventDefault();
                if (c.form && c.form.id === 'acp-bulk-form') {
                    var action = document.querySelector('select[name="bulk_action"]');
                    var any = document.querySelector('[data-acp-check]:checked');
                    if (!action || !action.value || !any) {
                        ask(t.selectFirst || 'Select requests and an action first.');
                        return;
                    }
                }
                ask(c.getAttribute('data-acp-confirm')).then(function (ok) {
                    if (ok) { c.dataset.acpOk = '1'; c.click(); }
                });
                return;
            }
        }

        // Quick status button in the list.
        var q = e.target.closest('[data-acp-quick]');
        if (q) {
            var form = document.getElementById('acp-quick-form');
            form.elements.req_id.value = q.getAttribute('data-acp-quick');
            form.elements.status.value = q.getAttribute('data-status');
            form.submit();
            return;
        }

        // Follow-up shortcuts (local time of the browser = time of the admin).
        var d = e.target.closest('[data-acp-in]');
        if (d) {
            var input = document.getElementById('acp-follow');
            var now = new Date();
            var kind = d.getAttribute('data-acp-in');
            if (kind === '2h') { now.setHours(now.getHours() + 2); }
            if (kind === 'tomorrow') { now.setDate(now.getDate() + 1); now.setHours(9, 0, 0, 0); }
            if (kind === 'week') { now.setDate(now.getDate() + 7); now.setHours(9, 0, 0, 0); }
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            input.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
            var cb = document.querySelector('.acp-status-pick input[value="call_later"]');
            if (cb) { cb.checked = true; }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-acp-check-all]')) {
            document.querySelectorAll('[data-acp-check]').forEach(function (b) { b.checked = e.target.checked; });
        }
        if (e.target.matches('[data-acp-autosubmit]') && e.target.form) {
            e.target.form.submit();
        }
    });
})();
