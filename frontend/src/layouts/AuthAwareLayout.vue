<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { isStaffRole } from '@/modules/morefoto/types';
import CabinetLayout from '@/modules/morefoto/layouts/CabinetLayout.vue';

// Service pages keep the cabinet shell for a signed-in staff member; guests and accounts without a role see a blank page.
const auth = useAuthStore();
const inCabinet = computed(() => auth.isAuthenticated && isStaffRole(auth.user?.role));
</script>

<template>
  <CabinetLayout v-if="inCabinet" />
  <v-app v-else theme="MoreFotoTheme" class="morefoto-app">
    <main class="mf-access-page"><RouterView /></main>
  </v-app>
</template>
