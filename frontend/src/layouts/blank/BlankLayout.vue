<script setup lang="ts">
import CookieNotice from '@/modules/morefoto/legal/components/CookieNotice.vue';
import LegalFooter from '@/modules/morefoto/legal/components/LegalFooter.vue';
</script>
<template>
  <v-app theme="MoreFotoTheme" class="morefoto-app">
    <CookieNotice />
    <RouterView />
    <LegalFooter />
  </v-app>
</template>
