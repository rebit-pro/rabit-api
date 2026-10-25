# E10 — отчёты продаж по ролям и выгрузка в PDF

Ветка `codex/e10-sales-reports` от main `463d1f9`. Зависимости B2, D2 и E9 слиты. План и журнал: `docs/plans/E10_sales-reports/`. Волна добавлена в граф этой же веткой (`postMvp`, N2 зависит от неё); попутно G2 отмечена merged (PR #212, 65bd194).

Раздел «Отчёты» в кабинете для всех ролей:

| Роль | Что видит |
| --- | --- |
| Воспитатель | Свою группу по детям и свою съёмку по своим группам |
| Руководитель | Своё учреждение: съёмки, группы, дети |
| Куратор | Назначенные учреждения |
| Организатор | Все учреждения |

В отчёте группы для каждого ребёнка показаны код и миниатюра, статус оплаты, заказы, печать, цифровые файлы и сумма. На верхних уровнях — строки групп, съёмок или учреждений. Любой уровень скачивается в PDF фирменного шаблона «Море фото».

## Решения (24.10.2026)

- **E10-DEC-01.** «Кто заказал» — код и миниатюра ребёнка. Email, телефон, имя покупателя, номер и ключ заказа не показываются.
- **E10-DEC-02.** Куратор видит только назначенные учреждения.
- **E10-DEC-03.** PDF собирается на сервере (mpdf).
- **E10-DEC-04.** Организатор получает тот же отчёт и PDF.
- **E10-DEC-05.** Показатели «продано» — по E9-DEC-01…05: только `paid` без поздней оплаты; digital без J4-копий и подарка; комплекты отдельно.
- **E10-DEC-06.** Статусы ребёнка:
  - «Оплатил» — есть оплаченный заказ;
  - «Поздняя оплата» — есть только оплата после закрытия;
  - «Заказ без оплаты» — есть только `unpaid`, `pending` или `declined`;
  - «Нет заказа».
- **E10-DEC-07.** Строки группы — текущие дети разметки и дети из заказов этой группы. Ребёнок, переведённый после заказа (D3), остаётся в исходной группе под кодом из снимка.
- **E10-DEC-08.** Миниатюра — первый готовый кадр ребёнка, только для переданной группы и только в области отчёта.
- **E10-DEC-09.** В отчёте съёмки воспитатель видит только свои группы.

## Контракты

### COM-15 `GET /api/v1/sales-report`

Bearer. Фильтры `institutionId`, `shootId`, `groupId`; уровень задаёт самый узкий из них.

```json
{
  "data": {
    "level": "group",
    "institution": { "id": "…", "name": "Детский сад № 5" },
    "shoot": { "id": "…", "name": "Осень" },
    "group": { "id": "…", "name": "Солнышко" },
    "totals": { "children": 24, "orderedFamilies": 15, "paidFamilies": 12, "paidOrders": 14, "prints": 40, "digitalFiles": 18, "bundles": 2, "paidTotal": 2625000 },
    "products": [{ "id": "…", "name": "Печать 10×15", "prints": 30 }],
    "rows": [],
    "children": [{ "id": "…", "code": "A", "status": "paid", "orders": 2, "paidOrders": 1, "prints": 3, "digitalFiles": 2, "bundles": 0, "paidTotal": 125000, "hasPreview": true }],
    "generatedAt": "2026-10-24T12:00:00+00:00"
  }
}
```

- `level`: `all`, `institution`, `shoot` или `group`.
- `rows` — строки уровня (`id`, `name`, `totals`), включая элементы без продаж. У группы `rows` пуст, у остальных уровней пуст `children`.
- Деньги — копейки, сумма строк оплаченных заказов; итог группы равен COM-14.
- Область:
  - organizer — всё;
  - curator и head — учреждения назначения;
  - teacher — назначенные группы;
  - остальные роли — 403 `FORBIDDEN`;
  - чужой, несуществующий или некорректный объект — 404 (`GROUP_NOT_FOUND`, `SHOOT_NOT_FOUND`, `INSTITUTION_NOT_FOUND`);
  - без входа — 401.

### COM-16 `GET /api/v1/sales-report/pdf`

Те же фильтры и та же область. Ответ — `application/pdf` с `Content-Disposition: attachment; filename="morefoto-otchet-<уровень>-<дата МСК>.pdf"`.

### COM-17 `GET /api/v1/sales-report/children/{child_id}/preview`

Миниатюра ребёнка (WebP). Требуется область отчёта и переданная ссылка группы; иначе 404 `CHILD_NOT_FOUND` или `PREVIEW_UNAVAILABLE`.

### Изменение E9

`SalesAccess` расширен руководителем. COM-14 теперь отвечает head для групп его учреждений; раньше отвечал 403.

## Реализация

- **`morefoto.commerce`, Sales:**
  - `SalesReportController` → `GetSalesReportUseCase`, `ExportSalesReportPdfUseCase`, `GetChildPreviewUseCase`;
  - `SalesReportScope` — уровень и группы через `GroupDirectoryInterface`, страницы по 100;
  - `SalesReportRepository` — по одному агрегату на разбивку и итог, плюс печать по продукции;
  - `ChildSalesStatusPolicy` — правило E10-DEC-06.
- **PDF:** `Infrastructure/Sales/Pdf/` — `SalesReportHtml` (разметка шаблона) и `MpdfSalesReportWriter`.
  - Шрифты — статические TTF Manrope 500/600 и Golos Text 400/600 в `resources/pdf/fonts` (OFL), собраны из woff2 frontend.
  - Временный каталог — `MOREFOTO_PDF_TEMP_PATH` или `sys_get_temp_dir()/morefoto-mpdf`.
- **`morefoto.media`:** контракт `Rebit\Share\Contracts\Media\GroupChildrenInterface` — дети групп, счётчики, поиск ребёнка, миниатюра. Реализация — `GroupChildren` + `GroupChildRepository`.
- **`rebit.share`:** `PdfFileOutputDto`, `PdfFileResponse`, `pdfFile()` в `AuthenticatedApiJsonController`.
- Миграций нет.

## Frontend

`frontend/src/modules/morefoto/sales-report/`. Каталог не называется `reports`: это имя занято игнором отчётов E2E в `frontend/.gitignore`.

- Пункт меню «Отчёты» для четырёх ролей (live), маршрут `/cabinet/reports?institutionId=&shootId=&groupId=`.
- При входе без фильтров руководитель сразу попадает в своё учреждение, а воспитатель спускается до единственной группы. Хлебные крошки ведут вверх до верхнего уровня роли.
- Плитки итога, таблица уровня (`UiDataTable`, сортировка и страницы в браузере), дети с миниатюрой и статусом, печать по форматам, кнопка «Скачать PDF».

## Проверки

- **PHPUnit:**
  - `SalesReportTest` — области ролей, предки, уровни, статусы, переносы, миниатюра;
  - `SalesReportPdfTest` — разметка без ПДн, валидный PDF с фирменными шрифтами;
  - `SalesReportArchitectureTest`;
  - `GroupChildrenTest`;
  - обновлены `GroupSalesTest` и `GroupSalesArchitectureTest`.
- **Frontend unit:** `tests/ui/sales-report.test.mjs`; навигация.
- **E2E `zzzzzzzzzzzzzz-sales-report` (последняя в группе b):**
  - группа = COM-14 и статусы по COM-12; уровни съёмки, учреждения и «все» сходятся;
  - роли: another-teacher, teacher, head до и после назначения, c4-head, curator, 401/404;
  - PDF и миниатюра;
  - разделы воспитателя и руководителя на 1440 и 390 px, скачивание PDF, скриншоты.
- **E9-спека:** вместо «head → 403» проверяется «руководитель чужого учреждения → 404»; позитивный случай — в E10-спеке.
- **Полный гейт PASS:**
  - прогон `rabit-e2e-2c525d639406` на c52ac3b: a 81, b 75, 19 верификаторов БД;
  - тестовый магазин ЮKassa выключен из-за #174 (прецедент D4);
  - с включённым магазином E10-спека тоже проходит (прогон `rabit-e2e-d765cec03cd7`), там падали только G1-T14/G2-T14.
- **Гейт нашёл дефект, который не видели unit, typecheck и ревью:** маршрут `SalesReports` отсутствовал в белом списке live-guard (`frontend/src/router/index.ts`), раздел был недоступен. Исправлено; тест навигации теперь сверяет пункты live-меню со списком guard.

## Выкладка

- Только код backend и frontend: миграций и новых сервисов нет.
- Порядок:
  1. backend (FPM и backend nginx);
  2. DI smoke `SalesReportController`, `GetSalesReportUseCase`, `ExportSalesReportPdfUseCase`, `GetChildPreviewUseCase`, `GroupChildrenInterface`;
  3. пробный `GET /api/v1/sales-report/pdf` под организатором;
  4. frontend.
- Для миниатюр в PDF нужен GD с WebP: production- и development-образы его собирают.
- Frontend без нового backend показывает в разделе ошибку с повтором; остальной кабинет работает.

## Ограничения

- Нет фильтра по датам и периодам, выгрузки XLSX/CSV и рассылки отчётов.
- Полные финансы (комиссии, возвраты, вознаграждение) — N1; возвратов до I2 нет.
- Воспитатель и руководитель увидят раздел только после входа в кабинет: B2 и рабочая почта (#161, #218).
