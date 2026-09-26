import { MF_SEA } from '@/theme/tokens';
/** Shared geometry and native Vuetify behavior; business rules belong to feature modules. */
const field = {
  variant: 'outlined',
  density: 'comfortable',
  color: 'primary',
  hideDetails: 'auto'
};
const menuProps = { contentClass: 'morefoto-app mf-ui-overlay' };
export const morefotoUiDefaults = {
  VBtn: { color: 'primary', variant: 'flat' },
  VCard: { rounded: 'md' },
  VTextField: { ...field },
  VSelect: { ...field, menuProps, noDataText: 'Нет доступных вариантов' },
  VAutocomplete: { ...field, menuProps, noDataText: 'Ничего не найдено' },
  VTextarea: { ...field, rows: 3, autoGrow: true },
  VFileInput: { ...field },
  VCheckbox: { density: 'comfortable', color: 'primary', hideDetails: 'auto' },
  VRadioGroup: {
    density: 'comfortable',
    color: 'primary',
    hideDetails: 'auto'
  },
  VSwitch: {
    density: 'comfortable',
    color: 'primary',
    hideDetails: 'auto',
    inset: true
  },
  VTooltip: { location: 'top' },
  // The current page reads as a filled primary button; without it the number disappears on a dark square (#147).
  VPagination: { activeColor: 'primary', density: 'comfortable' },
  // Cold scrim on the base of sea-900 (design plan 7.3).
  VDialog: { scrim: MF_SEA[900], opacity: 0.48 }
};
