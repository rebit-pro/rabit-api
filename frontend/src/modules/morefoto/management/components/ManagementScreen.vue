<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { getCatalog } from '../../commerce/mocks/catalog';
import { money } from '../../commerce/money';
import { readDemo } from '../../mocks/storage';
import { roleLabels } from '../../types';
import type { UiTableColumn } from '../../ui/table-types';
import type { GroupConditions } from '../types';
import { editProduct, editConditions, editUser } from '../commands';
import { useManagement } from '../useManagement';
import { useManagementEditor } from '../useManagementEditor';
import OrganizationTable from '../../organization/components/OrganizationTable.vue';
import AdminDialog from './AdminDialog.vue';
import ProductFields from './ProductFields.vue';
import ConditionsFields from './ConditionsFields.vue';
import UserFields from './UserFields.vue';
const props = defineProps<{ mode: 'catalog' | 'conditions' | 'users' }>();
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore();
const { data, loading, error, reload, catalog, version } = useManagement();
const notice = shallowRef('');
const {
  command,
  busy,
  error: saveError,
  errors,
  restored,
  open,
  close,
  reset,
  save
} = useManagementEditor(() => {
  notice.value = 'Изменения сохранены.';
  void reload();
});
const shoot = computed(() =>
  data.value?.shoots.find((x) => x.id === route.params.shootId && x.institutionId === route.params.institutionId)
);
const institution = computed(() => data.value?.institutions.find((x) => x.id === shoot.value?.institutionId));
const groups = computed(() => data.value?.groups.filter((x) => x.shootId === shoot.value?.id) ?? []);
const groupId = computed({
  get: () => (typeof route.query.group === 'string' ? route.query.group : (groups.value[0]?.id ?? null)),
  set: (value: string | null) => {
    void router.replace({ query: { ...route.query, group: value ?? undefined } });
  }
});
const group = computed(() => groups.value.find((x) => x.id === groupId.value));
const effective = computed(() => {
  void version.value;
  return props.mode === 'conditions' && group.value ? getCatalog(group.value.id) : catalog.value;
});
const inherit = computed(() => {
  void version.value;
  return !group.value || readDemo<Record<string, GroupConditions>>('group-conditions:v1', {})[group.value.id]?.inherit !== false;
});
watch(
  () => route.fullPath,
  () => {
    close();
    notice.value = '';
  }
);
const title = computed(() => (props.mode === 'users' ? 'Сотрудники' : props.mode === 'conditions' ? 'Условия группы' : 'Каталог и цены'));
const dialogTitle = computed(() =>
  command.value?.kind === 'product'
    ? 'Продукция'
    : command.value?.kind === 'user'
      ? 'Пользователь'
      : props.mode === 'conditions'
        ? 'Условия · ' + group.value?.name
        : 'Общий прайс и предложения'
);
const productColumns: UiTableColumn[] = [
  { key: 'name', label: 'Продукция', primary: true, sortable: true },
  { key: 'format', label: 'Формат / единица', mobile: true },
  { key: 'price', label: 'Цена', type: 'money', mobile: true, sortable: true },
  { key: 'discount', label: 'Сотрудникам', mobile: true },
  { key: 'available', label: 'Доступность', mobile: true }
];
const userColumns: UiTableColumn[] = [
  { key: 'name', label: 'Имя', primary: true, sortable: true },
  { key: 'email', label: 'Email', mobile: true },
  { key: 'role', label: 'Роль', mobile: true, sortable: true },
  { key: 'scope', label: 'Назначения', mobile: true },
  { key: 'active', label: 'Доступ', mobile: true }
];
const rows = computed(() =>
  props.mode === 'users'
    ? (data.value?.users.map((x) => ({
        id: String(x.id),
        name: x.name,
        email: x.email,
        role: roleLabels[x.role],
        scope:
          x.role === 'organizer'
            ? 'Все учреждения'
            : x.role === 'teacher'
              ? data
                  .value!.groups.filter((g) => g.teacherId === x.id)
                  .map((g) => g.name)
                  .join(', ') || 'Нет назначений'
              : data
                  .value!.institutions.filter((i) => (x.role === 'curator' ? i.curatorId === x.id : i.headId === x.id))
                  .map((i) => i.name)
                  .join(', ') || 'Нет назначений',
        active: x.active ? 'Активен' : 'Отключён'
      })) ?? [])
    : effective.value.products.map((p) => ({
        id: p.id,
        name: p.name,
        format:
          (p.format ?? (p.kind === 'physical' ? (p.name.match(/\d+\s*×\s*\d+/)?.[0] ?? 'По описанию') : 'Электронный файл')) +
          ' / ' +
          (p.unit ?? (p.kind === 'bundle' ? 'комплект' : p.kind === 'digital' ? 'файл' : p.printCount > 1 ? 'комплект' : 'шт.')),
        price: p.price,
        discount: p.staffDiscount ? '50%' : 'Без скидки',
        available: p.active ? 'В продаже' : 'Отключено'
      }))
);
function product(id?: string) {
  open(
    () =>
      editProduct(
        catalog.value,
        catalog.value.products.find((p) => p.id === id)
      ),
    'product:' + (id ?? 'new')
  );
}
function user(id: number | null) {
  if (data.value) open(() => editUser(data.value!, id), 'user:' + (id ?? 'new'));
}
function conditions() {
  if (data.value && (props.mode === 'catalog' || group.value))
    open(
      () =>
        editConditions(catalog.value, effective.value, data.value!, props.mode === 'conditions' ? group.value!.id : null, inherit.value),
      'conditions:' + (props.mode === 'conditions' ? group.value!.id : 'global')
    );
}
function edit(id: string) {
  if (props.mode === 'users') user(Number(id));
  else if (props.mode === 'conditions') conditions();
  else product(id);
}
</script>
<template>
  <RouterLink
    v-if="mode === 'conditions'"
    :to="shoot ? '/cabinet/institutions/' + shoot.institutionId + '/shoots/' + shoot.id : '/cabinet/institutions'"
    class="mf-back"
    >← {{ shoot?.name ?? 'Учреждения' }}</RouterLink
  >
  <header class="mf-page-heading">
    <p class="mf-eyebrow">{{ mode === 'users' ? 'КОМАНДА' : 'АССОРТИМЕНТ И ПРЕДЛОЖЕНИЯ' }}</p>
    <h1>{{ title }}</h1>
    <p class="mf-muted">
      {{
        mode === 'users'
          ? 'Роли и назначения сотрудников'
          : mode === 'conditions'
            ? [institution?.name, shoot?.name].filter(Boolean).join(' → ')
            : 'Общие условия покупки для групп без собственного прайса'
      }}
    </p>
    <div v-if="data" class="mf-actions mt-5">
      <v-btn v-if="mode === 'catalog'" prepend-icon="mdi-plus" @click="product()">Новая продукция</v-btn>
      <v-btn v-if="mode === 'users'" prepend-icon="mdi-plus" @click="user(null)">Новый пользователь</v-btn>
      <v-btn
        v-if="mode !== 'users'"
        :disabled="mode === 'conditions' && !group"
        :variant="mode === 'catalog' ? 'outlined' : 'flat'"
        @click="conditions"
        >{{ mode === 'conditions' ? 'Изменить условия группы' : 'Прайс и предложения' }}</v-btn
      >
    </div>
  </header>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-5"
    >{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert
  >
  <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка управления" class="mb-5" />
  <template v-if="data">
    <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
    <v-alert v-if="mode !== 'users'" type="info" variant="tonal" class="mb-6" data-testid="management-demo-notice"
      >Демонстрационные условия — требуют согласования. Цены, состав предложения и подарок сотрудникам не утверждены. Изменения действуют
      для новых покупок; неоплаченный заказ потребует проверки, оплаченная цена сохранится.</v-alert
    >
    <v-alert v-else type="info" variant="tonal" class="mb-6"
      >Назначения определяют доступ к учреждениям, съёмкам и группам в демонстрационном кабинете.</v-alert
    >
    <template v-if="mode === 'conditions'">
      <v-alert v-if="!shoot" type="warning" variant="tonal">Съёмка в этом учреждении не найдена.</v-alert>
      <template v-else>
        <v-select
          v-model="groupId"
          :items="groups"
          item-title="name"
          item-value="id"
          label="Группа для настройки условий"
          data-testid="conditions-group"
          class="management-group"
        />
        <p v-if="!groups.length" class="mf-muted">Сначала создайте группу в съёмке.</p>
        <p v-else-if="!group" role="alert">Группа этой съёмки не найдена. Выберите группу из списка.</p>
        <p v-else class="mf-muted mb-5">
          {{ inherit ? 'Наследует общий прайс и предложения.' : 'Собственный прайс и предложения этой группы.' }}
        </p>
      </template>
    </template>
    <template v-if="mode !== 'conditions' || group">
      <div v-if="mode !== 'users'" class="management-summary mb-6">
        <div>
          <span class="mf-muted">В продаже</span><strong>{{ effective.products.filter((p) => p.active).length }} позиций</strong>
        </div>
        <div>
          <span class="mf-muted">Подарочный комплект</span
          ><strong>{{ effective.giftThreshold > 0 ? 'От ' + money(effective.giftThreshold) : 'Не предлагается' }}</strong>
        </div>
        <div>
          <span class="mf-muted">Подарок сотрудникам</span
          ><strong>{{ effective.giftThreshold > 0 && effective.giftForStaff ? 'Включён в демонстрации' : 'Не предлагается' }}</strong>
        </div>
      </div>
      <OrganizationTable
        :key="mode + ':' + groupId"
        :title="mode === 'users' ? 'Сотрудники' : 'Ассортимент'"
        :rows="rows"
        :columns="mode === 'users' ? userColumns : productColumns"
        :empty="mode === 'users' ? 'Пользователей пока нет' : 'Ассортимент пуст'"
        :action="mode === 'conditions' ? 'Условия' : 'Редактировать'"
        @open="edit"
      />
    </template>
  </template>
  <AdminDialog
    :open="!!command"
    :title="dialogTitle"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    @close="close"
    @save="save"
    @reset="reset"
  >
    <ProductFields
      v-if="command?.kind === 'product'"
      v-model="command"
      :errors="errors"
      :existing="catalog.products.some((p) => command?.kind === 'product' && p.id === command.product.id)"
    />
    <ConditionsFields v-else-if="command?.kind === 'conditions'" v-model="command" :errors="errors" :catalog="catalog" />
    <UserFields
      v-else-if="command?.kind === 'user' && data"
      v-model="command"
      :errors="errors"
      :state="data"
      :actor-id="auth.user?.id ?? 0"
    />
  </AdminDialog>
</template>
<style scoped>
.management-group {
  max-width: 560px;
}
.management-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  background: var(--mf-color-border);
  border: 1px solid var(--mf-color-border);
  border-radius: 4px;
  overflow: hidden;
}
.management-summary > div {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px;
  background: var(--mf-color-surface);
  min-width: 0;
}
.management-summary strong {
  font-size: 18px;
  overflow-wrap: anywhere;
}
@media (max-width: 768px) {
  .management-summary {
    grid-template-columns: 1fr;
  }
}
</style>
