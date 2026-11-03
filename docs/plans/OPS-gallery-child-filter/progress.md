# OPS - прогресс фильтра галереи

## Точка продолжения

- 03.11.2026, codex/ops-gallery-child-filter от main 9b89f306905bf64e21013305844051145706e16a; main проверен gh API.
- Завершено: исправление, PR #231 и frontend-only публикация на app.morefoto36.ru. Продуктовый commit fc1e88bd1e270dc90655a382ac0ac51c28969cae. Следующий шаг - пользователь обновляет открытую галерею. Обязательных действий агента не осталось.
- Проверки GF-01..GF-06 PASS. После публикации 37 серий/148 назначений сохранены, каждый код возвращает 4 кадра. Приватная ссылка и изображения не публиковались в GitHub.
- E2E не запускаются. БД/галерея/фотографии не меняются. Отступы из опубликованного PR #230 сохраняются только в release-сборке.
- Рабочее дерево: продуктовый код закоммичен; финальное изменение - только plan/progress. Прежний untracked docs/plans/OPS-stage-gallery-cleanup/ не включать. PR открыт, merge не выполняется. Продолжение при необходимости: gh pr view 231 --repo rebit-pro/rabit-api; git status --short.

## Проверки

GF-01 PASS; GF-02 PASS; GF-03 PASS; GF-04 PASS; GF-05 PASS; GF-06 PASS.

## 03.11.2026 - диагностика

GalleryFilters передаёт один код через query.child. GallerySnapshot уже содержит children[].code и список назначений фотографий каждой серии. В useGallery после flatten выполняется includes по коду кадра, поэтому A совпадает с AA/BA и др. Выбор точного объекта серии устраняет общий класс ошибки и сохраняет общие кадры в правильном назначении.

## Воспроизведение и исправление

- Исходный includes перенесён без изменения в чистую функцию для регрессионного прогона. Команда node --experimental-strip-types --test tests/ui/gallery-filter.test.mjs: 1 PASS, 6 FAIL; воспроизведены A/AA/BA, ZZ/ZZZ, кадры и общие назначения.
- Затем реализован точный выбор children[].code с точным поиском кадра, useGallery подключён к этой функции. Новые компоненты и изменения UI не требуются.

## Проверки 03.11.2026 перед commit

- GF-01 PASS: node --experimental-strip-types --test tests/ui/gallery-filter.test.mjs. Все 44 тестовые серии по 4 кадра, включая A-Z, AA-AK, BA/BB/CC/AAA/BBB/CCC/ZZZ, выбираются отдельно. Семь unit-кейсов PASS.
- GF-02 PASS: той же командой проверены пустой код, пустая галерея, неизвестный код и ZZ при наличии ZZZ.
- GF-03 PASS: точный поиск A001/AA001 и отсутствие совпадения по 001.
- GF-04 PASS: lowercase, пробелы, alphanumeric legacy A001/A0011 и назначения общего кадра.
- Дополнительно node --experimental-strip-types --test tests/ui/gallery-filter.test.mjs tests/ui/gallery-path.test.mjs tests/commerce/photo-paging.test.mjs: 18/18 PASS.
- GF-05 PASS: node node_modules/eslint/bin/eslint.js src/modules/morefoto/gallery/filter.ts src/modules/morefoto/gallery/composables/useGallery.ts tests/ui/gallery-filter.test.mjs; npm run build (vue-tsc --noEmit + Vite); git diff --check. Сборка в WSL Node 24.15.0, root только для существующего root-owned cache node_modules. CSS не изменялся, stylelint неприменим.
- Самопроверка diff: только общий фильтр, подключение computed и unit-тесты; URL нормализация и навигация PhotoViewer сохранены. Нет выделения букв из имени файла или специальных исключений A/BB/CC. Выбор основан на GalleryChild.code.
- GF-06 частично PASS: read-only JSON текущей галереи проверен тем же filterGalleryPhotos локально в Node. 37 серий/148 фото, все 37 кодов дают ровно свои 4 назначения. Изображения не скачивались; сохраняются только агрегаты и SHA метаданных, приватная ссылка в PR не включается.
- Preflight сервера: frontend morefoto-frontend:pr-230-71918f5, 2 реплики, один Ready Active node; свободно 12.10 GB. Снимки Spec и метаданных сохранены локально в ignored var/ops-gallery-child-filter.
- E2E/браузерные прогоны и стенды не запускались. Merge не выполняется.

## Подготовка публикации

- Создан PR https://github.com/rebit-pro/rabit-api/pull/231, продуктовый commit fc1e88bd1e270dc90655a382ac0ac51c28969cae. Merge не выполнялся.
- Release-only дерево извлечено из этого commit; только StaffConsentDialog.vue восстановлен из ранее одобренного и опубликованного 71918f5 (PR #230). Все 835 файлов сверены с источниками по содержимому/SHA-256. PR #231 не включает diff #230.
- Вспомогательная проверка release была перезапущена с обходом только tracked-файлов вместо дерева зависимостей. Продуктовый код при этом не менялся.
- Перед rollout прежний image morefoto-frontend:pr-230-71918f5 остаётся rollback; затрагивается только morefoto_frontend.

## Публикация и итоговая проверка 03.11.2026

- Release-only npm run build с VITE_API_URL=/api, VITE_API_MOCKS_ENABLED=false и версией pr-231-fc1e88b - PASS (typecheck + Vite). Изолированная сборка включает только frontend этого PR и уже опубликованный StaffConsentDialog из #230. Manifest 835 файлов сохранён локально. Ошибка кодировки stdout вспомогательного Windows Python после успешной сборки устранена использованием UTF-8; результат сборки дополнительно проверен по логу и артефактам, повторная сборка не требовалась.
- Опубликован образ `morefoto-frontend:pr-231-fc1e88b`, image ID `sha256:27aac1f7799dd2f20875830427e6c664ff084a128e91165a174d798b548fe6b9`. Обновлён только morefoto_frontend. Rollout completed, 2/2 реплики. Нормализованный Spec frontend, кроме Image, совпал; все остальные service Spec совпали.
- Публичный HTML ссылается на новую сборку. HTTPS-артефакты `CabinetLayout-BgTgyCJ3.css, GalleryPage-B2-j8QR6.js` побайтно совпали с локальными по SHA-256. Исправленная форма согласия сохранила text-wrap:balance и отступы.
- /health - 200; защищённый /api/v1/institutions без токена - 401.
- GF-06 PASS: после rollout повторно прочитаны только метаданные текущей галереи. 37 серий, 148 назначений, все коды дают ровно свои 4 кадра; SHA состава назначений до/после совпал: c9911cf515ada77ea6568bbc954b93fbd56831623d87afab9d3d221ce87a0173. Файлы фотографий и БД не изменялись.
- E2E не запускались. Это unit-проверки чистого фильтра и read-only проверка артефактов/JSON; браузерного прогона и тестового стенда не было.
- Автоматический rollback предусматривался и не потребовался. Старый image сохранён. Команда отката: ssh photo.rebit-pro 'docker service update --no-resolve-image --image morefoto-frontend:pr-230-71918f5 --detach=false morefoto_frontend'.
- PR https://github.com/rebit-pro/rabit-api/pull/231 открыт. Final docs-only commit не меняет frontend-дерево опубликованного продуктового commit.
