<?php

declare(strict_types=1);

namespace FlatCms\Http;

use FlatCms\Contracts\Subsystem;
use FlatCms\Content\Index;
use FlatCms\Core\Events;
use FlatCms\Core\Request;
use FlatCms\Support\Fs;

/**
 * Maps a request URL to a route. Module routes are checked before content routes.
 */
final class Router implements Subsystem
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $redirects = [];

    public function __construct(
        private readonly Index $index,
        private readonly Events $events,
        private readonly Fs $fs,
    ) {
    }

    public function boot(\FlatCms\App $app): void
    {
        $this->redirects = $this->fs->readYaml('config/redirects.yaml', []);
    }

    public function build(): void
    {
    }

    /**
     * Register a route. Patterns use {name} placeholders.
     */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => strtoupper($method), 'pattern' => $pattern, 'handler' => $handler];
    }

    public function match(Request $request): ?Route
    {
        $path = $request->path();
        $canonical = $this->canonicalize($path);

        if ($canonical !== null) {
            return new Route('redirect', ['url' => $canonical], null);
        }

        $redirect = $this->redirects[$path] ?? null;
        if (is_string($redirect)) {
            return new Route('redirect', ['url' => $redirect], null);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method() && $route['method'] !== 'ANY') {
                continue;
            }
            $params = $this->matchPattern($route['pattern'], $path);
            if ($params !== null) {
                $this->events->emit('route.matched', $route);

                return new Route('handler', $params, $route['handler']);
            }
        }

        $item = $this->matchContent($path);
        if ($item !== null) {
            $this->events->emit('route.matched', $item);

            return new Route('content', ['id' => $item['id']], null);
        }

        $this->events->emit('route.notfound', $path);

        return null;
    }

    /**
     * Trailing slash is not canonical: /about/ redirects to /about.
     */
    private function canonicalize(string $path): ?string
    {
        if ($path === '/' || !str_ends_with($path, '/')) {
            return null;
        }

        return rtrim($path, '/') ?: '/';
    }

    private function matchContent(string $path): ?array
    {
        $id = trim($path, '/');

        $entry = $this->index->get($id);
        if ($entry !== null && ($entry['published'] ?? true) !== false) {
            return $entry;
        }

        // Match by slug when the folder name differs from the URL fragment.
        foreach ($this->index->all() as $candidate) {
            if (($candidate['published'] ?? true) === false) {
                continue;
            }
            if (ltrim((string) ($candidate['url'] ?? ''), '/') === $id) {
                return $candidate;
            }
        }

        return null;
    }

    private function matchPattern(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $pattern
        );
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
