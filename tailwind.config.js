// Tailwind CLI build for AcadSync (replaces cdn.tailwindcss.com).
// Rebuild after adding classes to any page or script:  npm run build:css
// Design tokens from the AcadSync v2 Figma components page.
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./Frontend/**/*.html', './Frontend/js/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
      },
      colors: {
        canvas: '#F6F3EE',
        surface: '#FFFFFF',
        line: '#ECE8E2',
        charcoal: '#1F1F24',
        muted: '#62626B',          // secondary text: at least 4.5:1 on white, the sand ground and every pastel tint
        hint: '#6B6B74',           // placeholders: 4.8:1 on the sand field, 5.3:1 on white
        subtle: '#A9A9B2',
        lavender: { DEFAULT: '#A99CF5', tint: '#E6E1FD', light: '#C2B8FA', dark: '#6E5FD8' },
        peach: { DEFAULT: '#F6A76B', tint: '#FCE3CF', dark: '#D9824A' },
        sun: { DEFAULT: '#F8D65A', tint: '#FDF3C7' },
        mint: { DEFAULT: '#BFE8D4', tint: '#E5F6EE' },
        success: '#4FB386',
        danger: { DEFAULT: '#E5675C', tint: '#FDE4E1' },
      },
      borderRadius: {
        '4xl': '2rem',
      },
      boxShadow: {
        card: '0 1px 2px rgba(31,31,36,.04), 0 8px 24px -12px rgba(31,31,36,.12)',
        float: '0 18px 40px -12px rgba(31,31,36,.45)',
      },
    },
  },
};
