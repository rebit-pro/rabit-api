# Прогресс платёжного плана MoreFoto

## Точка продолжения

- Ветка: `codex/sberpay-start-plan`, PR https://github.com/rebit-pro/rabit-api/pull/35. Историческое имя сохранено; активный провайдер — ЮKassa.
- Checkout: `/home/user/rabit-api-worktrees/yookassa-plan`. База — main `fdb6302f78ade1a827509fd356eb2dc8ad9be608`, слит в ветку merge-коммитом `18d3d8312dc922ad850ec9e1d033586a7e926761`; точный HEAD журнала — `git rev-parse HEAD`.
- Завершено 25.09.2026: ветка обновлена на актуальный main, конфликт `docs/waves/graph.json` разрешён как «main + собственная дельта PR #35», YK-01/02/04/05/06/08 повторены, план и отчёт обновлены.
- Следующий шаг: merge PR #35 по решению пользователя. После merge — ветка `codex/e6-payment-cost-pricing` от актуального main и план `docs/plans/E6_payment-cost-pricing/`.
- G1: runtime-зависимости E5/F2 слиты. Держат решения D09 (sandbox: тестовый магазин, служебные API ID реестра) и D12 (предложен узкий вариант для G1), плюс регистрация тестового магазина ЮKassa. ИП не оформлен; для тестового магазина он не нужен, для боевого и схемы чеков G2 — нужен выбор статуса (ИП/ООО или самозанятость).
- Учёт merge пакета design-ux (U1–U8/B3/B4, PR #53) в графе выполняет следующая волна (E6). Валидатор при этом потребует правки отрицательных fixtures для полностью слитого пакета — см. запись 25.09.
- Дерево: после push ожидается чистое; канонический MoreFoto в этом обновлении не менялся (SHA в `docs/waves/yookassa-mvp/verification.json`, раздел `resync20260925`).
- Следующая проверка: `python3 tools/verify-wave-graph.py docs/waves/graph.json`; `python3 /home/user/MoreFoto/docs/04-bitrix-modules/wave_graph.py /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json`; `git diff --check origin/main HEAD`.

## 2026-09-21 — Источник и уточнения

Orteka: `TASK-10094235_sberpay-web-sdk`, HEAD `c3bcc1b32eac197d7511920de92ac71342811814`, чистое дерево. Прочитаны SberPayClientProvider, provider interface, UseCase, request/response mapper и зависимости Sale. Подтверждены register.do/getOrderStatusExtended.do/widget. В интерфейсе и клиенте нет денежного refund. ReturnController означает возврат браузера. Order/Payment Sale не переносим на Старт.

Пользователь подтвердил интерфейс в админ-панели MoreFoto и собственную реализацию заказов/платежей. Сохраняем Commerce/Payments/Settlement существующего плана.

Sandbox запуск команд не работает (`setup refresh had errors`); разрешённый elevated режим работает. SSH fetch не прошёл; HTTPS fetch с credential helper настроенного gh успешен. GitHub читается только gh. В WSL нет rg, поиск — Windows rg. Первая запись plan/progress через provider-prefixed PowerShell path завершилась ошибкой; исправлено явным UNC путём, файлы записаны до изменения графа.

## Проверки

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| TC-01 | PASS | 2026-09-21 | `git -c 'credential.helper=!/home/user/.local/bin/gh auth git-credential' fetch https://github.com/rebit-pro/rabit-api.git main:refs/remotes/origin/main`; main=origin/main=8cba22c; `git switch -c codex/sberpay-start-plan origin/main` |
| TC-02 | PASS | 2026-09-21 | `git -C /home/user/orteka status --short --branch; git ... rev-parse HEAD`; чтение исходников из карты адаптации, поиск Sale/refund в lib |
| TC-03 | PASS | 2026-09-21 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 40 волн, 99 ID, 35 WNN, 10 negative fixtures; после уточнения E4 готова E5, G1/G2/I2 заблокированы |
| TC-04 | PASS | 2026-09-21 | `python3 /home/user/MoreFoto/docs/04-bitrix-modules/wave_graph.py /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json`: совпадающий результат, сохранён в verification.json |
| TC-05 | PASS | 2026-09-21 | `python3 /home/user/MoreFoto/docs/04-bitrix-modules/render-waves.py` выполнен дважды, SHA результата одинаковый; `cmp docs/waves/graph.json /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json` = 0; `git -C /home/user/MoreFoto apply --check --reverse /home/user/rabit-api/docs/waves/sberpay-start/morefoto-plan-sync.patch` = 0 |
| TC-06 | PASS | 2026-09-21 | `git diff --check; git status --short; git diff --stat`: ошибок пробелов нет; только план/граф/артефакты. Финальная проверка повторяется перед фиксацией |
| RT-01 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-02 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-03 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-04 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-05 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-06 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-07 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |
| RT-08 | BLOCKED | 2026-09-21 | Runtime не реализуется до merge зависимостей; соответствующий сценарий не запускался |

## 2026-09-21 — План и собственное хранение

Обновлены paymentIntegration и scope/acceptance E5/G1/G2/I2 в обоих графах. Генератор MoreFoto выводит выбранный провайдер, Старт, владельцев хранения, обязательный UI и оставшиеся вопросы D09. Дополнение D09 внесено в decisions.md. Runtime-зависимости, статусы и 99 API ID не менялись. Генератор фактически выполнен: Rendered 40 independent waves. Отдельно проверена официальная документация refund.do: серверная идемпотентность заголовка заявлена как разрабатываемая, поэтому неизвестный исход требует сверки и не разрешает слепой повтор.

## 2026-09-21 — Проверки, расхождения и завершение планирования

Первый прогон двух валидаторов прошёл: 40 волн, 99 API ID, 35 WNN и 10 отрицательных fixtures. Он показал устаревшее состояние E4 (inProgress). Git ancestry подтвердил merge PR #30 (`7e606e5`) в текущий main; план доработан до правки, затем E4 отмечена merged в обеих копиях, baseline обновлён до 8cba22c. Генератор теперь берёт дату/base/merged/готовые волны из JSON вместо старого фиксированного текста про A8. Повторный прогон дал readyFromMain=[E5]. D09 целиком не принята, runtime dependencies и старые 99 владельцев ID сохранены; отдельные assertions подтвердили это.

Проверена воспроизводимость Markdown повторным запуском; графы побайтно равны. Patch четырёх канонических файлов MoreFoto сохранён в `docs/waves/sberpay-start/morefoto-plan-sync.patch` и проверен `git apply --check --reverse` без изменения файлов. Контрольные SHA и результаты — в verification.json. Временный снимок before удалён только после создания и проверки patch.

Смежное расхождение: ветка/отчёт `codex/d3-stage-media-recovery` уже слиты PR #32, но D3 в каноническом графе — «Атомарные переносы ребёнка и служебной заявки», зависящие от E5. Это разные объёмы под одним ID; D3 не отмечена merged по факту recovery. Разрешить конфликт именования отдельной синхронизацией, не объявляя переносы готовыми.

Плановый срез готов, продуктовый СберПэй/реестр/refund не реализованы и runtime-тесты не запускались. Настройки банка, данные реальных платежей, исходники Orteka не менялись. Коммит/публикация и продуктовая реализация отражаются отдельно; автоматического merge/deploy нет.

## 2026-09-21 — Перед локальной фиксацией

Все P1–P6 закрыты, TC-01–TC-06 PASS. Финальный git diff --check выполнен без ошибок. Фиксируются только пять плановых файлов: plan/progress, graph.json, patch MoreFoto и verification.json. Плановые файлы коммитятся в ветку по правилу AGENTS.md; push/PR/merge/deploy не выполняются. После коммита проверить чистоту рабочего дерева и один собственный коммит относительно origin/main.

### Проверка staged diff

Первый `git diff --cached --check` дал FAIL: строки пустого контекста внутри нового unified patch содержали одиночный пробел. До staging обычный diff не проверял untracked patch. Исправлен только способ записи пустых строк контекста, без изменения целевых документов; повторный `git -C /home/user/MoreFoto apply --check --reverse /home/user/rabit-api/docs/waves/sberpay-start/morefoto-plan-sync.patch` прошёл. Финальный staged whitespace check выполняется перед коммитом; TC-06 учитывает staged и untracked артефакты.

## 2026-09-21 — Запрос публикации плана

Пользователь прочитал план и явно попросил сохранить его в репозитории и создать PR; на этом текущая работа заканчивается, реализации сейчас не требуется. Зафиксировано: договор эквайринга находится в процессе получения. До правок проверены чистое дерево, HEAD `2308bf5`, отсутствие PR по ветке (`gh pr list --head codex/sberpay-start-plan --state all --json number,title,state,url` вернул []). План дополнен P7 и статусом договора.

Перед push: проверить актуальный origin/main, валидатор графа и diff; публиковать только эту ветку. Merge/deployment не входят в запрос.

Проверки перед публикацией: HTTPS fetch через gh credential helper успешен, origin/main остался 8cba22c; python3 tools/verify-wave-graph.py docs/waves/graph.json — PASS (40 волн, 99 ID, 35 WNN, 10 negative fixtures); git diff --check — PASS. Runtime/E2E не запускались: меняются только документы, пользователь явно отложил реализацию.

## 2026-09-21 — PR создан

Коммит `2a8f016` сохранил статус договора и запрос публикации. Push `HEAD:refs/heads/codex/sberpay-start-plan` по HTTPS через gh credential helper успешен. Команда `gh pr create --repo rebit-pro/rabit-api --base main --head codex/sberpay-start-plan --title 'План интеграции СберПэй для MoreFoto на Битрикс Старт' --body-file /tmp/rabit-sberpay-plan-pr-body.md` создала https://github.com/rebit-pro/rabit-api/pull/35; PR прикреплён к задаче Codex.

P7 закрыт. Перед завершающим push повторяется `git diff --cached --check`; после push проверить `gh pr view 35 --json url,state,baseRefName,headRefName,headRefOid` и `git status --short`. Merge/deployment/реализация не выполняются.

## 2026-09-22 — ЮKassa первым провайдером и E6

Пользователь выбрал ЮKassa (ЮMoney для бизнеса) со встроенными чеками; СберПэй остаётся необязательным резервом только с решённой фискализацией. В ответе на уточнение выбрано округление вверх до 50 ₽ для заранее общей цены. Публичные 2,8% + 1% у ЮKassa без применимого НДС, СБП индивидуально; это зафиксировано как открытая проверка тарифа, не повод откладывать сам план.

Создан изолированный worktree существующей ветки PR #35. `git merge --no-edit main` завершился без конфликтов; включены только слитые изменения E5. Runtime отсутствует: текущая задача продолжает согласование/обновление платёжного плана, необходимые зависимости G1 ещё не слиты.

YK-01…YK-07: PENDING. Runtime E6/G1/G2/I2: PENDING, прогоны не выполнялись.


## 2026-09-22 — Граф, проверки и подготовка публикации

В оба графа внесены ЮKassa/встроенные чеки и E6. Только N2 получил зависимость E6; G1/G2/I2 сохранили runtime-зависимости. E5 отмечена merged по факту GitHub. F2/D3/N1 канонического плана сохранены побайтно как объекты JSON. В PR не переносился соседний незамерженный план F2/N1.

Первая проверка DAG прошла; `git diff --check` выявил лишнюю пустую строку EOF plan.md — исправлено. Генератор/API-валидатор прошли, но `node docs/05-rest-api/validate-postman.cjs` в WSL дал `node: command not found`. Использован штатный bundled Node.js Windows, статическая проверка завершилась PASS. Это исправления окружения/формата, не runtime-прогоны.

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| YK-01 | PASS | 2026-09-22 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 41/99/35, 10 отрицательных fixtures, ready E6 |
| YK-02 | PASS | 2026-09-22 | `python3 /home/user/MoreFoto/docs/04-bitrix-modules/wave_graph.py /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json`: 41/99/35, ready E6/F2 |
| YK-03 | PASS | 2026-09-22 | `python3 docs/04-bitrix-modules/render-waves.py`, `python3 docs/05-rest-api/build.py`, `python3 docs/05-rest-api/validate.py` из MoreFoto; повторная генерация не меняет SHA. Bundled `node.exe docs/05-rest-api/validate-postman.cjs`: 144 scripts, 99 positive/198 negative fixtures, только offline |
| YK-04 | PASS | 2026-09-22 | Адресные Python assertions: исходные 99 владельцев и dependencies сохранены (кроме новой E6→N2); F2/D3/N1 канонической версии не изменены; `git -C /home/user/MoreFoto apply --check --reverse .../morefoto-plan-update.patch` PASS |
| YK-05 | PASS | 2026-09-22 | `python3 -` с целочисленной формулой: 8 примеров, 6850 граничных значений, минимальность шага и компенсация ставки, скидка 50% → 275 ₽. Результаты в verification.json; это проверка спецификации |
| YK-06 | PASS | 2026-09-22 | `git diff --check` PASS; staged diff проверяется перед commit и должен завершиться без ошибок |
| YK-07 | PASS | 2026-09-22 | Commit `29e590a`, HTTPS push через gh credential helper, `gh pr edit 35 --title ... --body-file /tmp/rabit-yookassa-pr35-body.md`; `gh pr view` подтвердил title/head/base/url |

Backend/frontend/runtime/HTTP/E2E: PENDING для будущих реализаций, в плановом PR не запускались. Реальный merchant не создан и оплата не включена.

## 2026-09-22 — План опубликован

Коммит `29e590a` опубликован в существующую ветку PR #35. Заголовок: «План MVP: ЮKassa с чеками и учёт расходов в цене (E6)». Описание переписано вокруг текущего решения, исходный аудит СберПэй сохранён как история. Первая попытка `gh pr edit` завершилась временной сетевой ошибкой api.github.com; повтор после чтения состояния успешен. GitHub подтвердил head/base/title. P1–P6 завершены, YK-01–YK-07 PASS. Финальные отметки plan/progress публикуются отдельным docs-коммитом; после него проверяются чистота обоих checkout и head PR.

Оплата/ценовая опция ещё не реализованы и не включены. F2 продолжает отдельную работу; изменение плана не подменяет merge зависимости. Следующий готовый по зависимостям продуктовый срез — E6; параметры merchant/чеков и D12 для G1 уточняются в его плане.

## 2026-09-25 — Обновление на актуальный main

Пользователь попросил обновить PR #35 и затем взять E6. Со слов пользователя, ИП ещё не оформлен. MAX и боевой магазин ЮKassa ждут статуса продавца. По официальной документации MAX ботов могут создавать юрлица, ИП и самозанятые — резиденты РФ; самозанятые проходят верификацию через Госуслуги. Тестовый магазин ЮKassa доступен до данных компании и договора. Подробности и ссылки — в плане, раздел «Статус на 25.09.2026».

`git merge --no-edit origin/main` (main `fdb6302`) дал один конфликт — `docs/waves/graph.json`. С базы PR (`4b507b3`) main добавил F2/D3 merged, пакет design-ux (U-волны, B3/B4, `deliveryBundles`, 110 API ID). Дельта PR #35: E6, `paymentIntegration`, scope/review/acceptance/activation G1/G2/I2, scope E5, E6 в unlocks E3/E5 и в dependsOn N2, evidence `D09-provider-platform-ui` и `E6-PRICE-01`. Поля не пересекаются, кроме `E5.unlocks`, где сохранены оба значения.

Граф собран двумя независимыми способами: (1) main + дельта PR #35 по полям; (2) канонический `backend-waves.json` с N1 из main. Результаты равны; записан вариант с порядком ключей канонического плана и форматированием `json.dumps(indent=2, ensure_ascii=False)` + перевод строки. Канонический план отличается от графа PR только N1 из плана дашбордов PR #36 — он в этот PR не переносится.

Пакет design-ux в графе остаётся `inProgress`, хотя PR #53 слит (`b20423f`, 24.09.2026). По правилу учёт merge идёт веткой следующей волны. Найденная особенность: симуляция (U1–U8/B3/B4 → merged в копии графа) роняет `tools/verify-wave-graph.py` на fixture «bundle depends on unmerged wave outside the bundle» — слитая волна с неслитой зависимостью не отклоняется. Fixture «bundle partially merged» построен на той же предпосылке. Их нужно перестроить (или добавить проверку «merged-волна зависит только от merged») в той же ветке, что отметит пакет слитым.

Исторический `morefoto-plan-update.patch` больше не применяется обратно к текущему MoreFoto: после 22.09 пакет design-ux изменил те же файлы (`git apply --check --reverse` — FAIL на postman/verification). Patch остаётся снимком 22.09; текущая сверка — YK-08.

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| YK-01 | PASS | 2026-09-25 | `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 51 волна, 110 ID, 35 WNN, 13 отрицательных fixtures; readyFromMain E6, U1, U5 (U1/U5 — формально, до учёта merge PR #53) |
| YK-02 | PASS | 2026-09-25 | `python3 docs/04-bitrix-modules/wave_graph.py docs/04-bitrix-modules/backend-waves.json` в MoreFoto: 51/110/35, тот же readyFromMain |
| YK-03 | N/A | 2026-09-25 | Канонический план не менялся, генераторы не запускались |
| YK-04 | PASS | 2026-09-25 | Общие paymentIntegration/E6/G1/G2/I2/E5/N2 графа PR и канонического плана равны; различие — только N1 (PR #36). Обратная проверка исторического patch — FAIL, ожидаемо (см. выше) |
| YK-05 | PASS | 2026-09-25 | `python3 -` с целочисленной формулой: 8 примеров и 14 610 граничных случаев (10 ставок), кратность 50 ₽, компенсация ставки и минимальность шага; 500 ₽ → 550 ₽, скидка 50% → 275 ₽ |
| YK-06 | PASS | 2026-09-25 | `git diff --check origin/main HEAD` — без ошибок. `git diff --cached --check` в merge показывает пробелы в файлах, уже лежащих на main (design-ux, F2), — не часть diff PR |
| YK-07 | PENDING | 2026-09-25 | Push и описание PR — следующий шаг этой записи |
| YK-08 | PASS | 2026-09-25 | Две сборки графа (main + дельта PR; канонический план с N1 main) равны; `cp` результата в `docs/waves/graph.json`, merge-коммит `18d3d83` |

Runtime, HTTP и E2E не запускались: PR содержит только документы и граф.
