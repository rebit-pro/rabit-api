<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\ValueObject;

/**
 * Изображение с поддержкой webp, retina и lazyload.
 */
final readonly class Image
{
    private const string PLACEHOLDER_IMG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyNpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMTQ4IDc5LjE2NDAzNiwgMjAxOS8wOC8xMy0wMTowNjo1NyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIDIxLjAgKFdpbmRvd3MpIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjZGRUIwRDEyM0JDQjExRUI5MzA4QTU3RkJBMEIyQkYwIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjZGRUIwRDEzM0JDQjExRUI5MzA4QTU3RkJBMEIyQkYwIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NkZFQjBEMTAzQkNCMTFFQjkzMDhBNTdGQkEwQjJCRjAiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6NkZFQjBEMTEzQkNCMTFFQjkzMDhBNTdGQkEwQjJCRjAiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz4yEoi1AAAAEElEQVR42mL4//8/A0CAAQAI/AL+26JNFgAAAABJRU5ErkJggg==';

    public function __construct(
        /** Основной src изображения */
        public string $src,
        /** alt и title текст изображения */
        public string $alt,
        public ?string $webp = null,
        public ?string $retinaSrc = null,
        public ?string $retinaWebp = null,
        public ?int $width = null,
        public ?int $height = null,
        /**
         * CSS классы изображения
         *
         * @var string[]
         */
        public array $classes = [],
        public bool $lazyload = false,
        /** placeholder для lazyload, null если не использовать */
        public ?string $lazyloadPlaceholder = self::PLACEHOLDER_IMG,
    ) {}

    /**
     * Генерирует img тег.
     *
     * Поддерживает lazyload через data-src.
     */
    public function toHtml(): string
    {
        return sprintf(
            '<img%s>',
            $this->buildImgAttributes(),
        );
    }

    /**
     * Генерирует HTML <picture> тег с поддержкой:
     *  - webp
     *  - retina (2x)
     *  - lazyload
     *
     * Если webp отсутствует — возвращает обычный <img>.
     */
    public function toPictureHtml(): string
    {
        if (null === $this->webp) {
            return $this->toHtml();
        }

        // srcset для webp
        $webpSrcset = $this->buildSrcset($this->webp, $this->retinaWebp);

        // fallback srcset (jpg/png)
        $fallbackSrcset = $this->buildSrcset($this->src, $this->retinaSrc);

        // Для lazyload используем data-srcset
        $sourceAttr = $this->lazyload ? 'data-srcset' : 'srcset';

        return sprintf(
            '<picture>
                <source type="image/webp" %s="%s">
                <source %s="%s">
                <img%s>
            </picture>',
            $sourceAttr,
            $webpSrcset,
            $sourceAttr,
            $fallbackSrcset,
            $this->buildImgAttributes(includeSrc: false),
        );
    }

    /**
     * Собирает HTML-атрибуты для <img>.
     *
     * @param bool $includeSrc Добавлять ли src/data-src атрибут
     */
    private function buildImgAttributes(bool $includeSrc = true): string
    {
        $attributes = [
            'class' => $this->buildClasses(),
            'alt' => $this->alt,
            'title' => $this->alt,
        ];

        if (null !== $this->width) {
            $attributes['width'] = (string)$this->width;
        }

        if (null !== $this->height) {
            $attributes['height'] = (string)$this->height;
        }

        if (true === $includeSrc) {
            if ($this->lazyload) {
                if (null !== $this->lazyloadPlaceholder) {
                    $attributes['src'] = $this->lazyloadPlaceholder;
                }

                $attributes['data-src'] = $this->src;
            } else {
                $attributes['src'] = $this->src;
            }
        }

        return $this->attributesToString($attributes);
    }

    private function buildClasses(): string
    {
        $classes = $this->classes;

        if ($this->lazyload) {
            $classes[] = 'lazyload';
        }

        return implode(' ', $classes);
    }

    /**
     * Собирает srcset с поддержкой retina (2x).
     */
    private function buildSrcset(string $src, ?string $retina): string
    {
        if (null === $retina) {
            return $src;
        }

        return sprintf('%s 1x, %s 2x', $src, $retina);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function attributesToString(array $attributes): string
    {
        $result = '';

        foreach ($attributes as $name => $value) {
            $result .= sprintf(' %s="%s"', $name, $value);
        }

        return $result;
    }
}
