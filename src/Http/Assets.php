<?php

declare(strict_types=1);

namespace FlatCms\Http;

use FlatCms\Contracts\Subsystem;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Support\Fs;

/**
 * Serves theme assets (/assets/<theme>/...) and item attachments
 * (/media/<item id>/<file>) through PHP so the content and themes folders do not
 * have to be web reachable.
 */
final class Assets implements Subsystem
{
    private const MIME = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'mjs' => 'text/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
    ];

    private ?Fs $fs = null;

    public function boot(\FlatCms\App $app): void
    {
        $this->fs = $app->fs;

        $app->router->add('GET', '/assets/{theme}/{path:.+}', fn (Request $r, array $p): Response => $this->theme($p));
        $app->router->add('GET', '/media/{id:.+}/{file}', fn (Request $r, array $p): Response => $this->media($p));
    }

    public function build(): void
    {
    }

    private function theme(array $params): Response
    {
        $theme = $params['theme'];
        if (preg_match('/^[a-z0-9_-]+$/', $theme) !== 1) {
            return new Response('Not Found', 404);
        }

        return $this->send('themes/' . $theme . '/assets', (string) $params['path']);
    }

    private function media(array $params): Response
    {
        return $this->send('content', $params['id'] . '/' . $params['file']);
    }

    private function send(string $base, string $relative): Response
    {
        $fs = $this->fs();
        $safe = $fs->safePath($base, $relative);
        if ($safe === null) {
            return new Response('Not Found', 404);
        }

        $path = $base . '/' . $safe;
        if (!$fs->isFile($path)) {
            return new Response('Not Found', 404);
        }

        $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
        $type = self::MIME[$ext] ?? null;
        if ($type === null) {
            return new Response('Not Found', 404);
        }

        $response = new Response($fs->read($path));
        $response->header('Content-Type', $type);
        $response->header('Cache-Control', 'public, max-age=86400');
        $response->header('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function fs(): Fs
    {
        if ($this->fs === null) {
            throw new \RuntimeException('Assets subsystem is not booted');
        }

        return $this->fs;
    }
}
