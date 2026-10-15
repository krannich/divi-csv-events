// External Dependencies.
import React, { ReactElement, useEffect, useMemo, useRef, useState } from 'react';

// Divi Dependencies.
import {
  ModuleContainer,
  ElementComponents,
} from '@divi/module';

// WordPress Dependencies.
import { __ } from '@wordpress/i18n';

// Local Dependencies.
import { CsvEventsModuleEditProps, CsvEvent } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';

// Translatable month/day names — evaluated lazily to ensure i18n is initialized.
const getMonthsShort = () => ['', __('Jan', 'divi-csv-events'), __('Feb', 'divi-csv-events'), __('Mär', 'divi-csv-events'), __('Apr', 'divi-csv-events'), __('Mai', 'divi-csv-events'), __('Jun', 'divi-csv-events'), __('Jul', 'divi-csv-events'), __('Aug', 'divi-csv-events'), __('Sep', 'divi-csv-events'), __('Okt', 'divi-csv-events'), __('Nov', 'divi-csv-events'), __('Dez', 'divi-csv-events')];
const getMonthsLong  = () => ['', __('Januar', 'divi-csv-events'), __('Februar', 'divi-csv-events'), __('März', 'divi-csv-events'), __('April', 'divi-csv-events'), __('Mai', 'divi-csv-events'), __('Juni', 'divi-csv-events'), __('Juli', 'divi-csv-events'), __('August', 'divi-csv-events'), __('September', 'divi-csv-events'), __('Oktober', 'divi-csv-events'), __('November', 'divi-csv-events'), __('Dezember', 'divi-csv-events')];
const getWdays       = () => [__('So', 'divi-csv-events'), __('Mo', 'divi-csv-events'), __('Di', 'divi-csv-events'), __('Mi', 'divi-csv-events'), __('Do', 'divi-csv-events'), __('Fr', 'divi-csv-events'), __('Sa', 'divi-csv-events')];

function formatTime(e: CsvEvent): string {
  const start = e.start_time || '';
  const end   = e.end_time   || '';
  if (start && end) return `${start}\u2013${end} Uhr`;  // en-dash
  if (start)       return `${start} Uhr`;
  if (e.time)     return `${e.time} Uhr`;              // legacy fallback
  return '';
}

function parseDate(datum: string, zeit?: string): Date {
  return new Date(datum + 'T' + (zeit || '00:00') + ':00');
}


const DatePart = ({ part, text }: { part: 'wday' | 'day' | 'mon'; text: string }): ReactElement => (
  <span className={`dcsve_csv_events__d-${part}`}>{text}</span>
);

const EventDate = ({ event, d }: { event: CsvEvent; d: DisplayFlags }): ReactElement => {
  const wdays = getWdays();
  const months = getMonthsShort();
  const explode = (date: string): [string, string, string] => {
    const x = parseDate(date);
    return [wdays[x.getDay()], String(x.getDate()), months[x.getMonth() + 1]];
  };
  const a = explode(event.date);
  const hasEnd = !!event.end_date;

  if (!d.stacked) {
    const one = (p: [string, string, string], key: string): ReactElement => (
      <React.Fragment key={key}>
        {d.weekday && <><DatePart part="wday" text={`${p[0]},`} />{' '}</>}
        <DatePart part="day" text={`${p[1]}.`} />{' '}
        <DatePart part="mon" text={`${p[2]}.`} />
      </React.Fragment>
    );
    return <>{one(a, 'a')}{hasEnd && <>{' \u2013 '}{one(explode(event.end_date as string), 'b')}</>}</>;
  }

  const block = (p: [string, string, string], key: string): ReactElement => (
    <span className="dcsve_csv_events__d-block" key={key}>
      {d.weekday && <DatePart part="wday" text={p[0]} />}
      <DatePart part="day" text={p[1]} />
      <DatePart part="mon" text={p[2]} />
    </span>
  );
  return (
    <span className="dcsve_csv_events__d-stack">
      {block(a, 'a')}
      {hasEnd && <><span className="dcsve_csv_events__d-sep" aria-hidden="true">{'\u2013'}</span>{block(explode(event.end_date as string), 'b')}</>}
    </span>
  );
};

function badgeParts(e: CsvEvent): [string, string] {
  const monthsShort = getMonthsShort();
  const d = parseDate(e.date);
  if (!e.end_date) return [String(d.getDate()), monthsShort[d.getMonth() + 1]];
  const ed = parseDate(e.end_date);
  if (d.getMonth() === ed.getMonth() && d.getFullYear() === ed.getFullYear()) {
    return [`${d.getDate()}\u2013${ed.getDate()}`, monthsShort[d.getMonth() + 1]];
  }
  return [`${d.getDate()}. ${monthsShort[d.getMonth() + 1]} \u2013`, `${ed.getDate()}. ${monthsShort[ed.getMonth() + 1]}`];
}

interface DisplayFlags {
  weekday: boolean;
  stacked: boolean;
  time: boolean;
  location: boolean;
  description: boolean;
  link: boolean;
  monthHeadings: boolean;
  tableHeader: boolean;
  linkLabel: string;
}

const PinIcon = (): ReactElement => (
  <svg className="dcsve_csv_events__pin" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false">
    <path fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" />
    <circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" strokeWidth="2" />
  </svg>
);

const EventLocation = ({ event }: { event: CsvEvent }): ReactElement | null => (
  event.location
    ? <><PinIcon /><span className="dcsve_csv_events__location">{event.location}</span></>
    : null
);

const EventMeta = ({ event, d }: { event: CsvEvent; d: DisplayFlags }): ReactElement | null => {
  const t = d.time ? formatTime(event) : '';
  const hasLocation = d.location && !!event.location;
  if (!t && !hasLocation) return null;
  return (
    <>
      {t}
      {t && hasLocation && ' · '}
      {hasLocation && <EventLocation event={event} />}
    </>
  );
};

const EventLink = ({ event, d }: { event: CsvEvent; d: DisplayFlags }): ReactElement | null => (
  event.link
    ? <a className="dcsve_csv_events__link" href={event.link} target="_blank" rel="noopener noreferrer">{d.linkLabel}</a>
    : null
);

function groupByMonth(events: CsvEvent[]): Record<string, CsvEvent[]> {
  const monthsLong = getMonthsLong();
  const grouped: Record<string, CsvEvent[]> = {};
  events.forEach(e => {
    const d = parseDate(e.date);
    const key = monthsLong[d.getMonth() + 1] + ' ' + d.getFullYear();
    if (!grouped[key]) grouped[key] = [];
    grouped[key].push(e);
  });
  return grouped;
}

// ─── View renderers ───

const ListView = ({ events, d }: { events: CsvEvent[]; d: DisplayFlags }): ReactElement => {
  const grouped = groupByMonth(events);
  return (
    <div className="dcsve_csv_events__view" data-view="list">
      {Object.entries(grouped).map(([month, items]) => (
        <div className="dcsve_csv_events__group" key={month}>
          {d.monthHeadings && <div className="dcsve_csv_events__month dcsve_csv_events__el-month">{month}</div>}
          {items.map((e, i) => {
            const t = d.time ? formatTime(e) : '';
            return (
              <div className="dcsve_csv_events__list-item" key={i}>
                <div className="dcsve_csv_events__list-date dcsve_csv_events__el-date">
                  <EventDate event={e} d={d} />
                  {t && <strong>{t}</strong>}
                </div>
                <div className="dcsve_csv_events__list-body">
                  <div className="dcsve_csv_events__list-title dcsve_csv_events__el-title">{e.title}</div>
                  {d.location && e.location && (
                    <div className="dcsve_csv_events__list-meta dcsve_csv_events__el-meta"><EventLocation event={e} /></div>
                  )}
                  {d.description && e.description && (
                    <div className="dcsve_csv_events__list-desc dcsve_csv_events__el-desc">{e.description}</div>
                  )}
                  {d.link && <EventLink event={e} d={d} />}
                </div>
              </div>
            );
          })}
        </div>
      ))}
    </div>
  );
};

const CardsView = ({ events, d }: { events: CsvEvent[]; d: DisplayFlags }): ReactElement => {
  const grouped = groupByMonth(events);
  return (
    <div className="dcsve_csv_events__view" data-view="cards">
      {Object.entries(grouped).map(([month, items]) => (
        <div className="dcsve_csv_events__group" key={month}>
          {d.monthHeadings && <div className="dcsve_csv_events__month dcsve_csv_events__el-month">{month}</div>}
          <div className="dcsve_csv_events__cards-grid">
            {items.map((e, i) => {
              const [badgeDay, badgeMon] = badgeParts(e);
              return (
                <div className="dcsve_csv_events__card" key={i}>
                  <div className="dcsve_csv_events__card-date dcsve_csv_events__el-date">
                    <span className="dcsve_csv_events__card-day dcsve_csv_events__d-day">{badgeDay}</span>
                    <span className="dcsve_csv_events__card-mon dcsve_csv_events__d-mon">{badgeMon}</span>
                  </div>
                  <div className="dcsve_csv_events__card-body">
                    <div className="dcsve_csv_events__card-title dcsve_csv_events__el-title">{e.title}</div>
                    {((d.time && formatTime(e)) || (d.location && e.location)) && (
                      <div className="dcsve_csv_events__card-meta dcsve_csv_events__el-meta"><EventMeta event={e} d={d} /></div>
                    )}
                    {d.description && e.description && <div className="dcsve_csv_events__card-desc dcsve_csv_events__el-desc">{e.description}</div>}
                    {d.link && <EventLink event={e} d={d} />}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
};

const TableView = ({ events, d }: { events: CsvEvent[]; d: DisplayFlags }): ReactElement => {
  const showDetails = d.description || d.link;
  const columns = 2 + (d.time ? 1 : 0) + (d.location ? 1 : 0) + (showDetails ? 1 : 0);

  const rows = useMemo(() => {
    let lastMonth = '';
    const result: ReactElement[] = [];
    events.forEach((e, i) => {
      const dt = parseDate(e.date);
      const m = getMonthsLong()[dt.getMonth() + 1] + ' ' + dt.getFullYear();
      if (d.monthHeadings && m !== lastMonth) {
        lastMonth = m;
        result.push(
          <tr className="dcsve_csv_events__table-month" key={`m-${m}`}>
            <td className="dcsve_csv_events__el-month" colSpan={columns}>{m}</td>
          </tr>
        );
      }
      result.push(
        <tr key={`${e.date}-${e.time}-${i}`}>
          <td className="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date"><EventDate event={e} d={d} /></td>
          {d.time && <td className="dcsve_csv_events__table-nowrap dcsve_csv_events__el-date">{formatTime(e).replace(/\sUhr$/, '')}</td>}
          <td className="dcsve_csv_events__table-title dcsve_csv_events__el-title">{e.title}</td>
          {d.location && <td className="dcsve_csv_events__el-meta"><EventLocation event={e} /></td>}
          {showDetails && (
            <td className="dcsve_csv_events__table-desc dcsve_csv_events__el-desc">
              {d.description && e.description}
              {d.link && <EventLink event={e} d={d} />}
            </td>
          )}
        </tr>
      );
    });
    return result;
  }, [events, d, columns, showDetails]);

  return (
    <div className="dcsve_csv_events__view" data-view="table">
      <div className="dcsve_csv_events__table-wrap">
        <table className="dcsve_csv_events__table">
          {d.tableHeader && (
            <thead>
              <tr>
                <th>{__('Datum', 'divi-csv-events')}</th>
                {d.time && <th>{__('Uhrzeit', 'divi-csv-events')}</th>}
                <th>{__('Veranstaltung', 'divi-csv-events')}</th>
                {d.location && <th>{__('Ort', 'divi-csv-events')}</th>}
                {showDetails && <th>{__('Details', 'divi-csv-events')}</th>}
              </tr>
            </thead>
          )}
          <tbody>{rows}</tbody>
        </table>
      </div>
    </div>
  );
};

const SliderView = ({ events, d }: { events: CsvEvent[]; d: DisplayFlags }): ReactElement => (
  <div className="dcsve_csv_events__view" data-view="slider">
    <div className="dcsve_csv_events__slider-wrap">
      <div className="dcsve_csv_events__slider-track">
        {events.map((e, i) => {
          const [badgeDay, badgeMon] = badgeParts(e);
          return (
            <div className="dcsve_csv_events__slider-card" key={i}>
              <div className="dcsve_csv_events__slider-top">
                <div className="dcsve_csv_events__slider-badge dcsve_csv_events__el-date">
                  <div className="dcsve_csv_events__slider-badge-day dcsve_csv_events__d-day">{badgeDay}</div>
                  <div className="dcsve_csv_events__slider-badge-mon dcsve_csv_events__d-mon">{badgeMon}</div>
                </div>
                <div className="dcsve_csv_events__slider-title dcsve_csv_events__el-title">{e.title}</div>
              </div>
              {((d.time && formatTime(e)) || (d.location && e.location)) && (
                <div className="dcsve_csv_events__slider-detail dcsve_csv_events__el-meta"><EventMeta event={e} d={d} /></div>
              )}
              {d.description && e.description && <div className="dcsve_csv_events__slider-desc dcsve_csv_events__el-desc">{e.description}</div>}
              {d.link && <EventLink event={e} d={d} />}
            </div>
          );
        })}
      </div>
    </div>
  </div>
);

/**
 * CSV Events Module edit component for the Visual Builder.
 *
 * @since 1.0.0
 */
export const CsvEventsModuleEdit = (props: CsvEventsModuleEditProps): ReactElement => {
  const {
    attrs,
    elements,
    id,
    name,
  } = props;

  const [events, setEvents] = useState<CsvEvent[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Helper: check if a toggle value is "on".
  const isOn = (val: string | boolean | undefined | null, defaultVal = true): boolean => {
    if (val === undefined || val === null || val === '') return defaultVal;
    if (typeof val === 'boolean') return val;
    return val === 'on' || val === 'true';
  };

  // Read attributes.
  const sourceMode  = (attrs?.csvSourceMode?.innerContent?.desktop?.value?.mode) || 'file';
  const csvSrc      = attrs?.csvSource?.innerContent?.desktop?.value?.src || '';
  const csvContent  = attrs?.csvContent?.innerContent?.desktop?.value?.content || '';
  const settings    = attrs?.eventSettings?.innerContent?.desktop?.value || {};
  const period      = settings.period || 'year';
  const periodCount = parseInt(settings.periodCount || '1', 10);
  const count       = parseInt(settings.count || '0', 10);
  const showPast    = isOn(settings.showPast, false);
  const view        = settings.view || '';
  const showFilter  = isOn(settings.showFilter, true);
  const showViewSwitcher = isOn(settings.showViewSwitcher, true);
  const showPrint   = isOn(settings.showPrint, false);
  const printPosition = settings.printPosition || 'top';
  const printTop    = showPrint && printPosition !== 'bottom';
  const printBottom = showPrint && printPosition !== 'top';
  const printButton = <span className="dcsve_csv_events__btn dcsve_csv_events__btn-print">{__('Drucken', 'divi-csv-events')}</span>;
  const accentColor = settings.accentColor || '#2e7d32';
  const display: DisplayFlags = {
    weekday:       isOn(settings.showWeekday, true),
    stacked:       settings.dateLayout === 'stacked',
    time:          isOn(settings.showTime, true),
    location:      isOn(settings.showLocation, true),
    description:   isOn(settings.showDescription, true),
    link:          isOn(settings.showLink, true),
    monthHeadings: isOn(settings.showMonthHeadings, true),
    tableHeader:   isOn(settings.showTableHeader, true),
    linkLabel:     (settings.linkLabel || '').trim() || __('Weitere Informationen', 'divi-csv-events'),
  };

  const innerContent = attrs?.eventSettings?.innerContent;
  const tlDesktop = innerContent?.desktop?.value?.tableMobile || 'scroll';
  const tlTablet  = innerContent?.tablet?.value?.tableMobile || tlDesktop;
  const tlPhone   = innerContent?.phone?.value?.tableMobile || tlTablet;
  const tableStackClasses = ([['desktop', tlDesktop], ['tablet', tlTablet], ['phone', tlPhone]] as const)
    .filter(([, layout]) => layout === 'stack' || layout === 'split')
    .map(([band, layout]) => `dcsve_csv_events--table-${layout}-${band}`)
    .join(' ');

  const fetchAbortRef = useRef<AbortController>();

  useEffect(() => {
    const hasSource = sourceMode === 'file' ? !!csvSrc : !!csvContent;
    if (!hasSource) {
      setEvents([]);
      setError('');
      return;
    }

    const debounceTimer = setTimeout(() => {
      if (fetchAbortRef.current) {
        fetchAbortRef.current.abort();
      }

      fetchAbortRef.current = new AbortController();
      setLoading(true);
      setError('');

      const wpApiSettings = window.wpApiSettings
        || window.parent?.wpApiSettings
        || { root: '/wp-json/', nonce: '' };

      const baseUrl = wpApiSettings.root + 'divi-csv-events/v1/events';

      const filterPayload = {
        period,
        period_count: String(periodCount),
        count:        String(count),
        show_past:    showPast ? 'true' : 'false',
      };

      let requestInit: RequestInit;
      let url: string;

      if (sourceMode === 'paste') {
        url = baseUrl;
        requestInit = {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce':   wpApiSettings.nonce || '',
          },
          body: JSON.stringify({
            csv_content: csvContent,
            ...filterPayload,
          }),
          signal: fetchAbortRef.current.signal,
        };
      } else {
        const params = new URLSearchParams({
          csv_url: csvSrc,
          ...filterPayload,
        });
        url = baseUrl + '?' + params.toString();
        requestInit = {
          method: 'GET',
          headers: { 'X-WP-Nonce': wpApiSettings.nonce || '' },
          signal: fetchAbortRef.current.signal,
        };
      }

      fetch(url, requestInit)
        .then(res => res.json().then(data => {
          if (!res.ok && data?.error) throw new Error(data.error);
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return data;
        }))
        .then(data => {
          if (data && data.error) {
            setError(data.error);
            setEvents([]);
          } else {
            setEvents(Array.isArray(data) ? data : []);
            setError('');
          }
          setLoading(false);
        })
        .catch(err => {
          if (err.name !== 'AbortError') {
            setError(err.message);
            setLoading(false);
          }
        });
    }, 300);

    return () => {
      clearTimeout(debounceTimer);
      if (fetchAbortRef.current) {
        fetchAbortRef.current.abort();
      }
    };
  }, [sourceMode, csvSrc, csvContent, period, periodCount, count, showPast]);

  // Determine which view to show.
  const currentView = view || 'list';

  const renderView = () => {
    if (currentView === 'cards') return <CardsView events={events} d={display} />;
    if (currentView === 'table') return <TableView events={events} d={display} />;
    if (currentView === 'slider') return <SliderView events={events} d={display} />;
    return <ListView events={events} d={display} />;
  };

  return (
    <ModuleContainer
      attrs={attrs}
      elements={elements}
      id={id}
      name={name}
      stylesComponent={ModuleStyles}
      classnamesFunction={moduleClassnames}
      scriptDataComponent={ModuleScriptData}
    >
      {elements.styleComponents({
        attrName: 'module',
      })}
      <ElementComponents
        attrs={attrs?.module?.decoration ?? {}}
        id={id}
      />
      <div className={`dcsve_csv_events__inner${display.stacked ? ' dcsve_csv_events--date-stacked' : ''}${tableStackClasses ? ' ' + tableStackClasses : ''}`} style={{ '--dcsve-accent': accentColor } as React.CSSProperties}>
        {elements.render({ attrName: 'heading' })}

        {/* Controls bar */}
        {(showFilter || showViewSwitcher || printTop) && (
          <div className="dcsve_csv_events__controls">
            {showFilter && (
              <div className="dcsve_csv_events__periods">
                {[
                  { key: 'week',    label: __('Woche', 'divi-csv-events') },
                  { key: 'month',   label: __('Monat', 'divi-csv-events') },
                  { key: 'quarter', label: __('Quartal', 'divi-csv-events') },
                  { key: 'year',    label: __('Jahr', 'divi-csv-events') },
                  { key: 'all',     label: __('Alle', 'divi-csv-events') },
                ].map(p => (
                  <span
                    key={p.key}
                    className={`dcsve_csv_events__btn${p.key === period ? ' dcsve_csv_events__btn--active' : ''}`}
                  >
                    {p.label}
                  </span>
                ))}
              </div>
            )}
            {showViewSwitcher && (
              <div className="dcsve_csv_events__views">
                {[
                  { key: 'list', icon: '\u2630' },
                  { key: 'cards', icon: '\u25A6' },
                  { key: 'table', icon: '\u25A4' },
                  { key: 'slider', icon: '\u25B6' },
                ].map(v => (
                  <span
                    key={v.key}
                    className={`dcsve_csv_events__btn dcsve_csv_events__btn-view${v.key === currentView ? ' dcsve_csv_events__btn--active' : ''}`}
                    title={v.key}
                  >
                    {v.icon}
                  </span>
                ))}
              </div>
            )}
            {printTop && <div className="dcsve_csv_events__print">{printButton}</div>}
          </div>
        )}

        <div className="dcsve_csv_events__content">
          {loading && (
            <div className="dcsve_csv_events__empty">{__('Loading events...', 'divi-csv-events')}</div>
          )}

          {!loading && sourceMode === 'file' && !csvSrc && (
            <div className="dcsve_csv_events__empty">
              {__('Please upload a CSV file in Content > CSV Source.', 'divi-csv-events')}
            </div>
          )}

          {!loading && sourceMode === 'paste' && !csvContent && (
            <div className="dcsve_csv_events__empty">
              {__('Please paste CSV data in Content > CSV Data.', 'divi-csv-events')}
            </div>
          )}

          {!loading && (sourceMode === 'file' ? csvSrc : csvContent) && error && (
            <div className="dcsve_csv_events__warning">
              <strong>{__('CSV structure is invalid.', 'divi-csv-events')}</strong><br />{error}
            </div>
          )}

          {!loading && (sourceMode === 'file' ? csvSrc : csvContent) && !error && events.length === 0 && (
            <div className="dcsve_csv_events__empty">
              {__('No events found for the selected period.', 'divi-csv-events')}
            </div>
          )}

          {!loading && events.length > 0 && renderView()}
        </div>
        {printBottom && <div className="dcsve_csv_events__print dcsve_csv_events__print--bottom">{printButton}</div>}
      </div>
    </ModuleContainer>
  );
};
