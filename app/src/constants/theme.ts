// Brand palette and typography from the approved mockup (see CLAUDE.md, section 7).
export const colors = {
  background: '#17120F',
  surface: '#241C17',
  border: '#3A2E25',
  bar: '#100C0A',
  barBorder: '#2E241D',
  text: '#F6EEE3',
  textBody: '#D9CCBD',
  textMuted: '#BFAF9E',
  primary: '#C8321F',
  onPrimary: '#FFFFFF',
  accent: '#F2B705',
  accentSoft: '#3A2C10',
  onAccent: '#17120F',
} as const;

// Each weight is its own font family on Android, so weights are picked by family name.
export const fonts = {
  heading: 'ZillaSlab_700Bold',
  regular: 'Figtree_400Regular',
  medium: 'Figtree_500Medium',
  semibold: 'Figtree_600SemiBold',
  bold: 'Figtree_700Bold',
} as const;

// Minimum touch target size (accessibility).
export const minTouchSize = 44;
