# Issues #147 и #148 — журнал

## Точка продолжения

- Ветка `codex/issues-147-148-photo-card-ui`, worktree `/home/user/rabit-api-worktrees/issues-photo-card-ui`,
  base `origin/main` `e847e87`. Issues #147, #148.
- Завершено: реализация, быстрые проверки, визуальная проверка на заглушках.
- Сейчас: PR и полный gate; merge и деплой — по команде пользователя.
- Блокеров и открытых решений нет.

## Статус тест-кейсов

| ID | Статус | Дата | Доказательство |
|---|---|---|---|
| T01 | PASS | 2026-09-26 | stub: высоты 247 (1440) и 293 (390), кнопок за краем 0 |
| T02 | PASS (stub) / PENDING (live) | 2026-09-26 | stub: 60 → 0; live — в `#106` |
| T03 | PASS | 2026-09-26 | скриншоты `card-*` |
| T04 | PASS | 2026-09-26 | stub: активная «2» на подложке 0.12, скриншот mobile |
| T05 | PASS | 2026-09-26 | замеры до/при диалоге: шапка 1425/1425, контент 1097/1097, карточка 247/247 (до исправления 1440/1112/251) |
| T06 | PENDING | — | gate |

## Журнал

### 2026-09-26

- Замечания пользователя (скриншот stage): карточка кадра разъезжается, нужен «Выбрать все», чекбокс в углу, код в
  полосе снизу, убрать «Без ребёнка» и выделить такие кадры; чёрный квадрат пагинации; сдвиг фона у попапов.
- Сдвиг: Vuetify скрывает полосу прокрутки (`html.v-overlay-scroll-blocked`), компенсация не работает на раскладке
  — ширина росла на 15 px. Исправлено `scrollbar-gutter: stable` + отключение `padding-inline-end`.
- Пагинация: правило Vuetify `.v-pagination .v-pagination__item--is-active .v-btn__overlay { opacity: var(--v-border-opacity) }`
  при `border-opacity: 1` темы давало сплошной квадрат цвета текста; переопределено с большей специфичностью.
- Иконки добавлены в реестр `plugins/icons.ts` (`mdi-star`, `mdi-star-outline`, `mdi-checkbox-multiple-marked-outline`,
  `mdi-selection-remove`, `mdi-account-question-outline`).
- `npm run check` PASS (65 node-тестов). Ошибка JS в stub от незаглушённого `/legal/consents/pending` — артефакт заглушек.
