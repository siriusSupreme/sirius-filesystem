<?php

namespace Sirius\Filesystem\Contracts;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param  string  $name
     * @return \Sirius\Filesystem\Contracts\Filesystem
     */
    public function disk($name = null);
}
