<script setup lang="ts">
import { toRef } from 'vue';
import { formatMoment } from '../formatters';
import { useLiveFiles } from '../composables/useLiveFiles';
import { fileSize, filesCount } from '../live/files-rules';
const props = defineProps<{ orderKey: string }>();
const { files, loading, busy, preparing, error, load, download } = useLiveFiles(toRef(props, 'orderKey'));
</script>
<template>
  <section id="order-files" class="mf-panel downloads" aria-labelledby="downloads-title" data-testid="order-files">
    <p class="mf-eyebrow">ЭЛЕКТРОННЫЕ ФОТОГРАФИИ</p>
    <h2 id="downloads-title">Ваши файлы</h2>
    <v-skeleton-loader v-if="loading && !files" type="paragraph" />
    <template v-else-if="files">
      <p v-if="files.state === 'unpaid'" class="mf-muted mt-3" data-testid="files-state-unpaid">
        Оригиналы без водяного знака откроются здесь сразу после подтверждённой оплаты.
      </p>
      <p v-else-if="files.state === 'review'" class="mf-muted mt-3" data-testid="files-state-review">
        Оплата пришла после закрытия приёма заказов. Организатор согласует выдачу файлов. Повторно платить не нужно.
      </p>
      <p v-else-if="files.state === 'empty'" class="mf-muted mt-3" data-testid="files-state-empty">
        В этом заказе только печатная продукция: электронных файлов и подарочного комплекта нет.
      </p>
      <template v-else>
        <p class="downloads__deadline mt-3" data-testid="files-deadline">
          {{ files.state === 'expired' ? 'Срок скачивания истёк' : 'Доступны до' }}
          {{ files.expiresAt ? formatMoment(files.expiresAt) : '' }} мск
        </p>
        <p class="mf-muted mt-2 downloads__note">
          Один календарный месяц после оплаты. Закрытие группы не сокращает срок, повторное скачивание в этот период бесплатно.
        </p>
        <v-alert v-if="files.state === 'expired'" type="warning" variant="tonal" class="mt-4" data-testid="files-state-expired"
          >Срок скачивания закончился. Если файлы нужны снова, напишите организатору — продление не гарантируется.</v-alert
        >
        <template v-else>
          <div class="downloads__action">
            <v-btn
              color="primary"
              prepend-icon="mdi-download"
              :loading="busy === 'archive'"
              :disabled="!!busy"
              data-testid="files-archive"
              @click="download()"
              >Скачать всё архивом</v-btn
            >
            <span class="mf-muted downloads__note" data-testid="files-summary"
              >{{ filesCount(files.items.length) }} · {{ fileSize(files.totalBytes) }} · ZIP</span
            >
          </div>
          <p v-if="preparing" class="downloads__note mb-3" role="status" data-testid="files-preparing">
            Готовим архив. Для большого комплекта это может занять пару минут — страницу можно не закрывать.
          </p>
          <ul class="downloads__list">
            <li v-for="file in files.items" :key="file.photoId" class="downloads__file" data-testid="files-item">
              <div class="downloads__name">
                <strong>{{ file.code }}</strong
                ><span class="mf-muted downloads__note">{{ file.filename }} · {{ fileSize(file.bytes) }}</span>
              </div>
              <v-btn
                variant="outlined"
                color="primary"
                :aria-label="'Скачать кадр ' + file.code"
                :loading="busy === file.photoId"
                :disabled="!!busy"
                data-testid="files-download"
                @click="download(file.photoId)"
                >Скачать</v-btn
              >
            </li>
          </ul>
        </template>
      </template>
    </template>
    <v-alert v-if="error" type="error" variant="tonal" class="mt-4" data-testid="files-error">
      {{ error }}
      <template #append><v-btn v-if="!files" variant="text" @click="load">Повторить</v-btn></template>
    </v-alert>
  </section>
</template>
<style scoped>
.downloads {
  min-width: 0;
}
.downloads__deadline {
  font-weight: 600;
  line-height: 1.6;
}
.downloads__note {
  font-size: var(--mf-text-small);
}
.downloads__action {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-3);
  margin-block: var(--mf-space-6) var(--mf-space-3);
}
.downloads__list {
  list-style: none;
  padding: 0;
  display: grid;
  gap: var(--mf-space-3);
}
.downloads__file {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  padding-top: var(--mf-space-3);
}
.downloads__name {
  display: grid;
  gap: var(--mf-space-1);
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}
@media (max-width: 479px) {
  .downloads__file {
    flex-wrap: wrap;
  }
  .downloads__file .v-btn {
    margin-left: auto;
  }
}
</style>
