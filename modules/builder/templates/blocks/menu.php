<?php
/**
 * Menu particle. Builds navigation from content items (a chosen type, optionally
 * narrowed by property filters) or from a hand-typed list, and renders it as a
 * list of links, optionally nested one level deep through the items' parent ids.
 *
 * @var array $settings
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$source = ($settings['source'] ?? 'pages') === 'custom' ? 'custom' : 'pages';
$depth = max(1, min(3, (int) ($settings['depth'] ?? 1)));
$root = trim((string) ($settings['root'] ?? ''));
$cssClass = trim((string) ($settings['class'] ?? 'site-nav'));
if ($cssClass === '') {
    $cssClass = 'site-nav';
}

/**
 * @param list<array{title: string, url: string, children: list<array>}> $entries
 */
$render = static function (array $entries, int $level, int $depth, string $cssClass, callable $self): string {
    if ($entries === []) {
        return '';
    }
    $html = '<ul class="' . htmlspecialchars($cssClass . ($level > 1 ? ' ' . $cssClass . '--sub' : ''), ENT_QUOTES) . '">';
    foreach ($entries as $entry) {
        $html .= '<li>';
        $html .= '<a href="' . htmlspecialchars($entry['url'], ENT_QUOTES) . '">' . htmlspecialchars($entry['title'], ENT_QUOTES) . '</a>';
        if ($level < $depth && $entry['children'] !== []) {
            $html .= $self($entry['children'], $level + 1, $depth, $cssClass, $self);
        }
        $html .= '</li>';
    }

    return $html . '</ul>';
};

/**
 * Compare an item property against a filter value. Booleans, numbers and lists
 * are coerced so "да/нет", "1/0" and "true/false" all work.
 */
$matches = static function (mixed $actual, string $op, mixed $expected): bool {
    $normalise = static function (mixed $value): mixed {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return $value;
    };

    $left = $normalise($actual);
    $right = $normalise($expected);

    return match ($op) {
        '!=', '<>' => $left != $right,
        'contains' => is_array($actual)
            ? in_array($expected, $actual, false) || in_array($right, $actual, false)
            : str_contains((string) $left, (string) $right),
        '>', '>=', '<', '<=' => is_numeric($left) && is_numeric($right)
            ? match ($op) {
                '>' => (float) $left > (float) $right,
                '>=' => (float) $left >= (float) $right,
                '<' => (float) $left < (float) $right,
                default => (float) $left <= (float) $right,
            }
            : false,
        default => $left == $right,
    };
};

$entries = [];

if ($source === 'custom') {
    foreach (preg_split('/\r\n|\r|\n/', (string) ($settings['items'] ?? '')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        $title = $parts[0];
        $url = $parts[1] ?? $parts[0];
        if ($title === '') {
            continue;
        }
        $entries[] = ['title' => $title, 'url' => $this->link($url), 'children' => []];
    }
} else {
    $contentType = trim((string) ($settings['content_type'] ?? 'page'));
    $query = $app->query()->sort('order');
    if ($contentType !== '') {
        $query = $query->type($contentType);
    }

    $filters = [];
    foreach (($settings['filters'] ?? []) as $filter) {
        if (!is_array($filter)) {
            continue;
        }
        $field = trim((string) ($filter['field'] ?? ''));
        if ($field === '') {
            continue;
        }
        $filters[] = [
            'field' => $field,
            'op' => trim((string) ($filter['op'] ?? '=')) ?: '=',
            'value' => $filter['value'] ?? '',
        ];
    }

    $items = array_values(array_filter(
        $query->find(),
        static function ($item) use ($filters, $matches): bool {
            foreach ($filters as $filter) {
                if (!$matches($item->get($filter['field']), $filter['op'], $filter['value'])) {
                    return false;
                }
            }

            return true;
        }
    ));

    $byParent = [];
    $ids = [];
    $short = static fn (string $id): string => basename(str_replace('\\', '/', $id));
    foreach ($items as $item) {
        $byParent[$item->parentId()][] = $item;
        $ids[$item->id()] = true;
        $ids[$short($item->id())] = true;
    }

    $build = static function (string $parent) use (&$build, $byParent, $short): array {
        $children = $byParent[$parent] ?? [];
        if ($parent !== '' && $children === []) {
            $children = $byParent[$short($parent)] ?? [];
        }
        $out = [];
        foreach ($children as $item) {
            $out[] = [
                'title' => $item->title(),
                'url' => $item->url(),
                'children' => $build($item->id()),
            ];
        }

        return $out;
    };

    $start = ($root !== '' && isset($ids[$root])) ? $root : '';
    $entries = $build($start);
}

if ($entries === []) {
    return;
}
?>
<nav class="block block--menu <?= $this->e($cssClass) ?>-wrap">
    <?= $render($entries, 1, $depth, $cssClass, $render) ?>
</nav>
