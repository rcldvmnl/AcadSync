// Design tokens from the AcadSync v2 Figma components page.
tailwind.config = {
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
        muted: '#8A8A94',
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
