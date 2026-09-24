import { defineComponent, h, type PropType } from 'vue';
import type { IconSet } from 'vuetify';
import { VSvgIcon } from 'vuetify/components/VIcon';
import { MF_ICONS } from './icons';

/**
 * Renders `mdi-*` names as inline SVG from the registry through Vuetify's own SVG icon (keeps role and aria).
 * Vuetify routes only string icons to a set; components and `svg:` aliases never reach it.
 */
const MfSvgIcon = defineComponent({
  name: 'MfSvgIcon',
  inheritAttrs: false,
  props: {
    icon: { type: String, default: '' },
    tag: { type: [String, Object, Function] as PropType<string>, required: true }
  },
  setup(props, { attrs }) {
    return () => h(VSvgIcon, { ...attrs, tag: props.tag, icon: MF_ICONS[props.icon] ?? '' });
  }
});

export const mfSvgIcons: IconSet = { component: MfSvgIcon };
