// VrakeIT Service Worker — Web Push Handler
// Version: 1.0.0
// Must be at the root of the project for full scope

const CACHE_NAME = 'vrakeit-sw-v1';

// ── Install Event ────────────────────────────────────────────
self.addEventListener('install', (event) => {
  self.skipWaiting(); // Activate immediately
});

// ── Activate Event ───────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim()); // Take control immediately
});

// ── Push Event ───────────────────────────────────────────────
// Fired when the server sends a Web Push notification
self.addEventListener('push', (event) => {
  let data = {};

  // The push may have no body (empty push) — we still show a notification
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      // Non-JSON body — use it as the notification body text
      data = { body: event.data.text() };
    }
  }

  const title   = data.title   || '🚨 New Incident Report — VrakeIT';
  const options = {
    body:    data.body    || 'A new incident report has been submitted. Tap to review.',
    icon:    data.icon    || '/vrakeit/assets/img/system_logo.png',
    badge:   '/vrakeit/assets/img/system_logo.png',
    tag:     data.tag     || 'vrakeit-report-' + Date.now(),
    data: {
      url: data.url || '/vrakeit/enforcer_landing.php',
    },
    actions: [
      { action: 'view',    title: '👁 View Report' },
      { action: 'dismiss', title: 'Dismiss'        }
    ],
    requireInteraction: true, // Keeps visible until user interacts
    vibrate: [200, 100, 200, 100, 200], // Vibration pattern on Android
    renotify: true, // Re-alert even if same tag already showing
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// ── Notification Click ───────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'dismiss') return;

  const targetUrl = event.notification.data?.url || '/vrakeit/enforcer_portal.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Focus an existing enforcer portal tab if one is already open
      for (const client of clientList) {
        if (client.url.includes('enforcer_portal') && 'focus' in client) {
          return client.focus();
        }
      }
      // Otherwise open a new tab
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
