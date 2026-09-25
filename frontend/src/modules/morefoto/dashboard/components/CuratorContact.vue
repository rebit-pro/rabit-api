<script setup lang="ts">
defineProps<{
  readonly contact?: { name: string; email: string };
  readonly institutionName: string;
}>();
</script>
<template>
  <aside class="curator-contact">
    <h3>Куратор · {{ institutionName }}</h3>
    <p v-if="contact">
      {{ contact.name }} ·
      <a :href="'mailto:' + contact.email">{{ contact.email }}</a>
    </p>
    <p v-else class="mf-muted">Куратор пока не назначен. Обратитесь к организатору.</p>
    <p class="mf-muted">Обратитесь по вопросам передачи ссылки, сроков и списков сотрудников.</p>
  </aside>
</template>
<style scoped>
.curator-contact {
  margin-top: 24px;
  padding: 20px 0;
  border-top: 1px solid var(--mf-color-border);
  overflow-wrap: anywhere;
}
.curator-contact p {
  margin-top: 8px;
}
</style>
