# sirius-filesystem #


### 用法 ###




    <?php
    
    use Sirius\Filesystem\FilesystemManager
   
    $fm=new FilesystemManager();
    
    $fm->driver('local')->put($path, $contents, $options);


