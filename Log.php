<?php
namespace Void;

class Log extends \stdClass implements \Iterator {
	private $valid = false; // used by Iterator interface
	protected $data;
	protected $metadata;
	
	public function __construct()
	{
		$this->data = array();
		$this->metadata = array();
	}
	
	public function __get($name)
	{
		return isset($this->metadata[$name]) ? $this->metadata[$name] : null;
	}
	
	public function __set($name, $val) 
	{
		if(!is_string($name)) $name = (string)$name;
		$this->metadata[$name] = $val;
		return $this;
	}
	
	public function __toString()
	{
		return $this->render();
	}
	
	public function render($separator=PHP_EOL)
	{
		return implode($separator, $this->data);
	}
	
	public function __invoke($info, $clean=false)
	{
		if($clean) $this->data = array();
		$this->data[] = $info;
	}
	
	public function current () { return current($this->data); }
	public function key ()     { return key($this->data); }
	public function next ()    { $this->valid = next($this->data); }
	public function rewind ()  { reset($this->data); }
	public function valid ()   { return $this->valid; }
}
