<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import CuratorScreen from '../curator/components/CuratorScreen.vue';
import StaffOrdersLiveScreen from '../curator/components/StaffOrdersLiveScreen.vue';
</script>
<template><CuratorScreen v-if="isMockApiEnabled" /><StaffOrdersLiveScreen v-else /></template>
