# #219: подвал без ссылки на студию — прогресс

## Точка продолжения

- Ветка `codex/issues-219-footer-studio-link` от main 463d1f9; worktree `/home/user/rabit-api-worktrees/issues-219-footer-studio-link`.
- Завершено: issue #219, план, код и E2E, быстрые проверки (FSL-T01 PASS).
- Сейчас: PR и самопроверка.
- Следующий шаг: полный gate `make test-e2e`.
- Блокеров нет. Merge и выкладка — по указанию владельца «Обнови прод» после зелёного gate.
- Тест-кейсы: FSL-T01 PASS; FSL-T02…T04 PENDING.

## Журнал

### 19.10.2026 — старт

- Указание владельца: убрать ссылку на студию rebit-pro.ru в подвале и обновить прод.
- Других веток и PR по подвалу нет (`git branch -r`, `gh pr list --search`). На main после релиза 65bd194 — только документы.

### 19.10.2026 — код и быстрые проверки

- `LegalFooter.vue`: удалена строка `footer-developer` и стиль ссылок нижней полосы (`.mf-legal-footer__base a` больше не нужен);
  комментарий UI-DEC-07 обновлён. E2E `zzzz-storefront-windows.spec.ts`: подвал виден, без «ReBit Studio» и ссылок на rebit-pro.ru.
- FSL-T01 PASS: новый том `rabit-issues219-node` (`npm ci`), в `mcr.microsoft.com/playwright:v1.52.0-jammy` от root:
  `npm run check` → exit 0 (lint, stylelint, typecheck, typecheck:e2e, test:ui 100/100); `npm run test:commerce` → 253/253.
