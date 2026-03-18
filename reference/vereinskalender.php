<?php
/**
 * Plugin Name: Vereinskalender
 * Description: Einfacher Event-Kalender mit CSV-Datenquelle. Shortcode: [vereinskalender]
 * Version: 1.0.0
 * Author: Schützenverein Schneverdingen
 *
 * CSV-Datei ablegen unter: wp-content/uploads/vereinskalender/termine.csv
 * Format: Datum;Uhrzeit;Titel;Ort;Beschreibung
 * Beispiel: 2026-06-13;08:00;Schützenfest Tag 1;Festplatz;Festumzug und Königsschießen
 *
 * Shortcode-Parameter:
 *   view     = list | cards | table | slider          (Default: alle umschaltbar)
 *   count    = Anzahl Termine, z.B. 3                  (Default: alle)
 *   period   = week | month | quarter | year | all     (Default: year)
 *   filter   = true | false                            (Default: true)
 *   heading  = Optionale Überschrift                   (Default: keine)
 *   past     = true | false - vergangene anzeigen      (Default: false)
 *
 * Beispiele:
 *   [vereinskalender]
 *   [vereinskalender view="cards" count="3" filter="false" heading="Nächste Termine"]
 *   [vereinskalender view="slider" count="5" filter="false"]
 *   [vereinskalender view="list" count="3" filter="false"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Vereinskalender {

    private static $instance_count = 0;

    public static function init() {
        add_shortcode( 'vereinskalender', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
    }

    /**
     * CSV einlesen und als Array zurückgeben
     */
    private static function load_csv() {
        $upload_dir = wp_upload_dir();
        $csv_path   = $upload_dir['basedir'] . '/vereinskalender/termine.csv';

        if ( ! file_exists( $csv_path ) ) {
            return [];
        }

        $events = [];
        $handle = fopen( $csv_path, 'r' );
        if ( ! $handle ) return [];

        // BOM entfernen falls vorhanden
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $header = fgetcsv( $handle, 0, ';' );
        if ( ! $header ) {
            fclose( $handle );
            return [];
        }

        while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
            if ( count( $row ) < 3 ) continue;

            $datum = trim( $row[0] ?? '' );
            $zeit  = trim( $row[1] ?? '' );
            $titel = trim( $row[2] ?? '' );
            $ort   = trim( $row[3] ?? '' );
            $desc  = trim( $row[4] ?? '' );

            if ( empty( $datum ) || empty( $titel ) ) continue;

            $events[] = [
                'datum'  => $datum,
                'zeit'   => $zeit,
                'titel'  => esc_html( $titel ),
                'ort'    => esc_html( $ort ),
                'desc'   => esc_html( $desc ),
                'ts'     => strtotime( $datum . ' ' . $zeit ),
            ];
        }

        fclose( $handle );

        // Chronologisch sortieren
        usort( $events, function( $a, $b ) {
            return $a['ts'] - $b['ts'];
        });

        return $events;
    }

    /**
     * Events nach Zeitraum filtern
     */
    private static function filter_events( $events, $period, $show_past ) {
        $now = current_time( 'timestamp' );
        $today_start = strtotime( 'today midnight', $now );

        // Vergangene ausfiltern
        if ( ! $show_past ) {
            $events = array_filter( $events, function( $e ) use ( $today_start ) {
                return $e['ts'] >= $today_start;
            });
            $events = array_values( $events );
        }

        if ( $period === 'all' ) return $events;

        $end = $now;
        switch ( $period ) {
            case 'week':    $end = strtotime( '+1 week', $now ); break;
            case 'month':   $end = strtotime( '+1 month', $now ); break;
            case 'quarter': $end = strtotime( '+3 months', $now ); break;
            case 'year':    $end = strtotime( '+1 year', $now ); break;
        }

        return array_values( array_filter( $events, function( $e ) use ( $now, $end, $today_start, $show_past ) {
            $start = $show_past ? 0 : $today_start;
            return $e['ts'] >= $start && $e['ts'] <= $end;
        }));
    }

    /**
     * Styles registrieren (inline, kein externes CSS nötig)
     */
    public static function register_assets() {
        // Wird nur geladen wenn Shortcode genutzt wird
    }

    /**
     * Shortcode rendern
     */
    public static function render_shortcode( $atts ) {
        self::$instance_count++;
        $id = 'vk-' . self::$instance_count;

        $atts = shortcode_atts( [
            'view'    => '',        // list, cards, table, slider — leer = alle umschaltbar
            'count'   => 0,         // 0 = alle
            'period'  => 'year',
            'filter'  => 'true',
            'heading' => '',
            'past'    => 'false',
        ], $atts, 'vereinskalender' );

        $events    = self::load_csv();
        $show_past = $atts['past'] === 'true';
        $has_filter = $atts['filter'] === 'true';
        $fixed_view = sanitize_text_field( $atts['view'] );
        $count     = absint( $atts['count'] );
        $period    = sanitize_text_field( $atts['period'] );
        $heading   = sanitize_text_field( $atts['heading'] );

        if ( empty( $events ) ) {
            return '<div class="vk-empty">Keine Termine vorhanden. Bitte CSV-Datei unter <code>wp-content/uploads/vereinskalender/termine.csv</code> anlegen.</div>';
        }

        // Events als JSON für JS
        $events_json = wp_json_encode( $events );

        // Config für diese Instanz
        $config = wp_json_encode( [
            'id'        => $id,
            'fixedView' => $fixed_view,
            'count'     => $count,
            'period'    => $period,
            'hasFilter' => $has_filter,
            'showPast'  => $show_past,
            'heading'   => $heading,
        ] );

        ob_start();

        // CSS nur einmal ausgeben
        if ( self::$instance_count === 1 ) {
            self::render_styles();
        }
        ?>

        <div class="vk-wrap" id="<?php echo esc_attr( $id ); ?>">
            <?php if ( $heading ) : ?>
                <h3 class="vk-heading"><?php echo esc_html( $heading ); ?></h3>
            <?php endif; ?>

            <?php if ( $has_filter ) : ?>
                <div class="vk-controls">
                    <div class="vk-periods" data-target="<?php echo esc_attr( $id ); ?>"></div>
                    <?php if ( empty( $fixed_view ) ) : ?>
                        <div class="vk-views" data-target="<?php echo esc_attr( $id ); ?>"></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="vk-content"></div>
        </div>

        <script>
        (function() {
            var events = <?php echo $events_json; ?>;
            var config = <?php echo $config; ?>;
            if (typeof window.VKInstances === 'undefined') window.VKInstances = {};
            window.VKInstances[config.id] = { events: events, config: config };

            if (typeof window.VKInit === 'function') {
                window.VKInit(config.id);
            }
        })();
        </script>

        <?php

        // JS nur einmal ausgeben
        if ( self::$instance_count === 1 ) {
            self::render_script();
        }

        return ob_get_clean();
    }

    /**
     * CSS ausgeben
     */
    private static function render_styles() {
        ?>
        <style>
        .vk-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .vk-heading { font-size: 1.25rem; font-weight: 600; margin: 0 0 1rem; }
        .vk-empty { padding: 2rem; text-align: center; color: #666; background: #f7f7f7; border-radius: 8px; }
        .vk-controls { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 1rem; }
        .vk-periods { display: flex; gap: 4px; flex-wrap: wrap; }
        .vk-views { margin-left: auto; display: flex; gap: 4px; }
        .vk-btn {
            padding: 6px 14px; font-size: 13px; border: 1px solid #ddd; border-radius: 6px;
            background: #fff; color: #333; cursor: pointer; transition: all 0.15s;
            font-family: inherit; line-height: 1.4;
        }
        .vk-btn:hover { border-color: #999; }
        .vk-btn.active { background: #2c2c2a; color: #fff; border-color: #2c2c2a; }
        .vk-btn-view { padding: 6px 10px; min-width: 36px; text-align: center; }

        /* Monatsgruppe */
        .vk-month { font-size: 15px; font-weight: 600; color: #666; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #eee; }
        .vk-group { margin-bottom: 1.5rem; }

        /* Listenansicht */
        .vk-list-item {
            display: flex; gap: 12px; padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .vk-list-date { min-width: 90px; font-size: 13px; color: #888; padding-top: 2px; }
        .vk-list-date strong { display: block; color: #333; font-weight: 600; }
        .vk-list-body { flex: 1; }
        .vk-list-title { font-weight: 600; font-size: 15px; margin-bottom: 2px; color: #222; }
        .vk-list-meta { font-size: 13px; color: #888; }

        /* Kachelansicht */
        .vk-cards-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;
        }
        .vk-card {
            background: #fff; border: 1px solid #eee; border-radius: 10px;
            padding: 14px 16px; display: flex; gap: 12px; align-items: flex-start;
            transition: border-color 0.15s;
        }
        .vk-card:hover { border-color: #ccc; }
        .vk-card-date {
            min-width: 44px; height: 44px; border-radius: 8px; background: #e8f5e9;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            line-height: 1; flex-shrink: 0;
        }
        .vk-card-day { font-size: 16px; font-weight: 700; color: #2e7d32; }
        .vk-card-mon { font-size: 10px; color: #388e3c; text-transform: uppercase; letter-spacing: 0.5px; }
        .vk-card-body { flex: 1; min-width: 0; }
        .vk-card-title { font-weight: 600; font-size: 14px; margin-bottom: 3px; color: #222; }
        .vk-card-meta { font-size: 12px; color: #888; }
        .vk-card-desc { font-size: 12px; color: #aaa; margin-top: 4px; }

        /* Tabellenansicht */
        .vk-table-wrap { overflow-x: auto; }
        .vk-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .vk-table th {
            text-align: left; padding: 8px 10px; font-weight: 600;
            color: #888; font-size: 12px; border-bottom: 1px solid #ddd;
        }
        .vk-table td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; }
        .vk-table .vk-table-month td {
            padding: 12px 10px 6px; font-weight: 600; font-size: 14px;
            color: #666; border-bottom: 1px solid #eee;
        }
        .vk-table .vk-table-title { font-weight: 600; }
        .vk-table .vk-table-desc { color: #888; }

        /* Slider */
        .vk-slider-wrap {
            position: relative; overflow: hidden;
        }
        .vk-slider-track {
            display: flex; gap: 12px; overflow-x: auto; scroll-behavior: smooth;
            scrollbar-width: none; -ms-overflow-style: none; padding: 4px 0;
        }
        .vk-slider-track::-webkit-scrollbar { display: none; }
        .vk-slider-card {
            min-width: 240px; max-width: 280px; flex-shrink: 0;
            background: #fff; border: 1px solid #eee; border-radius: 10px;
            padding: 14px 16px; transition: border-color 0.15s;
        }
        .vk-slider-card:hover { border-color: #ccc; }
        .vk-slider-top { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
        .vk-slider-badge {
            background: #e8f5e9; border-radius: 8px; padding: 6px 10px;
            text-align: center; line-height: 1;
        }
        .vk-slider-badge-day { font-size: 18px; font-weight: 700; color: #2e7d32; }
        .vk-slider-badge-mon { font-size: 10px; color: #388e3c; text-transform: uppercase; }
        .vk-slider-title { font-weight: 600; font-size: 15px; color: #222; }
        .vk-slider-detail { font-size: 13px; color: #888; margin-top: 3px; }
        .vk-slider-desc {
            font-size: 13px; color: #aaa; margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .vk-slider-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 32px; height: 32px; border-radius: 50%; border: 1px solid #ddd;
            background: #fff; cursor: pointer; font-size: 16px; display: none;
            align-items: center; justify-content: center; color: #333;
            transition: all 0.15s; z-index: 2; opacity: 0;
        }
        .vk-slider-wrap:hover .vk-slider-nav { display: flex; opacity: 1; }
        .vk-slider-nav:hover { background: #f5f5f5; border-color: #999; }
        .vk-slider-prev { left: 0; }
        .vk-slider-next { right: 0; }
        @media (hover: none) {
            .vk-slider-nav { display: none !important; }
        }

        /* No results */
        .vk-no-events { padding: 2rem; text-align: center; color: #999; }

        /* Responsive */
        @media (max-width: 480px) {
            .vk-controls { flex-direction: column; align-items: flex-start; }
            .vk-views { margin-left: 0; }
            .vk-cards-grid { grid-template-columns: 1fr; }
            .vk-list-item { flex-direction: column; gap: 4px; }
            .vk-list-date { display: flex; gap: 8px; min-width: auto; }
        }
        </style>
        <?php
    }

    /**
     * JavaScript ausgeben
     */
    private static function render_script() {
        ?>
        <script>
        (function() {
            var MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
            var MONTHS_SHORT = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
            var WDAYS = ['So','Mo','Di','Mi','Do','Fr','Sa'];
            var PERIODS = [
                {l:'Woche',k:'week'},{l:'Monat',k:'month'},
                {l:'Quartal',k:'quarter'},{l:'Jahr',k:'year'},{l:'Alle',k:'all'}
            ];
            var VIEW_ICONS = {list:'☰', cards:'▦', table:'▤', slider:'►'};
            var VIEW_LABELS = {list:'Liste', cards:'Kacheln', table:'Tabelle', slider:'Slider'};

            function parseTS(e) {
                return new Date(e.datum + 'T' + (e.zeit || '00:00') + ':00');
            }

            function fmtDate(d) {
                return WDAYS[d.getDay()] + ', ' + d.getDate() + '. ' + MONTHS_SHORT[d.getMonth()] + '.';
            }

            function filterByPeriod(events, period, showPast) {
                var now = new Date();
                var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var filtered = events;

                if (!showPast) {
                    filtered = filtered.filter(function(e) {
                        return parseTS(e) >= todayStart;
                    });
                }

                if (period === 'all') return filtered;

                var end = new Date(now);
                if (period === 'week') end.setDate(end.getDate() + 7);
                else if (period === 'month') end.setMonth(end.getMonth() + 1);
                else if (period === 'quarter') end.setMonth(end.getMonth() + 3);
                else if (period === 'year') end.setFullYear(end.getFullYear() + 1);

                return filtered.filter(function(e) {
                    var d = parseTS(e);
                    return d <= end;
                });
            }

            function grouped(events) {
                var g = {};
                events.forEach(function(e) {
                    var d = parseTS(e);
                    var k = MONTHS[d.getMonth()] + ' ' + d.getFullYear();
                    if (!g[k]) g[k] = [];
                    g[k].push(e);
                });
                return g;
            }

            function renderList(events) {
                var g = grouped(events);
                var html = '';
                for (var month in g) {
                    html += '<div class="vk-group"><div class="vk-month">' + month + '</div>';
                    g[month].forEach(function(e) {
                        var d = parseTS(e);
                        html += '<div class="vk-list-item">' +
                            '<div class="vk-list-date">' + fmtDate(d) + '<strong>' + (e.zeit || '') + ' Uhr</strong></div>' +
                            '<div class="vk-list-body">' +
                                '<div class="vk-list-title">' + e.titel + '</div>' +
                                '<div class="vk-list-meta">' + e.ort + (e.desc ? ' · ' + e.desc : '') + '</div>' +
                            '</div></div>';
                    });
                    html += '</div>';
                }
                return html;
            }

            function renderCards(events) {
                var g = grouped(events);
                var html = '';
                for (var month in g) {
                    html += '<div class="vk-group"><div class="vk-month">' + month + '</div>';
                    html += '<div class="vk-cards-grid">';
                    g[month].forEach(function(e) {
                        var d = parseTS(e);
                        html += '<div class="vk-card">' +
                            '<div class="vk-card-date"><span class="vk-card-day">' + d.getDate() + '</span>' +
                            '<span class="vk-card-mon">' + MONTHS_SHORT[d.getMonth()] + '</span></div>' +
                            '<div class="vk-card-body">' +
                                '<div class="vk-card-title">' + e.titel + '</div>' +
                                '<div class="vk-card-meta">' + (e.zeit || '') + ' Uhr · ' + e.ort + '</div>' +
                                (e.desc ? '<div class="vk-card-desc">' + e.desc + '</div>' : '') +
                            '</div></div>';
                    });
                    html += '</div></div>';
                }
                return html;
            }

            function renderTable(events) {
                var html = '<div class="vk-table-wrap"><table class="vk-table">' +
                    '<thead><tr><th>Datum</th><th>Uhrzeit</th><th>Veranstaltung</th><th>Ort</th><th>Details</th></tr></thead><tbody>';
                var lastMonth = '';
                events.forEach(function(e) {
                    var d = parseTS(e);
                    var m = MONTHS[d.getMonth()] + ' ' + d.getFullYear();
                    if (m !== lastMonth) {
                        html += '<tr class="vk-table-month"><td colspan="5">' + m + '</td></tr>';
                        lastMonth = m;
                    }
                    html += '<tr><td style="white-space:nowrap;">' + fmtDate(d) + '</td>' +
                        '<td style="white-space:nowrap;">' + (e.zeit || '') + '</td>' +
                        '<td class="vk-table-title">' + e.titel + '</td>' +
                        '<td>' + e.ort + '</td>' +
                        '<td class="vk-table-desc">' + e.desc + '</td></tr>';
                });
                html += '</tbody></table></div>';
                return html;
            }

            function renderSlider(events) {
                var sliderId = 'vk-slider-' + Math.random().toString(36).substr(2, 6);
                var html = '<div class="vk-slider-wrap">' +
                    '<div class="vk-slider-track" id="' + sliderId + '">';
                events.forEach(function(e) {
                    var d = parseTS(e);
                    html += '<div class="vk-slider-card">' +
                        '<div class="vk-slider-top">' +
                            '<div class="vk-slider-badge">' +
                                '<div class="vk-slider-badge-day">' + d.getDate() + '</div>' +
                                '<div class="vk-slider-badge-mon">' + MONTHS_SHORT[d.getMonth()] + '</div>' +
                            '</div>' +
                            '<div class="vk-slider-title">' + e.titel + '</div>' +
                        '</div>' +
                        '<div class="vk-slider-detail">' + (e.zeit || '') + ' Uhr · ' + e.ort + '</div>' +
                        (e.desc ? '<div class="vk-slider-desc">' + e.desc + '</div>' : '') +
                    '</div>';
                });
                html += '</div>';
                html += '<button class="vk-slider-nav vk-slider-prev" onclick="document.getElementById(\'' + sliderId + '\').scrollBy({left:-260,behavior:\'smooth\'})" aria-label="Zurück">‹</button>';
                html += '<button class="vk-slider-nav vk-slider-next" onclick="document.getElementById(\'' + sliderId + '\').scrollBy({left:260,behavior:\'smooth\'})" aria-label="Weiter">›</button>';
                html += '</div>';
                return html;
            }

            function render(instanceId) {
                var inst = window.VKInstances[instanceId];
                if (!inst) return;

                var c = inst.config;
                var wrap = document.getElementById(c.id);
                if (!wrap) return;

                var currentPeriod = inst.currentPeriod || c.period;
                var currentView = inst.currentView || c.fixedView || 'list';

                var events = filterByPeriod(inst.events, currentPeriod, c.showPast);
                if (c.count > 0) events = events.slice(0, c.count);

                var content = wrap.querySelector('.vk-content');

                if (events.length === 0) {
                    content.innerHTML = '<div class="vk-no-events">Keine Termine im gewählten Zeitraum.</div>';
                    return;
                }

                if (currentView === 'list') content.innerHTML = renderList(events);
                else if (currentView === 'cards') content.innerHTML = renderCards(events);
                else if (currentView === 'table') content.innerHTML = renderTable(events);
                else if (currentView === 'slider') content.innerHTML = renderSlider(events);

                // Controls aktualisieren
                if (c.hasFilter) {
                    var pb = wrap.querySelector('.vk-periods');
                    if (pb) {
                        pb.innerHTML = '';
                        PERIODS.forEach(function(p) {
                            var btn = document.createElement('button');
                            btn.className = 'vk-btn' + (currentPeriod === p.k ? ' active' : '');
                            btn.textContent = p.l;
                            btn.onclick = function() {
                                inst.currentPeriod = p.k;
                                render(instanceId);
                            };
                            pb.appendChild(btn);
                        });
                    }

                    var vb = wrap.querySelector('.vk-views');
                    if (vb && !c.fixedView) {
                        vb.innerHTML = '';
                        var views = ['list','cards','table','slider'];
                        views.forEach(function(v) {
                            var btn = document.createElement('button');
                            btn.className = 'vk-btn vk-btn-view' + (currentView === v ? ' active' : '');
                            btn.innerHTML = VIEW_ICONS[v];
                            btn.title = VIEW_LABELS[v];
                            btn.onclick = function() {
                                inst.currentView = v;
                                render(instanceId);
                            };
                            vb.appendChild(btn);
                        });
                    }
                }
            }

            window.VKInit = function(instanceId) {
                render(instanceId);
            };

            // Bereits geladene Instanzen initialisieren
            if (window.VKInstances) {
                for (var id in window.VKInstances) {
                    render(id);
                }
            }
        })();
        </script>
        <?php
    }
}

Vereinskalender::init();
