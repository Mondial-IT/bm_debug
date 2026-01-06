/*
 * Copyright (c) Mondial-IT BV - Blue Marloc 2025
 *   Created on 2025-12-23 at 12:12:12
 */

/* global Drupal, once, drupalSettings */

(function (Drupal, once, drupalSettings) {
  'use strict';

  const BM_DEBUG_DEFAULTS = {
    enabled: true,
    overlay: true,
    capture: {
      windowError: true,
      unhandledRejection: true,
      drupalAjax: true,
      xhr: true,
      fetch: true,
    },
    noisyPatterns: [
      'AbortError',
      'The operation was aborted',
      'NetworkError when attempting to fetch resource',
      'Load timeout for modules',
    ],
    maxErrors: 50,
    trace: {
      enabled: true,
      maxFrames: 20,
      includeArgs: false,
    },
    cleanup: {
      enabled: true,
      kint: true,
    }
  };

  function deepMerge(target, source) {
    if (!source) return target;
    for (const key of Object.keys(source)) {
      if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
        target[key] = deepMerge(target[key] || {}, source[key]);
      } else {
        target[key] = source[key];
      }
    }
    return target;
  }

  function getConfig() {
    const fromSettings = drupalSettings?.bm_debug || {};
    const cfg = deepMerge(JSON.parse(JSON.stringify(BM_DEBUG_DEFAULTS)), fromSettings);
    try {
      const ls = localStorage.getItem('bm_debug');
      if (ls) deepMerge(cfg, JSON.parse(ls));
    } catch (e) {}
    cfg.enabled = !!cfg.enabled;
    cfg.overlay = !!cfg.overlay;
    return cfg;
  }

  const CFG = getConfig();

  if (!window.BM) window.BM = {};
  if (!window.BM.log) window.BM.log = {};
  window.BM.log.color1 ||= 'background:#139ad0;color:#fff;padding:2px 6px;border-radius:3px;';
  window.BM.log.color2 ||= 'background:#f1f9ff;color:#139ad0;padding:2px 6px;border-radius:3px;';

  const STATE = {
    installed: false,
    errors: [],
    observer: null,
    inHandler: false,
    overlays: [],
    overlayCount: 0,
    hardDisabled: false,
    orig: {}
  };

  const OVERLAY_LIMIT = 4;

  function nowIso() {
    return new Date().toISOString();
  }

  function normalizeStack(stack) {
    if (!stack) return '';
    return String(stack)
      .split('\n')
      .filter(l =>
        !l.includes('bm-debug.js') &&
        !l.includes('captureAndReport') &&
        !l.includes('installGlobalHandlers')
      )
      .slice(0, CFG.trace.maxFrames)
      .join('\n');
  }

  function captureTrace(skip = 0) {
    try {
      return normalizeStack(new Error().stack.split('\n').slice(2 + skip).join('\n'));
    } catch {
      return '';
    }
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function looksNoisy(message) {
    return CFG.noisyPatterns.some(p => String(message || '').includes(p));
  }

  function toPlainError(err) {
    if (!err) return { name: 'Error', message: '', stack: '' };
    if (typeof err === 'string') return { name: 'Error', message: err, stack: '' };
    return { name: err.name || 'Error', message: err.message || String(err), stack: err.stack || '' };
  }

  function safeJson(data) {
    try {
      return JSON.stringify(data, null, 2);
    } catch {
      return '';
    }
  }

  function renderOverlay(entry) {
    if (!CFG.overlay) return;
    if (STATE.overlayCount >= OVERLAY_LIMIT) return;

    const idx = STATE.overlayCount++;
    const el = document.createElement('div');

    el.style.position = 'fixed';
    el.style.top = 80 + idx * 30 + 'px';
    el.style.left = 80 + idx * 30 + 'px';
    el.style.width = '800px';
    el.style.maxHeight = '75vh';
    el.style.overflow = 'auto';
    el.style.zIndex = 2147483647 - idx;
    el.style.background = '#f1f9ff';
    el.style.border = '2px solid #139ad0';
    el.style.borderRadius = '10px';
    el.style.padding = '16px';
    el.style.cursor = 'move';
    el.style.boxShadow = '0 0 18px rgba(0,0,0,.45)';

    el.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <img src="https://bluemarloc.com/bpiofiles/logo_2_500_0.png" alt="Blue Marloc" style="height:34px;">
          <div>
            <div style="font-size:18px;font-weight:800;">Blue Marloc detected an error</div>
            <div style="font-size:12px;opacity:.85;">
              ${escapeHtml(entry.time)} · ${escapeHtml(entry.type)}
            </div>
          </div>
        </div>
        <div style="display:flex;gap:8px;">
          <button data-copy style="padding:6px 10px;border:1px solid #139ad0;background:#fff;border-radius:6px;cursor:pointer;">
            Copy
          </button>
          <button data-close style="padding:6px 10px;border:1px solid #139ad0;background:#fff;border-radius:6px;cursor:pointer;">
            Close
          </button>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid rgba(19,154,208,.3);margin:12px 0;">

      <details open>
        <summary style="cursor:pointer;font-weight:700;">Message</summary>
        <pre style="white-space:pre-wrap;margin:6px 0 0 0;">${escapeHtml(entry.message)}</pre>
      </details>

      ${entry.stack ? `
        <details>
          <summary style="cursor:pointer;font-weight:700;">Stack</summary>
          <pre style="white-space:pre-wrap;">${escapeHtml(entry.stack)}</pre>
        </details>` : ''}

      ${entry.trace ? `
        <details>
          <summary style="cursor:pointer;font-weight:700;">Trace</summary>
          <pre style="white-space:pre-wrap;">${escapeHtml(entry.trace)}</pre>
        </details>` : ''}

      <details>
        <summary style="cursor:pointer;font-weight:700;">Context</summary>
        <pre style="white-space:pre-wrap;">${escapeHtml(safeJson(entry.context))}</pre>
      </details>
    `;

    document.body.appendChild(el);
    STATE.overlays.push(el);

    el.querySelector('[data-close]').onclick = () => el.remove();
    el.querySelector('[data-copy]').onclick = async () => {
      try {
        await navigator.clipboard.writeText(safeJson(entry));
      } catch (e) {}
    };

    let sx = 0, sy = 0, ox = 0, oy = 0, dragging = false;

    el.addEventListener('mousedown', (e) => {
      if (e.target.tagName === 'BUTTON') return;
      dragging = true;
      sx = e.clientX;
      sy = e.clientY;
      const r = el.getBoundingClientRect();
      ox = r.left;
      oy = r.top;
      e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
      if (!dragging) return;
      el.style.left = ox + (e.clientX - sx) + 'px';
      el.style.top = oy + (e.clientY - sy) + 'px';
    });

    document.addEventListener('mouseup', () => dragging = false);
  }

  function captureAndReport(type, errLike, context) {
    if (!CFG.enabled || STATE.inHandler || STATE.hardDisabled) return;

    try {
      STATE.inHandler = true;

      const err = toPlainError(errLike);
      if (looksNoisy(err.message)) return;

      const entry = {
        time: nowIso(),
        type,
        message: err.message,
        stack: normalizeStack(err.stack),
        trace: CFG.trace.enabled ? captureTrace(1) : '',
        context: context || {}
      };

      STATE.errors.push(entry);

      console.groupCollapsed('%c BMDebug %c ' + type, window.BM.log.color1, window.BM.log.color2);
      console.log(entry);
      console.groupEnd();

      renderOverlay(entry);

      if (STATE.overlayCount >= OVERLAY_LIMIT) {
        STATE.hardDisabled = true;
        CFG.enabled = false;
      }
    } finally {
      STATE.inHandler = false;
    }
  }

  window.addEventListener('error', function (e) {
    try { e.preventDefault(); } catch {}
    captureAndReport('window.error', e.error || e.message, { source: 'window' });
  }, true);

  window.addEventListener('unhandledrejection', function (e) {
    try { e.preventDefault(); } catch {}
    captureAndReport('unhandledrejection', e.reason, { source: 'promise' });
  }, true);

  if (!window.BM.BMDebug) {
    window.BM.BMDebug = {
      install() {},
      report: captureAndReport,
      resetLimiter() {
        STATE.overlayCount = 0;
        STATE.hardDisabled = false;
        CFG.enabled = true;
      }
    };
  }

  Drupal.behaviors.bm_debug = {
    attach(context) {
      once('bm-debug-init', 'html', context).forEach(() => {});
    }
  };

})(Drupal, once, drupalSettings);
