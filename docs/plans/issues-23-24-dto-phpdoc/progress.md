# Issues #23 и #24 — журнал

## Точка продолжения

- Дата: 2026-09-21.
- Ветка: `codex/issues-23-24-dto-phpdoc`.
- Worktree: `/home/user/rabit-api-worktrees/issues-23-24-dto-phpdoc`.
- Base и HEAD перед первым коммитом документации:
  `6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc` (origin/main, merge F1).
  После публикации точный HEAD проверяется через `git rev-parse HEAD` и `gh pr view --json headRefOid`.
- Issues: [#23](https://github.com/rebit-pro/rabit-api/issues/23) и
  [#24](https://github.com/rebit-pro/rabit-api/issues/24), оба OPEN.
- PR: ещё не создан; предназначен только для плана, без закрытия issues.
- Документация: [план](plan.md), [A8](../../waves/a8/README.md),
  [F1](../F1_staff_requests/plan.md).
- Завершено: чтение issues/review, проверка merge F1, изолированный worktree,
  первичная инвентаризация путей, план с рисками и T01–T12.
- Сейчас: документы проверены, выполняется commit/push и создание PR.
- Один следующий шаг: закоммитить проверенный план и создать PR в main через gh.
- Блокеров для плана нет. Реализация отложена по прямому указанию пользователя.
  Решения по error mapping/передаче auth-контекста относятся к будущей реализации.
- Рабочее дерево: только новые `docs/plans/issues-23-24-dto-phpdoc/plan.md` и
  `progress.md`; код приложения не изменялся.
- Следующие команды: `git diff --cached --check`; `git diff --cached --name-only`;
  `git status --short`; после PR — `/home/user/.local/bin/gh pr view --json url,state,baseRefName,headRefName,headRefOid,files`.

## Хронология

### 2026-09-21 — запрос и разведка

- Пользователь поручил объединить issues #23/#24 в одном PR в отдельном worktree.
- Исходный checkout: `/home/user/rabit-api`, ветка `codex/e4-private-storefront`,
  HEAD `b8549339488fd24c208b5a69a8d9e26974d4b785`, рабочее дерево было чистым.
  Его ветка и файлы не переключались; код E4 не переносился.
- Прочитаны AGENTS.md/CLAUDE.md, issues через
  `/home/user/.local/bin/gh issue view 23 --json number,title,body,state,url,comments`
  и аналогичную команду для #24; контекст PR #21/#25 — через `gh pr view --json number,title,reviews`.
- `gh pr list --state all --limit 12 --json number,title,state,mergedAt,headRefName,baseRefName,url`:
  F1/PR #25 MERGED 2026-09-21, dependency закрыта.
- `git fetch origin main` через HTTPS и credential helper gh: PASS, main
  `6b8164747e3ed3f6f73a7a91efc0d28b2eb365bc`.
- Создан worktree командой
  `git worktree add -b codex/issues-23-24-dto-phpdoc /home/user/rabit-api-worktrees/issues-23-24-dto-phpdoc origin/main`.
  `git rev-parse HEAD` совпал с main; исходный checkout не изменён.
- Стандартный терминал Codex не стартовал из-за setup refresh error; использован разрешённый
  shell с повышенными правами. App create_worktree не сработал из-за dubious ownership Windows Git;
  worktree создан штатным Git внутри WSL, глобальный safe.directory не менялся.
- Выполнена механическая инвентаризация PHP-путей через
  `Get-ChildItem -Recurse -File -Filter '*.php'` с фильтрами Application/*UseCase.php
  и Application/Domain/**/Service/*.php: 47 UseCase + 23 Service = 70 файлов.
  Смысловой аудит всех 70 классов ещё не выполнен.
- Прочитаны commerce controllers/factories/authorized services, DTO/mapper, DI/routes,
  общая F1 request/error/auth обвязка, composer/frontend scripts и процедура A8.
  Выявлены риски отличающегося error contract, транзакционной проверки токена,
  строгого wire parsing и поведения в Application DTO. Решения внесены в план.
- Постороннее замечание: E2E runner и A8 содержат исторические defaults rebit-p2p.
  Для будущего запуска предусмотрены явные E2E_KERNEL_ROOT/E2E_VENDOR_ROOT;
  код runner и исторические документы не изменены.

### 2026-09-21 — изменение scope пользователем

- Пользователь уточнил: «в реализацию брать не будем», нужен только план, commit и PR.
- На момент уточнения код, конфигурация и тесты не менялись.
- Scope ограничен plan.md/progress.md. Runtime, E2E, formatter и новые тесты не запускались.
- Реализация T04–T12 ожидает отдельного поручения; публикация плана её не запускает.

## Результаты тест-кейсов

| ID | Статус | Дата | Команда/доказательство |
| --- | --- | --- | --- |
| T01 | PASS | 2026-09-21 | `git diff --cached --check` (exit 0); `git diff --cached --name-only`: только plan.md и progress.md |
| T02 | PASS | 2026-09-21 | PowerShell: проверено ровно 2 UTF-8 файла, наличие T01–T12 в каждом, отсутствие U+FFFD; ручное review scope/рисков/статусов завершено |
| T03 | PENDING | 2026-09-21 | PR ещё не создан; после push проверить head/files/base и OPEN issues через gh |
| T04 | PENDING | 2026-09-21 | Только инвентаризация 70 путей; смысловой аудит и сравнение PHP-токенов не запускались |
| T05 | PENDING | 2026-09-21 | Architecture/unit не запускались: реализация отложена |
| T06 | PENDING | 2026-09-21 | HTTP happy paths не запускались: реализация отложена |
| T07 | PENDING | 2026-09-21 | HTTP validation cases не запускались: реализация отложена |
| T08 | PENDING | 2026-09-21 | Auth/replay cases не запускались: реализация отложена |
| T09 | PENDING | 2026-09-21 | Idempotency/revision cases не запускались: реализация отложена |
| T10 | PENDING | 2026-09-21 | PHP lint/PHPStan/PHPUnit/CS Fixer не запускались: PHP diff отсутствует |
| T11 | PENDING | 2026-09-21 | Frontend/full E2E не запускались: runtime diff отсутствует |
| T12 | PENDING | 2026-09-21 | Disposable стенд и visual desktop/mobile не запускались |

### 2026-09-21 — проверка перед первым commit/push

- `git diff --cached --check`: PASS, exit 0.
- `git diff --cached --name-only`: ровно два документа в папке задачи; PHP/frontend/config diff отсутствует.
- T02: read-only PowerShell проверка через `Get-ChildItem -File` и
  `[System.IO.File]::ReadAllText`: 2 файла, все T01–T12, нет символов повреждённого Unicode.
- Ручное review: будущие проверки имеют PENDING; текущий PR не запускает реализацию и не закрывает issues.
- Исходная попытка SSH fetch завершилась ошибкой соединения; выполненный после неё HTTPS fetch
  через gh credential helper успешно подтвердил актуальный main. Это не блокирует публикацию.
- Перед commit в индексе только plan.md/progress.md; следующий шаг — commit, push и PR через gh.