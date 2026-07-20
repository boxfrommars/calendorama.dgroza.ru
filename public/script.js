const INFO = {
    loading: 'Loading…',
    ajaxError: 'Сожалею, но, похоже, ошибка :–(',
    noImage: 'Странно, но большое изображение загрузить не удаётся :–(',
};

const FADE_DURATION = 400;
const CALENDAR_FADE_DURATION = 1000;
const REQUEST_TIMEOUT = 30000;

const wrap = document.querySelector('#wrap');
const monthWrap = document.querySelector('#month-wrap');

const state = {
    calendarDate: null,
    activeRequest: null,
    routeVersion: 0,
    tooltipTarget: null,
};

function parseHash(hash) {
    const match = hash.replace(/^#/, '').match(/^d([0-9]{1,4})_([0-9]{1,2})(?:_([0-9]{1,2}))?$/);
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

function formatMonthHash(date) { return `d${date.year}_${date.month}`; }
function formatDayHash(date) { return `d${date.year}_${date.month}_${date.day}`; }
function sameMonth(left, right) { return left !== null && left.year === right.year && left.month === right.month; }

function animate(element, keyframes, duration) {
    return element.animate(keyframes, {duration, easing: 'linear'}).finished.catch(() => undefined);
}

async function fadeIn(element, duration = FADE_DURATION) {
    element.style.display = 'block';
    await animate(element, [{opacity: 0}, {opacity: 1}], duration);
    element.style.opacity = '1';
}

async function fadeOutAndRemove(element, callback = null) {
    if (!element?.isConnected) {
        callback?.();
        return;
    }
    await animate(element, [{opacity: 1}, {opacity: 0}], FADE_DURATION);
    element.remove();
    callback?.();
}

function removeTooltip() {
    document.querySelector('#tooltip')?.remove();
    state.tooltipTarget = null;
}

function clean() {
    document.querySelectorAll('.cleanable').forEach((element) => fadeOutAndRemove(element));
    removeTooltip();
}

function hideInfo() { document.querySelector('#info-block')?.remove(); }

function showInfo(text) {
    hideInfo();
    const info = document.createElement('div');
    info.id = 'info-block';
    info.className = 'loading cleanable';
    info.textContent = text;
    wrap.append(info);
}

async function appendCalendar(html, request) {
    const previous = monthWrap.querySelector('#calendar');
    if (previous) await animate(previous, [{opacity: 1}, {opacity: 0}], FADE_DURATION);
    if (state.activeRequest !== request || request.signal.aborted) return false;

    hideInfo();
    clean();
    monthWrap.innerHTML = html;
    const calendar = monthWrap.querySelector('#calendar');
    if (calendar) await fadeIn(calendar, CALENDAR_FADE_DURATION);
    return true;
}

async function loadCalendar(date) {
    state.activeRequest?.abort();
    const request = new AbortController();
    state.activeRequest = request;
    const timeout = window.setTimeout(() => request.abort(), REQUEST_TIMEOUT);
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
            showInfo(INFO.ajaxError);
        }
        return false;
    } finally {
        window.clearTimeout(timeout);
        if (state.activeRequest === request) state.activeRequest = null;
    }
}

function showFullImage(postView) {
    postView.style.height = 'auto';
    postView.style.width = 'auto';
    postView.style.overflow = 'visible';
    postView.style.padding = '';
    hideInfo();
    fadeIn(postView);
}

async function viewFull(source, version) {
    showInfo(INFO.loading);
    const previous = document.querySelector('#post-view');
    if (previous) await fadeOutAndRemove(previous);
    if (version !== state.routeVersion) return;

    const postView = document.createElement('div');
    postView.id = 'post-view';
    postView.className = 'cl cleanable faded postview';
    Object.assign(postView.style, {height: '1px', width: '1px', overflow: 'hidden', padding: '0'});
    const image = new Image();
    postView.append(image);
    wrap.append(postView);

    let handled = false;
    const show = () => {
        if (!handled) {
            handled = true;
            version === state.routeVersion ? showFullImage(postView) : postView.remove();
        }
    };
    const fail = () => {
        if (!handled) {
            handled = true;
            if (version === state.routeVersion) showInfo(INFO.noImage);
            else postView.remove();
        }
    };
    image.addEventListener('load', show, {once: true});
    image.addEventListener('error', fail, {once: true});
    image.src = source;
    if (image.complete) image.naturalWidth > 0 ? show() : fail();
}

async function handleHash() {
    const version = ++state.routeVersion;
    const date = window.location.hash ? parseHash(window.location.hash) : currentMonth();
    if (!date) return;

    const monthChanged = !sameMonth(state.calendarDate, date);
    state.calendarDate = date;
    if (date.day !== null) {
        if (monthChanged && !(await loadCalendar(date))) return;
        if (version === state.routeVersion) await viewFull(`/blog/${date.month}_${date.day}_full.jpg`, version);
    } else if (monthChanged) {
        await loadCalendar(date);
    } else {
        clean();
    }
}

async function openPseudoblock(id) {
    const template = document.querySelector(`#${id}-template`);
    if (!(template instanceof HTMLTemplateElement)) return;
    const previous = document.querySelector('.pseudoblock');
    if (previous) await fadeOutAndRemove(previous);
    const block = template.content.firstElementChild.cloneNode(true);
    wrap.append(block);
    await fadeIn(block);
}

function tooltipAnchor(target) { return target.closest('a.tooltip, a.permtooltip, a.pseudotooltip'); }

document.addEventListener('mouseover', (event) => {
    const anchor = tooltipAnchor(event.target);
    if (!anchor || anchor.contains(event.relatedTarget)) return;
    state.tooltipTarget = anchor;
    anchor.dataset.tooltipTitle = anchor.title;
    anchor.title = '';
    const image = anchor.querySelector('img');
    if (image) { image.dataset.tooltipAlt = image.alt; image.alt = ''; }
    const tooltip = document.createElement('p');
    tooltip.id = 'tooltip';
    tooltip.textContent = anchor.dataset.tooltipTitle;
    document.body.append(tooltip);
    fadeIn(tooltip);
});

document.addEventListener('mouseout', (event) => {
    const anchor = tooltipAnchor(event.target);
    if (!anchor || anchor.contains(event.relatedTarget)) return;
    anchor.title = anchor.dataset.tooltipTitle ?? '';
    const image = anchor.querySelector('img');
    if (image) image.alt = image.dataset.tooltipAlt ?? '';
    removeTooltip();
});

document.addEventListener('mousemove', (event) => {
    const tooltip = document.querySelector('#tooltip');
    if (!tooltip || !state.tooltipTarget) return;
    tooltip.style.top = `${event.pageY + 20}px`;
    tooltip.style.left = `${event.pageX + 20}px`;
});

document.addEventListener('click', (event) => {
    const pseudo = event.target.closest('a.pseudo');
    if (pseudo) {
        event.preventDefault();
        openPseudoblock(pseudo.id);
        return;
    }

    const closable = event.target.closest('.cl');
    if (closable) {
        fadeOutAndRemove(closable);
        if (closable.classList.contains('postview') && state.calendarDate) {
            window.location.hash = formatMonthHash(state.calendarDate);
        }
    }

    const goto = event.target.closest('a.goto');
    if (!goto) return;
    event.preventDefault();
    const date = parseHash(`#d${goto.id.slice(1)}`);
    if (date) window.location.hash = date.day === null ? formatMonthHash(date) : formatDayHash(date);
});

window.addEventListener('hashchange', handleHash);
handleHash();
