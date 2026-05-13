import { useEffect } from 'react';

export default function useClickAway(refs, handler, active = true) {
  useEffect(() => {
    if (!active) return undefined;
    const onDown = (e) => {
      const list = Array.isArray(refs) ? refs : [refs];
      for (const r of list) {
        const el = r && r.current;
        if (el && el.contains(e.target)) return;
      }
      handler(e);
    };
    document.addEventListener('pointerdown', onDown, true);
    return () => document.removeEventListener('pointerdown', onDown, true);
  }, [refs, handler, active]);
}
