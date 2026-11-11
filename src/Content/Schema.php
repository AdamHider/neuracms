<?php

declare(strict_types=1);

namespace FlatCms\Content;

use FlatCms\Contracts\Subsystem;
use FlatCms\Support\Fs;

/**
 * Description of a content type: its fields, which of them the index stores, and
 * its relation fields (a field that points at items of another type). Types live
 * in config/schemas/<type>.yaml and are edited from the admin panel. A type
 * without a file is still valid content, it simply has no described fields.
 */
final class Schema implements Subsystem
{
    public const FIELD_TYPES = [
        'string', 'text', 'markdown', 'int', 'float', 'bool', 'list', 'file', 'select', 'date', 'relation',
    ];

    /** Types that ship with the core; they can be edited but never deleted. */
    public const BUILTIN = ['page', 'section', 'menu-item'];

    /**
     * @var array<string, array{fields: array<string, array>, indexed: list<string>, label: string, builtin: bool}>
     */
    private array $cache = [];

    private ?Storage $storage = null;

    public function __construct(private readonly Fs $fs)
    {
    }

    public function boot(\FlatCms\App $app): void
    {
        $this->storage = $app->storage;
    }

    public function build(): void
    {
    }

    /**
     * Drop the in-memory cache. Call after a type file is written.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @return array{fields: array<string, array>, indexed: list<string>, label: string, builtin: bool}
     */
    public function get(string $type): array
    {
        if (isset($this->cache[$type])) {
            return $this->cache[$type];
        }

        $raw = $this->fs->readYaml('config/schemas/' . $type . '.yaml', []);

        $fields = [];
        $indexed = [];

        $rawFields = is_array($raw['fields'] ?? null) ? $raw['fields'] : [];
        foreach ($rawFields as $name => $field) {
            $field = is_array($field) ? $field : ['type' => (string) $field];
            $field['type'] = (string) ($field['type'] ?? 'string');
            if (!empty($field['indexed'])) {
                $indexed[] = (string) $name;
            }
            unset($field['indexed']);
            $fields[(string) $name] = $field;
        }

        foreach (is_array($raw['indexed'] ?? null) ? $raw['indexed'] : [] as $name) {
            if (is_string($name) && !in_array($name, $indexed, true)) {
                $indexed[] = $name;
            }
        }

        $label = $raw['label'] ?? null;

        return $this->cache[$type] = [
            'fields' => $fields,
            'indexed' => $indexed,
            'label' => is_string($label) && $label !== '' ? $label : $this->defaultLabel($type),
            'builtin' => array_key_exists('builtin', $raw)
                ? (bool) $raw['builtin']
                : in_array($type, self::BUILTIN, true),
        ];
    }

    /**
     * Every type that has a schema file, sorted.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->fs->listDir('config/schemas') as $name) {
            if (str_ends_with($name, '.yaml')) {
                $names[] = substr($name, 0, -5);
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @return array<string, array{fields: array<string, array>, indexed: list<string>, label: string, builtin: bool}>
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->names() as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    public function has(string $type): bool
    {
        return $this->get($type)['fields'] !== [];
    }

    public function isBuiltin(string $type): bool
    {
        return $this->get($type)['builtin'];
    }

    public function label(string $type): string
    {
        return $this->get($type)['label'];
    }

    /**
     * @return list<string>
     */
    public function indexedFields(string $type): array
    {
        return $this->get($type)['indexed'];
    }

    /**
     * Relation fields of a type: name => target type, multiplicity and label.
     *
     * @return array<string, array{to: string, multiple: bool, label: string}>
     */
    public function relations(string $type): array
    {
        $relations = [];
        foreach ($this->get($type)['fields'] as $name => $field) {
            if (($field['type'] ?? '') !== 'relation') {
                continue;
            }
            $relations[$name] = [
                'to' => (string) ($field['to'] ?? ''),
                'multiple' => (bool) ($field['multiple'] ?? false),
                'label' => (string) ($field['label'] ?? $name),
            ];
        }

        return $relations;
    }

    /**
     * Types that point at $type through a relation field.
     *
     * @return list<string>
     */
    public function referencedBy(string $type): array
    {
        $referrers = [];
        foreach ($this->names() as $name) {
            if ($name === $type) {
                continue;
            }
            foreach ($this->relations($name) as $relation) {
                if ($relation['to'] === $type) {
                    $referrers[] = $name;
                    break;
                }
            }
        }

        return $referrers;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        $defaults = [];
        foreach ($this->get($type)['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'relation') {
                continue;
            }
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
            $value = $item->get($name);

            if ($value === null || $value === '' || $value === []) {
                if ((bool) ($field['required'] ?? false)) {
                    $errors[] = sprintf('Field "%s" is required.', $name);
                }
                continue;
            }

            $type = (string) ($field['type'] ?? 'string');

            if ($type === 'relation') {
                foreach ($this->validateRelation($name, $field, $value) as $error) {
                    $errors[] = $error;
                }
                continue;
            }

            if (!in_array($type, self::FIELD_TYPES, true)) {
                continue;
            }

            if (!$this->matches($type, $value)) {
                $errors[] = sprintf('Field "%s" must be of type %s.', $name, $type);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return list<string>
     */
    private function validateRelation(string $name, array $field, mixed $value): array
    {
        $target = (string) ($field['to'] ?? '');
        $ids = (bool) ($field['multiple'] ?? false)
            ? (is_array($value) ? $value : [$value])
            : [$value];

        $errors = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                $errors[] = sprintf('Field "%s" holds an invalid reference.', $name);
                continue;
            }

            if ($this->storage === null) {
                continue;
            }

            $item = $this->storage->load($id);
            if ($item === null) {
                $errors[] = sprintf('Field "%s": referenced item "%s" does not exist.', $name, $id);
            } elseif ($target !== '' && $item->type() !== $target) {
                $errors[] = sprintf('Field "%s" must reference a "%s" item.', $name, $target);
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

    private function defaultLabel(string $type): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $type));
    }
}
