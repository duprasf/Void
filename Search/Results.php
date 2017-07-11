<?php
namespace Void\Search;

class Results implements \Iterator, \Countable, \ArrayAccess {
    /**
    * $_valid is for the iterator
    *
    * @var $_valid bool
    */
    private $_valid=false;

    public $operation;
    public $searchRegex;
    public $searchCaseInsensitive;
    public $filterName;
    public $path;
    public $searchTerm;
    public $replaceTerm = null;
    public $results = array();

    public function __construct(\Void\Search $search, $operation) {
        $this->searchRegex = $search->searchRegex;
        $this->searchCaseInsensitive = $search->searchCaseInsensitive;
        $this->filterName = $search->filterName;
        $this->path = $search->path;
        $this->searchTerm = $search->searchTerm;
        $this->operation = $operation;
    }

    public function addResult(array $result) {
        $this->results[] = $result;
        return $this;
    }

    public function getFileCount(){
        return count($this->results);
    }
    public function count() {
        $total = 0;
        foreach($this->results as $cr){
            if(isset($cr["finds"]))
                $total+=count($cr["finds"]);
        }
        return $total;
    }

    /**
    * Implementation of the ArrayAccess interface
    */
    public function offsetExists ($offset) {
        return isset($this->results[$offset])?true:false;
    }
    public function offsetGet ($offset){
        return isset($this->results[$offset]) ? $this->results[$offset] : null;
    }
    public function offsetSet ($offset, $value){
        if(isset($this->results[$offset])) {
            $this->results[$offset] = $value;
        }
        return $this;
    }
    public function offsetUnset($offset){
        if(isset($this->results[$offset])) {
            unset($this->results[$offset]);
        }
        return $this;
    }

    /**
    * Implementation of the Iterator interface
    */
    public function rewind()  { if(is_array($this->results)) sort($this->results); $this->_valid = (false !== reset($this->results)); }
    public function current() { $r = current($this->results); return $r; }
    public function key()     { return key($this->results); }
    public function next()    { $this->_valid = (false !== next($this->results)); }
    public function valid()   { return $this->_valid; }
}
