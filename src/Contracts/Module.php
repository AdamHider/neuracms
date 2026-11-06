<?php

declare(strict_types=1);

namespace FlatCms\Contracts;

use FlatCms\App;

/**
 * Contract implemented by every module placed in modules/<name>/.
 */
interface Module
{
    /**
     * Subscribe to events, add routes, register templates and assets.
     */
    public function boot(App $app): void;

    /**
     * Rebuild module specific indexes, if any.
     */
    public function build(): void;
}
