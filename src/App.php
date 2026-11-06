<?php

declare(strict_types=1);

namespace FlatCms;

use FlatCms\Content\Index;
use FlatCms\Content\Query;
use FlatCms\Content\Schema;
use FlatCms\Content\Storage;
use FlatCms\Contracts\Module;
use FlatCms\Contracts\Subsystem;
use FlatCms\Core\Config;
use FlatCms\Core\Events;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Http\Renderer;
use FlatCms\Http\Route;
use FlatCms\Http\Router;
use FlatCms\Support\Fs;
use Throwable;

/**
 * Application container. Builds subsystems in order, keeps them in a registry
 * ($app->storage, $app->index, ...) and runs the request cycle. No business logic.
 */
final class App
{
    public readonly Fs $fs;
    public readonly Events $events;
    public readonly Config $config;
    public readonly Storage $storage;
    public readonly Schema $schema;
    public readonly Index $index;
    public readonly Router $router;
    public readonly Renderer $renderer;

    /** @var array<string, Module> */
    private array $modules = [];

    /** @var list<string> */
    private array $moduleTemplatePaths = [];

    private bool $booted = false;

    public function __construct(private readonly string $root)
    {
        $this->fs = new Fs($root);
        $this->events = new Events();
        $this->config = new Config($this->fs);
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Boot the core subsystems and then the modules.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->bootSubsystem($this->config);

        $this->storage = new Storage($this->fs, $this->events);
        $this->bootSubsystem($this->storage);

        $this->schema = new Schema($this->fs);
        $this->bootSubsystem($this->schema);

        $this->index = new Index($this->fs, $this->storage, $this->schema, $this->events, $this->config);
        $this->bootSubsystem($this->index);

        $this->router = new Router($this->index, $this->events, $this->fs);
        $this->bootSubsystem($this->router);

        $this->renderer = new Renderer($this->events);
        $this->bootSubsystem($this->renderer);

        $this->loadModules();

        $this->events->emit('app.booted', $this);
    }

    public function query(): Query
    {
        return new Query($this->index, $this->storage);
    }

    /**
     * @return list<string>
     */
    public function moduleTemplatePaths(): array
    {
        return $this->moduleTemplatePaths;
    }

    /**
     * @return array<string, Module>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    public function module(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Rebuild derived data for the core and every module.
     */
    public function build(): void
    {
        $release = $this->index->lock();
        if ($release === null) {
            return;
        }

        try {
            $this->index->build();
            foreach ($this->modules as $module) {
                $module->build();
            }
        } finally {
            $release();
        }
    }

    /**
     * Handle one request and return the response. Kept separate from handle() so
     * tests can inspect the response without sending it.
     */
    public function handle(Request $request): Response
    {
        $this->events->emit('request.received', $request);

        if ($this->index->isStale()) {
            $this->build();
        }

        $route = $this->router->match($request);

        if ($route === null) {
            return $this->notFound();
        }

        if ($route->isRedirect()) {
            return Response::redirect((string) $route->params['url'], 301);
        }

        if ($route->isHandler()) {
            return $this->dispatch($route->handler, $request, $route->params);
        }

        return $this->renderContent((string) $route->params['id']);
    }

    public function handleGlobals(): void
    {
        $response = $this->handle(Request::fromGlobals());
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $this->events->emit('response.before_send', $response);
        $response->send();
    }

    private function renderContent(string $id): Response
    {
        $item = $this->storage->load($id);
        if ($item === null) {
            return $this->notFound();
        }

        $html = $this->renderer->render($item);

        return Response::html($html);
    }

    private function notFound(): Response
    {
        $html = $this->renderer->renderTemplate('404', ['site' => []]);

        return Response::html($html, 404);
    }

    private function dispatch(mixed $handler, Request $request, array $params): Response
    {
        if (!is_callable($handler)) {
            return Response::html('', 500);
        }

        $result = $handler($request, $params);

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html((string) $result);
    }

    private function bootSubsystem(Subsystem $subsystem): void
    {
        $subsystem->boot($this);
    }

    private function loadModules(): void
    {
        $modulesDir = $this->fs->path('modules');
        if (!is_dir($modulesDir)) {
            return;
        }

        foreach ($this->fs->listDir('modules', true) as $name) {
            $base = 'modules/' . $name;
            $manifest = $this->fs->readYaml($base . '/module.yaml', []);
            if ($manifest === []) {
                continue;
            }

            $enabled = (bool) $this->config->get('modules.' . $name . '.enabled', $manifest['enabled'] ?? true);
            if (!$enabled) {
                continue;
            }

            $class = $manifest['class'] ?? null;
            if (!is_string($class) || $class === '' || !class_exists($class)) {
                continue;
            }

            $module = new $class();
            if (!$module instanceof Module) {
                continue;
            }

            $this->modules[$name] = $module;
            $this->moduleTemplatePaths[] = $this->fs->path($base);
            $module->boot($this);
        }
    }

    /**
     * Run the request cycle, catching errors so production never leaks them.
     */
    public function run(): void
    {
        try {
            $this->boot();
            $this->handleGlobals();
        } catch (Throwable $e) {
            $this->reportError($e);
            $response = Response::html('Internal Server Error', 500);
            $response->send();
        }
    }

    private function reportError(Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        try {
            $this->fs->ensureDir('var/logs');
            $this->fs->write('var/logs/error.log', $this->fs->read('var/logs/error.log') . $line);
        } catch (Throwable) {
            // Logging must never mask the original failure.
        }

        if ((bool) $this->config->get('debug', false)) {
            echo '<pre>' . htmlspecialchars($line . $e->getTraceAsString(), ENT_QUOTES) . '</pre>';
        }
    }
}
