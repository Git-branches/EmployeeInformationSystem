// assets/js/app.js — general UI behavior

// Confirm before submitting any form marked with data-confirm.
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

// Fields marked data-uppercase are typed in upper case, matching how the
// information form is filled up. The caret is kept where the user left it.
document.addEventListener('input', function (event) {
    const field = event.target;
    if (!field.dataset || field.dataset.uppercase === undefined) return;
    const upper = field.value.toUpperCase();
    if (upper === field.value) return;
    const start = field.selectionStart;
    const end = field.selectionEnd;
    field.value = upper;
    if (start !== null) field.setSelectionRange(start, end);
});

// Auto-dismiss alerts after 5 seconds.
document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
    setTimeout(function () {
        if (window.bootstrap) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    }, 5000);
});

// ---- Daily salary, derived from the monthly salary --------------------------
// Mirrors daily_salary() in includes/functions.php: monthly ÷ working days,
// rounded to two decimals. The box is read-only, so this is the only way it
// can be filled in.
(function () {
    const monthly = document.getElementById('monthly_salary');
    const daily = document.getElementById('daily_salary');
    if (!monthly || !daily) return;

    const workingDays = parseInt(daily.dataset.workingDays, 10) || 22;

    function recalculate() {
        const value = parseFloat(monthly.value.replace(/,/g, ''));
        daily.value = (isNaN(value) || value < 0)
            ? ''
            : (value / workingDays).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
    }

    monthly.addEventListener('input', recalculate);
    // Run after autosave.js has restored any draft, so the two always agree.
    window.addEventListener('load', recalculate);
})();

// ---- Caret-preserving reformat ----------------------------------------------
// Rewrites an input's value with format(value) while keeping the caret after
// the same number of significant characters (digits, and the decimal point).
function reformatKeepingCaret(field, format) {
    const significant = /[0-9.]/;
    const caret = field.selectionStart === null ? field.value.length : field.selectionStart;
    let before = 0;
    for (let i = 0; i < caret; i++) {
        if (significant.test(field.value[i])) before++;
    }
    const formatted = format(field.value);
    if (formatted === field.value) return;
    field.value = formatted;
    let pos = 0;
    while (pos < formatted.length && before > 0) {
        if (significant.test(formatted[pos])) before--;
        pos++;
    }
    if (document.activeElement === field) field.setSelectionRange(pos, pos);
}

// ---- Money fields: 1,000.09 ---------------------------------------------------
// Digits and one decimal point only, grouped with commas as typed and padded
// to two decimals on leaving the field. The server strips the commas.
(function () {
    function group(value) {
        let clean = value.replace(/[^0-9.]/g, '');
        const dot = clean.indexOf('.');
        let whole = dot === -1 ? clean : clean.slice(0, dot);
        let cents = dot === -1 ? null : clean.slice(dot + 1).replace(/\./g, '').slice(0, 2);
        whole = whole.replace(/^0+(?=\d)/, '');
        if (whole === '' && cents !== null) whole = '0';
        whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return cents === null ? whole : whole + '.' + cents;
    }
    function finish(field) {
        const number = parseFloat(field.value.replace(/,/g, ''));
        if (field.value.trim() === '' || isNaN(number)) return;
        field.value = number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.querySelectorAll('input[data-money]').forEach(function (field) {
        field.addEventListener('input', function () { reformatKeepingCaret(field, group); });
        field.addEventListener('blur', function () { finish(field); });
        // After autosave.js has restored any draft
        window.addEventListener('load', function () { finish(field); });
    });
})();

// ---- Philippine mobile numbers: 0994-800-7500 --------------------------------
// Only digits are kept; +63 / 63 becomes 0, and hyphens are inserted as the
// number is typed. A number on file in another form (e.g. a landline) is left
// alone until the user edits it.
(function () {
    function mask(value) {
        let digits = value.replace(/\D/g, '');
        if (digits.startsWith('63')) digits = '0' + digits.slice(2);
        digits = digits.slice(0, 11);
        if (digits.length <= 4) return digits;
        if (digits.length <= 7) return digits.slice(0, 4) + '-' + digits.slice(4);
        return digits.slice(0, 4) + '-' + digits.slice(4, 7) + '-' + digits.slice(7);
    }
    document.querySelectorAll('input[data-ph-mobile]').forEach(function (field) {
        field.addEventListener('input', function () {
            reformatKeepingCaret(field, mask);
            field.classList.remove('is-invalid');
            field.setCustomValidity('');
        });
        field.addEventListener('blur', function () {
            const ok = field.value === '' || /^09\d{2}-\d{3}-\d{4}$/.test(field.value)
                || field.value === field.defaultValue;
            field.classList.toggle('is-invalid', !ok);
            field.setCustomValidity(ok ? '' : 'Enter an 11-digit mobile number starting with 09, e.g. 0994-800-7500.');
        });
    });
})();

// ---- Employee name: "No middle name" and the display-name preview -----------
// Mirrors employee_display_name() in includes/functions.php:
// SURNAME, FIRST M. EXT  (e.g. ROMERO, RHON J. JR.)
(function () {
    const preview = document.getElementById('name-preview');
    const noMiddle = document.getElementById('no_middle_name');
    if (!preview || !noMiddle) return;
    const get = function (id) {
        const el = document.getElementById(id);
        return el ? el.value.trim().replace(/\s+/g, ' ').toUpperCase() : '';
    };
    const middleField = document.getElementById('middle_name');

    function render() {
        const last = get('last_name');
        const middle = noMiddle.checked ? '' : get('middle_name');
        let ext = get('name_extension').replace(/\.+$/, '');
        if (ext === 'JR' || ext === 'SR') ext += '.';

        let given = get('first_name');
        if (middle) given += ' ' + middle.charAt(0) + '.';
        if (ext) given += ' ' + ext;
        given = given.trim();

        const name = last && given ? last + ', ' + given : (last || given);
        preview.textContent = name || '—';
    }

    function syncMiddle() {
        middleField.disabled = noMiddle.checked;
        if (noMiddle.checked) {
            middleField.value = '';
            middleField.classList.remove('is-invalid');
        }
        render();
    }

    ['last_name', 'first_name', 'middle_name', 'name_extension'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', render);
    });
    noMiddle.addEventListener('change', syncMiddle);
    // After autosave.js has restored any draft
    window.addEventListener('load', syncMiddle);
})();

// ---- Address: province → city/municipality → barangay ---------------------
// Lower lists are loaded from locations.php whenever a higher one changes.
(function () {
    const box = document.getElementById('address-fields');
    if (!box) return;
    const url = box.dataset.locationsUrl;
    const province = document.getElementById('address_province');
    const town = document.getElementById('address_municipality');
    const barangay = document.getElementById('address_barangay');

    function fill(select, names, placeholder, wanted) {
        select.innerHTML = '';
        select.add(new Option(placeholder, ''));
        names.forEach(function (name) { select.add(new Option(name, name)); });
        select.disabled = names.length === 0;
        if (wanted && names.indexOf(wanted) !== -1) select.value = wanted;
        select.classList.remove('is-invalid');
    }
    function reset(select, text) {
        select.innerHTML = '';
        select.add(new Option(text, ''));
        select.disabled = true;
        select.classList.remove('is-invalid');
    }
    function load(params) {
        return fetch(url + '?' + new URLSearchParams(params))
            .then(function (r) { return r.ok ? r.json() : []; })
            .catch(function () { return []; });
    }
    function loadTowns(wantedTown, wantedBarangay) {
        reset(barangay, 'Select a city/municipality first');
        if (!province.value) { reset(town, 'Select a province first'); return; }
        reset(town, 'Loading…');
        load({ province: province.value }).then(function (names) {
            fill(town, names, 'Select city/municipality…', wantedTown);
            if (town.value) loadBarangays(wantedBarangay);
        });
    }
    function loadBarangays(wanted) {
        if (!town.value) { reset(barangay, 'Select a city/municipality first'); return; }
        reset(barangay, 'Loading…');
        load({ province: province.value, municipality: town.value }).then(function (names) {
            fill(barangay, names, 'Select barangay…', wanted);
        });
    }

    province.addEventListener('change', function () { loadTowns(); });
    town.addEventListener('change', function () { loadBarangays(); });

    // autosave.js can only restore a choice whose option is already on the
    // page; bring back a draft's town and barangay once their lists load.
    window.addEventListener('load', function () {
        const form = box.closest('form[data-autosave]');
        if (!form || !province.value || town.value) return;
        let draft = null;
        try {
            draft = JSON.parse(localStorage.getItem('eis_autosave_' + form.dataset.autosave) || 'null');
        } catch (e) { /* storage unavailable */ }
        if (draft && draft.address_province === province.value && draft.address_municipality) {
            loadTowns(draft.address_municipality, draft.address_barangay);
        }
    });
})();

// ---- Live notifications bell ------------------------------------------------
(function () {
    const badge = document.getElementById('notif-badge');
    const menu = document.getElementById('notif-menu');
    const markRead = document.getElementById('notif-mark-read');
    if (!badge || !menu || !markRead) return;

    const baseUrl = document.body.dataset.baseUrl || '';
    const headerLi = menu.querySelector('li.dropdown-header') || menu.firstElementChild;

    function render(data) {
        badge.textContent = data.count;
        badge.style.display = data.count > 0 ? '' : 'none';
        markRead.style.display = data.count > 0 ? '' : 'none';

        menu.querySelectorAll('.notif-item, .notif-empty').forEach(function (el) { el.remove(); });

        let anchor = headerLi;
        if (data.items.length === 0) {
            const li = document.createElement('li');
            li.className = 'notif-empty';
            const span = document.createElement('span');
            span.className = 'dropdown-item-text text-muted';
            span.textContent = 'No pending alerts.';
            li.appendChild(span);
            anchor.after(li);
            return;
        }
        data.items.forEach(function (item) {
            const li = document.createElement('li');
            li.className = 'notif-item';
            const a = document.createElement('a');
            a.className = 'dropdown-item text-wrap';
            a.href = baseUrl + '/modules/employees/view.php?id=' + item.employee_id;
            const icon = document.createElement('i');
            icon.className = 'bi bi-exclamation-circle text-danger me-1';
            a.appendChild(icon);
            a.appendChild(document.createTextNode(item.message));
            const time = document.createElement('div');
            time.className = 'small text-muted';
            time.textContent = item.time;
            a.appendChild(time);
            li.appendChild(a);
            anchor.after(li);
            anchor = li;
        });
    }

    // "Mark all read": clear instantly, no page reload
    markRead.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        fetch(baseUrl + '/modules/requirements/notifications_read.php?ajax=1')
            .then(function (r) { return r.json(); })
            .then(function () { render({ count: 0, items: [] }); })
            .catch(function () { /* offline — leave as is */ });
    });

    // Background refresh so new alerts appear without reloading
    function refresh() {
        fetch(baseUrl + '/modules/requirements/notifications_feed.php')
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data) render(data); })
            .catch(function () { /* offline — try again next tick */ });
    }
    setInterval(refresh, 20000);
})();
