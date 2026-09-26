<script setup lang="ts">
import { shallowRef } from 'vue';
import AuthLogin from './authForms/AuthLogin.vue';
import AccessShell from './AccessShell.vue';
import FeedbackDialog from '@/modules/morefoto/support/components/FeedbackDialog.vue';

const feedbackOpen = shallowRef(false);
</script>

<template>
  <AccessShell title="Вход в кабинет" description="Введите email и пароль от личного кабинета">
    <template #header>
      <v-btn variant="outlined" prepend-icon="mdi-message-text-outline" data-testid="login-feedback-open" @click="feedbackOpen = true">
        Написать нам
      </v-btn>
    </template>
    <AuthLogin />
    <template #aside>
      <section class="login-about" aria-labelledby="login-about-title" data-testid="login-about">
        <h2 id="login-about-title" class="login-about__title">Кабинет «Море фото»</h2>
        <p class="login-about__lead">
          Рабочее место для фотосъёмок в детских садах: организаторы, кураторы, заведующие и воспитатели готовят съёмки, передают ссылки на
          галереи и следят за заказами.
        </p>
        <dl class="login-about__facts">
          <div>
            <dt>Родителям вход не нужен</dt>
            <dd>Галерея с фотографиями открывается по ссылке, которую присылает воспитатель или организатор.</dd>
          </div>
          <div>
            <dt>Как получить доступ</dt>
            <dd>Доступ выдаёт организатор: придёт письмо-приглашение, по ссылке из него задаётся пароль.</dd>
          </div>
          <div>
            <dt>Не получается войти?</dt>
            <dd>
              Восстановите пароль по ссылке «Забыли пароль?» или
              <button type="button" class="login-about__link" @click="feedbackOpen = true">напишите нам</button>.
            </dd>
          </div>
        </dl>
      </section>
    </template>
  </AccessShell>
  <FeedbackDialog v-model="feedbackOpen" />
</template>

<style scoped>
.login-about {
  display: grid;
  gap: var(--mf-space-4);
}
.login-about__title {
  color: var(--mf-color-text);
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-xl);
  font-weight: var(--mf-weight-semibold);
  line-height: var(--mf-leading-tight);
}
.login-about__lead {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-base);
  line-height: var(--mf-leading-normal);
}
.login-about__facts {
  display: grid;
  gap: var(--mf-space-4);
  margin: 0;
  padding-top: var(--mf-space-4);
  border-top: 1px solid var(--mf-color-divider);
}
.login-about__facts dt {
  color: var(--mf-color-text);
  font-weight: var(--mf-weight-semibold);
}
.login-about__facts dd {
  margin: var(--mf-space-1) 0 0;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-normal);
}
.login-about__link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--mf-color-link);
  font: inherit;
  font-weight: var(--mf-weight-medium);
  text-decoration: underline;
  cursor: pointer;
}
</style>
