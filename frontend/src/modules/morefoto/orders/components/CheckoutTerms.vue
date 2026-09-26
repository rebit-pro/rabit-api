<script setup lang="ts">
import type { GallerySnapshot } from '../../gallery/types';
import { documentPath, sellerLine } from '../../legal/rules';
import type { LegalDocument, Seller } from '../../legal/types';
import { isMockApiEnabled } from '@/mocks/config';
defineProps<{ gallery: GallerySnapshot; seller: Seller | null; offer: LegalDocument | null }>();
</script>
<template>
  <section class="mf-panel checkout-terms" aria-labelledby="checkout-terms-title">
    <h2 id="checkout-terms-title">Как получить заказ</h2>
    <dl>
      <div>
        <dt>Печатная продукция</dt>
        <dd>
          Передадим в {{ gallery.institutionName }} в течение семи календарных дней после завершения приёма группы. При продлении приёма
          дата передачи изменится.
        </dd>
      </div>
      <div>
        <dt>Электронные файлы</dt>
        <dd>
          После подтверждения оплаты. Срок скачивания — один календарный месяц со дня покупки.<template v-if="isMockApiEnabled">
            В демонстрации выдаются тестовые файлы.</template
          >
        </dd>
      </div>
      <div>
        <dt>Чек и связь</dt>
        <dd>
          Email обязателен для заказа и файлов. MAX доступен для чека, когда этот канал подключён. По вопросам заказа поможет
          {{ gallery.curator ? 'куратор ' + gallery.curator : 'куратор учреждения' }}.
        </dd>
      </div>
    </dl>
    <details v-if="isMockApiEnabled">
      <summary>Продавец и демонстрационные условия</summary>
      <p>
        Это тестовое оформление MoreFoto: цены и предложения служат для проверки интерфейса, платежи и отправка сообщений не выполняются.
        Используйте вымышленные контакты. Заполнение сохраняется только в этом браузере.
      </p>
    </details>
    <div v-else class="checkout-terms__seller" data-testid="checkout-seller">
      <p>Продавец: {{ sellerLine(seller) }}.</p>
      <p>
        Условия покупки, получения и возврата — в
        <a v-if="offer" :href="documentPath(offer.code, offer.version)" target="_blank" rel="noopener">публичной оферте</a
        ><span v-else>публичной оферте</span>. Контакты из формы хранятся в этом браузере не дольше 7 дней и удаляются после оформления
        заказа.
      </p>
    </div>
  </section>
</template>
<style scoped>
.checkout-terms h2 {
  font-size: 20px;
  margin-bottom: 20px;
}
.checkout-terms dl {
  display: grid;
  gap: 18px;
  font-size: 14px;
  line-height: 1.65;
}
.checkout-terms dt {
  font-weight: 600;
  margin-bottom: 4px;
}
.checkout-terms dd {
  margin: 0;
  color: var(--mf-color-text-secondary);
}
.checkout-terms details {
  border-top: 1px solid var(--mf-color-border);
  margin-top: 24px;
  padding-top: 18px;
  font-size: 13px;
  line-height: 1.7;
}
.checkout-terms summary {
  cursor: pointer;
  color: var(--mf-color-link);
}
.checkout-terms__seller {
  border-top: 1px solid var(--mf-color-border);
  margin-top: 24px;
  padding-top: 18px;
  font-size: 13px;
  line-height: 1.7;
  color: var(--mf-color-text-secondary);
}
.checkout-terms__seller p + p {
  margin-top: 8px;
}
.checkout-terms__seller a {
  color: var(--mf-color-link);
}
.checkout-terms details p {
  margin-top: 12px;
  color: var(--mf-color-text-secondary);
}
</style>
