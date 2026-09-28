<?php

namespace ConvertApi;

/**
 * Stub of convertapi/convertapi-php's ResultFile for unit tests.
 */
class ResultFile
{
    private $contents;

    public function __construct($contents = '')
    {
        $this->contents = $contents;
    }

    public function getContents()
    {
        return $this->contents;
    }
}
