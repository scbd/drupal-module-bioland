<?php

namespace ConvertApi;

/**
 * Stub of convertapi/convertapi-php's FileUpload for unit tests.
 */
class FileUpload
{
    public $filePath;

    public function __construct($filePath, $fileName = null)
    {
        $this->filePath = $filePath;
    }
}
