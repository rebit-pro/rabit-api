# Общие технические контракты

Каталог: `rebit.share/lib/Application/Contract/<Capability>/`.
Namespace: `Rebit\Share\Application\Contract\<Capability>`.

Здесь контракты технических возможностей: кеш (`Cache`), транспорт сообщений (`Messenger`), файловые операции (`File`), разрешение токена (`Auth`). Существующие PHP-классы и namespace сохраняются. Наличие интерфейса само по себе не означает наличие реализации.

Предметные межмодульные интерфейсы, DTO и события находятся отдельно — в [`lib/Contracts/<Domain>/`](../../Contracts/README.md). Внутренние порты остаются в модуле-владельце.
