<script setup lang="ts">
import { computed, reactive, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { isAxiosError } from 'axios';
import api from '@/api/http';
import MfLogo from '@/components/brand/MfLogo.vue';

interface ShootGallery {
  shootName: string;
  institutionName: string;
  state: 'preparing' | 'open';
  groups: { name: string; groupPath: string; coverUrl: string | null }[];
}
const route = useRoute();
const key = computed(() => String(route.params.shootKey));
const round = shallowRef(0);
const gallery = shallowRef<ShootGallery | null>(null);
const error = shallowRef('');
const missingCovers = reactive(new Set<string>());
watch(
  [key, round],
  async ([value], _, onCleanup) => {
    const controller = new AbortController();
    onCleanup(() => controller.abort());
    gallery.value = null;
    error.value = '';
    missingCovers.clear();
    if (!/^[a-f0-9]{32}$/.test(value)) {
      error.value = 'Ссылка недействительна';
      return;
    }
    try {
      const response = await api.get<ShootGallery>('/api/v1/public/galleries/shoots/' + value, { signal: controller.signal });
      if (!controller.signal.aborted) gallery.value = response.data;
    } catch (cause) {
      if (!controller.signal.aborted)
        error.value = isAxiosError(cause) && cause.response?.status === 404 ? 'Ссылка недействительна' : 'Не удалось загрузить группы';
    }
  },
  { immediate: true }
);
</script>
<template>
  <main class="shoot-gallery">
    <MfLogo :size="24" mono />
    <section v-if="error" role="alert">
      <h1>{{ error }}</h1>
      <v-btn v-if="error !== 'Ссылка недействительна'" variant="outlined" @click="round++">Повторить</v-btn>
    </section>
    <p v-else-if="!gallery" role="status">Загружаем галерею…</p>
    <template v-else>
      <header>
        <h1>{{ gallery.shootName }}</h1>
        <p class="mf-muted">{{ gallery.institutionName }}</p>
      </header>
      <section v-if="gallery.state === 'preparing'">
        <h2>Фотографии ещё готовятся</h2>
        <p>Откройте эту ссылку позже.</p>
      </section>
      <section v-else aria-labelledby="shoot-groups">
        <h2 id="shoot-groups">Выберите свою группу</h2>
        <div class="shoot-groups">
          <RouterLink
            v-for="group in gallery.groups"
            :key="group.groupPath"
            class="shoot-tile"
            :to="{ path: group.groupPath, state: { shootGallery: route.path } }"
          >
            <img
              v-if="group.coverUrl && !missingCovers.has(group.groupPath)"
              :src="api.getUri({ url: group.coverUrl })"
              alt=""
              loading="lazy"
              referrerpolicy="no-referrer"
              @error="missingCovers.add(group.groupPath)"
            />
            <span v-else class="shoot-placeholder" aria-hidden="true"><v-icon icon="mdi-image-multiple-outline" size="40" /></span>
            <strong>{{ group.name }}</strong>
          </RouterLink>
        </div>
      </section>
    </template>
  </main>
</template>
<style scoped>
.shoot-gallery {
  width: min(100%, 1120px);
  margin-inline: auto;
  padding: clamp(16px, 4vw, 48px);
}
.shoot-gallery header,
.shoot-gallery section {
  margin-block-start: 24px;
}
.shoot-groups {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 210px), 1fr));
  gap: 16px;
  margin-block-start: 16px;
}
.shoot-tile {
  overflow: hidden;
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  color: inherit;
  text-decoration: none;
}
.shoot-tile:hover,
.shoot-tile:focus-visible {
  outline: 2px solid var(--mf-color-primary);
}
.shoot-tile img,
.shoot-placeholder {
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  background: var(--mf-color-border);
}
.shoot-tile strong {
  display: block;
  padding: 12px 16px;
  overflow-wrap: anywhere;
}
</style>
