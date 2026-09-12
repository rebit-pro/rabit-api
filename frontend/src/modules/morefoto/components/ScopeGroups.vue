<script setup lang="ts">
import type { Group } from '../types';
import { galleryLinks } from '../gallery/links';
defineProps<{ groups: Group[] }>();
const stateLabels = {
  preparing: 'Подготовка',
  open: 'Приём открыт',
  closed: 'Приём закрыт'
};
</script>

<template>
  <div v-if="groups.length" class="mf-group-list">
    <article v-for="group in groups" :key="group.id" class="mf-group-row">
      <v-icon :icon="group.kind === 'staff' ? 'mdi-account-star-outline' : 'mdi-image-multiple-outline'" color="primary" size="26" />
      <div class="mf-group-copy">
        <h3>{{ group.name }}</h3>
        <p class="mf-muted">{{ group.shootName }}</p>
      </div>
      <v-btn
        v-if="group.galleryToken ?? galleryLinks[group.id]"
        :to="'/g/' + (group.galleryToken ?? galleryLinks[group.id])"
        variant="outlined"
        color="primary"
        :aria-label="'Галерея: ' + group.name"
        >Открыть галерею</v-btn
      >
      <v-btn :to="{ path: '/cabinet/links', query: { group: group.id } }" variant="text" :aria-label="'Сроки: ' + group.name"
        >Ссылка и сроки</v-btn
      >
      <v-chip size="small" variant="tonal" :color="group.state === 'open' ? 'success' : 'secondary'">{{ stateLabels[group.state] }}</v-chip>
    </article>
  </div>
  <p v-else class="mf-empty">Группы пока не назначены. Обратитесь к организатору.</p>
</template>
