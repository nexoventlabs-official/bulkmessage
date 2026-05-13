const NOTIF_PREF_KEY = 'bc:notifyEnabled';

export function isNotifSupported() {
  return typeof window !== 'undefined' && 'Notification' in window;
}

export function notifPermission() {
  if (!isNotifSupported()) return 'unsupported';
  return Notification.permission;
}

export async function ensurePermission() {
  if (!isNotifSupported()) return 'unsupported';
  if (Notification.permission === 'granted') return 'granted';
  if (Notification.permission === 'denied') return 'denied';
  try {
    return await Notification.requestPermission();
  } catch {
    return Notification.permission;
  }
}

export function getNotifyEnabled() {
  try { return localStorage.getItem(NOTIF_PREF_KEY) !== '0'; } catch { return true; }
}
export function setNotifyEnabled(v) {
  try { localStorage.setItem(NOTIF_PREF_KEY, v ? '1' : '0'); } catch { /* noop */ }
}

let audioCtx = null;
function getCtx() {
  if (audioCtx) return audioCtx;
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return null;
  audioCtx = new AC();
  return audioCtx;
}

export function playChime() {
  try {
    const ctx = getCtx();
    if (!ctx) return;
    if (ctx.state === 'suspended') ctx.resume().catch(() => {});
    const now = ctx.currentTime;
    const tone = (freq, start, dur) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0, now + start);
      gain.gain.linearRampToValueAtTime(0.18, now + start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + start + dur);
      osc.connect(gain).connect(ctx.destination);
      osc.start(now + start);
      osc.stop(now + start + dur + 0.05);
    };
    tone(880, 0, 0.18);
    tone(1175, 0.10, 0.22);
  } catch { /* noop */ }
}

const lastShownAt = new Map();
const MIN_INTERVAL_MS = 800;

export function showMessageNotification({ contactId, title, body, iconUrl, onClick }) {
  if (!isNotifSupported()) return;
  if (Notification.permission !== 'granted') return;
  if (!getNotifyEnabled()) return;
  const now = Date.now();
  const last = lastShownAt.get(contactId) || 0;
  if (now - last < MIN_INTERVAL_MS) return;
  lastShownAt.set(contactId, now);
  try {
    const n = new Notification(title || 'New message', {
      body: body || '',
      icon: iconUrl || '/logo.png',
      badge: '/logo.png',
      tag: contactId ? `bc:${contactId}` : 'bc:msg',
      renotify: true,
      silent: true,
    });
    if (onClick) {
      n.onclick = () => {
        try { window.focus(); } catch { /* noop */ }
        try { onClick(); } catch { /* noop */ }
        n.close();
      };
    }
    setTimeout(() => { try { n.close(); } catch { /* noop */ } }, 6000);
  } catch { /* noop */ }
}
