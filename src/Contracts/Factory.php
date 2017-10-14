<?php

namespace Support\Filesystem\Contracts;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param  string  $name
     * @return \Support\Filesystem\Contracts\Filesystem
     */
    public function disk($name = null);
}
