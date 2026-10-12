# OPS-k3-question-spec-race — гонка в E2E-тесте метки ответа куратора (#179)

## Цель и контекст

Сделать детерминированным тест `zz-questions.spec.ts` «a new curator answer is marked on the gallery until the parent opens it». Он падает в полном гейте и блокирует merge D4 (PR #175), хотя продуктовый код не менялся.

- Ветка `codex/ops-k3-question-spec-race` от `origin/main` 480427c; рабочая копия `/home/user/rabit-api-worktrees/ops-k3-question-spec-race`.
- Issue: [#179](https://github.com/rebit-pro/rabit-api/issues/179). Задача вне графа волн: ветка `codex/ops-…`, план `docs/plans/OPS-…/`.

## Причина

Тест открывает галерею, кладёт ключ вопроса `'b'.repeat(64)` в `localStorage` и только потом регистрирует `page.route('**/api/v1/public/questions/current')`. Если блок вопроса в галерее монтируется после записи ключа (галерея ждёт данных и витрины), `useGalleryQuestion.start()` сразу запрашивает настоящий `questions/current`. Сервер отвечает 404 на несуществующий ключ, и галерея стирает ключ. После `reload` ключа нет, тред не запрашивается, метки нет. В trace прогона `rabit-e2e-9f0b20f6ca72` есть 404 на `/api/v1/public/questions/current` и ни одного запроса после перезагрузки.

## Scope

- В тесте `page.route` регистрируется до `page.goto` и записи ключа: любой запрос `questions/current` получает подменённый тред.

## Исключено

- Продуктовый код вопросов куратору: поведение «404 стирает ключ» верное, это защита от чужого или удалённого ключа.
- Остальные тесты `zz-questions`: только этот подменяет тред несуществующим ключом.

## Checklist

- [x] S1. Исправить порядок в тесте.
- [x] S2. ESLint и `typecheck:e2e`.
- [x] S3. Частичный прогон группы a (`E2E_GROUPS=a`), где живёт `zz-questions`.
- [ ] S4. PR в `main` с закрытием #179.

## Критерии приёмки

- Тест не зависит от того, успела ли галерея смонтировать блок вопроса до записи ключа.
- Группа a проходит целиком.

## Тест-кейсы

| ID | Действие | Ожидаемый результат | Команда |
| --- | --- | --- | --- |
| K3R-T01 | Статические проверки E2E | без ошибок | `npx eslint e2e/live/zz-questions.spec.ts`, `npm run typecheck:e2e` |
| K3R-T02 | Группа a на изолированном стенде | 81 passed, `zz-questions` зелёный | `E2E_GROUPS=a make test-e2e` |
