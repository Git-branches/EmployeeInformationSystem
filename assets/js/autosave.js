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

    restore();

    form.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(save, 600);
    });

    form.addEventListener('submit', function () {
        localStorage.removeItem(key);
    });
})();
