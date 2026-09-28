<?php

namespace ConvertApi;

/**
 * Stub of convertapi/convertapi-php's ResultFile for unit tests.
 *
 * Mirrors the real 3.0.0 shape: a public $fileInfo array whose getUrl()
 * reads the Url key (an undefined key when the result was returned inline
 * as base64 FileData, i.e. StoreFile=false) and whose getContents() only
 * knows how to download from that Url. A string argument is a convenience
 * for tests that model a stored (Url) result and its downloaded bytes.
 */
class ResultFile
{
    public array $fileInfo;

    private $contents;

    public function __construct($fileInfoOrContents = '')
    {
        if (is_array($fileInfoOrContents)) {
            $this->fileInfo = $fileInfoOrContents;
            $this->contents = null;
            return;
        }
        $this->fileInfo = ['Url' => 'https://stub.convertapi.test/' . md5((string) $fileInfoOrContents)];
        $this->contents = (string) $fileInfoOrContents;
    }

    public function getUrl()
    {
        return $this->fileInfo['Url'];
    }

    public function getContents()
    {
        if ($this->contents !== null) {
            return $this->contents;
        }
        // Same failure mode as the real library when Url is absent.
        return file_get_contents($this->getUrl());
    }
}
