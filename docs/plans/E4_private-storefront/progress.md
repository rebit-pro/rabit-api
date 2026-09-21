# E4 — прогресс

## Точка продолжения

- Ветка codex/e4-private-storefront; base 6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc; HEAD 66adc99; PR E4 ещё не создан.
- F1 PR #25 MERGED 2026-09-21T10:11:13Z, merge 6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc. D2/E3 уже в main. Новый код E4 отсутствует.
- Завершены merge F1, согласование assignmentId, обновление канонического API/Postman и графа. Следующий шаг — реализация assignment UUID и capability lifecycle по уточнённому плану.
- E4-DEC-01 принят пользователем: assignmentId/productId/quantity. Следующий шаг — обновление канонического контракта и графа, затем реализация. Решение больше не блокирует код.
- Подготовлены план/журнал, graph.json и patch канонических источников docs/waves/e4/morefoto-contract.patch. Стороннее форматирование F1 routes.php сохранено в git stash с сообщением preserve local F1 routes formatting before E4; не переносить в E4.
- Источники: docs/waves/e3/README.md, docs/waves/w05/decisions.md, графы RaBit/MoreFoto, endpoints MED-01/COM-08/COM-09.
- Следующая проверка: git status --short; rg --files api/public/local/modules/morefoto.media/lib; изучить existing private preview и возможности межмодульных портов. Затем обновить план до реализации.

## Хронология

### 2026-09-21 — merge F1 и старт E4

- Пользователь разрешил merge и следующую волну; deployment не запрошен.
- Журнал повторного review сохранён docs-коммитом 1ac4c11 и отправлен в F1. Runtime совпадает с проверенным 74e44ea.
- Первая попытка gh pr merge: GraphQL Base branch was modified. Повторный fetch/gh pr view подтвердили неизменную базу 28bcad9; повтор gh pr merge 25 --merge --match-head-commit 1ac4c11e5eaf5d0935c5f994c27715c1a2826715 — PASS.
- gh pr view 25 --json state,mergedAt,mergeCommit: MERGED, 6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc. git switch main; git merge --ff-only origin/main; git switch -c codex/e4-private-storefront — PASS.
- E4-BASE PASS по присутствию зависимостей в актуальном main и PR #25; отдельную автоматическую DAG проверку ещё не запускали.
- Найдено E4-DEC-01: D2 разрешает один photoId нескольким детям, API COM-09 не передаёт выбор ребёнка; SalesPolicy считает порог по childId. Это требует явного уточнения контракта.
- E4-CAPABILITY/MEDIA/QUOTE/STALE/ARCH/UI/GRAPH/PUBLISH: PENDING, тесты E4 не запускались. Тесты F1 не выдаются за покрытие E4.


### 2026-09-21 — первичная техническая сверка

- Прочитаны media routes, PrivatePhotoStorageInterface/LocalPrivatePhotoStorage, SalesPolicy и frontend GallerySnapshot/CartQuote. Публичных MED-01/COM-08/09 в коде ещё нет. Приватное хранилище существует; защищённую выдачу превью нужно реализовать в E4.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` и та же команда для ../MoreFoto/docs/04-bitrix-modules/backend-waves.json: exit 0, DAG/99 endpoints/negative fixtures PASS. Однако deliveryState устарел: E4 считается blocked, слитые D2/E3/F1 не отражены. E4-GRAPH остаётся PENDING по актуальности готовности. Перед кодом сверить merge receipts предшественников и обновить обе карты/генераторы по принятому порядку.
- E4-DEC-01 ожидает ответ пользователя. Независимый первичный разбор завершён. Перед commit документов `git diff --check` PASS; код не менялся, E4 runtime-проверки не запускались.

### 2026-09-21 — E4-DEC-01 согласовано

Пользователь подтвердил ID связи ребёнок—снимок. В плане зафиксировано assignmentId; MED-01 выдаёт его, COM-09/будущий COM-10 используют в строках. Сервер проверяет связь и область, выводит ребёнка/суммы. Соседний MoreFoto не является Git checkout; внешние изменения генераторов будут сохранены patch в ветке E4. Runtime ещё не менялся.


### 2026-09-21 — контракт и граф обновлены

- E4-DEC-01 PASS: build.py использует assignmentId/productId/quantity для COM-09 и будущего COM-10; MED-01 описывает выдачу assignmentId; assignment_id объявлен в окружениях Postman. Старый photoId сохранён для административных API.
- Подтверждены gh merge receipts C4 #16, B2 #18, D1 #19, E3 #20, D2 #21, H1 #22, F1 #25; git merge-base --is-ancestor каждого merge SHA HEAD — PASS. Графы синхронизированы, E4 inProgress; D02–D05 приняты по E3. Другие незамерженные волны не активированы.
- `python3 ../MoreFoto/docs/04-bitrix-modules/render-waves.py` и `python3 ../MoreFoto/docs/05-rest-api/build.py` — PASS, 40 волн / 99 API.
- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS: E4 единственная readyFromMain, DAG/99 API/negative fixtures PASS. Отдельная проверка Python равенства двух graph JSON — PASS.
- `python3 ../MoreFoto/docs/05-rest-api/validate.py` — PASS, registry/Markdown/Postman parity, 99 API. JSON Schema validation не запускалась.
- Первый `node ../MoreFoto/docs/05-rest-api/validate-postman.cjs` из rabit-api — FAIL: скрипт разрешает путь от cwd. Повтор `node docs/05-rest-api/validate-postman.cjs` из /home/user/MoreFoto — PASS: 144 scripts, 99 positive/198 negative fixtures, 43 idempotency checks, 14 platform guards.
- Дополнительная проверка Python: COM-09/COM-10 имеют ровно assignmentId/productId/quantity в строке, MED-01 описывает assignmentId — PASS.
- Точный diff двух внешних канонических источников сохранён в docs/waves/e4/morefoto-contract.patch. В MoreFoto обновлены также производные карты/Postman. Patch позволяет воспроизвести изменения вне Git; перед повторным применением проверять текущее состояние.
- E4-GRAPH PASS для текущего планирования; остальные E4 runtime/HTTP/UI проверки PENDING. Код приложения ещё не менялся. Найдены обязательные работы по UUID связи и закрытию прямой выдачи preview, добавлены в plan.
- Перед commit `git diff --check` PASS. Этот commit фиксирует согласованный контракт и готовность зависимостей, не объявляет продуктовую волну завершённой. PR пока не создаётся.
