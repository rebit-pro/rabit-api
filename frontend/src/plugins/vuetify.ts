import { createVuetify } from 'vuetify';
import { ru } from 'vuetify/locale';
import { MoreFotoTheme } from '@/theme/MoreFotoTheme';
import { morefotoUiDefaults } from '@/modules/morefoto/ui/defaults';
import { aliases } from 'vuetify/iconsets/mdi-svg';
import { mfSvgIcons } from './iconset';
import { icons } from './mdi-icon';

export default createVuetify({
  locale: {
    locale: 'ru',
    fallback: 'ru',
    messages: { ru }
  },
  icons: {
    defaultSet: 'mdi',
    aliases: {
      ...aliases,
      ...icons
    },
    sets: {
      mdi: mfSvgIcons
    }
  },
  theme: {
    defaultTheme: 'MoreFotoTheme',
    themes: {
      MoreFotoTheme
    }
  },
  defaults: morefotoUiDefaults
});
