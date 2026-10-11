# Ускоренный MVP — прогресс

## Точка продолжения

- Дата: 11.10.2026. Ветка codex/ops-mvp-payment-print-email-plan, base fd7498a (origin/main), предыдущий checkpoint 66c0090; итоговый HEAD определяется `git log -1 --format=%h`. PR/push плана не выполнялись.
- Завершено: первоначальный ускоренный MVP-план/граф/канонический patch и проверки. В соседних операциях опубликована /legal и создана Gmail issue #161.
- Сейчас: уточнение плана завершено и проверено; сохранены порядок E7 → L1 → L3 → G2 → N3 и подпись «Разработано в ReBit Studio» в E7 с контролем N3. Код E7 не начинался.
- Следующий шаг реализации: E7 отдельной веткой от актуального main; L1 после её merge и решений Сивмы, L3 после merge L1. G2 завершается до N3; анкета и модерация идут параллельно.
- Открытые условия запуска: договор/боевой магазин/чеки, охват патента и остальные параметры D09, формат/email/срок ссылки Сивмы, MVP-LATE-01. ПСН подтверждена пользователем, остальные условия не объявлены закрытыми.
- Рабочее дерево: изменяются только документы плана/графа/карт и sync patch; предыдущие ветки сохранены отдельно. MoreFoto/read-only источники не меняются.
- Проверки: `python3 tools/verify-wave-graph.py docs/waves/graph.json`; проверка последовательного merge E7/L1/L3/G2/N3; каноническая генерация и apply/reverse/content parity patch; `git diff --check`.

## Журнал

### 11.10.2026 — исходное состояние

- Исходный чистый main отставал на 11 коммитов. `git fetch origin`, `git merge --ff-only origin/main`, `git switch -c codex/ops-mvp-payment-print-email-plan` — exit 0, база fd7498a.
- Через `/home/user/.local/bin/gh` прочитаны открытые/merged PR и связанные issues. Последний merged PR #158 — база знаний; последняя продуктовая волна J1 (#143, merge 591d698 от 26.09.2026), baseline графа её пока не отмечает.
- Пользователь подтвердил общий отсчёт: 7 дней на выбор и оплату печати / 14 дней на покупку электронных фото от выдачи ссылки группе; отправлять оплаченные печатные позиции группы после 7 дней.
- Сейчас подключён тестовый платёжный сервис, нужна боевая версия; наименование сверяется по коду, предыдущий план указывает ЮKassa.
- PDF прочитан read-only через pypdf: одна страница, данные реквизитов в Git не включаются.
- WSL без escalation вернул E_ACCESSDENIED; разрешённый запуск работает. rg в WSL отсутствует. Нативная apply_patch не смогла записать файл через UNC; запись выполнена Python через WSL в разрешённом checkout.

## Проверки документации

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| PLAN-01 | PASS | 11.10.2026 | gh PR #80/#22/#143 = MERGED; ancestor-check J1 exit 0; код прочитан |
| PLAN-02 | PASS | 11.10.2026 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 55 волн, 120 ID, 35 WNN, 17 negative fixtures; readyMvpFromMain=[E7]; сохранены 99/118 ID, проверены 4 merge-порядка |
| PLAN-03 | PASS | 11.10.2026 | Канонические wave_graph/render-waves/build/validate + bundled Node offline fixtures; sync patch apply/reverse/content PASS; optional external schema not-run |
| PLAN-04 | PASS | 11.10.2026 | Финальный staged diff: 7 разрешённых файлов, git diff --cached --check exit 0; invoice_numeric_details_in_deliverables=[] |

### 11.10.2026 — инвентаризация и решение по scope

- PLAN-01 PASS: `gh pr view 80`, `gh pr view 22`, `gh pr view 143` возвращают MERGED; `git merge-base --is-ancestor 591d698 origin/main` exit 0. Прочитаны код и уже готовый J1 gate-report, новые E2E не запускались.
- Проверен провайдер: DI использует api.yookassa.ru/v3, YooKassaRequestMapper, /api/v1/webhooks/{provider}/payments. Receipt в createPayment отсутствует: G2 обязательна до реальных платежей.
- GroupCalendar содержит общий closesAt=T0+7 и deliveryDueAt=T0+14 для физической передачи; digitalSalesUntil отсутствует. J1 filesAvailableUntil — календарный месяц после оплаты, сохраняем отдельно от 14 дней покупки.
- Пользователю дана инструкция подключения договора ЮKassa по официальным источникам. Срок внешнего подключения не выдаётся за готовность сегодня.
- Пользователь сообщил, что формат Сивмы пока неизвестен и уточнит его. Архив+CSV — предложение, не принятое условие. Открыты MVP-SIVMA-01 и MVP-LATE-01.
- Сформирован план E7/G2/L1/L3/N3 и post-MVP backlog; plan.md обновлён до изменения графа/инструментов. D09/D06 не объявляются глобально закрытыми.
- Канонический ../MoreFoto не является Git checkout; прямой записи за пределы workspace не выполняем, готовим воспроизводимый patch на временной копии с генерацией/валидацией.

- Создана временная копия канонических публичных docs: `/tmp/rabit-mvp-plan-vlr97u4x`. Граф содержит 55 волн/120 ID (118 сохранены + PRD-08/09), новые E7/L3/N3, early L1 и post-MVP backlog. В валидатор добавлены проверки отсутствия транзитивной зависимости MVP от backlog и внешних условий запуска.

### 11.10.2026 — проверки графа и канонической генерации

- `python3 tools/verify-wave-graph.py docs/waves/graph.json` — PASS: 55 волн, 120 API ID, 35 legacy WNN, 17 отрицательных fixtures; readyMvpFromMain=[E7].
- `python3 /tmp/rabit-mvp-plan-vlr97u4x/docs/04-bitrix-modules/wave_graph.py .../backend-waves.json` — PASS с теми же результатами.
- `python3 /tmp/rabit-mvp-plan-vlr97u4x/docs/04-bitrix-modules/render-waves.py` — PASS, 55 волн.
- `python3 /tmp/rabit-mvp-plan-vlr97u4x/docs/05-rest-api/build.py` — PASS, 120 запросов, 7 генерируемых артефактов.
- `python3 /tmp/rabit-mvp-plan-vlr97u4x/docs/05-rest-api/validate.py` — первый запуск FAIL: Missing source AUTH-01 во временной копии (исходники frontend не копировались). Добавлены read-only symlink к уже существующим публичным source-файлам MoreFoto; повтор выполняется далее. HTTP/E2E не запускались.

- Повторная каноническая генерация `render-waves.py`/`build.py` PASS. `validate.py` второй запуск FAIL: во временной копии отсутствовала ссылка на docs/02-domain/README.md; добавлены read-only symlink на остальные существующие публичные разделы docs. WSL `node` отсутствует, offline Postman проверяется комплектным Windows Node.js. Это ограничения временной копии/runtime, не E2E.

- `validate.py` третий запуск FAIL: частично скопирован docs/frontend/business-review, отсутствовал contracts.md; read-only ссылки дополнены рекурсивно только в этом публичном разделе.
- Комплектный Windows Node выполнил offline Postman fixtures, но не смог записать verification.json через UNC (UNKNOWN). Для завершения проверки создаётся Windows temp-копия JSON/скрипта; runtime проекта не затрагивается.

- Канонический validate.py PASS после восстановления ссылок: 120 запросов, 55 волн, 169 скриптов; liveApiRequestsExecuted=false; optional external JSON Schema validation not-run.
- Комплектный Windows Node, temp C:/Users/User/AppData/Local/Temp/codex-rabit-mvp-postman-20261011, validate-postman.cjs PASS: 169 scriptsParsed, 120 positive/240 negative fixtures, 47 repeat checks, 14 platform guards, 2 platform negative fixtures. Только offline.
- Проверка API/merge-порядков: первый запуск указал несуществующий frozen backend-waves.json, исправлен источник на реальный snapshot/endpoints.json. Повтор PASS: все исходные 99 и предыдущие 118 ID сохранены; добавлены PRD-08/09. Из 120 перестановок пяти волн — 4 допустимых независимых merge-порядка после отдельного разрешения D06/D09, N3 всегда последняя. Реальные D06/D09 остаются открытыми. Проверен переход planned→merged с сохранением releaseStage=mvp.

### 11.10.2026 — финальный sync patch

- После уточнения PRD-02 и валидатора снова запущены render-waves.py, build.py, validate.py — PASS (55 волн, 120 запросов, 169 скриптов).
- Повтор Node из нового вызова сначала FAIL: TEMP sandbox меняется между вызовами, каталога прежнего вызова нет. Исправление: создавать temp-копию JSON/скрипта и запускать bundled Node в одном вызове. Финальный повтор PASS с прежними 120 positive/240 negative fixtures, без API/E2E.
- Сформирован morefoto-plan-sync.patch; git apply --check, apply на отдельную temp-копию, --reverse --check — PASS, побайтовое совпадение всех изменённых канонических артефактов. Соседний MoreFoto не изменён.

### 11.10.2026 — финальная проверка и передача

- Первый staged `git diff --cached --check` нашёл пробелы у пустых context-строк внутри текстового sync patch. Убраны только context-префиксы на пустых строках; повторные apply-check/apply/reverse-check и побайтовая сверка 13 файлов PASS. Содержимое канонических результатов не менялось.
- Read-only проверка числовых ИНН/ОГРНИП/счетов из PDF по всем deliverables: invoice_numeric_details_in_deliverables=[] — PASS; значения не печатались и не сохранялись.
- Все задачи планирования завершены. Перед commit обновлены план, результаты и точка продолжения; runtime, реальные платежи/письма, E2E, merge/deployment не выполнялись.

### 11.10.2026 — порядок работ и разработчик приложения

- Владелец просит выполнять E7, L1 и L3 во время рассмотрения анкеты, а G2 оставить последней реализацией перед запуском. По графу E7/L1/L3 не зависят от G2; предпочтение можно сохранить без изменения runtime-зависимостей. Нерешённый формат Сивмы не замалчивается.
- Разделены выбор сервиса чеков в обязательном разделе анкеты сейчас и реализация передачи данных чека в G2 позже. По скриншоту отправка анкеты пока заблокирована, паспортный раздел не завершён. Официальные инструкции ЮKassa повторно прочитаны 11.10.2026.
- Название разработчика уточнено самим пользователем: ReBit Studio. В E7 добавляется подпись «Разработано в ReBit Studio», N3 проверяет её наличие. Предыдущее распознавание Rabbit Studio не используется.
- Сначала обновлён plan.md; затем обновляются граф и канонические артефакты. PLAN-05/06 пока PENDING, runtime/код/UI не менялись.

- PLAN-05 PASS: отдельная Python-проверка последовательно добавила E7/L1/L3/G2/N3 в baseline merged без нарушения dependsOn; у E7/L1/L3 нет зависимости G2. Decision gates остаются обязательными. `python3 tools/verify-wave-graph.py docs/waves/graph.json` — exit 0, 55 волн/120 ID/35 WNN, 17 отрицательных fixtures, readyMvpFromMain=[E7].
- PLAN-06 PASS: в E7 scope/acceptance и N3 acceptance указана точная подпись «Разработано в ReBit Studio»; обновлены README/a7 и канонический backend-waves в временной копии. `python3 /tmp/rabit-mvp-plan-vlr97u4x/docs/04-bitrix-modules/wave_graph.py .../backend-waves.json`, `render-waves.py`, `docs/05-rest-api/build.py`, `validate.py` — exit 0, 55 волн, 120 запросов, 169 скриптов, liveApiRequestsExecuted=false. Optional external schema not-run.
- Пересобран morefoto-plan-sync.patch: `git apply --check` на неизменённом MoreFoto, apply/reverse-check на отдельной копии и побайтовое совпадение 13 файлов с проверенной генерацией — PASS. Соседний MoreFoto не изменён.
- Offline Node fixtures: первый запуск FAIL до проверки — native Windows sandbox TEMP не разрешил дочерние каталоги; повтор в разрешённом каталоге визуализаций FAIL из-за отсутствующего verification.json в копии. Прочитаны все readFileSync валидатора, добавлен входной verification.json. Финальный запуск bundled Node `docs/05-rest-api/validate-postman.cjs` — PASS: 169 scriptsParsed, 120 positive/240 negative, 47 repeat, 14 platform guards, 2 platform negative fixtures. API/браузер/E2E не запускались.
- Перед коммитом проверяются только шесть файлов текущего уточнения; исходные ID и dependsOn не менялись. Значения реквизитов/адресов/контактов не добавлены. Подпись ещё не реализована на сайте; сохранена в планах волн.

- Финальная проверка PASS: JSON-сверка до/после сохранила все ID, dependsOn и остальные волны; `git diff --check` exit 0; поиск конкретных значений реквизитов/контактов пуст. Итоговый коммит включает только шесть проверенных документов/артефактов.
