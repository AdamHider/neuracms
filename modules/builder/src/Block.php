<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * One block on a page: a type name plus its settings. Blocks are stored inside
 * item.yaml under "layout", so a page and its blocks travel together.
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
    ) {
    }

    public function id(): string
    {
        return $this->id ?? substr(md5($this->type . serialize($this->settings)), 0, 8);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function with(string $key, mixed $value): self
    {
        $settings = $this->settings;
        $settings[$key] = $value;

        return new self($this->type, $settings, $this->id);
    }

    /**
     * @return array{type: string, id?: string, settings: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id(), 'settings' => $this->settings];
    }

    public static function fromArray(array $data): self
    {
        $type = (string) ($data['type'] ?? '');
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;

        return new self($type, $settings, $id);
    }
}
