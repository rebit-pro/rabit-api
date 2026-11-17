**Отдельный QA этапа 7 MoreFoto36: PASS на единственном разрешённом повторе. Это не code review.**

Проверку выполнил `gpt-6-astra`, effort `max`, без делегирования. Финальный run `rabit-e2e-40b49eeedfbe`: exit **0**, runner **580.0 с** (supervisor **580.53 с**), 2026-11-17T16:14:54.483756+00:00 — 2026-11-17T16:24:37.028569+00:00. Все 47 этапов runner имеют `passed`, scope — `full gate`.

Worktree: `/home/user/rabit-api/.worktrees/ops-institution-digital-archive`; ветка `codex/ops-institution-digital-archive`. Base/merge-base `b826a157d608c63cbf9e50aab5ebc0b54de228bb`, проверенный code head и HEAD после QA `1459df1336f318905eaf5f6085daff1147243c47`.

Manifest: [source-manifest.json](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/docs/plans/OPS-institution-digital-archive/artifacts/source-manifest.json). SHA-256 `5680b31bce5dc02c089331ef794f6f017010108c7b1521c38f17f8a5a309cea7`. До первой попытки, между попытками и после повтора совпали **83/83** исходных SHA-256. Код, ожидания, таймауты и конфигурация тестов не менялись. Единственное tracked-изменение — `progress.md`, который ведёт родитель.

В обеих попытках выполнена одна и та же команда из указанного worktree:

```sh
python3 tools/run-browser-e2e.py run --groups a,b --kernel /home/user/rebit-p2p/api/public/bitrix --vendor /home/user/rabit-api/api/vendor --php-cli rabit-api-e2e-php-cli:local --php-fpm rabit-api-e2e-php-fpm:local --nginx rabit-api-e2e-nginx:local
```

| Проверка второго run | Факт |
| --- | --- |
| PHPUnit, unit + functional | 1406 PASS, 53 683 assertions, 0 failed/skipped |
| PHP lint / PHPStan | 1829 файлов / No errors; штатный PHPStan cache восстановлен |
| Frontend | lint/styles/typecheck/typecheck:e2e/build PASS; UI 129, commerce 253; 0 failed/skipped/cancelled/todo |
| Реальный Chromium + HTTP | a: 81, b: 83; всего 164 PASS, 0 failed/skipped/flaky; retries 0 |
| Native MySQL/Bitrix | Все 22 verifier PASS; отдельно Notification H1 PASS |
| Этап 7 | Browser 23,848 с; native 69 assertions PASS; visual PASS |

Counts проверены по реальным `state.json`, всем `stages`, обеим `results.json`, PHPUnit/frontend логам и success marker каждого native verifier. Каждый обязательный spec выполнен. [Сводка проверки counts](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/verified-counts.json).

**Первая попытка сохранена как FAIL.**

Run `rabit-e2e-480386667006`: browser **160 PASS / 2 FAIL / 2 skipped / 0 flaky**. G1-T14 начал работу в 15:59:49 UTC и за 30 секунд не дождался текста «Оплата не прошла» после тестовой СБП: оставалось «Проверяем оплату». До перехода на YooMoney тест не дошёл. Два зависимых serial-теста пропущены. G2-T14 не получил sandbox-платёж/чек (`provider` оказался undefined). Причина G1 не доказана как transient.

Отдельно в 16:01:52.361 UTC Codex зафиксировал exit 143 сессии 79631; управляющий runner PID 88285 исчез, его state остался с выполняющейся группой b. G1 упал раньше этого прерывания. Доступные metadata не показывают отправителя сигнала; подходящих записей OOM/kill в проверенном system journal нет. Отсутствие записи не доказывает причину. Во время G2 после исчезновения runner также остался неотвеченный fixture request.

Исходный runner успел выполнить три native verifier группы a. После фиксации сбоя на том же disposable-стенде выполнена отдельно отмеченная диагностика оставшихся 19: **18 PASS / 1 FAIL** (G1: отсутствует завершённая пара отказ СБП → оплаченная карта). Этап 7 в этой диагностике прошёл 69 assertions. Диагностика не превращает первый полный run в PASS и не переписывает его stages.

Пользователь явно разрешил один повтор как проверку воспроизводимости. До него первый FAIL/снимки/логи сохранены, его ресурсы удалены, head и 83 hash повторно сверены. Для повтора использована независимая process session с прямой записью stdout, heartbeat и записью сигналов/exit code. Аргументы runner и таймауты тестов сохранены; во втором запуске `signals=[]`, exit 0. G1/G2 прошли. Это факт неповторения сбоя, а не доказательство его первопричины. ID всех Docker-образов и frontend lock hash обеих попыток совпадают.

[Первая попытка](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/attempt-01.json) · [Её browser b results](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/api/var/e2e/rabit-e2e-480386667006/b/b/results.json) · [Диагностика native b](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/diagnostic-native-b.json) · [Исследование 143](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/interruption-investigation.json).

**Покрытие этапа 7 и метрики.**

Реальные HTTP/UI и Files-worker создали архив купленной истории: **4 исходных файла, 12 купленных строк**, точные ZIP/CSV/байты, исходные имена, разнесение коллизий и исключение подарков/печатных/неоплаченных/late строк. Проверены идемпотентность, отсутствующий оригинал без частичного ready, organizer/curator/teacher/anonymous, отзыв назначения для уже выданной ссылки и неизменность истёкшего buyer key.

Native MySQL подтвердил GET_LOCK двух сессий, lease/owner/CAS, запрет публикации stale owner, recovery после записи, empty, повтор миграции и guarded down, семь суток от готовности, закрытие скачивания до cron, purge всех attempt/tmp при сохранении состава/CSV и Media-оригиналов. Покупательские выдача/ZIP/письма и остальные 22 native-проверки прошли.

Синтетический источник **67108864 bytes (64 МиБ)**: PHP peak delta **0 bytes**, RSS delta **0 KiB**; buyerExpiryPreserved=true. Эти значения — добавочный high-water mark, не полная память процесса. Пределы 20 ГиБ / 10 000 файлов / 50 000 связей не бенчмаркировались. [Native log](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/api/var/e2e/rabit-e2e-40b49eeedfbe/b/verify-institution-digital.log) · [Сохранённые native metrics](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/attempt-02-institution-digital-proof.json).

**Визуальная оценка: PASS.** Все три свежих PNG второго run реально открыты через `view_image`, без preview tools. Desktop 1440×1000: диалог целиком помещается, даты/сроки/ZIP/CSV и закрытие читаются. Mobile 390×844: сообщение о скачивании не сжато и не обрезано, кнопки и карточка читаются. Mobile-footer: после вертикальной прокрутки полностью доступны пояснение/ограничения и «Закрыть». Горизонтальный overflow и полная высота notice дополнительно проверены browser assertions. Отдельной visual-серии empty/expired нет; они покрываются unit/native.

[desktop](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/screenshots-attempt-02/institution-digital-desktop.png) · [mobile](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/screenshots-attempt-02/institution-digital-mobile.png) · [mobile-footer](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/screenshots-attempt-02/institution-digital-mobile-footer.png) · [Визуальные наблюдения и SHA-256](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/visual-attempt-02.json).

**Ресурсы и границы результата.** Оба run имеют `stopped=true`, `ownerRestored=true`, `cleanupErrors=[]`. Проверка Docker по меткам каждого run нашла **0 контейнеров, 0 сетей и 0 томов**. Состояние, StartedAt и RestartCount всех 56 исходных контейнеров сохранились. Основной Compose/рабочая БД не запускались и не изменялись; kernel/vendor использовались read-only. Старые отчёты/PNG не перезаписывались; контроль 10 ключевых артефактов первой попытки после повтора PASS.

Opt-in heavy media bench и Cucumber demo не входят в выполненную команду; 0 skipped относится к её обязательному полному a+b набору. QA не выполнял code review, commit, push, PR, merge или deploy. Результат закрывает отдельную QA-проверку указанного code head; решение о merge остаётся у родителя с учётом остальных требований.

Основные доказательства: [state.json](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/api/var/e2e/rabit-e2e-40b49eeedfbe/state.json) · [лог полного повтора](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/attempt-02.log) · [browser a](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/api/var/e2e/rabit-e2e-40b49eeedfbe/a/a/results.json) · [browser b](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/api/var/e2e/rabit-e2e-40b49eeedfbe/b/b/results.json) · [83 hash после QA](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/manifest-after.json) · [очистка обоих run](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/cleanup-final.json) · [машиночитаемый отчёт](/home/user/rabit-api/.worktrees/ops-institution-digital-archive/var/institution-digital-archive/premerge-astra-qa/report.json).
