(function () {
  'use strict';

  const I18N = window.dcsveI18n || {};
  const MONTHS = I18N.months || ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const MONTHS_SHORT = I18N.monthsShort || ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
  const WDAYS = I18N.wdays || ['So','Mo','Di','Mi','Do','Fr','Sa'];
  const LINK_LABEL = I18N.moreInfo || 'Weitere Informationen';
  const TIME_FORMAT = I18N.time || '%s Uhr';
  const NO_EVENTS = '<div class="dcsve_csv_events__no-events">' + (I18N.noEvents || 'Keine Termine im gewählten Zeitraum.') + '</div>';

  // ─── Init ───

  function initAll() {
    const modules = document.querySelectorAll('.dcsve_csv_events');
    for (let i = 0; i < modules.length; i++) {
      initModule(modules[i]);
    }
  }

  function initModule(wrap) {
    const configEl = wrap.querySelector('.dcsve-config');
    const config = configEl ? JSON.parse(configEl.textContent) : {};
    wrap._dcsveConfig = config;
    wrap._dcsveCurrentView = config.fixedView || 'list';

    // Period filter buttons.
    const periodBtns = wrap.querySelectorAll('.dcsve_csv_events__periods .dcsve_csv_events__btn');
    for (let i = 0; i < periodBtns.length; i++) {
      periodBtns[i].addEventListener('click', function () {
        const period = this.getAttribute('data-period');
        const all = wrap.querySelectorAll('.dcsve_csv_events__periods .dcsve_csv_events__btn');
        for (let b = 0; b < all.length; b++) all[b].classList.remove('dcsve_csv_events__btn--active');
        this.classList.add('dcsve_csv_events__btn--active');
        fetchAndRender(wrap, period);
      });
    }

    // View switcher buttons.
    const viewBtns = wrap.querySelectorAll('.dcsve_csv_events__views .dcsve_csv_events__btn-view');
    for (let j = 0; j < viewBtns.length; j++) {
      viewBtns[j].addEventListener('click', function () {
        const all = wrap.querySelectorAll('.dcsve_csv_events__views .dcsve_csv_events__btn-view');
        for (let b = 0; b < all.length; b++) all[b].classList.remove('dcsve_csv_events__btn--active');
        this.classList.add('dcsve_csv_events__btn--active');
        wrap._dcsveCurrentView = this.getAttribute('data-view');
        switchView(wrap, wrap._dcsveCurrentView);
      });
    }

    // Slider navigation (delegate to content area).
    wrap.addEventListener('click', function (e) {
      const nav = e.target.closest('.dcsve_csv_events__slider-nav');
      if (!nav) return;
      const sliderId = nav.getAttribute('data-slider');
      const track = document.getElementById(sliderId);
      if (!track) return;
      const amount = nav.classList.contains('dcsve_csv_events__slider-prev') ? -260 : 260;
      track.scrollBy({ left: amount, behavior: 'smooth' });
    });
  }

  // ─── Fetch & Render ───

  function fetchAndRender(wrap, period) {
    const config = wrap._dcsveConfig;
    const dataEl = wrap.querySelector('.dcsve-data');
    if (!dataEl) return;

    // Paste mode has no public REST route; the data tag holds the unfiltered list.
    if (config.sourceMode === 'paste') {
      let all = [];
      try { all = JSON.parse(dataEl.textContent) || []; } catch (e) { return; }
      const events = filterEvents(all, period, config.periodCount || 1, !!config.showPast, config.count || 0);
      renderAllViews(wrap, events, config);
      switchView(wrap, wrap._dcsveCurrentView);
      return;
    }

    const csvUrl = config.csvUrl || '';
    if (!csvUrl) return;

    const params = new URLSearchParams({
      csv_url:      csvUrl,
      period:       period,
      period_count: String(config.periodCount || 1),
      count:        String(config.count || 0),
      show_past:    config.showPast ? '1' : '0',
    });

    const restUrl = (window.dcsveRestUrl || '/wp-json/') + 'divi-csv-events/v1/events?' + params.toString();

    fetch(restUrl)
      .then(function (res) { return res.json(); })
      .then(function (events) {
        renderAllViews(wrap, events, config);
        switchView(wrap, wrap._dcsveCurrentView);
      })
      .catch(function () {
        // Silently handle fetch errors on the frontend.
      });
  }

  // ─── Client-side filter (mirrors CsvParser::filter_events) ───

  function isoDate(d) {
    const m = d.getMonth() + 1, day = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
  }

  function lastDayOfMonth(year, month) {
    return isoDate(new Date(year, month, 0));
  }

  function lastDay(e) {
    return e.end_date || e.date;
  }

  function filterEvents(events, period, periodCount, showPast, count) {
    const now = new Date();
    const today = isoDate(now);
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    const n = Math.max(1, parseInt(periodCount, 10) || 1);

    let result = showPast ? events.slice() : events.filter(function (e) { return lastDay(e) >= today; });

    if (period !== 'all') {
      let startDate, endDate;
      if (period === 'week') {
        const dow = now.getDay() === 0 ? 7 : now.getDay();
        const monday = new Date(year, month - 1, now.getDate() - (dow - 1));
        const end = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + n * 7 - 1);
        startDate = isoDate(monday);
        endDate = isoDate(end);
      } else if (period === 'month') {
        startDate = isoDate(new Date(year, month - 1, 1));
        const endMonth = month + n - 1;
        endDate = lastDayOfMonth(year + Math.floor((endMonth - 1) / 12), ((endMonth - 1) % 12) + 1);
      } else if (period === 'quarter') {
        const qStart = Math.floor((month - 1) / 3) * 3 + 1;
        const qEnd = qStart + n * 3 - 1;
        startDate = isoDate(new Date(year, qStart - 1, 1));
        endDate = lastDayOfMonth(year + Math.floor((qEnd - 1) / 12), ((qEnd - 1) % 12) + 1);
      } else {
        startDate = year + '-01-01';
        endDate = (year + n - 1) + '-12-31';
      }
      const effectiveStart = showPast ? startDate : (startDate > today ? startDate : today);
      result = result.filter(function (e) { return lastDay(e) >= effectiveStart && e.date <= endDate; });
    }

    if (count > 0) result = result.slice(0, count);
    return result;
  }

  // ─── Render functions ───

  function parseDate(datum) {
    const parts = datum.split('-');
    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
  }

  function formatTime(e, withSuffix) {
    const start = e.start_time || '', end = e.end_time || '';
    const time = start && end ? start + '\u2013' + end : (start || e.time || '');
    if (!time) return '';
    return withSuffix === false ? time : TIME_FORMAT.replace('%s', time);
  }


  function renderDate(e, d) {
    const span = function (part, text) { return '<span class="dcsve_csv_events__d-' + part + '">' + esc(text) + '</span>'; };
    const explode = function (date) { const x = parseDate(date); return [WDAYS[x.getDay()], String(x.getDate()), MONTHS_SHORT[x.getMonth()]]; };
    const a = explode(e.date);
    const hasEnd = !!e.end_date;
    if (!d.stacked) {
      const one = function (p) { return (d.weekday ? span('wday', p[0] + ',') + ' ' : '') + span('day', p[1] + '.') + ' ' + span('mon', p[2] + '.'); };
      let html = one(a);
      if (hasEnd) html += ' \u2013 ' + one(explode(e.end_date));
      return html;
    }
    const block = function (p) { return '<span class="dcsve_csv_events__d-block">' + (d.weekday ? span('wday', p[0]) : '') + span('day', p[1]) + span('mon', p[2]) + '</span>'; };
    let html = block(a);
    if (hasEnd) html += '<span class="dcsve_csv_events__d-sep" aria-hidden="true">\u2013</span>' + block(explode(e.end_date));
    return '<span class="dcsve_csv_events__d-stack">' + html + '</span>';
  }

  function badgeParts(e) {
    const d = parseDate(e.date);
    if (!e.end_date) return [String(d.getDate()), MONTHS_SHORT[d.getMonth()]];
    const ed = parseDate(e.end_date);
    if (d.getMonth() === ed.getMonth() && d.getFullYear() === ed.getFullYear()) {
      return [d.getDate() + '\u2013' + ed.getDate(), MONTHS_SHORT[d.getMonth()]];
    }
    return [d.getDate() + '. ' + MONTHS_SHORT[d.getMonth()] + ' \u2013', ed.getDate() + '. ' + MONTHS_SHORT[ed.getMonth()]];
  }

  const PIN_SVG = '<svg class="dcsve_csv_events__pin" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z"/><circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

  function displayFlags(config) {
    const d = config.display || {};
    const on = function (k) { return d[k] === undefined ? true : !!d[k]; };
    return { time: on('time'), location: on('location'), description: on('description'), link: on('link'), monthHeadings: on('monthHeadings'), tableHeader: on('tableHeader'), weekday: on('weekday'), stacked: !!d.stacked, linkLabel: d.linkLabel || LINK_LABEL };
  }

  function renderLocation(e) {
    if (!e.location) return '';
    return PIN_SVG + '<span class="dcsve_csv_events__location">' + esc(e.location) + '</span>';
  }

  function renderMeta(e, d) {
    const parts = [];
    const t = d.time ? formatTime(e) : '';
    if (t) parts.push(esc(t));
    if (d.location && e.location) parts.push(renderLocation(e));
    return parts.join(' &middot; ');
  }

  function renderLink(e, d) {
    if (!e.link) return '';
    return '<a class="dcsve_csv_events__link" href="' + esc(e.link) + '" target="_blank" rel="noopener noreferrer">' + esc(d.linkLabel) + '</a>';
  }

  function groupByMonth(events) {
    const grouped = {};
    const order = [];
    for (let i = 0; i < events.length; i++) {
      const d = parseDate(events[i].date);
      const key = MONTHS[d.getMonth()] + ' ' + d.getFullYear();
      if (!grouped[key]) { grouped[key] = []; order.push(key); }
      grouped[key].push(events[i]);
    }
    return { groups: grouped, order: order };
  }

  function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function renderList(events, d) {
    const g = groupByMonth(events);
    let html = '';
    for (let i = 0; i < g.order.length; i++) {
      const month = g.order[i];
      const items = g.groups[month];
      html += '<div class="dcsve_csv_events__group">';
      if (d.monthHeadings) html += '<div class="dcsve_csv_events__month">' + esc(month) + '</div>';
      for (let j = 0; j < items.length; j++) {
        const e = items[j];
        html += '<div class="dcsve_csv_events__list-item">';
        html += '<div class="dcsve_csv_events__list-date dcsve_csv_events__el-date">' + renderDate(e, d);
        const t = d.time ? formatTime(e) : '';
        if (t) html += '<strong>' + esc(t) + '</strong>';
        html += '</div>';
        html += '<div class="dcsve_csv_events__list-body">';
        html += '<div class="dcsve_csv_events__list-title dcsve_csv_events__el-title">' + esc(e.title) + '</div>';
        if (d.location && e.location) html += '<div class="dcsve_csv_events__list-meta dcsve_csv_events__el-meta">' + renderLocation(e) + '</div>';
        if (d.description && e.description) html += '<div class="dcsve_csv_events__list-desc dcsve_csv_events__el-desc">' + esc(e.description) + '</div>';
        if (d.link) html += renderLink(e, d);
        html += '</div></div>';
      }
      html += '</div>';
    }
    return html || NO_EVENTS;
  }

  function renderCards(events, d) {
    const g = groupByMonth(events);
    let html = '';
    for (let i = 0; i < g.order.length; i++) {
      const month = g.order[i];
      const items = g.groups[month];
      html += '<div class="dcsve_csv_events__group">';
      if (d.monthHeadings) html += '<div class="dcsve_csv_events__month">' + esc(month) + '</div>';
      html += '<div class="dcsve_csv_events__cards-grid">';
      for (let j = 0; j < items.length; j++) {
        const e = items[j], badge = badgeParts(e);
        html += '<div class="dcsve_csv_events__card">';
        html += '<div class="dcsve_csv_events__card-date dcsve_csv_events__el-date"><span class="dcsve_csv_events__card-day dcsve_csv_events__d-day">' + esc(badge[0]) + '</span>';
        html += '<span class="dcsve_csv_events__card-mon dcsve_csv_events__d-mon">' + esc(badge[1]) + '</span></div>';
        html += '<div class="dcsve_csv_events__card-body">';
        const meta = renderMeta(e, d);
        html += '<div class="dcsve_csv_events__card-title dcsve_csv_events__el-title">' + esc(e.title) + '</div>';
        if (meta) html += '<div class="dcsve_csv_events__card-meta dcsve_csv_events__el-meta">' + meta + '</div>';
        if (d.description && e.description) html += '<div class="dcsve_csv_events__card-desc dcsve_csv_events__el-desc">' + esc(e.description) + '</div>';
        if (d.link) html += renderLink(e, d);
        html += '</div></div>';
      }
      html += '</div></div>';
    }
    return html || NO_EVENTS;
  }

  function renderTable(events, d) {
    const showDetails = d.description || d.link;
    const columns = 2 + (d.time ? 1 : 0) + (d.location ? 1 : 0) + (showDetails ? 1 : 0);
    let html = '<div class="dcsve_csv_events__table-wrap"><table class="dcsve_csv_events__table">';
    if (d.tableHeader) {
      html += '<thead><tr><th>' + esc(I18N.colDate || 'Datum') + '</th>';
      if (d.time) html += '<th>' + esc(I18N.colTime || 'Uhrzeit') + '</th>';
      html += '<th>' + esc(I18N.colTitle || 'Veranstaltung') + '</th>';
      if (d.location) html += '<th>' + esc(I18N.colLocation || 'Ort') + '</th>';
      if (showDetails) html += '<th>' + esc(I18N.colDetails || 'Details') + '</th>';
      html += '</tr></thead>';
    }
    html += '<tbody>';
    let lastMonth = '';
    for (let i = 0; i < events.length; i++) {
      const e = events[i], dt = parseDate(e.date);
      const m = MONTHS[dt.getMonth()] + ' ' + dt.getFullYear();
      if (d.monthHeadings && m !== lastMonth) { html += '<tr class="dcsve_csv_events__table-month"><td colspan="' + columns + '">' + esc(m) + '</td></tr>'; lastMonth = m; }
      html += '<tr><td class="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">' + renderDate(e, d) + '</td>';
      if (d.time) html += '<td class="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">' + esc(formatTime(e, false)) + '</td>';
      html += '<td class="dcsve_csv_events__table-title dcsve_csv_events__el-title">' + esc(e.title) + '</td>';
      if (d.location) html += '<td class="dcsve_csv_events__el-meta">' + renderLocation(e) + '</td>';
      if (showDetails) {
        html += '<td class="dcsve_csv_events__table-desc dcsve_csv_events__el-desc">';
        if (d.description) html += esc(e.description);
        if (d.link) html += renderLink(e, d);
        html += '</td>';
      }
      html += '</tr>';
    }
    html += '</tbody></table></div>';
    if (events.length === 0) html = NO_EVENTS;
    return html;
  }

  function renderSlider(events, d) {
    const sliderId = 'dcsve-slider-' + Math.random().toString(36).substr(2, 8);
    let html = '<div class="dcsve_csv_events__slider-wrap">';
    html += '<div class="dcsve_csv_events__slider-track" id="' + sliderId + '">';
    for (let i = 0; i < events.length; i++) {
      const e = events[i], badge = badgeParts(e);
      html += '<div class="dcsve_csv_events__slider-card">';
      html += '<div class="dcsve_csv_events__slider-top">';
      html += '<div class="dcsve_csv_events__slider-badge dcsve_csv_events__el-date">';
      html += '<div class="dcsve_csv_events__slider-badge-day dcsve_csv_events__d-day">' + esc(badge[0]) + '</div>';
      html += '<div class="dcsve_csv_events__slider-badge-mon dcsve_csv_events__d-mon">' + esc(badge[1]) + '</div>';
      html += '</div>';
      const meta = renderMeta(e, d);
      html += '<div class="dcsve_csv_events__slider-title dcsve_csv_events__el-title">' + esc(e.title) + '</div>';
      html += '</div>';
      if (meta) html += '<div class="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta">' + meta + '</div>';
      if (d.description && e.description) html += '<div class="dcsve_csv_events__slider-desc dcsve_csv_events__el-desc">' + esc(e.description) + '</div>';
      if (d.link) html += renderLink(e, d);
      html += '</div>';
    }
    html += '</div>';
    html += '<button class="dcsve_csv_events__slider-nav dcsve_csv_events__slider-prev" data-slider="' + sliderId + '" aria-label="' + esc(I18N.prev || 'Zurück') + '">&lsaquo;</button>';
    html += '<button class="dcsve_csv_events__slider-nav dcsve_csv_events__slider-next" data-slider="' + sliderId + '" aria-label="' + esc(I18N.next || 'Weiter') + '">&rsaquo;</button>';
    html += '</div>';
    if (events.length === 0) html = NO_EVENTS;
    return html;
  }

  function renderAllViews(wrap, events, config) {
    const content = wrap.querySelector('.dcsve_csv_events__content');
    if (!content) return;

    const showViewSwitcher = config.showViewSwitcher;
    const fixedView = config.fixedView || '';

    // Determine which views to render.
    const views = showViewSwitcher ? ['list', 'cards', 'table', 'slider'] : (fixedView ? [fixedView] : ['list', 'cards', 'table', 'slider']);
    const defaultView = wrap._dcsveCurrentView || fixedView || 'list';
    const d = displayFlags(config);

    let html = '';
    for (let i = 0; i < views.length; i++) {
      const v = views[i];
      const display = (v === defaultView) ? '' : ' style="display:none"';
      html += '<div class="dcsve_csv_events__view" data-view="' + v + '"' + display + '>';
      if (v === 'list')   html += renderList(events, d);
      if (v === 'cards')  html += renderCards(events, d);
      if (v === 'table')  html += renderTable(events, d);
      if (v === 'slider') html += renderSlider(events, d);
      html += '</div>';
    }

    content.innerHTML = html;
  }

  function switchView(wrap, targetView) {
    const views = wrap.querySelectorAll('.dcsve_csv_events__view');
    for (let i = 0; i < views.length; i++) {
      views[i].style.display = views[i].getAttribute('data-view') === targetView ? '' : 'none';
    }
  }

  // ─── Boot ───

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
