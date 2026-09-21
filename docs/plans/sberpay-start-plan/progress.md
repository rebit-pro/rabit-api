# Прогресс: СберПэй для Битрикс «Старт»

## Точка продолжения

- Ветка `codex/sberpay-start-plan`; base/main/origin/main `8cba22c7655b5886d5fe663523214bcd059e674b`. HEAD перед локальной фиксацией совпадает с base; итоговый HEAD — коммит с этим журналом (`git rev-parse HEAD`, `git log -1 --oneline`).
- PR этой задачи: создание разрешено пользователем 2026-09-21, перед публикацией существующий PR по ветке не найден. Issue не создавалась.
- Документы: `plan.md`, `docs/waves/graph.json`, `/home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json`.
- Завершено: исходники/инструкции/ветки, подтверждены Старт и UI MoreFoto, создана ветка, описана адаптация.
- Сейчас: публикация готового плана и создание PR по явной просьбе пользователя. Договор эквайринга в процессе получения, реализация отложена.
- Следующий шаг: открыть PR с планом и передать ссылку пользователю. К реализации оплаты вернуться при наступлении соответствующих волн и получении условий договора/настроек; E5/F2 и решения остаются зависимостями.
- Блокеры runtime: E5/F2 не реализованы/не приняты; D09 частично открыт (refund contract/фискализация/sandbox), D12 остаётся gate. Плановый срез не заблокирован.
- Рабочее дерево перед фиксацией: `docs/waves/graph.json`, `docs/plans/sberpay-start-plan/{plan,progress}.md`, `docs/waves/sberpay-start/{morefoto-plan-sync.patch,verification.json}`. Runtime не менялся. Канонические правки MoreFoto уже применены локально, их проверенный patch сохранён в ветке; соседний MoreFoto не является git-репозиторием.
- Команды: `python3 tools/verify-wave-graph.py docs/waves/graph.json`; `python3 /home/user/MoreFoto/docs/04-bitrix-modules/wave_graph.py /home/user/MoreFoto/docs/04-bitrix-modules/backend-waves.json`; `git diff --check`.

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
