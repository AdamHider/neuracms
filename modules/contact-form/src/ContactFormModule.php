<?php

declare(strict_types=1);

namespace FlatCms\Modules\ContactForm;

use FlatCms\App;
use FlatCms\Contracts\Module;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Support\Fs;

/**
 * Contact form module. Demonstrates the whole module lifecycle: routes, a config
 * file, its own templates and an event listener.
 */
final class ContactFormModule implements Module
{
    private ?App $app = null;

    public function boot(App $app): void
    {
        $this->app = $app;

        $app->config->mergeFile('modules/contact-form/config/config.yaml');

        $app->router->add('GET', '/contact', fn (Request $request, array $params): Response => $this->page($request));
        $app->router->add('POST', '/contact', fn (Request $request, array $params): Response => $this->submit($request));

        $app->events->on('site.data', static function (array $site): array {
            $site['nav'][] = ['title' => 'Контакты', 'url' => '/contact'];

            return $site;
        });
    }

    public function build(): void
    {
    }

    private function page(Request $request, array $errors = [], array $values = []): Response
    {
        $app = $this->requireApp();

        $html = $app->renderer->renderTemplate('contact', [
            'errors' => $errors,
            'values' => $values,
            'sent' => $request->query('sent') === '1',
        ]);

        return Response::html($html);
    }

    private function submit(Request $request): Response
    {
        $app = $this->requireApp();

        $values = [
            'name' => trim((string) $request->input('name', '')),
            'email' => trim((string) $request->input('email', '')),
            'message' => trim((string) $request->input('message', '')),
        ];

        $errors = $this->validate($values);
        if ($errors !== []) {
            return $this->page($request, $errors, $values)->setStatus(422);
        }

        $this->store($values);

        return Response::redirect('/contact?sent=1', 303);
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'] = 'Укажите имя.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        }
        if (mb_strlen($values['message']) < 5) {
            $errors['message'] = 'Сообщение слишком короткое.';
        }
        if (mb_strlen($values['message']) > 5000) {
            $errors['message'] = 'Сообщение слишком длинное.';
        }

        return $errors;
    }

    private function store(array $values): void
    {
        $fs = new Fs($this->requireApp()->root());
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $record = [
            'name' => $values['name'],
            'email' => $values['email'],
            'message' => $values['message'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => date('c'),
        ];

        $fs->write('var/messages/' . $id . '.json', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }

    private function requireApp(): App
    {
        if ($this->app === null) {
            throw new \RuntimeException('Module is not booted');
        }

        return $this->app;
    }
}
