<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * Description of one block type: its name, human title, the settings it accepts
 * and which template renders it.
 */
class BlockType
{
    /**
     * @param array<string, array{type?: string, label?: string, default?: mixed, options?: array}> $fields
     */
    public function __construct(
        private readonly string $name,
        private readonly string $title,
        private readonly array $fields = [],
        private readonly ?string $template = null,
        private readonly string $category = 'Общие',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function template(): string
    {
        return $this->template ?? 'blocks/' . $this->name;
    }

    public function category(): string
    {
        return $this->category;
    }

    /**
     * @return array<string, array>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @return array{name: string, title: string, category: string, fields: array}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'category' => $this->category,
            'fields' => $this->fields,
        ];
    }

    /**
     * Fill a block's settings with the field defaults.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function withDefaults(array $settings): array
    {
        foreach ($this->fields as $name => $field) {
            if (!array_key_exists($name, $settings) && array_key_exists('default', $field)) {
                $settings[$name] = $field['default'];
            }
        }

        return $settings;
    }
}
