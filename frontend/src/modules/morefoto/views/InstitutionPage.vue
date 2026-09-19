<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import InstitutionScreen from '../structure/components/InstitutionScreen.vue';
const InstitutionDetails = defineAsyncComponent(() => import('../components/InstitutionDetails.vue'));
const OrganizationInstitutionScreen = defineAsyncComponent(() => import('../organization/components/OrganizationInstitutionScreen.vue'));
const auth = useAuthStore(),
  route = useRoute();
</script>
<template>
  <InstitutionScreen v-if="!isMockApiEnabled" :key="$route.fullPath" :institution-id="String(route.params.institutionId)" />
  <OrganizationInstitutionScreen v-else-if="auth.user?.role === 'organizer'" :key="$route.fullPath" />
  <InstitutionDetails v-else />
</template>
