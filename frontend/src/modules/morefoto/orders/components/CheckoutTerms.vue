<script setup lang="ts">
import type { GallerySnapshot } from '../../gallery/types';
import { documentPath, sellerLine } from '../../legal/rules';
import type { LegalDocument, Seller } from '../../legal/types';
import { isMockApiEnabled } from '@/mocks/config';
import { formatMoment } from '../formatters';
defineProps<{ gallery: GallerySnapshot; seller: Seller | null; offer: LegalDocument | null }>();
</script>
<template>
  <!-- UI-DEC-04: the terms stay next to the order but folded; the seller is also named in the footer. -->
  <details class="mf-panel checkout-terms" data-testid="checkout-terms">
    <summary>
      <h2 id="checkout-terms-title">Как получить заказ</h2>
      <span>Сроки печати и файлов, чек, продавец</span>
    </summary>
    <dl>
      <div>
        <dt>Печатная продукция</dt>
        <dd>
          Заказ и оплата — до окончания приёма печати<template v-if="gallery.closesAt"> ({{ formatMoment(gallery.closesAt) }} мск)</template
          >. Передадим в {{ gallery.institutionName }} в течение семи календарных дней после окончания приёма печати. При продлении приёма
          дата передачи изменится.
        </dd>
      </div>
      <div>
        <dt>Электронные файлы</dt>
        <dd>
          Покупка — до окончания продажи электронных фото<template v-if="gallery.digitalSalesUntil">
            ({{ formatMoment(gallery.digitalSalesUntil) }} мск)</template
          >. Файлы — после подтверждения оплаты, срок скачивания — один календарный месяц со дня покупки. К каждому оплаченному печатному
          кадру — его электронная копия без доплаты.<template v-if="isMockApiEnabled"> В демонстрации выдаются тестовые файлы.</template>
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
  </details>
</template>
<style scoped>
.checkout-terms {
  padding: var(--mf-space-4) var(--mf-space-5);
}
.checkout-terms > summary {
  display: grid;
  gap: 2px;
  cursor: pointer;
  list-style: none;
}
.checkout-terms > summary::-webkit-details-marker {
  display: none;
}
.checkout-terms > summary h2 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: var(--mf-text-base);
}
.checkout-terms > summary h2::after {
  content: '+';
  color: var(--mf-color-link);
  font-weight: 400;
}
.checkout-terms[open] > summary h2::after {
  content: '−';
}
.checkout-terms > summary span {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.checkout-terms[open] > summary {
  margin-bottom: var(--mf-space-4);
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
.checkout-terms details summary {
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
