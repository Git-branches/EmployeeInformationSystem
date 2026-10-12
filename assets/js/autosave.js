// assets/js/autosave.js
// Offline auto-save: any form marked data-autosave="<key>" has its fields
// saved to localStorage as the user types, restored after an accidental
// closure or power interruption, and cleared on successful submit.

(function () {
    'use strict';

    const form = document.querySelector('form[data-autosave]');
    if (!form) return;

    const key = 'eis_autosave_' + form.dataset.autosave;
    const statusEl = document.getElementById('autosave-status');
    let timer = null;

    function fields() {
        return Array.from(form.elements).filter(function (el) {
            return el.name
                && el.type !== 'file'
                && el.type !== 'password'
                && el.type !== 'hidden'
                && el.type !== 'submit';
        });
    }

    function isCheck(el) {
        return el.type === 'checkbox' || el.type === 'radio';
    }

    function save() {
        const data = {};
        fields().forEach(function (el) {
            data[el.name] = isCheck(el) ? el.checked : el.value;
        });
        try {
            localStorage.setItem(key, JSON.stringify(data));
            if (statusEl) {
                statusEl.textContent = 'Draft auto-saved at ' + new Date().toLocaleTimeString();
            }
        } catch (e) { /* storage full/unavailable — ignore */ }
    }

    function restore() {
        let data;
        try {
            data = JSON.parse(localStorage.getItem(key) || 'null');
        } catch (e) {
            return;
        }
        if (!data) return;

        // Only restore into an untouched form (don't clobber server values on edit pages)
        const dirty = fields().some(function (el) {
            return !isCheck(el) && el.value !== '' &&
                data[el.name] !== undefined && data[el.name] !== el.value;
        });

        fields().forEach(function (el) {
            if (data[el.name] === undefined) return;
            if (isCheck(el)) {
                if (!dirty) el.checked = data[el.name] === true;
            } else if (el.value === '' || !dirty) {
                el.value = data[el.name];
            }
        });
        if (statusEl) {
            statusEl.textContent = 'Unsaved draft restored from auto-save.';
            statusEl.classList.add('text-success');
        }
    }

    /** Throw the draft away, so it is not restored on the next visit. */
    function discard() {
        try {
            localStorage.removeItem(key);
        } catch (e) { /* storage unavailable — nothing to remove */ }
    }

    restore();

    form.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(save, 600);
    });

    form.addEventListener('submit', discard);

    // "Clear Form" / "Undo Changes": empty the form back to how it arrived —
    // blank when adding, the saved details when editing — and drop the draft.
    // Without dropping it the entry would be restored on the next visit.
    const clearButton = document.getElementById('clear-form');
    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (!window.confirm(clearButton.dataset.clearConfirm)) return;

            clearTimeout(timer);   // a pending auto-save would write it straight back
            discard();
            form.reset();          // back to the values the page was rendered with

            // Clear what the browser does not reset on its own.
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
                if (el.setCustomValidity) el.setCustomValidity('');
            });
            form.querySelectorAll('input[type="file"]').forEach(function (el) { el.value = ''; });

            // Bring the parts of the page that follow what was typed — the
            // name preview, the daily salary, the "no middle name" box — back
            // in step. They listen for load, which is also how they catch up
            // with a restored draft.
            //
            // The address lists are deliberately left alone: form.reset() has
            // already put back the options the page was rendered with, and a
            // change event here would clear the city and barangay instead.
            window.dispatchEvent(new Event('load'));

            if (statusEl) {
                statusEl.textContent = 'Form cleared.';
                statusEl.classList.remove('text-success');
            }
            const first = form.querySelector('input:not([type="hidden"]):not([disabled])');
            if (first) first.focus();
        });
    }
})();
