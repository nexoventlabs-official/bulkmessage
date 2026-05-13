import React from 'react';

function PersonGlyph({ size }) {
  const inner = Math.round(size * 0.62);
  return (
    <svg width={inner} height={inner} viewBox="0 0 24 24" fill="#FFFFFF" aria-hidden="true">
      <circle cx="12" cy="8.5" r="3.6" />
      <path d="M4.5 20c0-3.59 3.36-6 7.5-6s7.5 2.41 7.5 6v.5H4.5V20z" />
    </svg>
  );
}

export default function Avatar({ name, url, size = 48, className = '' }) {
  if (url) {
    return (
      <img
        src={url}
        alt={name || ''}
        className={`rounded-full object-cover shrink-0 ${className}`}
        style={{ width: size, height: size }}
        onError={(e) => { e.currentTarget.style.display = 'none'; }}
      />
    );
  }
  return (
    <div
      className={`rounded-full flex items-center justify-center bg-[#DFE5E7] shrink-0 ${className}`}
      style={{ width: size, height: size }}
      title={name || ''}
    >
      <PersonGlyph size={size} />
    </div>
  );
}
