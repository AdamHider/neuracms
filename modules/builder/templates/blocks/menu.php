<?php
/**
 * Menu particle. Builds navigation from one of three sources and renders it as a
 * list of links, optionally nested one level deep.
 *
 * @var array $settings
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$source = in_array($settings['source'] ?? 'menu', ['menu', 'pages', 'custom'], true) ? $settings['source'] : 'menu';
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

$entries = [];

if ($source === 'pages') {
    foreach ($app->query()->kind(\FlatCms\Content\Schema::KIND_PAGE)->sort('order')->find() as $page) {
        $entries[] = ['title' => $page->title(), 'url' => $page->url(), 'children' => []];
    }
} elseif ($source === 'custom') {
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
    // Menu items, grouped into a tree through their "parent" property. A parent
    // may be given as the full id ("menu/services") or the short name ("services").
    $items = $app->query()->type('menu-item')->sort('order')->find();
    $byParent = [];
    $ids = [];
    $short = static fn (string $id): string => basename(str_replace('\\', '/', $id));
    foreach ($items as $item) {
        $byParent[(string) $item->get('parent', '')][] = $item;
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
            $link = (string) $item->get('link', '');
            $out[] = [
                'title' => $item->title(),
                'url' => $link !== '' ? $link : $item->url(),
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
