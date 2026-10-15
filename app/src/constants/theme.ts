import type { TextStyle } from 'react-native';

// Brand palette and typography, aligned with the redesigned website (neutral charcoal,
// ember red and mustard). Text on dark surfaces is always white or light grey.
export const colors = {
  background: '#232323',
  surface: '#2F2F2F',
  border: '#474747',
  divider: '#3A3A3A',
  bar: '#1B1B1B',
  barBorder: '#3A3A3A',
  text: '#FFFFFF',
  textBody: '#D2D2D2',
  textMuted: '#D2D2D2',
  textTertiary: '#A9A9A9',
  primary: '#C8321F',
  onPrimary: '#FFFFFF',
  accent: '#F2B705',
  accentSoft: '#42391E',
  onAccent: '#141414',
  error: '#FF8A75',
  overlay: 'rgba(35,35,35,0.85)',
} as const;

// Each weight is its own font family on Android, so weights are picked by family name.
export const fonts = {
  heading: 'BarlowCondensed_800ExtraBold',
  regular: 'Barlow_400Regular',
  medium: 'Barlow_500Medium',
  semibold: 'Barlow_600SemiBold',
  bold: 'Barlow_700Bold',
} as const;

// Headings: condensed extra-bold, uppercase, like the website.
export const headingText = {
  fontFamily: fonts.heading,
  textTransform: 'uppercase',
  letterSpacing: 0.3,
} as const satisfies TextStyle;

// Minimum touch target size (accessibility).
export const minTouchSize = 44;
