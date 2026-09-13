<script setup lang="ts">
import { reviewLinks } from '../fixture';
const roles = [
  { name: 'Организатор', email: 'organizer@morefoto.test', task: 'Подготовка, цены, пользователи, производство и передача.' },
  { name: 'Куратор', email: 'curator@morefoto.test', task: 'Сопровождение «Солнечного», обращения, продления и исключения.' },
  { name: 'Руководитель', email: 'head@morefoto.test', task: 'Сводка «Солнечного» без родительских данных.' },
  { name: 'Воспитатель', email: 'teacher@morefoto.test', task: 'Группа «Звёздочки», передача ссылки и список сотрудников.' }
];
</script>
<template>
  <section class="mf-panel">
    <h2>Роли и начало пути</h2>
    <p class="mf-muted mt-3">
      Входите обычной формой. Общий демонстрационный пароль: <strong>morefoto-demo</strong>. Перед сменой роли выйдите из кабинета. Родитель
      открывает галерею без регистрации.
    </p>
    <div class="review-roles mt-5">
      <article v-for="role in roles" :key="role.email">
        <h3>{{ role.name }}</h3>
        <p class="mt-2">{{ role.email }}</p>
        <p class="mf-muted mt-2">{{ role.task }}</p>
      </article>
    </div>
    <div class="mf-actions mt-5">
      <v-btn to="/cabinet/overview">Открыть кабинет</v-btn><v-btn variant="outlined" :to="reviewLinks.regular">Галерея «Звёздочки»</v-btn
      ><v-btn variant="outlined" :to="reviewLinks.staff">Галерея сотрудников</v-btn
      ><v-btn variant="outlined" :to="reviewLinks.other">Галерея школы</v-btn>
    </div>
  </section>
  <section class="mf-panel">
    <h2>Последовательность проверки</h2>
    <ol class="review-path mt-4">
      <li>
        Организатор проверяет съёмку, фотографии, цены и назначения. Воспитатель передаёт ссылку группе и оформляет список сотрудников;
        куратор переносит полный набор.
      </li>
      <li>Родитель выбирает продукцию, оформляет и оплачивает заказ. Куратор видит этот заказ, руководитель — его вклад в сводку.</li>
      <li>Проверьте ожидание оплаты, обращение, продление, исправление и возврат. Время сценария меняется в блоке выше.</li>
      <li>
        После закрытия группы организатор формирует и запускает печатное задание, сверяет пакеты и передаёт их в учреждение. Родитель видит
        результат в своём заказе.
      </li>
      <li>Повторите действия после ошибки, перезагрузки и смены роли. Для нового независимого прохода восстановите исходный набор.</li>
    </ol>
  </section>
</template>
<style scoped>
.review-roles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
}
.review-path {
  display: grid;
  gap: 16px;
  padding-left: 22px;
  line-height: 1.7;
}
@media (max-width: 700px) {
  .review-roles {
    grid-template-columns: 1fr;
  }
}
</style>
