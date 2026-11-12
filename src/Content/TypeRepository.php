<?php

declare(strict_types=1);

namespace FlatCms\Content;

use FlatCms\Support\Fs;

/**
 * Creates, updates and deletes content types (the files under config/schemas/).
 * Types that ship with the core (page, section, menu-item) can be edited but not
 * deleted. Relation fields are stored like any other field, with a "to" key that
 * names the target type.
 */
final class TypeRepository
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_-]{0,48}$/';

    public function __construct(
        private readonly Fs $fs,
        private readonly Schema $schema,
    ) {
    }

    /**
     * Valid, lower-case type name that is safe to use as a file name.
     */
    public function normalizeName(string $name): ?string
    {
        $name = strtolower(trim($name));
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return null;
        }

        return $name;
    }

    public function exists(string $type): bool
    {
        return $this->fs->isFile($this->path($type));
    }

    public function isBuiltin(string $type): bool
    {
        return $this->schema->isBuiltin($type);
    }

    /**
     * @return array{label: string, fields: array<string, array>, indexed: list<string>, kind: string, admin: array{menu: bool, label: string, icon: string, order: int}}|null
     */
    public function load(string $type): ?array
    {
        if (!$this->exists($type)) {
            return null;
        }

        $schema = $this->schema->get($type);

        return [
            'label' => $schema['label'],
            'fields' => $schema['fields'],
            'indexed' => $schema['indexed'],
            'kind' => $schema['kind'],
            'admin' => $schema['admin'],
        ];
    }

    /**
     * Write a type. Passing a name that does not exist yet creates it.
     *
     * @param array<string, array>                 $fields
     * @param list<string>                         $indexed
     * @param array{menu?: bool, label?: string, icon?: string, order?: int} $admin
     */
    public function save(string $type, array $fields, array $indexed, string $label = '', ?string $kind = null, array $admin = []): bool
    {
        $type = $this->normalizeName($type);
        if ($type === null) {
            return false;
        }

        $clean = [];
        foreach ($fields as $name => $field) {
            if (!$this->validFieldName((string) $name)) {
                continue;
            }
            $clean[(string) $name] = $this->cleanField(is_array($field) ? $field : []);
        }

        $indexed = array_values(array_filter(
            $indexed,
            static fn (mixed $name): bool => is_string($name) && array_key_exists($name, $clean),
        ));

        $payload = ['type' => $type];
        if ($label !== '') {
            $payload['label'] = $label;
        }
        if ($kind === Schema::KIND_PAGE || $kind === Schema::KIND_ELEMENT) {
            $payload['kind'] = $kind;
        }
        if (($adminSettings = $this->cleanAdmin($admin)) !== []) {
            $payload['admin'] = $adminSettings;
        }
        $payload['fields'] = $clean;
        $payload['indexed'] = $indexed;

        $ok = $this->fs->writeYaml($this->path($type), $payload);
        $this->schema->flush();

        return $ok;
    }

    /**
     * Keep only the admin-menu keys that were actually set, so a type file stays
     * minimal when the feature is not used.
     *
     * @param array{menu?: bool, label?: string, icon?: string, order?: int} $admin
     *
     * @return array<string, mixed>
     */
    private function cleanAdmin(array $admin): array
    {
        $clean = [];
        if (!empty($admin['menu'])) {
            $clean['menu'] = true;
        }
        $label = trim((string) ($admin['label'] ?? ''));
        if ($label !== '') {
            $clean['label'] = $label;
        }
        $icon = trim((string) ($admin['icon'] ?? ''));
        if ($icon !== '') {
            $clean['icon'] = $icon;
        }
        if (isset($admin['order']) && $admin['order'] !== '' && (int) $admin['order'] !== 100) {
            $clean['order'] = (int) $admin['order'];
        }

        return $clean;
    }

    public function delete(string $type): bool
    {
        $type = $this->normalizeName($type);
        if ($type === null || $this->isBuiltin($type) || !$this->exists($type)) {
            return false;
        }

        $ok = $this->fs->delete($this->path($type));
        $this->schema->flush();

        return $ok;
    }

    /**
     * Turn the flat rows posted by the admin form into a field map, an indexed
     * list and a list of error messages.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array{fields: array<string, array>, indexed: list<string>, errors: list<string>}
     */
    public function parseRows(array $rows): array
    {
        $fields = [];
        $indexed = [];
        $errors = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            if (!$this->validFieldName($name)) {
                $errors[] = sprintf('Недопустимое имя поля: "%s".', $name);
                continue;
            }
            if (array_key_exists($name, $fields)) {
                $errors[] = sprintf('Поле "%s" повторяется.', $name);
                continue;
            }

            $type = (string) ($row['type'] ?? 'string');
            if (!in_array($type, Schema::FIELD_TYPES, true)) {
                $errors[] = sprintf('Неизвестный тип поля: "%s".', $type);
                continue;
            }

            $field = ['type' => $type];

            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $field['label'] = $label;
            }
            if (!empty($row['required'])) {
                $field['required'] = true;
            }
            if (array_key_exists('default', $row) && $row['default'] !== '') {
                $field['default'] = $this->castDefault($type, (string) $row['default']);
            }

            if ($type === 'relation') {
                $to = strtolower(trim((string) ($row['to'] ?? '')));
                if ($to === '' || $this->normalizeName($to) === null) {
                    $errors[] = sprintf('Для связи "%s" укажите тип-назначение.', $name);
                    continue;
                }
                $field['to'] = $to;
                $field['multiple'] = !empty($row['multiple']);
            }

            if ($type === 'select') {
                $options = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) ($row['options'] ?? '')),
                ), static fn (string $option): bool => $option !== ''));
                if ($options !== []) {
                    $field['options'] = $options;
                }
            }

            $fields[$name] = $field;
            if (!empty($row['indexed'])) {
                $indexed[] = $name;
            }
        }

        return ['fields' => $fields, 'indexed' => $indexed, 'errors' => $errors];
    }

    public function validFieldName(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,48}$/', strtolower($name)) === 1;
    }

    private function castDefault(string $type, string $raw): mixed
    {
        return match ($type) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => in_array(strtolower($raw), ['1', 'true', 'on', 'yes'], true),
            'list' => array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== '')),
            default => $raw,
        };
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function cleanField(array $field): array
    {
        $type = (string) ($field['type'] ?? 'string');
        $clean = ['type' => in_array($type, Schema::FIELD_TYPES, true) ? $type : 'string'];

        foreach (['label', 'to', 'default'] as $key) {
            if (isset($field[$key]) && $field[$key] !== '') {
                $clean[$key] = $field[$key];
            }
        }
        if (!empty($field['required'])) {
            $clean['required'] = true;
        }
        if (($clean['type'] ?? '') === 'relation') {
            $clean['multiple'] = (bool) ($field['multiple'] ?? false);
        }
        if (($clean['type'] ?? '') === 'select' && is_array($field['options'] ?? null)) {
            $clean['options'] = array_values($field['options']);
        }

        return $clean;
    }

    private function path(string $type): string
    {
        return 'config/schemas/' . $type . '.yaml';
    }
}
