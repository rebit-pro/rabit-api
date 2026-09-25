<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import ScopeOverview from '../components/ScopeOverview.vue';
import LiveOverview from '../overview/LiveOverview.vue';
</script>
<template><ScopeOverview v-if="isMockApiEnabled" /><LiveOverview v-else /></template>
