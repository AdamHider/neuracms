<?php
/**
 * A page renders flat: its header band, its sections and its footer band are all
 * siblings at the top level of the body. The builder layout is the only source of
 * the page's look, so a page without sections renders just the chrome.
 *
 * @var \FlatCms\Content\Item $item
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$body = $this->layout($item);
echo $this->partial('layout', ['content' => $body, 'item' => $item, 'title' => $item->title()]);
