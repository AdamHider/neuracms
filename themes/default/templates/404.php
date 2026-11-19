<?php
/**
 * 404 page. It has no content item, so the theme header/footer partials provide
 * the chrome.
 *
 * @var array $site
 * @var \FlatCms\Http\TemplateContext $this
 */
$body = '<div class="error-page g-section"><div class="g-container">'
    . '<h1>404</h1><p>Страница не найдена.</p>'
    . '<p><a href="' . $this->e($this->url('/')) . '">На главную</a></p>'
    . '</div></div>';
echo $this->partial('layout', ['content' => $body, 'title' => '404']);
