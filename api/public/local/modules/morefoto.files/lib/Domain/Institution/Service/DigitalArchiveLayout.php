<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Institution\Service;

use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;

/** Сохраняет исходный basename; одинаковые имена разделяет каталогами с полными устойчивыми ID. */
final readonly class DigitalArchiveLayout
{
    public function filename(string $name): string
    {
        if ('' === $name || 255 < strlen($name) || 1 !== preg_match('//u', $name)
            || 1 === preg_match('/[<>:"\\\\\/|?*\x00-\x1f\x7f]/u', $name)
            || '.' === $name || '..' === $name || rtrim($name, '. ') !== $name
            || 1 === preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/iD', $name)) {
            throw new DigitalArchiveException('INVALID_ORIGINAL_FILENAME');
        }

        return $name;
    }

    public function group(string $name, string $id): string
    {
        $clean = preg_replace('/[<>:"\\\\\/|?*\x00-\x1f\x7f]/u', '-', $name) ?? '';
        $clean = trim(mb_strcut($clean, 0, 150, 'UTF-8'), ". \t\r\n");

        return ('' === $clean ? 'Группа' : $clean) . ' [' . $id . ']';
    }

    /**
     * Совпадение имени файла с ID каталога тоже требует вложенности: распространяем её очередью, каждый кадр один раз.
     *
     * @param array<string, string> $filenames исходное имя по ID кадра одной группы
     *
     * @return array<string, true> кадры, которым нужен каталог photoId
     */
    public function collisions(array $filenames): array
    {
        $byName = [];
        foreach ($filenames as $id => $name) {
            $byName[mb_strtolower($this->filename($name), 'UTF-8')][] = $id;
        }
        $nested = [];
        $queue = [];
        foreach ($byName as $ids) {
            if (1 < count($ids)) {
                foreach ($ids as $id) {
                    $nested[$id] = true;
                    $queue[] = $id;
                }
            }
        }
        for ($index = 0; $index < count($queue); ++$index) {
            foreach ($byName[mb_strtolower($queue[$index], 'UTF-8')] ?? [] as $id) {
                if (!isset($nested[$id])) {
                    $nested[$id] = true;
                    $queue[] = $id;
                }
            }
        }

        return $nested;
    }

    /** @param array<string, true> $nested кадры с каталогом photoId */
    public function entry(string $group, string $photoId, string $filename, array $nested): string
    {
        $filename = $this->filename($filename);

        return $group . '/' . (isset($nested[$photoId]) ? $photoId . '/' : '') . $filename;
    }
}
