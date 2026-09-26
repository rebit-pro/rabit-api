#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Разовая операция выкатки #141: медиа-воркер переходит с root на www-data.
Превью, созданные воркером от root, получают владельца www-data, чтобы PHP-FPM мог их удалять.

Запуск на сервере от root:
  sudo RUNTIME_DATA_DIR=/path/to/runtime bash media-previews-owner.sh           # только проверка
  sudo RUNTIME_DATA_DIR=/path/to/runtime bash media-previews-owner.sh --apply   # смена владельца

Переменные:
  RUNTIME_DATA_DIR   runtime production: превью — $RUNTIME_DATA_DIR/upload/morefoto/previews,
                     приватные оригиналы — $RUNTIME_DATA_DIR/private-media
  PREVIEWS_DIR       каталог превью явно (stage); путь должен оканчиваться на /morefoto/previews
  PRIVATE_MEDIA_DIR  каталог приватных оригиналов явно; только отчёт, не меняется
  MEDIA_UID, MEDIA_GID  www-data в образах rabit-api-php-cli/php-fpm (по умолчанию 1000:1000;
                     имя www-data на хосте обычно означает UID 33 — его не использовать)

Проверка ничего не меняет и завершается с кодом 1, если есть превью с другим владельцем.
--apply меняет владельца только таких записей (для ссылок — самой ссылки, не цели), не выходя за файловую систему.
Перед --apply остановите медиа-воркер, чтобы он не создавал новых каталогов от root.
EOF
}

log() {
    printf '[media-previews-owner] %s\n' "$1" >&2
}

fail() {
    printf '[media-previews-owner][error] %s\n' "$1" >&2
    exit 1
}

# Число записей не с владельцем MEDIA_UID:MEDIA_GID и до пяти примеров «uid:gid права путь».
report_foreign() {
    local directory="$1"
    local title="$2"
    local count

    count="$(find "$directory" -xdev \( ! -uid "$MEDIA_UID" -o ! -gid "$MEDIA_GID" \) -printf '.' | wc -c)"
    log "$title: $directory — записей не от $MEDIA_UID:$MEDIA_GID: $count"
    find "$directory" -xdev \( ! -uid "$MEDIA_UID" -o ! -gid "$MEDIA_GID" \) -printf '  %U:%G %m %p\n' | sed -n '1,5p' >&2
    printf '%s' "$count"
}

apply=false
case "${1:-}" in
    '') ;;
    --apply) apply=true ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; exit 2 ;;
esac

readonly MEDIA_UID="${MEDIA_UID:-1000}"
readonly MEDIA_GID="${MEDIA_GID:-1000}"
[[ "$MEDIA_UID" =~ ^[0-9]+$ && "$MEDIA_GID" =~ ^[0-9]+$ && 0 -ne "$MEDIA_UID" ]] \
    || fail 'MEDIA_UID и MEDIA_GID — числа, MEDIA_UID не 0.'
[[ 0 -eq "$(id -u)" ]] || fail 'Запускать от root: каталоги превью принадлежат root, приватные оригиналы закрыты 0700.'

runtime="${RUNTIME_DATA_DIR:-}"
previews="${PREVIEWS_DIR:-${runtime:+$runtime/upload/morefoto/previews}}"
private="${PRIVATE_MEDIA_DIR:-${runtime:+$runtime/private-media}}"
[[ -n "$previews" ]] || fail 'Задайте RUNTIME_DATA_DIR или PREVIEWS_DIR.'
[[ -d "$previews" ]] || fail "Нет каталога превью: $previews"
previews="$(realpath -e "$previews")"
[[ "$previews" == */morefoto/previews ]] || fail "Каталог превью должен оканчиваться на /morefoto/previews: $previews"

foreign="$(report_foreign "$previews" 'Превью')"
if [[ -n "$private" ]]; then
    [[ -d "$private" ]] || fail "Нет каталога приватных оригиналов: $private"
    private_foreign="$(report_foreign "$private" 'Приватные оригиналы (не меняются)')"
    if [[ 0 -ne "$private_foreign" ]]; then
        log 'ВНИМАНИЕ: воркер от www-data не прочитает оригиналы 0600 другого владельца — разобрать вручную.'
    fi
fi

if ! "$apply"; then
    [[ 0 -eq "$foreign" ]] || { log 'Проверка: нужна смена владельца (--apply).'; exit 1; }
    log 'Проверка: все превью принадлежат www-data.'
    exit 0
fi

if [[ 0 -ne "$foreign" ]]; then
    find "$previews" -xdev \( ! -uid "$MEDIA_UID" -o ! -gid "$MEDIA_GID" \) -exec chown -h "$MEDIA_UID:$MEDIA_GID" {} +
fi
[[ 0 -eq "$(report_foreign "$previews" 'Превью после смены')" ]] || fail 'Остались превью другого владельца.'
log 'Готово: все превью принадлежат www-data.'
