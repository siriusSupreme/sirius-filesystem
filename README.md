# sirius-filesystem #


用法 
===

文件上传
---
目前系统自带的 用于 文件上传的 类，依赖于 Symfony 包，如果你不习惯使用这个包的话，可以自定义 用于文件上传的 类，自定义类 必须 继承 \SplFileInfo 类，且实现一个 公共的 用于生成唯一文件名的 方法。


自定义云端存储
---
系统自带了 locla 本地存储、s3 云端存储、ftp 存储等，如果你觉得自带的不够用或者不适用，你可以进行自定义，比如 七牛上传、OSS 云存储等。

    //1、定义 一个 适配器类
    namespace your/namespace
        
    use League\Flysystem\Adapter\AbstractAdapter;
    use League\Flysystem\Adapter\CanOverwriteFiles;
    use League\Flysystem\ConfigAwareTrait;
    use League\Flysystem\Filesystem;
        
    calss Qiniu extends AbstractAdapter implements CanOverwriteFiles{
        
        use ConfigAwareTrait;
            
        public function __constructor($config=[]){
            
            $this->setConfig($config);
        }
        
        //其它方法 自行实现
    }
        
    //2、注册 扩展，第一个 参数 对应 disks 里面的键名，自己定义，闭包的 参数 $config 对应 键名 相应的值，即 适配器类 的配置 
    $fs->extend('qiniu',function($config){
        return new Filesystem(new Qiniu($config));
    });
        
    //使用
    $fs->disk('qiniu')->其他方法
    



代码
---


    <?php
        
    use Sirius\Filesystem\FilesystemManager
        
    //配置项，具体参考 config.php 文件
    $config=[]
    $fm=new FilesystemManager($config);
        
    //获取 文件系统 适配器 驱动
        
    //1、获取 本地 或者 云端 适配器
    $adapter=$fm->disk('disks 的 键名');
    //或者
    $adapter=$fm->driver('disks 的 键名');
        
    //2、获取 默认云端 适配器
    $adapter=$fm->clound();
        
    //文件处理，具体参考 \Sirius\Filesystem\FilesystemAdapter 类方法
    $adapter->put('路径','内容 或者 资源句柄（一般用于文件上传）',[驱动器的配置选项],'如果传递的是资源句柄，该项则为 生成文件名 的 方法');
    
    
    
    
    
    
    


