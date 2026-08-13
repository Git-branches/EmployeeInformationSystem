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
        const value = parseFloat(monthly.value);
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
