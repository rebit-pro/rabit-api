# F2 — прогресс

## Точка продолжения

- Ветка `codex/f2-link-handoff` в worktree `/home/user/rabit-api-worktrees/f2-link-handoff`. Base `origin/main` 8cba22c7655b5886d5fe663523214bcd059e674b. PR ещё нет.
- Основной checkout `/home/user/rabit-api` занят параллельной сессией E5 (PR #37); его не трогать.
- Завершено:
  - перестановка F2 перед D3 в `docs/waves/graph.json` и каноническом плане MoreFoto;
  - сбор спецификации и кода;
  - согласование четырёх решений (см. plan.md);
  - plan/progress.
- Сейчас: первый commit (граф + план), затем миграция и межмодульные контракты.
- Следующий шаг: миграция `Version20260922150001` и контракты rebit.share.
- Блокеров нет. Полный `make test-e2e` — только после review без блокеров (указание пользователя от 22.09.2026 по E5).
- Рабочее дерево: `docs/waves/graph.json`, `docs/waves/f2/morefoto-graph.patch`, `docs/plans/F2_link-handoff/*` — к первому commit. Внешний MoreFoto уже изменён (без Git), резервная копия исходных файлов в scratchpad сессии.
- Команды следующей проверки:
  - `python3 tools/verify-wave-graph.py docs/waves/graph.json`
  - `python3 /home/user/MoreFoto/docs/05-rest-api/validate.py`
  - `cd /home/user/MoreFoto && node docs/05-rest-api/validate-postman.cjs`

## Хронология

### 2026-09-22 — старт и перестановка графа

- Пользователь поручил F2 параллельно с E5, считая её независимой. По графу F2 зависела от D3 («Атомарные переносы»), а D3 — от E5 (PR #37 не слит). Слитый PR #32 «D3» был исправлением stage и занимал тот же ID, отсюда путаница.
- D3 также зависит от E5 и открытых D11/D12. Пользователь выбрал вариант «F2 перед D3».
  - F2 теперь зависит от E3/F1/E4 без D11.
  - D3 зависит от D2/F1/E5/F2.
  - В D3 перенесены проверки «→ заказ», «запрет разметки после заказа» и «перенос сбрасывает подготовку»: MED-05/06, HND-02/03/04, COM-10.
- Скрипт перестановки сначала прогнан на копии MoreFoto.
  - `render-waves.py` и `build.py` на копии без изменений входа — идемпотентны (пустой diff).
  - После перестановки на копии: `validate.py` exit 0, `node docs/05-rest-api/validate-postman.cjs` из корня копии exit 0, `wave_graph.validate_graph` готовит E5 и F2, 10 негативных fixtures.
  - Первый запуск `validate.py` на копии упал на отсутствующих `source` (AUTH-01, SHR-01): копия не содержала `frontend` и `../rebit-p2p`. После symlink — PASS. Проблема среды копии, не изменения.
- Применено к `/home/user/MoreFoto` после резервной копии: `validate.py` exit 0, `validate-postman.cjs` exit 0, `diff -rq` с отрепетированной копией пуст.
- `docs/waves/graph.json` в ветке: та же перестановка плюс отметка E4 merged (байт в байт как в ветке E5). `python3 tools/verify-wave-graph.py docs/waves/graph.json`: 40 волн, 99 API, 35 legacy, `readyFromMain` = E5, F2, 10 негативных fixtures — PASS.
- Patch исходного `backend-waves.json` сохранён в `docs/waves/f2/morefoto-graph.patch`.
- Пользователь выбрал решения:
  - ключ галереи хранить открытым текстом (моя рекомендация — шифрованная копия — отклонена);
  - `sentAt` не раньше выдачи ключа;
  - заявки F1 блокируют подготовку до D3;
  - собственная revision ссылки.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда и доказательство |
| --- | --- | --- | --- |
| F2-GRAPH | PASS | 2026-09-22 | `verify-wave-graph.py` (40/99/10 negative, ready E5+F2), MoreFoto `validate.py` и `validate-postman.cjs` exit 0 |
| F2-CALENDAR | PENDING | — | — |
| F2-READINESS | PENDING | — | — |
| F2-PERMISSIONS | PENDING | — | — |
| F2-PREPARE | PENDING | — | — |
| F2-TRANSMIT | PENDING | — | — |
| F2-REPEAT | PENDING | — | — |
| F2-CORRECT | PENDING | — | — |
| F2-EXTENSION | PENDING | — | — |
| F2-CLOSE-BOUNDARY | PENDING | — | — |
| F2-INVALIDATION | PENDING | — | — |
| F2-RACE-MEDIA | PENDING | — | — |
| F2-GALLERY-QUOTE | PENDING | — | — |
| F2-TOKEN | PENDING | — | — |
| F2-ARCH | PENDING | — | — |
| F2-CONTRACT | PENDING | — | — |
| F2-UI | PENDING | — | — |
| F2-VISUAL | PENDING | — | — |
| F2-PUBLISH | PENDING | — | — |
