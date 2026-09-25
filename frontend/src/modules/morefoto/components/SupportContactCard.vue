<script setup lang="ts">
import type { SupportContact } from '@/api/auth';
defineProps<{ contact: SupportContact }>();
</script>

<template>
  <dl class="support-contact" data-testid="support-contact">
    <div v-if="contact.name">
      <dt>Организатор</dt>
      <dd>{{ contact.name }}</dd>
    </div>
    <div v-if="contact.email">
      <dt>Email</dt>
      <dd>
        <a :href="'mailto:' + contact.email">{{ contact.email }}</a>
      </dd>
    </div>
    <div v-if="contact.phone">
      <dt>Телефон</dt>
      <dd>
        <a :href="'tel:' + contact.phone.replace(/[^+\d]/g, '')">{{ contact.phone }}</a>
      </dd>
    </div>
  </dl>
</template>

<style scoped>
.support-contact {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--mf-space-4);
  margin: 0;
}
.support-contact dt {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.support-contact dd {
  margin: var(--mf-space-1) 0 0;
  overflow-wrap: anywhere;
}
.support-contact a {
  color: var(--mf-color-link);
}
</style>
