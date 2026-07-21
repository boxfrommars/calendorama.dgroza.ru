const INFO = {
    loading: 'Loading…',
    ajaxError: 'Сожалею, но, похоже, ошибка :–(',
    noImage: 'Странно, но большое изображение загрузить не удаётся :–(',
};

const FADE_DURATION = 400;
const CALENDAR_FADE_DURATION = 1000;
const REQUEST_TIMEOUT = 30000;

const elements = {
    alert: document.querySelector('[data-region="alert"]'),
    month: document.querySelector('[data-region="month"]'),
    postView: document.querySelector('[data-region="post-view"]'),
    postImage: document.querySelector('[data-region="post-view"] img'),
    status: document.querySelector('[data-region="status"]'),
    tooltip: document.querySelector('[data-region="tooltip"]'),
};

const state = {
    calendarDate: null,
    activeRequest: null,
    routeVersion: 0,
    tooltipTarget: null,
    panel: null,
    dialogOpeners: new WeakMap(),
};

function parseHash(hash) {
    const route = hash.includes('#') ? hash.slice(hash.indexOf('#')) : hash;
    const match = route.replace(/^#/, '').match(/^d([0-9]{1,4})_([0-9]{1,2})(?:_([0-9]{1,2}))?$/);
    if (!match) return null;

    const date = {year: Number(match[1]), month: Number(match[2]), day: match[3] ? Number(match[3]) : null};
    if (date.year < 1 || date.year > 9999 || date.month < 1 || date.month > 12) return null;
    if (date.day !== null && (date.day < 1 || date.day > 31)) return null;
    return date;
}

function currentMonth() {
    const now = new Date();
    return {year: now.getFullYear(), month: now.getMonth() + 1, day: null};
}

function formatMonthHash(date) { return `#d${date.year}_${date.month}`; }
function formatDayHash(date) { return `#d${date.year}_${date.month}_${date.day}`; }
function sameMonth(left, right) { return left !== null && left.year === right.year && left.month === right.month; }

function animate(element, keyframes, duration) {
    return element.animate(keyframes, {duration, easing: 'linear'}).finished.catch(() => undefined);
}

async function fadeIn(element, duration = FADE_DURATION) {
    await animate(element, [{opacity: 0}, {opacity: 1}], duration);
    element.style.opacity = '1';
}

async function openDialog(dialog, opener = null) {
    if (!dialog.open) dialog.show();
    if (opener) state.dialogOpeners.set(dialog, opener);
    await animate(dialog, [{opacity: 0}, {opacity: 1}], FADE_DURATION);
    dialog.style.opacity = '1';
    dialog.focus({preventScroll: true});
}

async function closeDialog(dialog, restoreFocus = true) {
    if (!dialog.open) return;
    await animate(dialog, [{opacity: 1}, {opacity: 0}], FADE_DURATION);
    if (dialog.open) dialog.close();
    dialog.style.opacity = '';

    const opener = state.dialogOpeners.get(dialog);
    state.dialogOpeners.delete(dialog);
    if (dialog === state.panel) {
        state.panel = null;
    }
    if (restoreFocus && opener?.isConnected) opener.focus({preventScroll: true});
}

function hideTooltip() {
    elements.tooltip.hidden = true;
    elements.tooltip.style.opacity = '';
    state.tooltipTarget = null;
}

async function clean() {
    const dialogs = document.querySelectorAll('dialog[open]');
    await Promise.all(Array.from(dialogs, (dialog) => closeDialog(dialog, false)));
    hideInfo();
    hideTooltip();
}

function hideInfo() {
    elements.status.hidden = true;
    elements.status.textContent = '';
    elements.alert.hidden = true;
    elements.alert.textContent = '';
}

function showInfo(text, isAlert = false) {
    hideInfo();
    const element = isAlert ? elements.alert : elements.status;
    element.textContent = text;
    element.hidden = false;
}

async function appendCalendar(html, request) {
    const previous = elements.month.querySelector('.calendar');
    if (previous) await animate(previous, [{opacity: 1}, {opacity: 0}], FADE_DURATION);
    if (state.activeRequest !== request || request.signal.aborted) return false;

    hideInfo();
    await clean();
    elements.month.innerHTML = html;
    const calendar = elements.month.querySelector('.calendar');
    if (calendar) await fadeIn(calendar, CALENDAR_FADE_DURATION);
    return true;
}

async function loadCalendar(date) {
    state.activeRequest?.abort();
    const request = new AbortController();
    state.activeRequest = request;
    const timeout = window.setTimeout(() => request.abort(), REQUEST_TIMEOUT);
    elements.month.setAttribute('aria-busy', 'true');
    showInfo(INFO.loading);

    try {
        const response = await fetch('/handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: new URLSearchParams({month: String(date.month), year: String(date.year)}),
            signal: request.signal,
        });
        if (!response.ok) throw new Error(`Calendar request failed with HTTP ${response.status}`);
        return await appendCalendar(await response.text(), request);
    } catch (error) {
        if (error.name !== 'AbortError' || state.activeRequest === request) {
            hideInfo();
            showInfo(INFO.ajaxError, true);
        }
        return false;
    } finally {
        window.clearTimeout(timeout);
        if (state.activeRequest === request) state.activeRequest = null;
        elements.month.setAttribute('aria-busy', 'false');
    }
}

function prepareFullImage() {
    Object.assign(elements.postView.style, {height: '1px', width: '1px', overflow: 'hidden', padding: '0'});
}

async function showFullImage(opener) {
    Object.assign(elements.postView.style, {height: 'auto', width: 'auto', overflow: 'visible', padding: ''});
    hideInfo();
    await openDialog(elements.postView, opener);
}

async function viewFull(date, version) {
    showInfo(INFO.loading);
    await closeDialog(elements.postView, false);
    if (version !== state.routeVersion) return;

    const route = formatDayHash(date);
    const opener = elements.month.querySelector(`[data-calendar-route="${route}"]`);
    const source = opener?.getAttribute('href') ?? `/blog/${date.month}_${date.day}_full.jpg`;
    const title = opener?.dataset.tooltip ?? '';
    prepareFullImage();
    elements.postImage.alt = title;

    let handled = false;
    const show = () => {
        if (handled) return;
        handled = true;
        if (version === state.routeVersion) showFullImage(opener);
    };
    const fail = () => {
        if (handled) return;
        handled = true;
        if (version === state.routeVersion) {
            closeDialog(elements.postView, false);
            showInfo(INFO.noImage, true);
        }
    };

    elements.postImage.onload = show;
    elements.postImage.onerror = fail;
    elements.postImage.src = source;
    if (elements.postImage.complete) elements.postImage.naturalWidth > 0 ? show() : fail();
}

async function handleHash() {
    const version = ++state.routeVersion;
    const date = window.location.hash ? parseHash(window.location.hash) : currentMonth();
    if (!date) return;

    const monthChanged = !sameMonth(state.calendarDate, date);
    state.calendarDate = date;
    if (date.day !== null) {
        if (monthChanged && !(await loadCalendar(date))) return;
        if (version === state.routeVersion) await viewFull(date, version);
    } else if (monthChanged) {
        await loadCalendar(date);
    } else {
        await clean();
    }
}

async function openPanel(name, opener) {
    const panel = document.querySelector(`[data-panel="${name}"]`);
    if (!(panel instanceof HTMLDialogElement)) return;
    if (state.panel && state.panel !== panel) await closeDialog(state.panel, false);
    state.panel = panel;
    if (!panel.open) await openDialog(panel, opener);
}

function routeFromAnchor(anchor) {
    return parseHash(anchor.dataset.calendarRoute ?? anchor.hash);
}

function navigate(date) {
    const hash = date.day === null ? formatMonthHash(date) : formatDayHash(date);
    if (window.location.hash === hash) handleHash();
    else window.location.hash = hash;
}

function tooltipTarget(target) {
    return target.closest('[data-tooltip]');
}

function showTooltip(target, event = null) {
    state.tooltipTarget = target;
    elements.tooltip.textContent = target.dataset.tooltip;
    elements.tooltip.hidden = false;
    const bounds = target.getBoundingClientRect();
    const top = event ? event.pageY + 20 : window.scrollY + bounds.bottom + 8;
    const left = event ? event.pageX + 20 : window.scrollX + bounds.left;
    elements.tooltip.style.top = `${top}px`;
    elements.tooltip.style.left = `${left}px`;
    animate(elements.tooltip, [{opacity: 0}, {opacity: 1}], FADE_DURATION);
}

document.addEventListener('mouseover', (event) => {
    const target = tooltipTarget(event.target);
    if (!target || target.contains(event.relatedTarget)) return;
    showTooltip(target, event);
});

document.addEventListener('mouseout', (event) => {
    const target = tooltipTarget(event.target);
    if (!target || target.contains(event.relatedTarget)) return;
    hideTooltip();
});

document.addEventListener('focusin', (event) => {
    const target = tooltipTarget(event.target);
    if (target) showTooltip(target);
});

document.addEventListener('focusout', (event) => {
    const target = tooltipTarget(event.target);
    if (target && !target.contains(event.relatedTarget)) hideTooltip();
});

document.addEventListener('mousemove', (event) => {
    if (!state.tooltipTarget) return;
    elements.tooltip.style.top = `${event.pageY + 20}px`;
    elements.tooltip.style.left = `${event.pageX + 20}px`;
});

document.addEventListener('click', async (event) => {
    const panelButton = event.target.closest('button[data-panel-target]');
    if (panelButton) {
        openPanel(panelButton.dataset.panelTarget, panelButton);
        return;
    }

    const anchor = event.target.closest('a[data-calendar-route]');
    if (anchor) {
        event.preventDefault();
        const date = routeFromAnchor(anchor);
        const panel = anchor.closest('dialog[data-panel]');
        if (panel) await closeDialog(panel);
        if (date) navigate(date);
        return;
    }

    const dialog = event.target.closest('dialog[data-close-on-click]');
    if (!dialog) return;
    if (dialog === elements.postView && state.calendarDate) {
        await closeDialog(dialog);
        navigate({...state.calendarDate, day: null});
    } else {
        await closeDialog(dialog);
    }
});

document.addEventListener('keydown', async (event) => {
    if (event.key !== 'Escape') return;
    if (elements.postView.open) {
        event.preventDefault();
        await closeDialog(elements.postView);
        if (state.calendarDate) navigate({...state.calendarDate, day: null});
    } else if (state.panel?.open) {
        event.preventDefault();
        await closeDialog(state.panel);
    }
});

window.addEventListener('hashchange', handleHash);
handleHash();
