<script setup lang="ts">
import { useStaffQuestion } from '../composables/useStaffQuestion';
import QuestionThread from './QuestionThread.vue';

const { question, loading, loadError, sending, sendError, reload, send } = useStaffQuestion();
</script>

<template>
  <section class="staff-question">
    <header class="staff-question__heading">
      <p class="mf-eyebrow">СВЯЗЬ С КУРАТОРОМ</p>
      <h1>Вопрос куратору</h1>
      <p class="mf-muted">Кураторы МореФото получают сообщения в мессенджере MAX и отвечают здесь. Переписка видна только вам.</p>
    </header>
    <div class="mf-panel staff-question__panel">
      <QuestionThread
        :messages="question?.messages ?? []"
        own="staff"
        :loading="loading"
        :load-error="loadError"
        :sending="sending"
        :send-error="sendError"
        :submit="send"
        empty-text="Напишите вопрос о съёмке, ссылках или списках детей — куратор ответит в рабочее время."
        @reload="reload()"
      />
    </div>
  </section>
</template>

<style scoped>
.staff-question {
  display: grid;
  gap: var(--mf-space-6);
  max-width: 760px;
}
.staff-question__heading h1 {
  font-size: 28px;
  line-height: 1.3;
  margin: 4px 0 8px;
}
@media (max-width: 600px) {
  .staff-question__heading h1 {
    font-size: 23px;
  }
  .staff-question__panel {
    padding: var(--mf-space-4);
  }
}
</style>
