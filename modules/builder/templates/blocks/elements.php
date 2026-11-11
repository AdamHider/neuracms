<?php
/**
 * Element list particle: renders items of a chosen element type as cards, using
 * the per-particle property mapping to decide which element property fills which
 * card field. Falls back to the element's own URL when no link property is set.
 *
 * @var array $settings
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$elementType = (string) ($settings['element_type'] ?? '');
if ($elementType === '') {
    return;
}

$limit = max(1, (int) ($settings['limit'] ?? 6));
$sort = in_array($settings['sort'] ?? 'order', ['order', 'title', 'date'], true) ? $settings['sort'] : 'order';
$dir = ($settings['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$columns = max(1, min(4, (int) ($settings['columns'] ?? 3)));

$items = $app->query()->type($elementType)->sort($sort, $dir)->limit($limit)->find();

$mapTitle = (string) ($settings['map_title'] ?? 'title');
$mapDescription = (string) ($settings['map_description'] ?? '');
$mapPrice = (string) ($settings['map_price'] ?? '');
$mapLink = (string) ($settings['map_link'] ?? '');
$mapImage = (string) ($settings['map_image'] ?? '');

$scalar = static function (mixed $value): string {
    if (is_array($value)) {
        $value = $value[0] ?? '';
    }

    return is_scalar($value) ? (string) $value : '';
};
?>
<section class="block block--elements">
    <?php if (($settings['title'] ?? '') !== ''): ?>
        <h2><?= $this->e($settings['title']) ?></h2>
    <?php endif; ?>
    <?php if ($items === []): ?>
        <p class="muted">Элементы этого типа пока не добавлены.</p>
    <?php else: ?>
        <div class="element-grid" style="--columns:<?= $columns ?>">
            <?php foreach ($items as $element): ?>
                <?php
                $title = $mapTitle !== '' ? $scalar($element->get($mapTitle)) : '';
                if ($title === '') {
                    $title = $element->title();
                }
                $description = $mapDescription !== '' ? $scalar($element->get($mapDescription)) : '';
                $price = $mapPrice !== '' ? $scalar($element->get($mapPrice)) : '';
                $image = $mapImage !== '' ? $scalar($element->get($mapImage)) : '';
                $link = $mapLink !== '' ? $scalar($element->get($mapLink)) : '';
                $href = $link !== '' ? $this->url($link) : $this->url($element->url());
                ?>
                <article class="element-card">
                    <?php if ($image !== ''): ?>
                        <a class="element-card__media" href="<?= $this->e($href) ?>">
                            <img src="<?= $this->e(preg_match('#^(https?:)?//#', $image) === 1 ? $image : $this->url($image)) ?>" alt="<?= $this->e($title) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <h3 class="element-card__title">
                        <a href="<?= $this->e($href) ?>"><?= $this->e($title) ?></a>
                    </h3>
                    <?php if ($description !== ''): ?>
                        <p class="element-card__description"><?= $this->e($description) ?></p>
                    <?php endif; ?>
                    <?php if ($price !== ''): ?>
                        <p class="element-card__price"><?= $this->e($price) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
