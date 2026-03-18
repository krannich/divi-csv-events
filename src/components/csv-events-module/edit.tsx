// External Dependencies.
import React, { ReactElement, useEffect, useRef, useState } from 'react';

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

// German month/day names for display.
const MONTHS_SHORT = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
const MONTHS_LONG  = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const WDAYS        = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

function parseDate(datum: string, zeit?: string): Date {
  return new Date(datum + 'T' + (zeit || '00:00') + ':00');
}

function fmtDate(d: Date): string {
  return WDAYS[d.getDay()] + ', ' + d.getDate() + '. ' + MONTHS_SHORT[d.getMonth() + 1] + '.';
}

function groupByMonth(events: CsvEvent[]): Record<string, CsvEvent[]> {
  const grouped: Record<string, CsvEvent[]> = {};
  events.forEach(e => {
    const d = parseDate(e.date);
    const key = MONTHS_LONG[d.getMonth() + 1] + ' ' + d.getFullYear();
    if (!grouped[key]) grouped[key] = [];
    grouped[key].push(e);
  });
  return grouped;
}

// ─── View renderers ───

const ListView = ({ events }: { events: CsvEvent[] }): ReactElement => {
  const grouped = groupByMonth(events);
  return (
    <div className="dcsve_csv_events__view" data-view="list">
      {Object.entries(grouped).map(([month, items]) => (
        <div className="dcsve_csv_events__group" key={month}>
          <div className="dcsve_csv_events__month">{month}</div>
          {items.map((e, i) => {
            const d = parseDate(e.date, e.time);
            return (
              <div className="dcsve_csv_events__list-item" key={i}>
                <div className="dcsve_csv_events__list-date">
                  {fmtDate(d)}
                  {e.time && <strong>{e.time} Uhr</strong>}
                </div>
                <div className="dcsve_csv_events__list-body">
                  <div className="dcsve_csv_events__list-title">{e.title}</div>
                  <div className="dcsve_csv_events__list-meta">
                    {e.location}{e.description ? ` · ${e.description}` : ''}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      ))}
    </div>
  );
};

const CardsView = ({ events }: { events: CsvEvent[] }): ReactElement => {
  const grouped = groupByMonth(events);
  return (
    <div className="dcsve_csv_events__view" data-view="cards">
      {Object.entries(grouped).map(([month, items]) => (
        <div className="dcsve_csv_events__group" key={month}>
          <div className="dcsve_csv_events__month">{month}</div>
          <div className="dcsve_csv_events__cards-grid">
            {items.map((e, i) => {
              const d = parseDate(e.date);
              return (
                <div className="dcsve_csv_events__card" key={i}>
                  <div className="dcsve_csv_events__card-date">
                    <span className="dcsve_csv_events__card-day">{d.getDate()}</span>
                    <span className="dcsve_csv_events__card-mon">{MONTHS_SHORT[d.getMonth() + 1]}</span>
                  </div>
                  <div className="dcsve_csv_events__card-body">
                    <div className="dcsve_csv_events__card-title">{e.title}</div>
                    <div className="dcsve_csv_events__card-meta">
                      {e.time ? `${e.time} Uhr · ` : ''}{e.location}
                    </div>
                    {e.description && <div className="dcsve_csv_events__card-desc">{e.description}</div>}
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

const TableView = ({ events }: { events: CsvEvent[] }): ReactElement => {
  let lastMonth = '';
  return (
    <div className="dcsve_csv_events__view" data-view="table">
      <div className="dcsve_csv_events__table-wrap">
        <table className="dcsve_csv_events__table">
          <thead>
            <tr>
              <th>{__('Datum', 'divi-csv-events')}</th>
              <th>{__('Uhrzeit', 'divi-csv-events')}</th>
              <th>{__('Veranstaltung', 'divi-csv-events')}</th>
              <th>{__('Ort', 'divi-csv-events')}</th>
              <th>{__('Details', 'divi-csv-events')}</th>
            </tr>
          </thead>
          <tbody>
            {events.map((e, i) => {
              const d = parseDate(e.date);
              const m = MONTHS_LONG[d.getMonth() + 1] + ' ' + d.getFullYear();
              const rows: ReactElement[] = [];
              if (m !== lastMonth) {
                lastMonth = m;
                rows.push(
                  <tr className="dcsve_csv_events__table-month" key={`m-${i}`}>
                    <td colSpan={5}>{m}</td>
                  </tr>
                );
              }
              rows.push(
                <tr key={i}>
                  <td style={{ whiteSpace: 'nowrap' }}>{fmtDate(d)}</td>
                  <td style={{ whiteSpace: 'nowrap' }}>{e.time}</td>
                  <td className="dcsve_csv_events__table-title">{e.title}</td>
                  <td>{e.location}</td>
                  <td className="dcsve_csv_events__table-desc">{e.description}</td>
                </tr>
              );
              return rows;
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};

const SliderView = ({ events }: { events: CsvEvent[] }): ReactElement => (
  <div className="dcsve_csv_events__view" data-view="slider">
    <div className="dcsve_csv_events__slider-wrap">
      <div className="dcsve_csv_events__slider-track">
        {events.map((e, i) => {
          const d = parseDate(e.date);
          return (
            <div className="dcsve_csv_events__slider-card" key={i}>
              <div className="dcsve_csv_events__slider-top">
                <div className="dcsve_csv_events__slider-badge">
                  <div className="dcsve_csv_events__slider-badge-day">{d.getDate()}</div>
                  <div className="dcsve_csv_events__slider-badge-mon">{MONTHS_SHORT[d.getMonth() + 1]}</div>
                </div>
                <div className="dcsve_csv_events__slider-title">{e.title}</div>
              </div>
              <div className="dcsve_csv_events__slider-detail">
                {e.time ? `${e.time} Uhr · ` : ''}{e.location}
              </div>
              {e.description && <div className="dcsve_csv_events__slider-desc">{e.description}</div>}
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
  const isOn = (val: any, defaultVal = true): boolean => {
    if (val === undefined || val === null || val === '') return defaultVal;
    if (typeof val === 'boolean') return val;
    return val === 'on' || val === 'true';
  };

  // Read attributes.
  const csvSrc      = (attrs?.csvSource?.innerContent as any)?.desktop?.value?.src || '';
  const settings    = (attrs?.eventSettings?.innerContent as any)?.desktop?.value || {};
  const period      = settings.period || 'year';
  const periodCount = parseInt(settings.periodCount || '1', 10);
  const count       = parseInt(settings.count || '0', 10);
  const showPast    = isOn(settings.showPast, false);
  const view        = settings.view || '';
  const showFilter  = isOn(settings.showFilter, true);
  const showViewSwitcher = isOn(settings.showViewSwitcher, true);
  const accentColor = settings.accentColor || '#2e7d32';

  const fetchAbortRef = useRef<AbortController>();

  // Fetch events using native fetch (bypasses potential useFetch issues in VB iframe).
  useEffect(() => {
    if (!csvSrc) {
      setEvents([]);
      return;
    }

    if (fetchAbortRef.current) {
      fetchAbortRef.current.abort();
    }

    fetchAbortRef.current = new AbortController();
    setLoading(true);
    setError('');

    const params = new URLSearchParams({
      csv_url:      csvSrc,
      period:       period,
      period_count: String(periodCount),
      count:        String(count),
      show_past:    showPast ? '1' : '0',
    });

    // Use the WP REST API base URL from the parent window.
    const wpApiSettings = (window as any).wpApiSettings
      || (window.parent as any)?.wpApiSettings
      || { root: '/wp-json/', nonce: '' };

    const restUrl = wpApiSettings.root + 'divi-csv-events/v1/events?' + params.toString();

    fetch(restUrl, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': wpApiSettings.nonce || '',
      },
      signal: fetchAbortRef.current.signal,
    })
    .then(res => {
      return res.json().then(data => {
        if (!res.ok && data?.error) {
          throw new Error(data.error);
        }
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        return data;
      });
    })
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

    return () => {
      if (fetchAbortRef.current) {
        fetchAbortRef.current.abort();
      }
    };
  }, [csvSrc, period, periodCount, count, showPast]);

  // Determine which view to show.
  const currentView = view || 'list';

  const renderView = () => {
    if (currentView === 'cards') return <CardsView events={events} />;
    if (currentView === 'table') return <TableView events={events} />;
    if (currentView === 'slider') return <SliderView events={events} />;
    return <ListView events={events} />;
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
      <div className="dcsve_csv_events__inner" style={{ '--dcsve-accent': accentColor } as React.CSSProperties}>
        {elements.render({ attrName: 'heading' })}

        {/* Controls bar */}
        {(showFilter || showViewSwitcher) && (
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
          </div>
        )}

        <div className="dcsve_csv_events__content">
          {loading && (
            <div className="dcsve_csv_events__empty">{__('Loading events...', 'divi-csv-events')}</div>
          )}

          {!loading && !csvSrc && (
            <div className="dcsve_csv_events__empty">
              {__('Please upload a CSV file in Content > CSV Source.', 'divi-csv-events')}
            </div>
          )}

          {!loading && csvSrc && error && (
            <div className="dcsve_csv_events__warning">
              <strong>{__('CSV structure is invalid.', 'divi-csv-events')}</strong><br />{error}
            </div>
          )}

          {!loading && csvSrc && !error && events.length === 0 && (
            <div className="dcsve_csv_events__empty">
              {__('No events found for the selected period.', 'divi-csv-events')}
            </div>
          )}

          {!loading && events.length > 0 && renderView()}
        </div>
      </div>
    </ModuleContainer>
  );
};
