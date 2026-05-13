const COUNTRIES = [
  { code: '1', iso2: 'US', name: 'United States / Canada' },
  { code: '91', iso2: 'IN', name: 'India' },
  { code: '92', iso2: 'PK', name: 'Pakistan' },
  { code: '94', iso2: 'LK', name: 'Sri Lanka' },
  { code: '880', iso2: 'BD', name: 'Bangladesh' },
  { code: '977', iso2: 'NP', name: 'Nepal' },
  { code: '60', iso2: 'MY', name: 'Malaysia' },
  { code: '62', iso2: 'ID', name: 'Indonesia' },
  { code: '63', iso2: 'PH', name: 'Philippines' },
  { code: '65', iso2: 'SG', name: 'Singapore' },
  { code: '66', iso2: 'TH', name: 'Thailand' },
  { code: '86', iso2: 'CN', name: 'China' },
  { code: '81', iso2: 'JP', name: 'Japan' },
  { code: '82', iso2: 'KR', name: 'South Korea' },
  { code: '971', iso2: 'AE', name: 'United Arab Emirates' },
  { code: '966', iso2: 'SA', name: 'Saudi Arabia' },
  { code: '44', iso2: 'GB', name: 'United Kingdom' },
  { code: '49', iso2: 'DE', name: 'Germany' },
  { code: '33', iso2: 'FR', name: 'France' },
  { code: '55', iso2: 'BR', name: 'Brazil' },
  { code: '20', iso2: 'EG', name: 'Egypt' },
  { code: '27', iso2: 'ZA', name: 'South Africa' },
  { code: '234', iso2: 'NG', name: 'Nigeria' },
  { code: '61', iso2: 'AU', name: 'Australia' },
  { code: '90', iso2: 'TR', name: 'Turkey' },
];

const SORTED = [...COUNTRIES].sort((a, b) => b.code.length - a.code.length);

export function isoToFlagEmoji(iso2) {
  if (!iso2 || iso2.length !== 2) return '🏳️';
  const base = 0x1f1e6;
  const A = 'A'.charCodeAt(0);
  const upper = iso2.toUpperCase();
  return String.fromCodePoint(
    base + (upper.charCodeAt(0) - A),
    base + (upper.charCodeAt(1) - A)
  );
}

export function resolveCountry(waId) {
  const digits = String(waId || '').replace(/\D/g, '');
  if (!digits) return { iso2: '', name: 'Unknown', flag: '🏳️', dialCode: '', nationalNumber: '' };
  for (const c of SORTED) {
    if (digits.startsWith(c.code)) {
      return {
        iso2: c.iso2,
        name: c.name,
        flag: isoToFlagEmoji(c.iso2),
        dialCode: c.code,
        nationalNumber: digits.slice(c.code.length),
      };
    }
  }
  return { iso2: '', name: 'Unknown', flag: '🏳️', dialCode: '', nationalNumber: digits };
}

export function formatPhone(waId) {
  const { dialCode, nationalNumber } = resolveCountry(waId);
  if (!dialCode) return `+${String(waId || '').replace(/\D/g, '')}`;
  const n = nationalNumber;
  if (dialCode === '91' && n.length === 10) {
    return `+${dialCode} ${n.slice(0, 5)} ${n.slice(5)}`;
  }
  if (dialCode === '1' && n.length === 10) {
    return `+${dialCode} (${n.slice(0, 3)}) ${n.slice(3, 6)}-${n.slice(6)}`;
  }
  const groups = [];
  let rest = n;
  while (rest.length > 3) {
    groups.unshift(rest.slice(-3));
    rest = rest.slice(0, -3);
  }
  if (rest) groups.unshift(rest);
  return `+${dialCode} ${groups.join(' ')}`;
}
