<?php

namespace Sirius\Filesystem;

use Closure;
use Aws\S3\S3Client;
use OpenCloud\Rackspace;
use Sirius\Support\Arr;
use InvalidArgumentException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Rackspace\RackspaceAdapter;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\AwsS3v3\AwsS3Adapter as S3Adapter;
use Sirius\Filesystem\Contracts\Factory as FactoryContract;
use Sirius\Support\Contracts\Repository;
use Sirius\Support\Repository as Config;


class FilesystemManager implements FactoryContract
{
  /**
   * @var FilesystemManager
   */
  private static $instance=null;

    /**
     * The repository instance.
     *
     * @var \Sirius\Support\Contracts\Repository
     */
    protected $config;

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
     * @param  Repository|array $config
     *
     */
    public function __construct($config=[])
    {
//      默认配置
      $defaults=require __DIR__.'/config.php';

      if ($config instanceof Repository){
        $config = $config->all();
      } else{
        $config=(array)$config;
      }

      $config=array_merge( $defaults,$config);

      $this->config = new Config( $config );

    }

  /**
   * 获取 文件系统管理器 实例
   *
   * @param Repository|array $config
   * @param bool $force
   *
   * @return FilesystemManager
   */
    public static function getInstance($config=[],$force=false){
      if (is_null( self::$instance) || $force===true){
        self::$instance=new self($config);
      }

      return self::$instance;
    }

    /**
     * Get a filesystem instance.
     *
     * @param  string  $name
     * @return \Sirius\Filesystem\FilesystemAdapter
     */
    public function drive($name = null)
    {
        return $this->disk($name);
    }

    /**
     * Get a filesystem instance.
     *
     * @param  string  $name
     *
     * @return \Sirius\Filesystem\FilesystemAdapter
     */
    public function disk($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->disks[$name] = $this->get($name);
    }

    /**
     * Get a default cloud filesystem instance.
     *
     * @return \Sirius\Filesystem\FilesystemAdapter
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
     * @return \Sirius\Filesystem\FilesystemAdapter
     */
    protected function get($name)
    {
        return $this->disks[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given disk.
     *
     * @param  string  $name
     *
     * @return \Sirius\Filesystem\FilesystemAdapter
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
     * @return \Sirius\Filesystem\FilesystemAdapter
     */
    protected function callCustomCreator(array $config)
    {
        $driver = $this->customCreators[$config['driver']]( $config);

        if ($driver instanceof Flysystem) {
            return $this->adapt($driver);
        }

        return $driver;
    }

    /**
     * Create an instance of the local driver.
     *
     * @param  array  $config
     * @return \Sirius\Filesystem\FilesystemAdapter
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
     * @return \Sirius\Filesystem\FilesystemAdapter
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
     * Create an instance of the Amazon S3 driver.
     *
     * @param  array  $config
     * @return \Sirius\Filesystem\Contracts\Cloud
     */
    public function createS3Driver(array $config)
    {
        $s3Config = $this->formatS3Config($config);

        $root = $s3Config['root'] ?? null;

        $options = $config['options'] ?? [];

        return $this->adapt($this->createFlysystem(
            new S3Adapter(new S3Client($s3Config), $s3Config['bucket'], $root, $options), $config
        ));
    }

    /**
     * Format the given S3 configuration with the default options.
     *
     * @param  array  $config
     * @return array
     */
    protected function formatS3Config(array $config)
    {
        $config += ['version' => 'latest'];

        if ($config['key'] && $config['secret']) {
            $config['credentials'] = Arr::only($config, ['key', 'secret']);
        }

        return $config;
    }

    /**
     * Create an instance of the Rackspace driver.
     *
     * @param  array  $config
     * @return \Sirius\Filesystem\Contracts\Cloud
     */
    public function createRackspaceDriver(array $config)
    {
        $client = new Rackspace($config['endpoint'], [
            'username' => $config['username'], 'apiKey' => $config['key'],
        ]);

        $root = $config['root'] ?? null;

        return $this->adapt($this->createFlysystem(
            new RackspaceAdapter($this->getRackspaceContainer($client, $config), $root), $config
        ));
    }

    /**
     * Get the Rackspace Cloud Files container.
     *
     * @param  \OpenCloud\Rackspace  $client
     * @param  array  $config
     * @return \OpenCloud\ObjectStore\Resource\Container
     */
    protected function getRackspaceContainer(Rackspace $client, array $config)
    {
        $urlType = $config['url_type'] ?? null;

        $store = $client->objectStoreService('cloudFiles', $config['region'], $urlType);

        return $store->getContainer($config['container']);
    }

    /**
     * Create a Flysystem instance with the given adapter.
     *
     * @param  \League\Flysystem\AdapterInterface  $adapter
     * @param  array  $config
     * @return \League\Flysystem\Filesystem
     */
    protected function createFlysystem(AdapterInterface $adapter, array $config)
    {
        $config = Arr::only($config, ['visibility', 'disable_asserts', 'url']);

        return new Flysystem($adapter, count($config) > 0 ? $config : null);
    }

    /**
     * Adapt the filesystem implementation.
     *
     * @param  \League\Flysystem\Filesystem  $filesystem
     *
     * @return \Sirius\Filesystem\FilesystemAdapter
     */
    protected function adapt(Flysystem $filesystem)
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
        return $this->config["disks.{$name}"];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->config['default'];
    }

    /**
     * Get the default cloud driver name.
     *
     * @return string
     */
    public function getDefaultCloudDriver()
    {
        return $this->config['cloud'];
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
