<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import ManagementScreen from '../management/components/ManagementScreen.vue';
const StaffManagementScreen = defineAsyncComponent(() => import('../staff/components/StaffManagementScreen.vue'));
</script>
<template><ManagementScreen v-if="isMockApiEnabled" mode="users" /><StaffManagementScreen v-else /></template>
