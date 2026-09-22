<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import OrderScreen from '../orders/components/OrderScreen.vue';
import OrderLiveScreen from '../orders/components/OrderLiveScreen.vue';
</script>
<template><OrderScreen v-if="isMockApiEnabled" /><OrderLiveScreen v-else /></template>
