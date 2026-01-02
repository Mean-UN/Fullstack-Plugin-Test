(function ($) {
    const apiBase = (window.WRSE_VARS && WRSE_VARS.endpoint) ? WRSE_VARS.endpoint : '';
    const refresh = (window.WRSE_VARS && WRSE_VARS.refresh) ? WRSE_VARS.refresh : { scoreboard: 30, live: 6, upcoming: 120 };

    const debounce = (fn, wait = 250) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(null, args), wait);
        };
    };

    function fetchJSON(path, params = {}) {
        const url = new URL(apiBase + path);
        Object.keys(params).forEach((k) => {
            if (params[k] !== undefined && params[k] !== null && params[k] !== '') {
                url.searchParams.append(k, params[k]);
            }
        });
        return fetch(url.toString(), {
            headers: { 'X-WP-Nonce': (window.WRSE_VARS && WRSE_VARS.nonce) || '' },
        }).then((r) => r.json()).catch(() => ({ data: [] }));
    }

    function calcWinProb(o) {
        if (!o) return { home: 0, draw: 0, away: 0 };
        const h = o.home ? 1 / o.home : 0;
        const d = o.draw ? 1 / o.draw : 0;
        const a = o.away ? 1 / o.away : 0;
        const sum = h + d + a;
        if (!sum) return { home: 0, draw: 0, away: 0 };
        return {
            home: Math.round((h / sum) * 100),
            draw: Math.round((d / sum) * 100),
            away: Math.round((a / sum) * 100),
        };
    }

    function renderScoreboard(el, matches) {
        const container = $(el).find('.wrse-list').removeClass('skeleton').empty();
        const chunkSize = 50;
        let index = 0;

        const renderChunk = () => {
            const slice = matches.slice(index, index + chunkSize);
            slice.forEach((m) => {
                const odds = typeof m.odds_1x2 === 'string' ? JSON.parse(m.odds_1x2) : (m.odds_1x2 || {});
                const prob = calcWinProb(odds);
                const row = $('<div class="wrse-row"></div>');
                row.addClass((m.status || '').toLowerCase());

                const score = `${m.score_home ?? 0} - ${m.score_away ?? 0}`;
                row.append(`<div class="wrse-league">${m.league || ''}</div>`);
                row.append(`<div class="wrse-time">${m.match_time_local || m.match_time_utc || ''}</div>`);
                row.append(`<div class="wrse-team">${m.home_team || ''}</div>`);
                row.append(`<div class="wrse-score">${score}</div>`);
                row.append(`<div class="wrse-team">${m.away_team || ''}</div>`);
                row.append(`<div class="wrse-odds"><span class="wrse-pill">H ${odds.home ?? '-'}</span><span class="wrse-pill">D ${odds.draw ?? '-'}</span><span class="wrse-pill">A ${odds.away ?? '-'}</span></div>`);
                row.append(`<div class="wrse-prob">H ${prob.home}% / D ${prob.draw}% / A ${prob.away}%</div>`);
                row.append(`<div class="wrse-status">${(m.status || '').toUpperCase()}</div>`);
                container.append(row);
            });
            index += chunkSize;
        };

        renderChunk();
        container.off('scroll').on('scroll', function () {
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 10) {
                renderChunk();
            }
        });
    }

    function renderUpcoming(el, matches, limit) {
        const list = $('<div></div>');
        matches.slice(0, limit).forEach((m) => {
            const odds = typeof m.odds_1x2 === 'string' ? JSON.parse(m.odds_1x2) : (m.odds_1x2 || {});
            const item = $('<div class="wrse-upcoming-item"></div>');
            item.append(`<span>${m.match_time_local || m.match_time_utc || ''}</span>`);
            item.append(`<span>${m.home_team || ''} vs ${m.away_team || ''}</span>`);
            item.append(`<span>${m.league || ''}</span>`);
            item.append(`<span>${odds.home ?? '-'} / ${odds.away ?? '-'}</span>`);
            list.append(item);
        });
        $(el).find('.wrse-upcoming-list').removeClass('skeleton').html(list.children());
    }

    function renderMatchCenter(el, data, oddsHistory) {
        const header = $(el).find('.wrse-mc-header');
        header.removeClass('skeleton');
        header.html(`<div>${data.home_team} vs ${data.away_team}</div><div>${data.match_time_local || data.match_time_utc}</div><div>${(data.status || '').toUpperCase()} ${data.score_home}-${data.score_away}</div>`);

        const summary = $(el).find('.wrse-mc-summary').removeClass('skeleton');
        summary.html(`<div>League: ${data.league || ''}</div><div>Minute: ${data.minute || 0}'</div>`);

        const oddsBox = $(el).find('.wrse-mc-odds').removeClass('skeleton');
        const odds = typeof data.odds_1x2 === 'string' ? JSON.parse(data.odds_1x2) : (data.odds_1x2 || {});
        let historyHtml = '';
        (oddsHistory || []).forEach((o) => {
            historyHtml += `<div class="wrse-odds-row"><span>${o.market_type}</span><span>${o.odds_after}</span><span>${o.created_at}</span></div>`;
        });
        oddsBox.html(`<h4>Odds</h4><div class="wrse-odds-row"><span>H ${odds.home ?? '-'}</span><span>D ${odds.draw ?? '-'}</span><span>A ${odds.away ?? '-'}</span></div>${historyHtml ? '<h5>History</h5>' + historyHtml : ''}`);

        $(el).find('.wrse-mc-lineups').removeClass('skeleton').html('<pre>' + JSON.stringify(data.lineups || {}, null, 2) + '</pre>');
        $(el).find('.wrse-mc-stats').removeClass('skeleton').html('<pre>' + JSON.stringify(data.stats || {}, null, 2) + '</pre>');
        $(el).find('.wrse-mc-h2h').removeClass('skeleton').html('<pre>' + JSON.stringify(data.h2h || {}, null, 2) + '</pre>');
    }

    function initScoreboard() {
        $('.wrse-scoreboard').each(function () {
            const el = this;
            const league = $(el).data('league') || 'all';
            const date = $(el).data('date') || 'today';
            const sortSelect = $(el).find('.wrse-sort');
            const searchInput = $(el).find('.wrse-search');
            const liveToggle = $(el).find('.wrse-live-first');

            const load = () => {
                fetchJSON('/matches', { league, date, sort: sortSelect.val() }).then((res) => {
                    let matches = res.data || [];
                    const term = (searchInput.val() || '').toLowerCase();
                    if (term) {
                        matches = matches.filter((m) => ((m.home_team || '').toLowerCase().includes(term) || (m.away_team || '').toLowerCase().includes(term)));
                    }
                    if (liveToggle.is(':checked')) {
                        matches = matches.sort((a, b) => {
                            const liveA = (a.status || '').toLowerCase() === 'live';
                            const liveB = (b.status || '').toLowerCase() === 'live';
                            if (liveA === liveB) return 0;
                            return liveA ? -1 : 1;
                        });
                    }
                    renderScoreboard(el, matches);
                });
            };

            sortSelect.on('change', load);
            liveToggle.on('change', load);
            searchInput.on('input', debounce(load, 300));
            load();
            setInterval(load, (refresh.scoreboard || 30) * 1000);
        });
    }

    function initUpcoming() {
        $('.wrse-upcoming').each(function () {
            const el = this;
            const limit = parseInt($(el).data('limit'), 10) || 5;
            const load = () => {
                fetchJSON('/matches', { live: false }).then((res) => {
                    const matches = (res.data || []).filter((m) => {
                        const ts = Date.parse(m.match_time_utc || '');
                        return ts && ts > Date.now();
                    });
                    renderUpcoming(el, matches, limit);
                });
            };
            load();
            setInterval(load, (refresh.upcoming || 120) * 1000);
        });
    }

    function initMatchCenter() {
        $('.wrse-match-center').each(function () {
            const el = this;
            const id = $(el).data('match-id');
            if (!id) return;
            const load = () => {
                Promise.all([
                    fetchJSON('/match/' + id),
                    fetchJSON('/odds/' + id),
                ]).then(([matchRes, oddsRes]) => {
                    renderMatchCenter(el, matchRes.data || {}, oddsRes.data || []);
                });
            };
            load();
            setInterval(load, (refresh.live || 6) * 1000);
        });
    }

    $(function () {
        initScoreboard();
        initUpcoming();
        initMatchCenter();
    });
})(jQuery);
