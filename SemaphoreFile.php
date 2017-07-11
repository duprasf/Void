<?php
namespace Void;

class SemaphoreFile extends Semaphore
{
    protected $file;

    public function __construct($total = null, $file = null)
    {
        parent::__construct($total);
        if(!is_null($file)) $this->setFile($file);
        else $this->setFile(tempnam(sys_get_temp_dir(), 'semaphore-'));
    }

    public function setFile($file)
    {
        if(is_dir($file) && is_writable($file)) {
            $this->file = tempnam($file, 'semaphore-');
        }
        elseif((file_exists($file) && is_writable($file)) || (!is_dir($file) && is_dir(dirname($file)) && is_writable(dirname($file)))) {
            $this->file = $file;
        }
        else {
            $this->file = null;
        }
        return $this;
    }

    public function isFileWritable()
    {
        return is_writable($this->file);
    }

    public function checkout()
    {
        if(file_exists($this->file)) {
            return false;
        }
        file_put_contents($this->file, date('Y-m-d H:i:s.u'));
        chmod($this->file, 0666);
        return true;
    }

    public function checkin()
    {
        if(file_exists($this->file))
            return unlink($this->file);
        return false;
    }

    public function detectProblems()
    {
        return array(
            "fileExists"=>file_exists($this->file),
            "isWritable"=>$this->isFileWritable(),
        );
    }
}
