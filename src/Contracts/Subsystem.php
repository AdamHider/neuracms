<?php

declare(strict_types=1);

namespace FlatCms\Contracts;

use FlatCms\App;

/**
 * Common contract implemented by every subsystem of the core.
 */
interface Subsystem
{
    /**
     * Receive dependencies and prepare the subsystem.
     */
    public function boot(App $app): void;

    /**
     * Rebuild derived data (index, cache).
     */
    public function build(): void;
}
