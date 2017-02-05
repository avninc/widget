<?php

namespace Pingpong\Widget;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class WidgetServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton('widget', function ($app) {
          $blade = $app['view']->getEngineResolver()->resolve('blade')->getCompiler();

          return new Widget($blade, $app);
        });

        $this->app->booting(function () {
            $loader = AliasLoader::getInstance();
            $loader->alias('Widget', 'Pingpong\Widget\WidgetFacade');

            $file = app_path('widgets.php');

            if (file_exists($file)) {
                include $file;
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('widget');
    }
}
