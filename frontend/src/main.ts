import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createHead } from '@unhead/vue/client';
import App from './App.vue';
import { router } from './router';
import { useAuthStore } from '@/stores/auth';
import vuetify from './plugins/vuetify';
import '@/styles/vuetify.scss';
import { initializeMoreFotoMocks } from '@/modules/morefoto/mocks/runtime';
import '@/styles/morefoto.scss';

initializeMoreFotoMocks();

const app = createApp(App);
const head = createHead();
const pinia = createPinia();

app.use(head);
app.use(pinia);

useAuthStore(pinia).restoreSession();

app.use(router);
app.use(vuetify).mount('#app');
