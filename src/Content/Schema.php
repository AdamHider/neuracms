<?php

declare(strict_types=1);

namespace FlatCms\Content;

use FlatCms\Contracts\Subsystem;
use FlatCms\Support\Fs;

/**
 * Optional description of an item type: list of fields, their type, whether they
 * are required and default values. If no schema exists the item is accepted as is.
 */
final class Schema implements Subsystem
{
    private const VALUE_TYPES = ['string', 'text', 'markdown', 'int', 'float', 'bool', 'list', 'file', 'select', 'date'];

    /** @var array<string, array> */
    private array $cache = [];

    public function __construct(private readonly Fs $fs)
    {
    }

    public function boot(\FlatCms\App $app): void
    {
    }

    public function build(): void
    {
    }

    /**
     * @return array{fields: array<string, array>, indexed: list<string>}
     */
    public function get(string $type): array
    {
        if (isset($this->cache[$type])) {
            return $this->cache[$type];
        }

        $raw = $this->fs->readYaml('config/schemas/' . $type . '.yaml', []);
        $fields = is_array($raw['fields'] ?? null) ? $raw['fields'] : [];
        $indexed = is_array($raw['indexed'] ?? null) ? array_values($raw['indexed']) : [];

        foreach ($fields as $name => $field) {
            if (!is_array($field)) {
                $fields[$name] = ['type' => (string) $field];
            }
        }

        return $this->cache[$type] = ['fields' => $fields, 'indexed' => $indexed];
    }

    public function has(string $type): bool
    {
        return $this->get($type)['fields'] !== [];
    }

    /**
     * @return list<string>
     */
    public function indexedFields(string $type): array
    {
        return $this->get($type)['indexed'];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        $defaults = [];
        foreach ($this->get($type)['fields'] as $name => $field) {
            if (array_key_exists('default', $field)) {
                $defaults[$name] = $field['default'];
            }
        }

        return $defaults;
    }

    /**
     * Validate an item against its schema. Returns a list of error messages.
     *
     * @return list<string>
     */
    public function validate(Item $item): array
    {
        $schema = $this->get($item->type());
        if ($schema['fields'] === []) {
            return [];
        }

        $errors = [];
        foreach ($schema['fields'] as $name => $field) {
            $required = (bool) ($field['required'] ?? false);
            $value = $item->get($name);

            if ($value === null || $value === '') {
                if ($required) {
                    $errors[] = sprintf('Field "%s" is required.', $name);
                }
                continue;
            }

            $type = (string) ($field['type'] ?? 'string');
            if (!in_array($type, self::VALUE_TYPES, true)) {
                continue;
            }

            if (!$this->matches($type, $value)) {
                $errors[] = sprintf('Field "%s" must be of type %s.', $name, $type);
            }
        }

        return $errors;
    }

    private function matches(string $type, mixed $value): bool
    {
        return match ($type) {
            'int' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'float' => is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)),
            'bool' => is_bool($value) || in_array($value, ['1', '0', 'true', 'false'], true),
            'list' => is_array($value),
            'string', 'text', 'markdown', 'file', 'select', 'date' => is_string($value) || is_numeric($value),
            default => true,
        };
    }
}
