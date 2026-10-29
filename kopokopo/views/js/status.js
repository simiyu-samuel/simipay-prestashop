/* Read-only polling: payment completion is decided by the server. */
(() => {
  'use strict';
  const panel = document.getElementById('kopokopo-status');
  if (!panel || ['failed', 'review'].includes(panel.dataset.state)) return;
  const endpoint = new URL(panel.dataset.statusUrl, window.location.href);
  if (endpoint.origin !== window.location.origin) return;
  let polls = 0;
  let errors = 0;
  const poll = async () => {
    if (++polls > 90) return;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);
    try {
      const response = await fetch(endpoint, {
        credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal
      });
      if (!response.ok) throw new Error('Status unavailable');
      const data = await response.json();
      errors = 0;
      if (typeof data.message === 'string') document.getElementById('kopokopo-message').textContent = data.message;
      if (data.state === 'completed' && data.redirect) {
        const destination = new URL(data.redirect, window.location.href);
        if (destination.origin === window.location.origin) window.location.replace(destination.href);
        return;
      }
      if (['failed', 'review'].includes(data.state)) {
        window.location.reload();
        return;
      }
    } catch (_) {
      if (++errors >= 5) return; // Keep the server-rendered reference and refresh instructions visible.
    } finally {
      clearTimeout(timeout);
    }
    setTimeout(poll, Math.min(10000, 3000 + polls * 150));
  };
  setTimeout(poll, 1500);
})();
