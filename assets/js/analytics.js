/**
 * Landing Page Manager Pro – Analytics screen (Landing Pages → Analytics): stat cards, tabs, charts,
 * engagement, heatmap, rage clicks, by template. Moved from Landing Page Manager in Pro 1.0.6.
 * Under By Page, each title opens that page's detail view (scroll depth, top links, heatmap grid).
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */
(function () {
    if (typeof window.lpmanagerAnalytics === 'undefined') return;

    const config = window.lpmanagerAnalytics;
    // Translated strings come from PHP (Analytics_Page::strings()); these are the English fallbacks.
    const strings = Object.assign({
        failedToLoad: 'Failed to load analytics: %s',
        unknownError: 'Unknown error',
        allTemplates: 'All templates',
        totalViews: 'Total views',
        conversions: 'Conversions',
        linkClicks: 'Link clicks',
        conversionRate: 'Conversion rate (CTR)',
        pageViews: 'Page views',
        enableOverview: 'Turn on Page views or Link clicks under Landing Pages → Tracking to see analytics.',
        noData: 'No data',
        edit: 'Edit',
        linkLabel: 'Link / label',
        clicks: 'Clicks',
        noLinkData: 'No link click data yet.',
        enableEngagement: 'Turn on Time on page or Scroll depth under Landing Pages → Tracking to see engagement data.',
        noEngagement: 'No engagement data yet.',
        page: 'Page',
        avgTime: 'Avg. time (s)',
        sessions: 'Sessions',
        enableHeatmap: 'Turn on Mouse-position heatmap under Landing Pages → Tracking to see data.',
        noHeatmap: 'No heatmap data yet. Visit landing pages with heatmap tracking turned on to see where visitors focus.',
        viewFullPage: 'View full page',
        close: 'Close',
        pagePreview: 'Page preview',
        clickToEnlarge: 'Click to view larger',
        heatmapGrid: 'Heatmap grid with counts',
        enableRage: 'Turn on Rage clicks under Landing Pages → Tracking to see data.',
        noRage: 'No rage clicks recorded yet.',
        rageCount: 'Rage click count',
    }, config.i18n || {});
    // A translated string, escaped for HTML.
    function t(key) { return escapeHtml(strings[key]); }
    const apiUrl = config.apiUrl;
    const nonce = config.nonce;
    let currentClientId = parseInt(config.clientId, 10) || 0;
    let currentTemplateFilter = (config.templateFilter || '').replace(/^\s+|\s+$/g, '');
    let analyticsData = null;

    const root = document.getElementById('lp-analytics-root');
    const loadingEl = document.getElementById('lp-analytics-loading');
    const errorEl = document.getElementById('lp-analytics-error');
    const clientFilter = document.getElementById('lp-client-filter');
    const templateFilter = document.getElementById('lp-template-filter');
    const HEATMAP_DESKTOP_WIDTH = 1366;
    const HEATMAP_CARD_HEIGHT = 2000;
    const HEATMAP_MODAL_HEIGHT = 2500;

    function showLoading(show) {
        if (loadingEl) loadingEl.style.display = show ? 'block' : 'none';
        if (errorEl) errorEl.hidden = true;
    }

    function showError(msg) {
        if (errorEl) {
            errorEl.textContent = msg;
            errorEl.hidden = false;
        }
        if (loadingEl) loadingEl.style.display = 'none';
    }

    // The page's detail view on this screen, keeping the current filters.
    function detailUrl(pageId) {
        const adminUrl = config.adminUrl ? config.adminUrl : 'admin.php';
        let q = ['page=' + encodeURIComponent(config.screenSlug || 'lp_analytics'), 'page_id=' + encodeURIComponent(pageId)];
        if (currentClientId) q.push('client_id=' + currentClientId);
        if (currentTemplateFilter) q.push('template=' + encodeURIComponent(currentTemplateFilter));
        return adminUrl + '?' + q.join('&');
    }

    function buildQueryString() {
        let q = [];
        if (currentClientId) q.push('client_id=' + currentClientId);
        if (currentTemplateFilter) q.push('template=' + encodeURIComponent(currentTemplateFilter));
        return q.length ? '?' + q.join('&') : '';
    }

    function fetchData() {
        showLoading(true);
        const url = apiUrl + buildQueryString();
        fetch(url, {
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
        })
            .then(function (res) {
                if (!res.ok) throw new Error(res.statusText);
                return res.json();
            })
            .then(function (data) {
                analyticsData = data;
                showLoading(false);
                populateTemplateFilter();
                renderOverview();
                renderClientsTable();
                renderPagesTable();
                renderTemplateTable();
                renderLinkPerformance();
                renderEngagement();
                renderHeatmap();
                renderRageClicks();
                initCharts();
            })
            .catch(function (err) {
                showError(strings.failedToLoad.replace('%s', err.message || strings.unknownError));
            });
    }

    function populateTemplateFilter() {
        if (!templateFilter) return;
        const list = (analyticsData && analyticsData.templates) ? analyticsData.templates : [];
        const current = (analyticsData && analyticsData.template_filter) !== undefined ? analyticsData.template_filter : currentTemplateFilter;
        let opts = '<option value="">' + t('allTemplates') + '</option>';
        list.forEach(function (t) {
            opts += '<option value="' + escapeHtml(t.slug) + '"' + (t.slug === current ? ' selected' : '') + '>' + escapeHtml(t.name) + '</option>';
        });
        templateFilter.innerHTML = opts;
    }

    function getSummary() {
        return (analyticsData && analyticsData.summary) ? analyticsData.summary : {};
    }

    function getTrackingEnabled() {
        return (analyticsData && analyticsData.tracking_enabled) ? analyticsData.tracking_enabled : {};
    }

    function renderOverview() {
        const summary = getSummary();
        const te = getTrackingEnabled();
        const container = document.getElementById('lp-summary-cards');
        if (!container) return;
        let html = '';
        if (te.page_views) {
            html += '<div class="lp-stat-card lp-stat-card--views"><h3 class="lp-stat-card__label">' + t('totalViews') + '</h3><p class="lp-stat-card__value">' + (summary.total_views != null ? Number(summary.total_views).toLocaleString() : '0') + '</p></div>';
        }
        if (te.conversions) {
            html += '<div class="lp-stat-card lp-stat-card--conversions"><h3 class="lp-stat-card__label">' + t('conversions') + '</h3><p class="lp-stat-card__value">' + (summary.total_conversions != null ? Number(summary.total_conversions).toLocaleString() : '0') + '</p></div>';
        }
        if (te.link_clicks) {
            html += '<div class="lp-stat-card lp-stat-card--clicks"><h3 class="lp-stat-card__label">' + t('linkClicks') + '</h3><p class="lp-stat-card__value">' + (summary.total_clicks != null ? Number(summary.total_clicks).toLocaleString() : '0') + '</p></div>';
        }
        if (te.conversions) {
            html += '<div class="lp-stat-card lp-stat-card--ctr"><h3 class="lp-stat-card__label">' + t('conversionRate') + '</h3><p class="lp-stat-card__value">' + (summary.ctr_conversion != null ? Number(summary.ctr_conversion) + '%' : '0%') + '</p></div>';
        }
        if (!html) {
            html = '<p class="lp-empty">' + t('enableOverview') + '</p>';
        }
        container.innerHTML = html;
    }

    var chartInstances = [];

    function initCharts() {
        if (typeof Chart === 'undefined') return;
        chartInstances.forEach(function (c) { if (c) c.destroy(); });
        chartInstances = [];
        const te = getTrackingEnabled();
        const dailyVisits = (analyticsData && analyticsData.daily_visits) ? analyticsData.daily_visits : {};
        const dailyConv = (analyticsData && analyticsData.daily_conversions) ? analyticsData.daily_conversions : {};
        const dates = Object.keys(dailyVisits).concat(Object.keys(dailyConv)).filter(function (v, i, a) { return a.indexOf(v) === i; }).sort();
        const visitValues = dates.map(function (d) { return dailyVisits[d] || 0; });
        const convValues = dates.map(function (d) { return dailyConv[d] || 0; });

        const defaultOpts = {
            maintainAspectRatio: false,
            responsive: true,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true }, x: { grid: { display: false } } },
        };
        const gradient = function (ctx, color, opacity) {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
            g.addColorStop(0, color.replace(')', ',' + opacity + ')').replace('rgb', 'rgba'));
            g.addColorStop(1, color.replace(')', ',0)').replace('rgb', 'rgba'));
            return g;
        };
        const c1 = document.getElementById('lp-chart-daily-visits');
        if (c1 && te.page_views) {
            const ctx = c1.getContext('2d');
            const g = ctx.createLinearGradient(0, 0, 0, 300);
            g.addColorStop(0, 'rgba(34, 113, 177, 0.35)');
            g.addColorStop(1, 'rgba(34, 113, 177, 0)');
            chartInstances.push(new Chart(c1, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{ label: strings.pageViews, data: visitValues, borderColor: '#2271b1', backgroundColor: g, fill: true, tension: 0.3, pointRadius: 3 }],
                },
                options: defaultOpts,
            }));
        } else if (c1) { c1.parentNode.style.display = 'none'; }
        const c2 = document.getElementById('lp-chart-daily-conversions');
        if (c2 && te.conversions) {
            const ctx = c2.getContext('2d');
            const g = ctx.createLinearGradient(0, 0, 0, 300);
            g.addColorStop(0, 'rgba(0, 163, 42, 0.35)');
            g.addColorStop(1, 'rgba(0, 163, 42, 0)');
            chartInstances.push(new Chart(c2, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{ label: strings.conversions, data: convValues, borderColor: '#00a32a', backgroundColor: g, fill: true, tension: 0.3, pointRadius: 3 }],
                },
                options: defaultOpts,
            }));
        } else if (c2) { c2.parentNode.style.display = 'none'; }
    }

    function renderClientsTable() {
        const tbody = document.getElementById('lp-tbody-clients');
        if (!tbody) return;
        const rows = (analyticsData && analyticsData.by_client) ? analyticsData.by_client : [];
        const adminUrl = (config.adminUrl) ? config.adminUrl : (window.location.pathname.replace(/\/[^/]*$/, '') + '/admin.php');
        tbody.innerHTML = rows.map(function (r) {
            const url = adminUrl + '?page=' + encodeURIComponent(config.screenSlug || 'lp_analytics') + '&client_id=' + r.client_id;
            return '<tr><td><a href="' + escapeHtml(url) + '">' + escapeHtml(r.name) + '</a></td><td>' + Number(r.pages_count) + '</td><td>' + Number(r.views).toLocaleString() + '</td><td>' + Number(r.conversions).toLocaleString() + '</td><td>' + Number(r.clicks).toLocaleString() + '</td><td>' + Number(r.ctr_conversion) + '%</td></tr>';
        }).join('') || '<tr><td colspan="6">' + t('noData') + '</td></tr>';
    }

    function renderPagesTable() {
        const tbody = document.getElementById('lp-tbody-pages');
        if (!tbody) return;
        const rows = (analyticsData && analyticsData.by_page) ? analyticsData.by_page : [];
        const editBase = config.editBase ? config.editBase : '';
        tbody.innerHTML = rows.map(function (r) {
            const editLink = editBase ? editBase.replace('REPLACE_ID', r.id) : ('post.php?post=' + r.id + '&action=edit');
            const detailLink = detailUrl(r.id);
            const tpl = r.template_slug ? escapeHtml(r.template_slug) : '—';
            return '<tr><td><a href="' + escapeHtml(detailLink) + '">' + escapeHtml(r.title) + '</a> <span class="lp-row-actions">· <a href="' + escapeHtml(editLink) + '">' + t('edit') + '</a></span></td><td>' + escapeHtml(r.client_name || '') + '</td><td>' + tpl + '</td><td>' + Number(r.views).toLocaleString() + '</td><td>' + Number(r.conversions).toLocaleString() + '</td><td>' + Number(r.clicks).toLocaleString() + '</td><td>' + Number(r.ctr_conversion) + '%</td></tr>';
        }).join('') || '<tr><td colspan="7">' + t('noData') + '</td></tr>';
    }

    function renderTemplateTable() {
        const tbody = document.getElementById('lp-tbody-template');
        if (!tbody) return;
        const rows = (analyticsData && analyticsData.by_template) ? analyticsData.by_template : [];
        tbody.innerHTML = rows.map(function (r) {
            return '<tr><td>' + escapeHtml(r.template_name) + '</td><td>' + Number(r.pages_count) + '</td><td>' + Number(r.views).toLocaleString() + '</td><td>' + Number(r.conversions).toLocaleString() + '</td><td>' + Number(r.clicks).toLocaleString() + '</td><td>' + Number(r.ctr_conversion) + '%</td></tr>';
        }).join('') || '<tr><td colspan="6">' + t('noData') + '</td></tr>';
    }

    function renderLinkPerformance() {
        const container = document.getElementById('lp-link-performance');
        if (!container) return;
        const data = (analyticsData && analyticsData.link_clicks) ? analyticsData.link_clicks : [];
        let html = '';
        data.forEach(function (block) {
            if (!block.links || block.links.length === 0) return;
            html += '<div class="lp-link-block"><h4 class="lp-link-block__title">' + escapeHtml(block.title) + '</h4><table class="lp-table lp-table--compact"><thead><tr><th>' + t('linkLabel') + '</th><th>' + t('clicks') + '</th></tr></thead><tbody>';
            block.links.forEach(function (link) {
                var label = link.label || link.href || '—';
                if (label.length > 80) label = label.slice(0, 77) + '…';
                html += '<tr><td>' + escapeHtml(label) + '</td><td>' + Number(link.count).toLocaleString() + '</td></tr>';
            });
            html += '</tbody></table></div>';
        });
        container.innerHTML = html || '<p class="lp-empty">' + t('noLinkData') + '</p>';
    }

    function renderEngagement() {
        const container = document.getElementById('lp-engagement-content');
        if (!container) return;
        const te = getTrackingEnabled();
        if (!te.time_on_page && !te.scroll_depth) {
            container.innerHTML = '<p class="lp-empty">' + t('enableEngagement') + '</p>';
            return;
        }
        const data = (analyticsData && analyticsData.engagement) ? analyticsData.engagement : [];
        const withData = data.filter(function (e) { return e.time_count > 0 || e.scroll_25 > 0 || e.scroll_50 > 0 || e.scroll_75 > 0 || e.scroll_100 > 0; });
        if (withData.length === 0) {
            container.innerHTML = '<p class="lp-empty">' + t('noEngagement') + '</p>';
            return;
        }
        let html = '<div class="lp-table-wrapper"><table class="lp-table"><thead><tr><th>' + t('page') + '</th><th>' + t('avgTime') + '</th><th>' + t('sessions') + '</th><th>25%</th><th>50%</th><th>75%</th><th>100%</th></tr></thead><tbody>';
        withData.forEach(function (e) {
            html += '<tr><td>' + escapeHtml(e.title) + '</td><td>' + Number(e.time_avg || 0) + '</td><td>' + Number(e.time_count || 0) + '</td><td>' + Number(e.scroll_25 || 0) + '</td><td>' + Number(e.scroll_50 || 0) + '</td><td>' + Number(e.scroll_75 || 0) + '</td><td>' + Number(e.scroll_100 || 0) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    /** Intensity 0–1: fully transparent (no color) → yellow → orange → red. No blue. */
    function heatmapColor(intensity) {
        if (intensity <= 0 || intensity < 0.02) return 'transparent';
        var r = 255;
        var g = intensity >= 1 ? 0 : Math.round(255 * (1 - intensity));
        var b = 0;
        var a = 0.4 + 0.55 * Math.min(1, intensity);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    }

    function buildHeatmapOverlayCells(grid) {
        var max = 1;
        for (var i = 0; i < 100; i++) { if (grid[i] > max) max = grid[i]; }
        var html = '';
        for (var row = 0; row < 10; row++) {
            for (var col = 0; col < 10; col++) {
                var idx = row * 10 + col;
                var v = grid[idx] || 0;
                var intensity = max > 0 ? v / max : 0;
                html += '<div class="lp-heatmap-overlay-cell" style="background:' + heatmapColor(intensity) + '" title="' + v + '"></div>';
            }
        }
        return html;
    }

    function stripAdminBarFromIframe(iframe) {
        if (!iframe) return;
        iframe.addEventListener('load', function () {
            try {
                var doc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
                if (!doc) return;
                var adminBar = doc.getElementById('wpadminbar');
                if (adminBar && adminBar.parentNode) {
                    adminBar.parentNode.removeChild(adminBar);
                }
                var styleId = 'lp-heatmap-hide-adminbar-style';
                if (!doc.getElementById(styleId)) {
                    var style = doc.createElement('style');
                    style.id = styleId;
                    style.textContent = '#wpadminbar{display:none!important;}html{margin-top:0!important;padding-top:0!important;}body.admin-bar{margin-top:0!important;padding-top:0!important;}';
                    (doc.head || doc.documentElement).appendChild(style);
                }
            } catch (e) {
                // Ignore cross-origin failures.
            }
        });
    }

    function applyHeatmapScale(previewEl, baseHeight) {
        if (!previewEl) return;
        var scroll = previewEl.querySelector('.lp-heatmap-preview__scroll, .lp-heatmap-modal__scroll');
        var canvas = previewEl.querySelector('.lp-heatmap-preview__canvas, .lp-heatmap-modal__canvas');
        if (!scroll || !canvas) return;

        var width = previewEl.clientWidth || HEATMAP_DESKTOP_WIDTH;
        var scale = width / HEATMAP_DESKTOP_WIDTH;
        if (!isFinite(scale) || scale <= 0) scale = 1;

        canvas.style.setProperty('--lp-heatmap-scale', String(scale));
        canvas.style.width = HEATMAP_DESKTOP_WIDTH + 'px';
        canvas.style.height = baseHeight + 'px';
        scroll.style.height = Math.max(1, Math.round(baseHeight * scale)) + 'px';
    }

    var heatmapModalTrigger = null;

    function openHeatmapModal(item, trigger) {
        var modal = document.getElementById('lp-heatmap-modal');
        if (!modal) return;
        heatmapModalTrigger = trigger || null;
        var titleEl = modal.querySelector('.lp-heatmap-modal__title');
        var viewLink = modal.querySelector('.lp-heatmap-modal__view-page');
        var iframe = modal.querySelector('.lp-heatmap-modal__iframe');
        var overlay = modal.querySelector('.lp-heatmap-modal__overlay');
        if (titleEl) titleEl.textContent = item.title;
        if (viewLink) { viewLink.href = item.url || '#'; viewLink.style.display = item.url ? '' : 'none'; }
        if (iframe) {
            iframe.src = item.url || '';
            stripAdminBarFromIframe(iframe);
        }
        if (overlay) overlay.innerHTML = buildHeatmapOverlayCells(item.grid || []);
        var preview = modal.querySelector('.lp-heatmap-modal__preview');
        applyHeatmapScale(preview, HEATMAP_MODAL_HEIGHT);
        modal.classList.add('lp-heatmap-modal--open');
        document.body.classList.add('lp-modal-open');
        var closeBtn = modal.querySelector('.lp-heatmap-modal__close');
        if (closeBtn) closeBtn.focus();
    }

    function closeHeatmapModal() {
        var modal = document.getElementById('lp-heatmap-modal');
        if (modal) {
            modal.classList.remove('lp-heatmap-modal--open');
            var iframe = modal.querySelector('.lp-heatmap-modal__iframe');
            if (iframe) iframe.src = 'about:blank';
        }
        document.body.classList.remove('lp-modal-open');
        if (heatmapModalTrigger) { heatmapModalTrigger.focus(); heatmapModalTrigger = null; }
    }

    function renderHeatmap() {
        const container = document.getElementById('lp-heatmap-content');
        if (!container) return;
        const te = getTrackingEnabled();
        if (!te.heatmap) {
            container.innerHTML = '<p class="lp-empty">' + t('enableHeatmap') + '</p>';
            return;
        }
        const data = (analyticsData && analyticsData.heatmap) ? analyticsData.heatmap : [];
        const withData = data.filter(function (h) { return h.grid && h.grid.some(function (v) { return v > 0; }); });
        if (withData.length === 0) {
            container.innerHTML = '<p class="lp-empty">' + t('noHeatmap') + '</p>';
            return;
        }
        var modal = document.getElementById('lp-heatmap-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'lp-heatmap-modal';
            modal.className = 'lp-heatmap-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'lp-heatmap-modal-title');
            modal.innerHTML = '<div class="lp-heatmap-modal__backdrop"></div><div class="lp-heatmap-modal__box" tabindex="-1">' +
                '<div class="lp-heatmap-modal__header"><h3 class="lp-heatmap-modal__title" id="lp-heatmap-modal-title"></h3><a class="lp-heatmap-modal__view-page" href="#" target="_blank" rel="noopener">' + t('viewFullPage') + '</a><button type="button" class="lp-heatmap-modal__close" aria-label="' + t('close') + '">&times;</button></div>' +
                '<div class="lp-heatmap-modal__preview">' +
                '<div class="lp-heatmap-modal__scroll"><div class="lp-heatmap-modal__canvas"><iframe class="lp-heatmap-modal__iframe" tabindex="-1" title="' + t('pagePreview') + '"></iframe><div class="lp-heatmap-overlay lp-heatmap-modal__overlay"></div></div></div>' +
                '</div></div>';
            modal.querySelector('.lp-heatmap-modal__backdrop').addEventListener('click', closeHeatmapModal);
            modal.querySelector('.lp-heatmap-modal__close').addEventListener('click', closeHeatmapModal);
            modal.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { e.preventDefault(); closeHeatmapModal(); return; }
                if (e.key !== 'Tab') return;
                var focusable = [modal.querySelector('.lp-heatmap-modal__view-page'), modal.querySelector('.lp-heatmap-modal__close')]
                    .filter(function (el) { return el && el.style.display !== 'none'; });
                var first = focusable[0], last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            });
            document.body.appendChild(modal);
        }
        var cardsHtml = '<div class="lp-heatmap-cards">';
        withData.forEach(function (h) {
            var url = (h.url || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            cardsHtml += '<div class="lp-heatmap-item"><div class="lp-heatmap-card" role="button" tabindex="0" data-post-id="' + (parseInt(h.post_id, 10) || '') + '">';
            cardsHtml += '<h4 class="lp-heatmap-card__title">' + escapeHtml(h.title) + '</h4>';
            cardsHtml += '<div class="lp-heatmap-preview">';
            cardsHtml += '<div class="lp-heatmap-preview__scroll" inert><div class="lp-heatmap-preview__canvas"><iframe class="lp-heatmap-preview__iframe" tabindex="-1" data-src="' + url + '" title="' + escapeHtml(h.title) + '"></iframe><div class="lp-heatmap-overlay">' + buildHeatmapOverlayCells(h.grid || []) + '</div></div></div>';
            cardsHtml += '</div>';
            cardsHtml += '<p class="lp-heatmap-card__hint">' + t('clickToEnlarge') + '</p>';
            cardsHtml += '</div>';
            cardsHtml += '<p class="lp-heatmap-item__grid"><a href="' + escapeHtml(detailUrl(h.post_id)) + '">' + t('heatmapGrid') + '<span class="screen-reader-text">: ' + escapeHtml(h.title) + '</span></a></p></div>';
        });
        cardsHtml += '</div>';
        container.innerHTML = cardsHtml;
        container.querySelectorAll('.lp-heatmap-preview').forEach(function (previewEl) {
            applyHeatmapScale(previewEl, HEATMAP_CARD_HEIGHT);
        });
        container.querySelectorAll('.lp-heatmap-preview__iframe').forEach(function (iframeEl) {
            stripAdminBarFromIframe(iframeEl);
        });
        if (activeTab === 'heatmap') loadHeatmapPreviews();
        container.querySelectorAll('.lp-heatmap-card').forEach(function (card) {
            var postId = card.getAttribute('data-post-id');
            var item = withData.filter(function (h) { return String(h.post_id) === String(postId); })[0];
            if (!item) return;
            function open() { openHeatmapModal(item, card); }
            card.addEventListener('click', open);
            card.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        });
        window.requestAnimationFrame(function () {
            container.querySelectorAll('.lp-heatmap-preview').forEach(function (previewEl) {
                applyHeatmapScale(previewEl, HEATMAP_CARD_HEIGHT);
            });
        });
    }

    function renderRageClicks() {
        const container = document.getElementById('lp-rage-content');
        if (!container) return;
        const te = getTrackingEnabled();
        if (!te.rage_clicks) {
            container.innerHTML = '<p class="lp-empty">' + t('enableRage') + '</p>';
            return;
        }
        const data = (analyticsData && analyticsData.rage_clicks) ? analyticsData.rage_clicks : [];
        if (data.length === 0) {
            container.innerHTML = '<p class="lp-empty">' + t('noRage') + '</p>';
            return;
        }
        let html = '<div class="lp-table-wrapper"><table class="lp-table"><thead><tr><th>' + t('page') + '</th><th>' + t('rageCount') + '</th></tr></thead><tbody>';
        data.forEach(function (r) {
            html += '<tr><td>' + escapeHtml(r.title) + '</td><td>' + Number(r.count).toLocaleString() + '</td></tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Heatmap previews are full page loads, so they load only once the Heatmap tab is opened.
    function loadHeatmapPreviews() {
        document.querySelectorAll('.lp-heatmap-preview__iframe[data-src]').forEach(function (iframeEl) {
            iframeEl.src = iframeEl.getAttribute('data-src');
            iframeEl.removeAttribute('data-src');
        });
    }

    let activeTab = 'overview';

    function switchTab(tabName) {
        activeTab = tabName;
        const tabs = root.querySelectorAll('.lp-tabs__tab');
        const panels = root.querySelectorAll('.lp-tab-panel');
        tabs.forEach(function (t) {
            t.classList.toggle('lp-tabs__tab--active', t.getAttribute('data-tab') === tabName);
            t.setAttribute('aria-selected', t.getAttribute('data-tab') === tabName ? 'true' : 'false');
        });
        panels.forEach(function (p) {
            const isActive = p.id === 'panel-' + tabName;
            p.classList.toggle('lp-tab-panel--active', isActive);
            p.hidden = !isActive;
        });
        if (tabName === 'heatmap') loadHeatmapPreviews();
    }

    // The URL to reload this screen with new filters.
    function filterUrl() {
        var base = config.adminUrl ? config.adminUrl : (window.location.origin + window.location.pathname);
        if (base.indexOf('?') >= 0) base = base.split('?')[0];
        var url = base + '?page=' + encodeURIComponent(config.screenSlug || 'lp_analytics');
        if (currentClientId) url += '&client_id=' + currentClientId;
        if (currentTemplateFilter) url += '&template=' + encodeURIComponent(currentTemplateFilter);
        return url + (activeTab !== 'overview' ? '#' + activeTab : '');
    }

    if (clientFilter) {
        clientFilter.addEventListener('change', function () {
            currentClientId = parseInt(this.value, 10) || 0;
            window.location.href = filterUrl();
        });
    }
    if (templateFilter) {
        templateFilter.addEventListener('change', function () {
            currentTemplateFilter = (this.value || '').trim();
            window.location.href = filterUrl();
        });
    }

    root.querySelectorAll('.lp-tabs__tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var name = this.getAttribute('data-tab');
            switchTab(name);
            // Keep the tab in the address, so reloading or coming back from a page's detail view returns here.
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search + (name !== 'overview' ? '#' + name : ''));
            }
        });
    });

    // Open the tab named in the address (#pages from a page's "All landing pages" link).
    (function () {
        var name = (window.location.hash || '').replace(/^#/, '');
        if (/^[a-z]+$/.test(name) && root.querySelector('.lp-tabs__tab[data-tab="' + name + '"]')) switchTab(name);
    })();

    window.addEventListener('resize', function () {
        document.querySelectorAll('.lp-heatmap-preview').forEach(function (previewEl) {
            applyHeatmapScale(previewEl, HEATMAP_CARD_HEIGHT);
        });
        var modalPreview = document.querySelector('.lp-heatmap-modal__preview');
        if (modalPreview) {
            applyHeatmapScale(modalPreview, HEATMAP_MODAL_HEIGHT);
        }
    });

    fetchData();
})();
