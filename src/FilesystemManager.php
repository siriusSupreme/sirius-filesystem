<?php

namespace Illuminate\Filesystem;

use Closure;
use Sirius\Filesystem\Contracts\Factory;
use Sirius\Support\Arr;
use InvalidArgumentException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Adapter\Local as LocalAdapter;

/**
 * @mixin \Sirius\Filesystem\Contracts\Filesystem
 */
class FilesystemManager implements Factory
{
    /**
     * The container instance.
     *
     * @var \Sirius\Container\Container
     */
    protected $container;

    /**
     * The array of resolved filesystem drivers.
     *
     * @var array
     */
    protected $disks = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * Create a new filesystem manager instance.
     *
     * @param  \Sirius\Container\Container $container
     *
     * @return void
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Get a filesystem instance.
     *
     * @param  string  $name
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    public function drive($name = null)
    {
        return $this->disk($name);
    }

    /**
     * Get a filesystem instance.
     *
     * @param  string  $name
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    public function disk($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->disks[$name] = $this->get($name);
    }

    /**
     * Get a default cloud filesystem instance.
     *
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    public function cloud()
    {
        $name = $this->getDefaultCloudDriver();

        return $this->disks[$name] = $this->get($name);
    }

    /**
     * Attempt to get the disk from the local cache.
     *
     * @param  string  $name
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    protected function get($name)
    {
        return $this->disks[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given disk.
     *
     * @param  string  $name
     * @return \Sirius\Filesystem\Contracts\Filesystem
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create'.ucfirst($config['driver']).'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        } else {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }
    }

    /**
     * Call a custom driver creator.
     *
     * @param  array  $config
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    protected function callCustomCreator(array $config)
    {
        $driver = $this->customCreators[$config['driver']]($this->container, $config);

        if ($driver instanceof FilesystemInterface) {
            return $this->adapt($driver);
        }

        return $driver;
    }

    /**
     * Create an instance of the local driver.
     *
     * @param  array  $config
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    public function createLocalDriver(array $config)
    {
        $permissions = $config['permissions'] ?? [];

        $links = ($config['links'] ?? null) === 'skip'
            ? LocalAdapter::SKIP_LINKS
            : LocalAdapter::DISALLOW_LINKS;

        return $this->adapt($this->createFlysystem(new LocalAdapter(
            $config['root'], LOCK_EX, $links, $permissions
        ), $config));
    }

    /**
     * Create an instance of the ftp driver.
     *
     * @param  array  $config
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    public function createFtpDriver(array $config)
    {
        $ftpConfig = Arr::only($config, [
            'host', 'username', 'password', 'port', 'root', 'passive', 'ssl', 'timeout',
        ]);

        return $this->adapt($this->createFlysystem(
            new FtpAdapter($ftpConfig), $config
        ));
    }

    /**
     * Create a Flysystem instance with the given adapter.
     *
     * @param  \League\Flysystem\AdapterInterface  $adapter
     * @param  array  $config
     * @return \League\Flysystem\FilesystemInterface
     */
    protected function createFlysystem(AdapterInterface $adapter, array $config)
    {
        $config = Arr::only($config, ['visibility', 'disable_asserts', 'url']);

        return new Flysystem($adapter, count($config) > 0 ? $config : null);
    }

    /**
     * Adapt the filesystem implementation.
     *
     * @param  \League\Flysystem\FilesystemInterface  $filesystem
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    protected function adapt(FilesystemInterface $filesystem)
    {
        return new FilesystemAdapter($filesystem);
    }

    /**
     * Set the given disk instance.
     *
     * @param  string  $name
     * @param  mixed  $disk
     * @return void
     */
    public function set($name, $disk)
    {
        $this->disks[$name] = $disk;
    }

    /**
     * Get the filesystem connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->container['config']["filesystems.disks.{$name}"];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->container['config']['filesystems.default'];
    }

    /**
     * Get the default cloud driver name.
     *
     * @return string
     */
    public function getDefaultCloudDriver()
    {
        return $this->container['config']['filesystems.cloud'];
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string    $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->disk()->$method(...$parameters);
    }
}
