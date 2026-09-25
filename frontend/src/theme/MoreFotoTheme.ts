import type { ThemeDefinition } from 'vuetify';
import { MF_INK, MF_SEA, MF_TONES, MF_WHITE } from './tokens';

/** Vuetify theme built from design tokens; component styles read the same values as --mf-* variables. */
export const MoreFotoTheme: ThemeDefinition = {
  dark: false,
  colors: {
    background: MF_INK[50],
    surface: MF_WHITE,
    'surface-variant': MF_INK[100],
    'on-background': MF_INK[900],
    'on-surface': MF_INK[900],
    'on-surface-variant': MF_INK[900],
    primary: MF_SEA[600],
    'primary-darken-1': MF_SEA[700],
    'on-primary': MF_WHITE,
    secondary: MF_INK[900],
    'on-secondary': MF_WHITE,
    accent: MF_SEA[200],
    lightText: MF_INK[600],
    inputBorder: MF_INK[400],
    error: MF_TONES.danger.fg,
    warning: MF_TONES.warning.fg,
    success: MF_TONES.success.fg,
    info: MF_SEA[600],
    pending: MF_TONES.pending.fg
  },
  variables: {
    'border-color': MF_INK[150],
    'border-opacity': 1,
    'high-emphasis-opacity': 1,
    'hover-opacity': 0.06,
    'focus-opacity': 0.1
  }
};
