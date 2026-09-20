<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import GroupConditionsScreen from '../conditions/components/GroupConditionsScreen.vue';
const ManagementScreen = defineAsyncComponent(() => import('../management/components/ManagementScreen.vue'));
</script>
<template><ManagementScreen v-if="isMockApiEnabled" mode="conditions" /><GroupConditionsScreen v-else /></template>
