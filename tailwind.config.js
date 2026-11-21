// Tailwind CLI build for AcadSync (replaces cdn.tailwindcss.com).
// Rebuild after adding classes to any page or script:  npm run build:css
// Design tokens: the owner's reference education app (2026-11-21), academic theme.
// Cool white screens; periwinkle, orange and yellow cards; charcoal cards and nav. See DESIGN.md.
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./Frontend/**/*.html', './Frontend/js/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
      },
      colors: {
        canvas: '#F4F4F7',         // cool grey ground behind white cards
        surface: '#FFFFFF',
        line: '#E9E9EF',
        charcoal: '#1C1C21',
        muted: '#5E5E68',          // secondary text: at least 4.5:1 on white, the ground and every tint
        hint: '#666670',           // placeholders: at least 4.5:1 on the grey field and on white
        subtle: '#A9A9B4',         // icons and borders only, never text
        lavender: { DEFAULT: '#A8A3F7', tint: '#ECEBFE', light: '#C9C6FB', dark: '#5F58D6' },   // periwinkle
        peach: { DEFAULT: '#F58D42', tint: '#FDE7D6', light: '#F9B886', dark: '#B9561A' },      // orange
        sun: { DEFAULT: '#FFD34E', tint: '#FFF3C4' },
        mint: { DEFAULT: '#BFE5D3', tint: '#E4F4EC' },   // success states only
        success: '#23774F',        // 5.5:1 on white, 4.9:1 on the mint tint
        danger: { DEFAULT: '#C8402F', tint: '#FDE5E2' },   // 5:1 as text on white and behind white badge text
      },
      borderRadius: {
        '4xl': '2rem',
      },
      boxShadow: {
        // white cards: a hairline edge plus a soft drop, like the reference's flat cards
        card: '0 0 0 1px rgba(28,28,33,.06), 0 6px 18px -12px rgba(28,28,33,.16)',
        float: '0 18px 40px -14px rgba(28,28,33,.45)',
      },
    },
  },
};
