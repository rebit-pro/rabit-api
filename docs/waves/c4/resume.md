# Продолжение после C4

Ветка codex/c4-institution-detail, рабочая копия /home/user/rebit-p2p/api/var/worktrees/c4. База 8962230accdc8fe913ef0a12211bee14c332fdd2; C3 PR15 фактически merged. Пользователь разрешил публикацию и merge C3; это выполнено через gh CLI /home/user/.local/bin/gh. Авторизация gh штатная, токены не выводить. GitHub connector write вернул403, встроенный browsertool не стартовал; не повторять эти обходные попытки.

C4 реализована: ORG04 и полный live-экран учреждения с независимыми страницами. Backend/native/frontend/browser/visual проверки пройдены; см. verification.json. Четыре снимка сохранены локально в api/var/c4/visual. Финансовый unavailable принят A6; имена сотрудников пока представлены фактическими ID до B2, ORG11 не подключён.

Одна волна — одна ветка и один PR в main. C4 подготовлена к отдельному PR; merge/deployment C4 не выполнялись. Не считать её merged по зелёным тестам. При изменении main повторить затронутые проверки. Полные результаты и неуспешная подготовительная попытка сохранены. MoreFoto вне Git: воспроизводимый source patch и artifact hashes находятся рядом.

Рабочий frontend находится в frontend/ репозитория rabit-api; соседний /home/user/MoreFoto используется для продуктового плана и API-документации, не как основная frontend-копия.
