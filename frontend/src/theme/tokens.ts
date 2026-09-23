/**
 * Единственный источник дизайн-токенов MoreFoto («Кадр и мазок v2»).
 * Hex-значения живут только здесь; `npm run tokens:build` генерирует из них `src/styles/_tokens.scss`.
 */

export const MF_WHITE = '#FFFFFF';

/** «Синий моря»: sea-600 — основной цвет действий, sea-200 — волна логотипа и иконки v1. */
export const MF_SEA = {
  50: '#EEF5FA',
  100: '#DCEBF4',
  200: '#C3DFF3',
  300: '#8FBFDD',
  400: '#5A9AC2',
  500: '#3A7FA8',
  600: '#24658A',
  700: '#1C5372',
  800: '#16405A',
  900: '#0F2C3E'
} as const;

/** Графит с холодным подтоном для текста, границ и фонов. */
export const MF_INK = {
  900: '#1E1E1E',
  800: '#2E3338',
  700: '#444B52',
  600: '#5E6872',
  500: '#66737F',
  400: '#738596',
  300: '#9AA7B2',
  200: '#C4CED6',
  150: '#DCE4EA',
  100: '#EDF1F4',
  50: '#F5F8FA'
} as const;

/** Пастель только для данных: аватарки, категории инфографики, теги. Индекс — номер категории. */
export const MF_PASTELS = [
  { name: 'sand', bg: '#F3E7D3', mid: '#D9B874', fg: '#6B4E16' },
  { name: 'coral', bg: '#F6D9D3', mid: '#E39A8A', fg: '#8A3A2C' },
  { name: 'shell', bg: '#F2DCE6', mid: '#DDA0BC', fg: '#7E3557' },
  { name: 'lavender', bg: '#E3DDF3', mid: '#B3A5DE', fg: '#4E3E8C' },
  { name: 'sky', bg: '#C3DFF3', mid: '#8FBFDD', fg: '#1C5372' },
  { name: 'mint', bg: '#D3EEE4', mid: '#93D1B4', fg: '#1F5F49' },
  { name: 'olive', bg: '#E4EDD0', mid: '#BFCB86', fg: '#4B5F1B' },
  { name: 'pebble', bg: '#E3E7E9', mid: '#B7C1C9', fg: '#3E4A54' }
] as const;

/** Статусные тона: fg — прежние цвета темы, pending — пурпур, чтобы не сливаться с warning. */
export const MF_TONES = {
  success: { fg: '#246C4F', bg: '#DFF2EA', border: '#A9DCC7' },
  warning: { fg: '#8A5A10', bg: '#FBF0DA', border: '#E8CF9A' },
  danger: { fg: '#B53A3A', bg: '#FBE4E4', border: '#EDB4B4' },
  info: { fg: '#1C5372', bg: '#E3EFF7', border: '#B9D6E8' },
  neutral: { fg: '#5E6872', bg: '#EDF1F4', border: '#DCE4EA' },
  pending: { fg: '#5A4796', bg: '#ECE8F7', border: '#CBC2EA' }
} as const;

export type MfPrimitive = `sea-${keyof typeof MF_SEA}` | `ink-${keyof typeof MF_INK}` | 'white';

/** Семантические цвета: компоненты ссылаются только на них, никогда на примитивы. */
export const MF_SEMANTIC: Readonly<Record<string, MfPrimitive>> = {
  bg: 'ink-50',
  surface: 'white',
  'surface-2': 'ink-100',
  'surface-inverse': 'ink-900',
  text: 'ink-900',
  'text-secondary': 'ink-600',
  'text-tertiary': 'ink-500',
  'text-disabled': 'ink-300',
  'text-inverse': 'white',
  link: 'sea-600',
  primary: 'sea-600',
  'primary-hover': 'sea-700',
  'primary-active': 'sea-800',
  'primary-soft': 'sea-50',
  'on-primary': 'white',
  secondary: 'ink-900',
  'secondary-hover': 'ink-800',
  accent: 'sea-200',
  border: 'ink-150',
  'border-strong': 'ink-400',
  'border-hover': 'ink-200',
  divider: 'ink-100',
  selected: 'sea-50',
  'selected-strong': 'sea-100',
  hover: 'ink-50',
  focus: 'sea-600',
  skeleton: 'ink-100',
  'skeleton-hi': 'ink-50',
  'nav-bg': 'white',
  'nav-fg': 'ink-700',
  'nav-hover': 'ink-50',
  'nav-active-bg': 'sea-50',
  'nav-active-fg': 'sea-700',
  'nav-divider': 'ink-100',
  'chart-accent': 'sea-600',
  'chart-accent-2': 'sea-300',
  'chart-track': 'ink-100',
  'chart-grid': 'ink-200',
  'bubble-in': 'ink-100',
  'bubble-out': 'sea-50'
};

/** Холодный scrim диалогов на базе sea-900. */
export const MF_OVERLAY = 'rgba(15, 44, 62, 0.48)';

/** Радиусы на 4px-сетке: одна поверхность — один радиус, вложенная — на 4px меньше. */
export const MF_RADIUS = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24, full: 9999 } as const;

/** Тени дополняют 1px-границу, а не заменяют её; подтон sea-900. */
export const MF_SHADOW = {
  xs: '0 1px 2px rgba(15, 44, 62, 0.06), 0 0 0 1px rgba(15, 44, 62, 0.04)',
  sm: '0 2px 6px rgba(15, 44, 62, 0.08), 0 1px 2px rgba(15, 44, 62, 0.04)',
  md: '0 8px 24px rgba(15, 44, 62, 0.12), 0 2px 6px rgba(15, 44, 62, 0.06)',
  lg: '0 20px 48px rgba(15, 44, 62, 0.18), 0 4px 12px rgba(15, 44, 62, 0.08)',
  'ring-surface': '0 0 0 2px var(--mf-color-surface)'
} as const;

export const MF_FONT = {
  sans: "'Golos Text', 'Golos Text Fallback', 'Segoe UI', Arial, sans-serif",
  display: "'Manrope', 'Manrope Fallback', 'Golos Text', 'Segoe UI', Arial, sans-serif"
} as const;

export const MF_TEXT = { xs: 12, sm: 13, md: 14, base: 16, lg: 18, xl: 21, '2xl': 28, '3xl': 32, '4xl': 40 } as const;
export const MF_LEADING = { tight: 1.25, snug: 1.35, normal: 1.5, relaxed: 1.6 } as const;
export const MF_TRACKING = { tight: '-0.01em', display: '-0.02em', caps: '0.08em' } as const;
export const MF_WEIGHT = { regular: 400, medium: 500, semibold: 600, bold: 700 } as const;

/** Дополнительный spacing; шаги 1–4, 6, 8 и размеры контролов остаются в `_morefoto-ui.scss` (UI01–UI04). */
export const MF_SPACE = { 5: 20, 10: 40, 12: 48, 16: 64 } as const;

/** Раскладка: значение до md (960px) и начиная с md. */
export const MF_LAYOUT = {
  gutter: { base: 16, md: 40 },
  'panel-pad': { base: 16, md: 24 },
  'content-max': 1320,
  'section-gap': 32
} as const;
export const MF_BREAKPOINT_MD = 960;

export const MF_MOTION = {
  duration: { fast: 120, base: 200, slow: 320, shimmer: 1600 },
  ease: { standard: 'cubic-bezier(0.2, 0, 0, 1)', exit: 'cubic-bezier(0.4, 0, 1, 1)' }
} as const;

export const MF_Z = { base: 0, raised: 1, sticky: 10, fab: 20, app: 1000, overlay: 2000, toast: 2100, 'skip-link': 2200 } as const;

export function primitiveHex(name: MfPrimitive): string {
  if ('white' === name) return MF_WHITE;
  const [scale, step] = name.split('-') as ['sea' | 'ink', string];
  const palette: Record<string, string> = 'sea' === scale ? MF_SEA : MF_INK;
  const hex = palette[step];
  if (undefined === hex) throw new Error('Unknown primitive ' + name);
  return hex;
}
