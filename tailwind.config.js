/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        night: '#0a0918',
        dusk: '#16143a',
        marigold: '#f6a21e',
        amber: '#ffd166',
        magenta: '#d6468f',
        teal: '#2ec4b6',
        parchment: '#f5ecd9',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        serif: ['"Playfair Display"', 'Georgia', 'serif'],
      },
    },
  },
  plugins: [],
};