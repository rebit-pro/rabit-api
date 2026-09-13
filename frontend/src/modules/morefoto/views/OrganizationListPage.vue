<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import StructureScreen from '../structure/components/StructureScreen.vue';
const OrganizationListScreen = defineAsyncComponent(() => import('../organization/components/OrganizationListScreen.vue'));
</script>
<template><OrganizationListScreen v-if="isMockApiEnabled" /><StructureScreen v-else :scope="{ kind: 'institution' }" /></template>
