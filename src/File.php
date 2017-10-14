<?php

namespace Sirius\Filesystem;

use Sirius\Filesystem\Traits\FileHelpers;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

class File extends SymfonyFile
{
    use FileHelpers;
}
