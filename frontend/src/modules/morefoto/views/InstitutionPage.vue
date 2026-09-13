<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import InstitutionDetails from '../components/InstitutionDetails.vue';
import OrganizationInstitutionScreen from '../organization/components/OrganizationInstitutionScreen.vue';
const auth = useAuthStore();
</script>
<template>
  <OrganizationInstitutionScreen v-if="auth.user?.role === 'organizer'" :key="$route.fullPath" />
  <InstitutionDetails v-else />
</template>
