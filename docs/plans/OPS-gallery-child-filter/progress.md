# OPS - прогресс фильтра галереи

## Точка продолжения

- 03.11.2026, codex/ops-gallery-child-filter от main 9b89f306905bf64e21013305844051145706e16a; main проверен gh API.
- Реализация и проверки завершены; следующий шаг - PR и frontend-only deploy. useGallery использует чистый точный фильтр.
- Проверки GF-01..GF-05 PASS. GF-06: preflight и метаданные PASS, rollout PENDING.
- E2E не запускаются. БД/галерея/фотографии не меняются. Отступы из опубликованного PR #230 сохраняются только в release-сборке.
- Рабочее дерево: новые plan/progress; прежний untracked docs/plans/OPS-stage-gallery-cleanup/ не включать.

## Проверки

GF-01 PENDING; GF-02 PENDING; GF-03 PENDING; GF-04 PENDING; GF-05 PENDING; GF-06 PENDING.

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
