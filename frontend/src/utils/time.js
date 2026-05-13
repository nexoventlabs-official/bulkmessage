import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import isToday from 'dayjs/plugin/isToday';
import isYesterday from 'dayjs/plugin/isYesterday';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(isToday);
dayjs.extend(isYesterday);
dayjs.extend(relativeTime);

export const IST = 'Asia/Kolkata';

export const ist = (d) => dayjs(d).tz(IST);

export function formatMessageTime(d) {
  return ist(d).format('h:mm A');
}

export function formatDaySeparator(d) {
  const x = ist(d);
  if (x.isToday()) return 'Today';
  if (x.isYesterday()) return 'Yesterday';
  return x.format('DD MMM YYYY');
}

export function sameDay(a, b) {
  return ist(a).format('YYYY-MM-DD') === ist(b).format('YYYY-MM-DD');
}

export function windowState(lastCustomerMessageAt) {
  if (!lastCustomerMessageAt) return { expired: true, remainingMs: 0, danger: false, totalHours: 24 };
  const totalHours = 24;
  const end = new Date(lastCustomerMessageAt).getTime() + totalHours * 60 * 60 * 1000;
  const remaining = end - Date.now();
  return {
    expired: remaining <= 0,
    remainingMs: Math.max(0, remaining),
    danger: remaining > 0 && remaining < (totalHours / 4) * 60 * 60 * 1000,
    totalHours,
  };
}

export function formatCountdown(ms) {
  const total = Math.max(0, Math.floor(ms / 1000));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
