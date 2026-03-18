(function () {
  'use strict';

  const MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const MONTHS_SHORT = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
  const WDAYS = ['So','Mo','Di','Mi','Do','Fr','Sa'];

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

    // Read CSV URL from the initially loaded data config.
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

  // ─── Render functions ───

  function parseDate(datum) {
    const parts = datum.split('-');
    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
  }

  function fmtDate(d) {
    return WDAYS[d.getDay()] + ', ' + d.getDate() + '. ' + MONTHS_SHORT[d.getMonth()] + '.';
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

  function renderList(events) {
    const g = groupByMonth(events);
    let html = '';
    for (let i = 0; i < g.order.length; i++) {
      const month = g.order[i];
      const items = g.groups[month];
      html += '<div class="dcsve_csv_events__group"><div class="dcsve_csv_events__month">' + esc(month) + '</div>';
      for (let j = 0; j < items.length; j++) {
        const e = items[j], d = parseDate(e.date);
        html += '<div class="dcsve_csv_events__list-item">';
        html += '<div class="dcsve_csv_events__list-date">' + esc(fmtDate(d));
        if (e.time) html += '<strong>' + esc(e.time + ' Uhr') + '</strong>';
        html += '</div>';
        html += '<div class="dcsve_csv_events__list-body">';
        html += '<div class="dcsve_csv_events__list-title">' + esc(e.title) + '</div>';
        html += '<div class="dcsve_csv_events__list-meta">' + esc(e.location) + (e.description ? ' &middot; ' + esc(e.description) : '') + '</div>';
        html += '</div></div>';
      }
      html += '</div>';
    }
    return html || '<div class="dcsve_csv_events__no-events">Keine Termine im gewählten Zeitraum.</div>';
  }

  function renderCards(events) {
    const g = groupByMonth(events);
    let html = '';
    for (let i = 0; i < g.order.length; i++) {
      const month = g.order[i];
      const items = g.groups[month];
      html += '<div class="dcsve_csv_events__group"><div class="dcsve_csv_events__month">' + esc(month) + '</div>';
      html += '<div class="dcsve_csv_events__cards-grid">';
      for (let j = 0; j < items.length; j++) {
        const e = items[j], d = parseDate(e.date);
        html += '<div class="dcsve_csv_events__card">';
        html += '<div class="dcsve_csv_events__card-date"><span class="dcsve_csv_events__card-day">' + d.getDate() + '</span>';
        html += '<span class="dcsve_csv_events__card-mon">' + MONTHS_SHORT[d.getMonth()] + '</span></div>';
        html += '<div class="dcsve_csv_events__card-body">';
        html += '<div class="dcsve_csv_events__card-title">' + esc(e.title) + '</div>';
        html += '<div class="dcsve_csv_events__card-meta">' + (e.time ? esc(e.time + ' Uhr') + ' &middot; ' : '') + esc(e.location) + '</div>';
        if (e.description) html += '<div class="dcsve_csv_events__card-desc">' + esc(e.description) + '</div>';
        html += '</div></div>';
      }
      html += '</div></div>';
    }
    return html || '<div class="dcsve_csv_events__no-events">Keine Termine im gewählten Zeitraum.</div>';
  }

  function renderTable(events) {
    let html = '<div class="dcsve_csv_events__table-wrap"><table class="dcsve_csv_events__table">';
    html += '<thead><tr><th>Datum</th><th>Uhrzeit</th><th>Veranstaltung</th><th>Ort</th><th>Details</th></tr></thead><tbody>';
    let lastMonth = '';
    for (let i = 0; i < events.length; i++) {
      const e = events[i], d = parseDate(e.date);
      const m = MONTHS[d.getMonth()] + ' ' + d.getFullYear();
      if (m !== lastMonth) { html += '<tr class="dcsve_csv_events__table-month"><td colspan="5">' + esc(m) + '</td></tr>'; lastMonth = m; }
      html += '<tr><td class="dcsve_csv_events__table-nowrap">' + esc(fmtDate(d)) + '</td>';
      html += '<td class="dcsve_csv_events__table-nowrap">' + esc(e.time) + '</td>';
      html += '<td class="dcsve_csv_events__table-title">' + esc(e.title) + '</td>';
      html += '<td>' + esc(e.location) + '</td>';
      html += '<td class="dcsve_csv_events__table-desc">' + esc(e.description) + '</td></tr>';
    }
    html += '</tbody></table></div>';
    if (events.length === 0) html = '<div class="dcsve_csv_events__no-events">Keine Termine im gewählten Zeitraum.</div>';
    return html;
  }

  function renderSlider(events) {
    const sliderId = 'dcsve-slider-' + Math.random().toString(36).substr(2, 8);
    let html = '<div class="dcsve_csv_events__slider-wrap">';
    html += '<div class="dcsve_csv_events__slider-track" id="' + sliderId + '">';
    for (let i = 0; i < events.length; i++) {
      const e = events[i], d = parseDate(e.date);
      html += '<div class="dcsve_csv_events__slider-card">';
      html += '<div class="dcsve_csv_events__slider-top">';
      html += '<div class="dcsve_csv_events__slider-badge">';
      html += '<div class="dcsve_csv_events__slider-badge-day">' + d.getDate() + '</div>';
      html += '<div class="dcsve_csv_events__slider-badge-mon">' + MONTHS_SHORT[d.getMonth()] + '</div>';
      html += '</div>';
      html += '<div class="dcsve_csv_events__slider-title">' + esc(e.title) + '</div>';
      html += '</div>';
      html += '<div class="dcsve_csv_events__slider-detail">' + (e.time ? esc(e.time + ' Uhr') + ' &middot; ' : '') + esc(e.location) + '</div>';
      if (e.description) html += '<div class="dcsve_csv_events__slider-desc">' + esc(e.description) + '</div>';
      html += '</div>';
    }
    html += '</div>';
    html += '<button class="dcsve_csv_events__slider-nav dcsve_csv_events__slider-prev" data-slider="' + sliderId + '" aria-label="Zurück">&lsaquo;</button>';
    html += '<button class="dcsve_csv_events__slider-nav dcsve_csv_events__slider-next" data-slider="' + sliderId + '" aria-label="Weiter">&rsaquo;</button>';
    html += '</div>';
    if (events.length === 0) html = '<div class="dcsve_csv_events__no-events">Keine Termine im gewählten Zeitraum.</div>';
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

    let html = '';
    for (let i = 0; i < views.length; i++) {
      const v = views[i];
      const display = (v === defaultView) ? '' : ' style="display:none"';
      html += '<div class="dcsve_csv_events__view" data-view="' + v + '"' + display + '>';
      if (v === 'list')   html += renderList(events);
      if (v === 'cards')  html += renderCards(events);
      if (v === 'table')  html += renderTable(events);
      if (v === 'slider') html += renderSlider(events);
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
