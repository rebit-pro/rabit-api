<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';
import { isMockApiEnabled } from '@/mocks/config';
import StructureScreen from '../structure/components/StructureScreen.vue';
const route = useRoute();
const OrganizationShootScreen = defineAsyncComponent(() => import('../organization/components/OrganizationShootScreen.vue'));
</script>
<template>
  <OrganizationShootScreen v-if="isMockApiEnabled" :key="$route.fullPath" /><StructureScreen
    v-else
    :key="$route.fullPath"
    :scope="{
      kind: 'group',
      institutionId: String(route.params.institutionId),
      shootId: String(route.params.shootId)
    }"
  />
</template>
