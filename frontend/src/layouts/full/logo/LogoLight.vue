<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="logo">
    <RouterLink to="/" aria-label="Rebit P2P Trader" class="text-decoration-none d-flex align-center">
      <img src="/favicon.svg" alt="Rebit P2P Trader" width="32" height="32" class="mr-2" />
      <span class="text-h5 font-weight-bold text-white">Rebit P2P Trader</span>
    </RouterLink>
  </div>
</template>
