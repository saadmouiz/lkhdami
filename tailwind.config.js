/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        'hestim-blue': '#00175f',
        'hestim-dark': '#1e293b',
      },
    },
  },
  plugins: [],
}

