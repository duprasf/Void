<?php
namespace Void;

class ArrayObject extends \ArrayObject
{
    /**
    * Merge new information into the ArrayObject
    *
    * @param array $array the new information to be added
    * @param bool $overwrite [true] overwrite the existing information if true, ignore if false
    * @return ArrayObject
    */
    public function merge(array $array, $overwrite = true)
    {
        foreach($array as $key=>$val) {
            if(!isset($this[$key]) || (isset($this[$key]) && $overwrite)) {
                $this[$key] = $val;
            }
        }

        return $this;
    }
}
