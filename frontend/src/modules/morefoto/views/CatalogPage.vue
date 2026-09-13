<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import CatalogScreen from '../catalog/components/CatalogScreen.vue';
const ManagementScreen = defineAsyncComponent(() => import('../management/components/ManagementScreen.vue'));
</script>
<template><ManagementScreen v-if="isMockApiEnabled" mode="catalog" /><CatalogScreen v-else /></template>
