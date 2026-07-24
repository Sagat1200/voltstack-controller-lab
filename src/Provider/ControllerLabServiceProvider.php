<?php

declare(strict_types=1);

namespace VoltStack\ControllerLab\Provider;

use Quantum\View\ViewFactory;
use VoltStack\ControllerLab\Service\Provider\Routes\RouteControllerLabService;
use VoltStack\Framework\ServiceProvider;

final class ControllerLabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('controller-lab', 'VoltStack\ControllerLab\ControllerLab');
    }

    public function boot(): void
    {
        $this->registerViewPaths();

        $enableDemoControllerRoutes = (bool) config(
            'controller-lab.enable_demo_controller_routes',
            in_array($this->app->environment(), ['local', 'testing'], true)
        );

        if ($enableDemoControllerRoutes) {
            RouteControllerLabService::registerLabControllerRoutes();
        }
    }

    private function registerViewPaths(): void
    {
        $resourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources';

        if (! is_dir($resourcePath)) {
            return;
        }

        $views = $this->app->make(ViewFactory::class);
        $paths = $views->paths();

        if (in_array($resourcePath, $paths, true)) {
            return;
        }

        $paths[] = $resourcePath;
        $views->setPaths($paths);
    }
}