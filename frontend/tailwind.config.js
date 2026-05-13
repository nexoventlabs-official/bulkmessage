/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx,ts,tsx}'],
  theme: {
    extend: {
      colors: {
        wati: {
          primary: '#00a884',
          primaryDark: '#008069',
          bg: '#e5ddd5',
          sidebar: '#ffffff',
          panel: '#f0f2f5',
          bubbleOut: '#dcf8c6',
          bubbleIn: '#ffffff',
          text: '#111b21',
          muted: '#54656f',
          header: '#f0f2f5',
          border: '#d1d7db',
        },
        admin: {
          accent: '#4f46e5',
          accentHover: '#4338ca',
          bg: '#f8fafc',
          card: '#ffffff',
          text: '#0f172a',
          muted: '#64748b',
          border: '#e2e8f0',
        }
      },
      fontFamily: {
        sans: ['Inter', 'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        bubble: '0 1px 0.5px rgba(11,20,26,.13)',
        'premium': '0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 0 3px rgba(0,0,0,0.02)',
        'premium-hover': '0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
      },
    },
  },
  plugins: [],
};
