<?php

namespace ConvertApi;

/**
 * Stub of convertapi/convertapi-php's Result for unit tests.
 */
class Result
{
    private $file;
    private $cost;

    public function __construct(ResultFile $file, $cost = 1)
    {
        $this->file = $file;
        $this->cost = $cost;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getConversionCost()
    {
        return $this->cost;
    }
}
