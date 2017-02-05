<?php

namespace Pingpong\Widget;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class Widget
{
    /**
     * @var BladeCompiler
     */
    protected $blade;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $groups = array();

    /**
     * @var array
     */
    protected $widgets = array();

    /**
     * The constructor.
     *
     * @param BladeCompiler $blade
     * @param Container     $container
     */
    public function __construct(BladeCompiler $blade, Container $container)
    {
        $this->blade = $blade;
        $this->container = $container;
    }

    /**
     * Register new widget.
     *
     * @param string          $name
     * @param string|callable $callback
     */
    public function register($name, $callback)
    {
        $this->widgets[$name] = $callback;

        $this->registerBlade($name);
    }

    /**
     * Register widget using a specified handler class.
     *
     * @param string $subscriber
     */
    public function subscribe($subscriber)
    {
        list($className, $method) = Str::parseCallback($subscriber, 'subscribe');

        $instance = $this->container->make($className);

        call_user_func_array([$instance, $method], [$this]);
    }

    /**
     * Register blade syntax for a specific widget.
     *
     * @param string $name
     */
    protected function registerBlade($name)
    {
        $this->blade->extend(function ($view, $compiler) use ($name) {
            $pattern = $this->createMatcher($name);

            $replace = '$1<?php echo Widget::'.$name.'$2; ?>';

            return preg_replace($pattern, $replace, $view);
        });
    }

    /**
     * Get the regular expression for a generic Blade function.
     *
     * @param  string  $function
     * @return string
     */
    protected function createMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*\))/';
    }

    /**
     * Determine whether a widget there or not.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->widgets);
    }

    /**
     * Calling a specific widget.
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return mixed
     */
    public function call($name, array $parameters = array())
    {
        return $this->get($name, $parameters);
    }

    /**
     * Calling a specific widget.
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return mixed
     */
    public function get($name, array $parameters = array())
    {
        if ($this->hasGroup($name)) {
            return $this->callGroup($name, $parameters);
        }

        if ($this->has($name)) {
            $callback = $this->widgets[$name];

            return $this->getCallback($callback, $parameters);
        }

        return null;
    }

    /**
     * Get a callback from specific widget.
     *
     * @param mixed $callback
     * @param array $parameters
     *
     * @return mixed
     */
    protected function getCallback($callback, array $parameters)
    {
        if ($callback instanceof Closure) {
            return $this->createCallableCallback($callback, $parameters);
        } elseif (is_string($callback)) {
            return $this->createStringCallback($callback, $parameters);
        } else {
            return;
        }
    }

    /**
     * Get a result from string callback.
     *
     * @param string $callback
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function createStringCallback($callback, array $parameters)
    {
        if (function_exists($callback)) {
            return $this->createCallableCallback($callback, $parameters);
        } else {
            return $this->createClassesCallback($callback, $parameters);
        }
    }

    /**
     * Get a result from callable callback.
     *
     * @param callable $callback
     * @param array    $parameters
     *
     * @return mixed
     */
    protected function createCallableCallback($callback, array $parameters)
    {
        return call_user_func_array($callback, $parameters);
    }

    /**
     * Get a result from classes callback.
     *
     * @param string $callback
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function createClassesCallback($callback, array $parameters)
    {
        list($className, $method) = Str::parseCallback($callback, 'register');

        $instance = $this->container->make($className);

        $callable = array($instance, $method);

        return $this->createCallableCallback($callable, $parameters);
    }

    /**
     * Group some widgets.
     *
     * @param string $name
     * @param array  $widgets
     */
    public function group($name, array $widgets)
    {
        $this->groups[$name] = $widgets;

        $this->registerBlade($name);
    }

    /**
     * Group some widgets, merging if previously set.
     *
     * @param string $name
     * @param array  $newWidgets
     */
    public function mergeGroup($name, array $newWidgets)
    {
        $widgets = $this->hasGroup($name) ? $this->groups[$name] : [];

        $this->groups[$name] = array_merge($widgets, $newWidgets);

        $this->registerBlade($name);
    }

    /**
     * Determine whether a group of widgets there or not.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasGroup($name)
    {
        return array_key_exists($name, $this->groups);
    }

    /**
     * Call a specific group of widgets.
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return string
     */
    public function callGroup($name, $parameters = array())
    {
        if (!$this->hasGroup($name)) {
            return;
        }

        $result = '';

        foreach ($this->groups[$name] as $key => $widget) {
            $result .= $this->get($widget, array_get($parameters, $key, array()));
        }

        return $result;
    }

    /**
     * Get a group of widgets.
     *
     * @param  string $name
     * @return array|null
     */
    public function getGroup($name)
    {
        if (!$this->hasGroup($name)) {
            return;
        }

        return $this->groups[$name];
    }

    /**
     * Get a collection of a group of widgets.
     *
     * @param  string $name
     * @return \Illuminate\Support\Collection|null
     */
    public function collectGroup($name)
    {
        if (!$this->hasGroup($name)) {
            return;
        }

        return collect($this->groups[$name]);
    }

    /**
     * Reorder widgets.
     *
     * @param array $widgets
     *
     * @return array
     */
    protected function reorderWidgets($widgets)
    {
        $formatted = [];

        foreach ($widgets as $key => $widget) {
            if (is_array($widget)) {
                $formatted[] = [
                    'name' => array_get($widget, 0),
                    'order' => array_get($widget, 1),
                ];
            } else {
                $formatted[] = [
                    'name' => $widget,
                    'order' => $key,
                ];
            }
        }

        return collect($formatted)->sortBy(function ($widget) {
            return $widget['order'];
        })->all();
    }

    /**
     * Handle call to the class.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters = array())
    {
        return $this->get($method, $parameters);
    }
}
