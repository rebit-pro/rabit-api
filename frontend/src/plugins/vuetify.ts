import { createVuetify } from 'vuetify';
import { ru } from 'vuetify/locale';
import { MoreFotoTheme } from '@/theme/MoreFotoTheme';
import { morefotoUiDefaults } from '@/modules/morefoto/ui/defaults';
import '@mdi/font/css/materialdesignicons.css';
import { aliases, mdi } from 'vuetify/iconsets/mdi';
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
      mdi
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
