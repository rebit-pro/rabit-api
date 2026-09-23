/// <reference types="vite/client" />

import 'vue-router';

declare global {
  interface ImportMetaEnv {
    readonly VITE_API_URL: string;
    readonly VITE_APP_VERSION?: string;
    readonly VITE_API_MOCKS_ENABLED?: string;
  }
}

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    description?: string;
    requiresAuth?: boolean;
    demoOnly?: boolean;
    staffRoles?: import('./src/modules/morefoto/types').StaffRole[];
  }
}
