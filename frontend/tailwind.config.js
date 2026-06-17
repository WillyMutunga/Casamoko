/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        darkBg: "#090d16",
        darkPanel: "rgba(17, 24, 39, 0.7)",
        darkBorder: "rgba(31, 41, 55, 0.5)",
      },
      fontFamily: {
        sans: ["Inter", "sans-serif"],
      },
      boxShadow: {
        glass: "0 8px 32px 0 rgba(0, 0, 0, 0.4)",
      },
      backdropBlur: {
        xs: "2px",
      }
    },
  },
  plugins: [],
}
