<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * One block on a page: a type name plus its settings. Blocks are stored inside
 * item.yaml under "layout", so a page and its blocks travel together.
 *
 * "title" is an editor label (it names the block in the builder, it is not
 * printed on the page) and "class" is added to the block's rendered wrapper.
 */
final class Block
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public readonly string $type,
        public readonly array $settings = [],
        public readonly ?string $id = null,
        public readonly string $title = '',
        public readonly string $cssClass = '',
    ) {
    }

    public function id(): string
    {
        return $this->id ?? substr(md5('block' . $this->type . serialize($this->settings)), 0, 8);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function with(string $key, mixed $value): self
    {
        $settings = $this->settings;
        $settings[$key] = $value;

        return new self($this->type, $settings, $this->id, $this->title, $this->cssClass);
    }

    /**
     * @return array{type: string, id?: string, title?: string, class?: string, settings?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $data = ['type' => $this->type];
        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        if ($this->title !== '') {
            $data['title'] = $this->title;
        }
        if ($this->cssClass !== '') {
            $data['class'] = $this->cssClass;
        }
        if ($this->settings !== []) {
            $data['settings'] = $this->settings;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $type = (string) ($data['type'] ?? '');
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
        $title = isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
        $class = isset($data['class']) && is_string($data['class']) ? trim($data['class']) : '';

        return new self($type, $settings, $id, $title, $class);
    }
}
