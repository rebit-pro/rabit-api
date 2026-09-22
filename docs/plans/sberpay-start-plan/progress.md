# Прогресс платёжного плана MoreFoto

## Точка продолжения

- Ветка: `codex/sberpay-start-plan`, PR https://github.com/rebit-pro/rabit-api/pull/35. Историческое имя сохранено; активный провайдер — ЮKassa.
- Checkout: `/home/user/rabit-api-worktrees/yookassa-plan`; main/base `4b507b3b3c27719888e38f582b48e6a56c0c5946`, проверенный содержательный HEAD `29e590a4d705df76cd6ad2b70de9318d03875e8e`; после него только завершающая отметка публикации в plan/progress (точный HEAD журнала — `git rev-parse HEAD`).
- Завершено: прочитаны прежний план/PR, подтверждён merge E5 #37; зафиксирован выбор ЮKassa/чеков, переключателя расходов и округления вверх до 50 ₽.
- Завершено: графы, канонические материалы, проверки и публикация PR #35 с новым заголовком/описанием. Следующий самостоятельный шаг — реализация E6 в отдельной ветке от актуального main; G1 начинает после merge F2 и закрытия оставшихся решений.
- Блокеры runtime G1: F2 #40 не merged; D09-параметры/служебный API и D12 остаются открыты. Выбор ЮKassa закрывает поставщиков, не все настройки. 3,8% — расчётный ориентир до проверки эффективной ставки договора.
- Дерево: все содержательные правки опубликованы; завершающие plan/progress коммитятся отдельно, после push ожидается чистое дерево. Основной checkout `rabit-api` на main не изменён. MoreFoto обновлён адресно; прежние F2/D3/N1 сохранены. Добавочный patch и исходные SHA опубликованы в PR.
- Следующая проверка: `python3 tools/verify-wave-graph.py docs/waves/graph.json`; `python3 /home/user/MoreFoto/docs/04-bitrix-modules/wave_graph.py /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json`; `git diff --check`.

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
