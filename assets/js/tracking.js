/**
 * Landing Page Manager Pro – front-end tracking.
 * Only runs features enabled via lpmanagerTracking (Settings → Conversion Tracking).
 * - Session: on load, fetches a fresh nonce and (by default) counts the page view, so
 *   pages served from a full-page cache are counted and never use an expired nonce.
 * - Link clicks and outbound clicks: sent per click.
 * - Conversions: phone/call button clicks.
 * - Time on page, heatmap: sent once when the visitor leaves (or hides the tab).
 * - Scroll depth, CTA visibility, form start/submit: sent at most once per page view.
 */
(function () {
    'use strict';

    if (typeof window.lpmanagerTracking === 'undefined') return;

    var cfg = window.lpmanagerTracking;

    function resolveAjaxUrl(url) {
        if (!url) return '/wp-admin/admin-ajax.php';
        if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) {
            try {
                var parsed = new URL(url);
                if (parsed.origin !== window.location.origin) {
                    return '/wp-admin/admin-ajax.php';
                }
            } catch (e) {
                return '/wp-admin/admin-ajax.php';
            }
        }
        return url;
    }

    var ajaxurl = resolveAjaxUrl(cfg.ajaxurl);
    var nonce = cfg.nonce;
    var postId = parseInt(cfg.postId, 10) || 0;
    if (!postId) return;

    var trackLinkClicks = !!cfg.trackLinkClicks;
    var trackConversions = !!cfg.trackConversions;
    var trackTimeOnPage = !!cfg.trackTimeOnPage;
    var trackScrollDepth = !!cfg.trackScrollDepth;
    var trackHeatmap = !!cfg.trackHeatmap;
    var trackRageClicks = !!cfg.trackRageClicks;
    var trackOutboundClicks = !!cfg.trackOutboundClicks;
    var trackCtaVisibility = !!cfg.trackCtaVisibility;
    var trackFormInteractions = !!cfg.trackFormInteractions;

    var CTA_SELECTOR = '.lpmanager-call-button, .cta-call-button, a[href^="tel:"]';

    /**
     * The nonce printed into the page may be stale when the page came from a full-page cache,
     * so requests wait for a fresh one from the session request below. If that request fails
     * or is slow, the printed nonce is used.
     */
    var ready = false;
    var queue = [];
    var SESSION_TIMEOUT_MS = 5000;

    function becomeReady(freshNonce) {
        if (ready) return;
        if (freshNonce) nonce = freshNonce;
        ready = true;
        var pending = queue;
        queue = [];
        pending.forEach(function (item) { post(item[0], item[1], item[2]); });
    }

    function startSession() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function () {
            var fresh = null;
            try {
                var res = JSON.parse(xhr.responseText);
                if (res && res.success && res.data && res.data.nonce) fresh = res.data.nonce;
            } catch (e) { /* fall back to the printed nonce */ }
            becomeReady(fresh);
        };
        xhr.onerror = function () { becomeReady(null); };
        xhr.send('action=lpmanager_pro_session&post_id=' + postId + '&count_view=' + (cfg.countView ? '1' : '0'));
        setTimeout(function () { becomeReady(null); }, SESSION_TIMEOUT_MS);
    }

    function send(action, data, useBeacon) {
        if (!ready) {
            // Queued requests may be flushed while the page unloads, so always allow a beacon.
            queue.push([action, data, true]);
            return;
        }
        post(action, data, useBeacon);
    }

    /**
     * POST form-encoded data to admin-ajax. Uses sendBeacon when the page may be unloading,
     * because browsers cancel ordinary requests during navigation.
     */
    function post(action, data, useBeacon) {
        var params = new URLSearchParams();
        params.append('action', action);
        params.append('nonce', nonce);
        params.append('post_id', String(data.post_id || postId));
        Object.keys(data).forEach(function (key) {
            if (key !== 'post_id' && data[key] !== null && data[key] !== undefined) {
                params.append(key, String(data[key]));
            }
        });
        if (useBeacon && navigator.sendBeacon) {
            if (navigator.sendBeacon(ajaxurl, params)) return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.send(params.toString());
    }

    function sendInteraction(type, useBeacon) {
        send('lpmanager_record_interaction', { type: type }, useBeacon);
    }

    /** Runs each callback once when the visitor leaves or hides the page. */
    var leaveHandlers = [];
    var left = false;
    function onLeave(fn) {
        leaveHandlers.push(fn);
    }
    function handleLeave() {
        if (left) return;
        left = true;
        leaveHandlers.forEach(function (fn) { fn(); });
        // Leaving before the session responded: send what is queued with the printed nonce.
        becomeReady(null);
    }
    window.addEventListener('pagehide', handleLeave);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') handleLeave();
    });

    startSession();

    // —— Link clicks, outbound clicks, conversions ——
    if (trackLinkClicks || trackConversions || trackOutboundClicks) {
        document.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('a') : null;
            if (!a || !a.href) return;
            var href = a.getAttribute('href');
            if (!href || href === '#' || href.indexOf('javascript:') === 0) return;
            var text = (a.textContent || '').trim().replace(/\s+/g, ' ');
            var isHttp = a.protocol === 'http:' || a.protocol === 'https:';
            var isOutbound = isHttp && a.hostname && a.hostname !== window.location.hostname;

            if (trackLinkClicks) {
                send('lpmanager_record_click', { href: href, text: text }, true);
            }
            if (trackOutboundClicks && isOutbound) {
                sendInteraction('outbound_click', true);
            }
            if (trackConversions && (a.classList.contains('lpmanager-call-button') || a.classList.contains('cta-call-button') || /^tel:/i.test(href))) {
                var dataPostId = parseInt(a.getAttribute('data-post-id'), 10);
                send('lpmanager_record_conversion', { post_id: dataPostId || postId }, true);
            }
        }, true);
    }

    // —— Time on page: one measurement per page view ——
    if (trackTimeOnPage) {
        var pageStart = Date.now();
        onLeave(function () {
            var seconds = Math.round((Date.now() - pageStart) / 1000);
            if (seconds > 0) send('lpmanager_record_engagement', { seconds: seconds }, true);
        });
    }

    // —— Scroll depth ——
    if (trackScrollDepth) {
        var scrollDepthsSent = { 25: false, 50: false, 75: false, 100: false };
        var onScroll = function () {
            var h = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            if (h <= 0) return;
            var pct = Math.round((window.scrollY || document.documentElement.scrollTop) / h * 100);
            [25, 50, 75, 100].forEach(function (d) {
                if (pct >= d && !scrollDepthsSent[d]) {
                    scrollDepthsSent[d] = true;
                    send('lpmanager_record_engagement', { scroll_depth: d }, false);
                }
            });
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // —— Heatmap: bin mouse positions into a 10x10 viewport grid, send once on leave ——
    if (trackHeatmap) {
        var grid = [];
        var i;
        for (i = 0; i < 100; i++) grid[i] = 0;
        var lastSample = 0;
        var sampleEveryMs = 100;

        document.addEventListener('mousemove', function (e) {
            var now = Date.now();
            if (now - lastSample < sampleEveryMs) return;
            lastSample = now;
            var w = window.innerWidth;
            var h = window.innerHeight;
            if (w <= 0 || h <= 0) return;
            var col = Math.min(9, Math.floor((e.clientX / w) * 10));
            var row = Math.min(9, Math.floor((e.clientY / h) * 10));
            grid[row * 10 + col]++;
        }, { passive: true });

        onLeave(function () {
            var cells = [];
            for (i = 0; i < 100; i++) {
                var n = grid[i];
                while (n-- > 0) cells.push(i);
            }
            if (cells.length) send('lpmanager_record_heatmap', { cells: JSON.stringify(cells) }, true);
        });
    }

    // —— Rage clicks: three or more clicks in the same area within a short time ——
    if (trackRageClicks) {
        var lastClick = { x: 0, y: 0, t: 0, count: 0 };
        var radius = 30;
        var windowMs = 500;

        document.addEventListener('click', function (e) {
            var t = Date.now();
            var dx = e.clientX - lastClick.x;
            var dy = e.clientY - lastClick.y;
            if (t - lastClick.t < windowMs && (dx * dx + dy * dy) < radius * radius) {
                lastClick.count++;
                if (lastClick.count >= 3) {
                    send('lpmanager_record_rage_click', {}, false);
                    lastClick.count = 0;
                }
            } else {
                lastClick.x = e.clientX;
                lastClick.y = e.clientY;
                lastClick.count = 1;
            }
            lastClick.t = t;
        }, true);
    }

    // —— CTA visibility: recorded once per page view when any CTA enters the viewport ——
    if (trackCtaVisibility) {
        var ctaSeen = false;
        var markCtaSeen = function () {
            if (ctaSeen) return;
            ctaSeen = true;
            sendInteraction('cta_view', false);
        };
        var ctas = document.querySelectorAll(CTA_SELECTOR);
        if (ctas.length && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        markCtaSeen();
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.5 });
            ctas.forEach(function (el) { observer.observe(el); });
        } else if (ctas.length) {
            var checkCta = function () {
                if (ctaSeen) return;
                ctas.forEach(function (el) {
                    var rect = el.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) markCtaSeen();
                });
            };
            window.addEventListener('scroll', checkCta, { passive: true });
            window.addEventListener('load', checkCta);
        }
    }

    // —— Form interactions: first field focus per form (start) and submit ——
    if (trackFormInteractions) {
        var startedForms = [];
        document.addEventListener('focusin', function (e) {
            var el = e.target;
            if (!el || !/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || !el.form) return;
            if (startedForms.indexOf(el.form) !== -1) return;
            startedForms.push(el.form);
            sendInteraction('form_start', false);
        });
        document.addEventListener('submit', function (e) {
            if (e.target && e.target.tagName === 'FORM') sendInteraction('form_submit', true);
        }, true);
    }
})();
