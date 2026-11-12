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

    /** Kind: a routable page whose appearance is defined in the builder. */
    public const KIND_PAGE = 'page';

    /** Kind: a structured data element (no page of its own, shown by particles). */
    public const KIND_ELEMENT = 'element';

    /**
     * Kinds of the core page-like types. Any other type is an element unless its
     * schema file sets "kind" explicitly. Menu items are elements: they are data
     * the Menu particle renders into navigation, not routable pages of their own.
     */
    private const PAGE_KINDS = ['page', 'section'];

    /**
     * @var array<string, array{fields: array<string, array>, indexed: list<string>, label: string, builtin: bool, kind: string, admin: array{menu: bool, label: string, icon: string, order: int}}>
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
     * @return array{fields: array<string, array>, indexed: list<string>, label: string, builtin: bool, kind: string, admin: array{menu: bool, label: string, icon: string, order: int}}
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
            'kind' => $this->readKind($type, $raw),
            'admin' => $this->readAdmin($type, $raw, $label),
        ];
    }

    /**
     * Admin-menu settings of a type: whether it gets its own sidebar entry, and
     * with which label, icon and order. Defaults to the type label and a generic
     * icon; "admin: {menu: true, ...}" in the schema file turns the entry on.
     *
     * @param array<string, mixed> $raw
     *
     * @return array{menu: bool, label: string, icon: string, order: int}
     */
    private function readAdmin(string $type, array $raw, mixed $label): array
    {
        $admin = is_array($raw['admin'] ?? null) ? $raw['admin'] : [];

        $menu = $admin['menu'] ?? $raw['in_admin_menu'] ?? false;

        $menuLabel = $admin['label'] ?? null;
        if (!is_string($menuLabel) || $menuLabel === '') {
            $menuLabel = is_string($label) && $label !== '' ? $label : $this->defaultLabel($type);
        }

        $icon = $admin['icon'] ?? null;

        return [
            'menu' => (bool) $menu,
            'label' => $menuLabel,
            'icon' => is_string($icon) && $icon !== '' ? $icon : 'content',
            'order' => (int) ($admin['order'] ?? 100),
        ];
    }

    /**
     * Page or element. A schema may set "kind" explicitly; otherwise the core
     * page types are pages and everything else is an element.
     *
     * @param array<string, mixed> $raw
     */
    private function readKind(string $type, array $raw): string
    {
        $kind = $raw['kind'] ?? null;
        if ($kind === self::KIND_PAGE || $kind === self::KIND_ELEMENT) {
            return $kind;
        }

        return in_array($type, self::PAGE_KINDS, true) ? self::KIND_PAGE : self::KIND_ELEMENT;
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

    public function kind(string $type): string
    {
        return $this->get($type)['kind'];
    }

    public function isPage(string $type): bool
    {
        return $this->kind($type) === self::KIND_PAGE;
    }

    public function isElement(string $type): bool
    {
        return $this->kind($type) === self::KIND_ELEMENT;
    }

    /**
     * Types of one kind, name => schema.
     *
     * @return array<string, array{fields: array<string, array>, indexed: list<string>, label: string, builtin: bool, kind: string}>
     */
    public function ofKind(string $kind): array
    {
        return array_filter($this->all(), static fn (array $schema): bool => $schema['kind'] === $kind);
    }

    /**
     * Page types (the core "pages" list).
     *
     * @return array<string, array>
     */
    public function pages(): array
    {
        return $this->ofKind(self::KIND_PAGE);
    }

    /**
     * Element types (the custom content elements).
     *
     * @return array<string, array>
     */
    public function elements(): array
    {
        return $this->ofKind(self::KIND_ELEMENT);
    }

    public function label(string $type): string
    {
        return $this->get($type)['label'];
    }

    /**
     * Types that asked for their own admin sidebar entry, sorted by "order" then
     * label. Each entry carries the type name, its menu label and icon.
     *
     * @return list<array{type: string, label: string, icon: string, kind: string}>
     */
    public function adminMenu(): array
    {
        $entries = [];
        foreach ($this->all() as $type => $schema) {
            if (empty($schema['admin']['menu'])) {
                continue;
            }
            $entries[] = [
                'type' => $type,
                'label' => $schema['admin']['label'],
                'icon' => $schema['admin']['icon'],
                'kind' => $schema['kind'],
                'order' => $schema['admin']['order'],
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            return $a['order'] <=> $b['order'] ?: strcmp($a['label'], $b['label']);
        });

        return array_map(static function (array $entry): array {
            unset($entry['order']);

            return $entry;
        }, $entries);
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
