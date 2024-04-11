<?php

namespace Void;

class Semaphore
{
    protected $count;
    protected $total;

    public function __construct($total = null)
    {
        if(!is_null($total)) {
            $this->setTotal($total);
        }
    }

    public function setTotal($num)
    {
        $this->count = $this->total = intval($num);
        return $this;
    }

    public function instanceLeft()
    {
        return $this->total - $this->count;
    }

    public function checkout()
    {
        if($this->instanceLeft()) {
            $this->count--;
            return true;
        }
        return false;
    }

    public function checkin()
    {
        $this->count++;
        if($this->count > $this->total) {
            $this->count = $this->total;
        }
        return $this;
    }
}
