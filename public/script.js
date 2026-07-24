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
    announcement: document.querySelector('[data-region="calendar-announcement"]'),
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
    pendingCalendarFocus: null,
    viewerOpener: null,
    viewerVersion: 0,
    animations: new WeakMap(),
};

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function parseHash(hash) {
    const route = hash.includes('#') ? hash.slice(hash.indexOf('#')) : hash;
    const match = route.replace(/^#/, '').match(/^d([0-9]{1,4})_([0-9]{1,2})(?:_([0-9]{1,2}))?$/);
    if (!match) return null;

    const date = {year: Number(match[1]), month: Number(match[2]), day: match[3] ? Number(match[3]) : null};
    if (date.year < 1 || date.year > 9999 || date.month < 1 || date.month > 12) return null;
    if (date.day !== null && (date.day < 1 || date.day > 31)) return null;
    return date;
}

function formatMonthHash(date) { return `#d${date.year}_${date.month}`; }
function formatDayHash(date) { return `#d${date.year}_${date.month}_${date.day}`; }
function sameMonth(left, right) { return left !== null && left.year === right.year && left.month === right.month; }

function animate(element, keyframes, duration) {
    state.animations.get(element)?.cancel();
    if (reducedMotion.matches) {
        state.animations.delete(element);
        return Promise.resolve();
    }

    const animation = element.animate(keyframes, {duration, easing: 'linear'});
    state.animations.set(element, animation);
    return animation.finished
        .catch(() => undefined)
        .finally(() => {
            if (state.animations.get(element) === animation) state.animations.delete(element);
        });
}

async function fadeIn(element, duration = FADE_DURATION) {
    await animate(element, [{opacity: 0}, {opacity: 1}], duration);
    element.style.opacity = '1';
}

function hideTooltip() {
    elements.tooltip.hidden = true;
    elements.tooltip.style.opacity = '';
    state.tooltipTarget = null;
}

async function resetTransientUi() {
    hidePanels();
    await closePostView(false, false);
    hideInfo();
    hideTooltip();
}

function hidePanels() {
    document.querySelectorAll('.panel-dialog:popover-open').forEach((panel) => panel.hidePopover());
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
    if (state.activeRequest !== request || request.signal.aborted) return false;
    const previous = elements.month.querySelector('.calendar');
    if (previous) await animate(previous, [{opacity: 1}, {opacity: 0}], FADE_DURATION);
    if (state.activeRequest !== request || request.signal.aborted) {
        if (previous) previous.style.opacity = '';
        return false;
    }

    await resetTransientUi();
    if (state.activeRequest !== request || request.signal.aborted) return false;
    elements.month.innerHTML = html;
    const calendar = elements.month.querySelector('.calendar');
    if (calendar) await fadeIn(calendar, CALENDAR_FADE_DURATION);
    if (state.activeRequest !== request || request.signal.aborted) return false;
    announceCalendar();
    restoreCalendarFocus();
    return true;
}

function announceCalendar() {
    const calendar = elements.month.querySelector('[data-calendar-view]');
    const label = calendar?.querySelector('.calendar')?.getAttribute('aria-label');
    if (label) elements.announcement.textContent = label;
}

function restoreCalendarFocus() {
    const pending = state.pendingCalendarFocus;
    state.pendingCalendarFocus = null;
    if (!pending || window.location.hash !== pending.hash) return;
    elements.month.querySelector(`[data-calendar-direction="${pending.direction}"]`)?.focus({preventScroll: true});
}

async function loadCalendar(date) {
    state.activeRequest?.abort();
    const request = new AbortController();
    state.activeRequest = request;
    const timeout = window.setTimeout(() => request.abort(), REQUEST_TIMEOUT);
    elements.month.setAttribute('aria-busy', 'true');
    showInfo(INFO.loading);

    try {
        const url = new URL('/calendar', window.location.origin);
        url.search = new URLSearchParams({month: String(date.month), year: String(date.year)});
        const response = await fetch(url, {
            cache: 'no-store',
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
        if (state.activeRequest === request) {
            state.activeRequest = null;
            elements.month.setAttribute('aria-busy', 'false');
        }
    }
}

async function openPostView(opener, routeVersion) {
    hidePanels();
    if (routeVersion !== state.routeVersion) return false;
    Object.assign(elements.postView.style, {height: 'auto', width: 'auto', overflow: 'visible', padding: ''});
    hideInfo();
    const version = ++state.viewerVersion;
    state.viewerOpener = opener;
    if (!elements.postView.open) elements.postView.show();
    await animate(elements.postView, [{opacity: 0}, {opacity: 1}], FADE_DURATION);
    if (!elements.postView.open || state.viewerVersion !== version) return false;
    elements.postView.style.opacity = '1';
    elements.postView.focus({preventScroll: true});
    return true;
}

function leaveDayRoute() {
    if (!state.calendarDate || state.calendarDate.day === null) return;
    const month = {...state.calendarDate, day: null};
    const hash = formatMonthHash(month);
    state.routeVersion++;
    state.calendarDate = month;
    state.pendingCalendarFocus = null;
    if (window.location.hash !== hash) window.history.pushState(null, '', hash);
}

async function closePostView(restoreFocus = true, updateRoute = true) {
    if (!elements.postView.open) return false;
    const version = ++state.viewerVersion;
    await animate(elements.postView, [{opacity: 1}, {opacity: 0}], FADE_DURATION);
    if (state.viewerVersion !== version) return false;
    if (elements.postView.open) elements.postView.close();
    elements.postView.style.opacity = '';

    const opener = state.viewerOpener;
    state.viewerOpener = null;
    if (restoreFocus && opener?.isConnected) opener.focus({preventScroll: true});
    if (updateRoute) leaveDayRoute();
    return true;
}

async function viewFull(date, version) {
    showInfo(INFO.loading);
    hidePanels();
    await closePostView(false, false);
    if (version !== state.routeVersion) return;

    const route = formatDayHash(date);
    const opener = elements.month.querySelector(`[data-calendar-route="${route}"]`);
    const source = opener?.getAttribute('href') ?? `/blog/${date.month}_${date.day}_full.jpg`;
    const title = opener?.dataset.tooltip ?? '';
    Object.assign(elements.postView.style, {height: '1px', width: '1px', overflow: 'hidden', padding: '0'});
    elements.postImage.alt = title;
    elements.postImage.src = source;

    try {
        await elements.postImage.decode();
    } catch {
        if (version === state.routeVersion) {
            await closePostView(false, false);
            showInfo(INFO.noImage, true);
        }
        return;
    }

    if (version === state.routeVersion) await openPostView(opener, version);
}

async function handleHash() {
    const version = ++state.routeVersion;
    const date = parseHash(window.location.hash || elements.month.dataset.initialCalendarRoute || '');
    if (!date) return;

    const monthChanged = !sameMonth(state.calendarDate, date);
    state.calendarDate = date;
    if (date.day !== null) {
        if (monthChanged && !(await loadCalendar(date))) return;
        if (version === state.routeVersion) await viewFull(date, version);
    } else if (monthChanged) {
        await loadCalendar(date);
    } else {
        await resetTransientUi();
    }
}

function routeFromAnchor(anchor) {
    return parseHash(anchor.dataset.calendarRoute ?? anchor.hash);
}

function navigate(date, focusDirection = null) {
    const hash = date.day === null ? formatMonthHash(date) : formatDayHash(date);
    state.pendingCalendarFocus = focusDirection ? {direction: focusDirection, hash} : null;
    if (window.location.hash === hash) handleHash();
    else window.location.hash = hash;
}

async function activateCalendarRoute(anchor, focusDirection = null) {
    const date = routeFromAnchor(anchor);
    if (elements.postView.open) await closePostView(false, false);
    if (date) navigate(date, focusDirection);
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
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const panelButton = target.closest('button[popovertarget]');
    if (panelButton && state.calendarDate?.day !== null) {
        if (elements.postView.open) {
            event.preventDefault();
            await closePostView(false, true);
            const panelId = panelButton.getAttribute('popovertarget');
            const panel = panelId === null ? null : document.getElementById(panelId);
            if (panel?.matches('[popover]')) panel.showPopover();
            return;
        }
        leaveDayRoute();
        hideInfo();
    }

    const anchor = target.closest('a[data-calendar-route]');
    if (anchor) {
        event.preventDefault();
        const focusDirection = event.detail === 0 ? anchor.dataset.calendarDirection ?? null : null;
        await activateCalendarRoute(anchor, focusDirection);
        return;
    }

    if (elements.postView.open && !elements.postView.contains(target)) {
        await closePostView(false, true);
    }
});

document.addEventListener('keydown', async (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const arrow = target?.closest('a[data-calendar-route][data-calendar-direction]');
    if (event.key === 'Enter' && arrow) {
        event.preventDefault();
        await activateCalendarRoute(arrow, arrow.dataset.calendarDirection ?? null);
        return;
    }

    if (event.key !== 'Escape') return;
    if (!elements.postView.open) return;
    event.preventDefault();
    await closePostView(true, true);
});

window.addEventListener('hashchange', handleHash);
handleHash();
