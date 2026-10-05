// Brand palette and typography from the approved mockup (see CLAUDE.md, section 7).
export const colors = {
  background: '#17120F',
  surface: '#241C17',
  border: '#3A2E25',
  text: '#F6EEE3',
  textMuted: '#BFAF9E',
  primary: '#C8321F',
  accent: '#F2B705',
} as const;

export const fonts = {
  heading: 'Zilla Slab',
  body: 'Figtree',
} as const;

// Minimum touch target size (accessibility).
export const minTouchSize = 44;
