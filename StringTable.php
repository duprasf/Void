<?php
namespace Void;
class StringTable implements \ArrayAccess {
	protected $__str;
	protected $defaultSection;
	protected $defaultLang;
	protected $autoEncode = false;
	protected $utf8Decode = false;
	protected static $tablesList = array();
	
	/**
	* Get a stringtable with the main file, if the file was already loaded it will not be reloaded again
	* 
	* @param string $file the xml file name
	* @param array|string|bool $options default options "section"=>"","lang"=>"","autoEncode"=>true,"utf8Decode"=>false
	* @return Void\StringTable
	*/
	public static function get($file, $options=null)
	{
		if(is_string($options))
			$options = array("section"=>$options);
		if(is_bool($options))
			$options = array("autoEncode"=>$options);
		if(!is_array($options)) 
			$options = array();
			
		$args = func_get_args();
		if(isset($args[2]))
			$options["lang"]=$args[2];
		if(isset($args[3]))
			$options["autoEncode"]=$args[3];

		$defaultOptions = array("section"=>"",
							   "lang"=>"",
							   "autoEncode"=>true,
							   "utf8Decode"=>false,
							   );
		$options = array_intersect_key($options, $defaultOptions) + $defaultOptions;
		
		if(!(isset(self::$tablesList[$file]) && self::$tablesList[$file] instanceof stringTable)){
			try {
				self::$tablesList[$file] = static::getNewObject($file);
			}
			catch(Exception $e) {
				// display warning message
				exit($e->getMessage());
			}
		}
		self::$tablesList[$file]->setDefaultSection($options["section"]);
		self::$tablesList[$file]->setDefaultLang($options["lang"]);
		self::$tablesList[$file]->setAutoEncode($options["autoEncode"]);
		return self::$tablesList[$file];
	}

	static protected function getNewObject($file) {
		return new self($file);
	}

	protected function __construct($file) {
		$this->replaceFile($file);
	}
	
	public function replaceFile($file) {
		if(file_exists($file)) {
			$this->__str = new \DomDocument();
			$this->__str->preserveWhiteSpace = false;
			$this->__str->validateOnParse = false;
			$this->__str->loadXML(file_get_contents($file));
		}
	}
	
	/**
	* This will merge a second (or more) file to the string table.
	* 
	* @param string $file the file to be merged
	* @param bool $insertBefore if set to true the information in the new file will take precidence to the existing strings
	*/
	public function mergeFile($file, $insertBefore = false) {
		if($this->__str == null) { $this->replaceFile($file); }
		else if(file_exists($file)) {
			$dDoc = new \DomDocument();
			$dDoc->preserveWhiteSpace = false;
			$dDoc->validateOnParse = false;
			$dDoc->loadXML(file_get_contents($file));

			$items = $dDoc->getElementsByTagName('section');
			foreach($items as $item) {
				if($item instanceOf DOMNode) {
					$r = $this->__str->importNode($item, true);
					if($r instanceof DOMElement) {
						if($insertBefore) {
							$this->__str->documentElement->insertBefore($r, $this->__str->documentElement->firstChild);
						}
						else {
							$this->__str->documentElement->appendChild($r);
						}
					}
				}
			}
		}
	}

	public function __invoke($var, $options=null) {
		return $this->getStr($var, $options);
	}
	
	public function getStr($var, $options=null){
		if(is_string($options))
			$options = array("section"=>$options);
		if(is_bool($options))
			$options = array("autoEncode"=>$options);
		if(!is_array($options)) 
			$options = array();
			
		$args = func_get_args();
		if(isset($args[2]))
			$options["lang"]=$args[2];
		if(isset($args[3]))
			$options["internal"]=$args[3];

		$defaultOptions = array("section"=>$this->defaultSection,
							   "lang"=>$this->defaultLang,
							   "internal"=>false,
							   "autoEncode"=>$this->autoEncode,
							   "utf8Decode"=>$this->utf8Decode,
							   );
		extract(array_intersect_key($options, $defaultOptions)+$defaultOptions);
		
		$return = "";
		$value = array(); 
		if($this->__str instanceof DOMDocument) {
			// find the section and string requested
			$xpath = new \DomXPath($this->__str);
			// if the string is available in the lang (or default language) return it
			if(!is_string($section) || !is_string($var) || !is_string($lang)) {
				throw new Exception\StringTable('Missing some information in the string table: section: "'.$section.'", var: "'.$var.'", lang: "'.$lang.'"');
			}
			if($value = $xpath->query("section[@name='{$section}']/str[@name='{$var}']/".substr($lang,0,2))->item(0)) {
				$return = $value->nodeValue;
			}
			// if the string is available in the DEFAULT_LANGUAGE return it
			else if(defined('DEFAULT_LANGUAGE') && $value = $xpath->query("section[@name='{$section}']/str[@name='{$var}']/".substr(DEFAULT_LANGUAGE,0,2))->item(0)) {
				$return = $value->nodeValue;
			}
			// if nothing was good enough to return, return the first element of this string
			else if($value = $xpath->query("section[@name='{$section}']/str[@name='{$var}']/*[1]")->item(0)) {
				$return = $value->nodeValue;
			}
			else {
				$return = "Not found: {$section}/{$var}";
				if($section != "common") {
					$common = $this->getStr($var, array("section"=>"common", "internal"=>true));
					if(strpos($common, "Not found: common/{$var}") === false) {
						$return = $common;
					}
				}
			}
		}
		else {
			$return = "stringTable was not able to retreive your information, malformed XML maybe?";
		}
		if($autoEncode) {
			$return = htmlentities($return, ENT_QUOTES, 'UTF-8', false);
		}
		if($utf8Decode) {
			$return = utf8_decode($return);
		}
		return $return;
	}

	public function getSection($options=null) {
		if(is_string($options))
			$options = array("section"=>$options);
		if(!is_array($options)) 
			$options = array();
			
		$args = func_get_args();
		if(isset($args[2]))
			$options["lang"]=$args[2];

		$defaultOptions = array("section"=>$this->defaultSection,
							   "lang"=>$this->defaultLang,
							   "autoEncode"=>$this->autoEncode,
							   );
		$options = array_merge(array_intersect_key($options, $defaultOptions), array_diff_key($defaultOptions, $options));
		extract($options);

		$value = array(); 
		if($this->__str instanceof DOMDocument) {
			// find the section and string requested
			$xpath = new \DomXPath($this->__str);
			// if the string is available in the lang (or default language) return it
			if($list = $xpath->query("section[@name='{$section}']/*")) {
				$return = array();
				foreach($list as $val) {
					$name = $val->getAttribute("name");
					$return[$name] = $this->getStr($name, $options);
				}
				return $return;
			}
		}
		return "stringTable was not able to retreive your information, malformed XML maybe?";
	}
	
	public function sectionExists($section) {
		$value = array(); 
		if($this->__str instanceof DOMDocument) {
			// find the section and string requested
			$xpath = new \DomXPath($this->__str);
			// if the string is available in the lang (or default language) return it
			if($list = $xpath->query("section[@name='{$section}']/*")) {
				return true;
			}
		}
		return false;
	}
	
	public function getFromSection($section, $str, $lang = "") {
		return $this->getStr($str, array("section"=>$section, "lang"=>$lang));
	}

	public function setDefaultSection($section) {
		$this->defaultSection = $section;
		return $this;
	}
	
	public function getDefaultSection() {
		return $this->defaultSection;
	}
	
	public function setDefaultLang($lang) {
		$this->defaultLang = $lang;
		return $this;
	}
	
	public function setAutoEncode($autoEncode) {
		$this->autoEncode = $autoEncode?true:false;
		return $this;
	}
	
	public function setUtf8Decode($utf8Decode) {
		$this->utf8Decode = $utf8Decode?true:false;
		return $this;
	}
	
	public function __get($name) {
		return $this->getStr($name);
	}

	public function offsetExists ($offset) {
		if(strpos($this->getStr($offset), "Not found:") === false) {
			return true;
		}
		return false;
	}
	public function offsetGet ($offset){
		return $this->getStr($offset);
	}
	public function offsetSet ($offset, $value){
		return false;
	}
	public function offsetUnset ($offset){
		return false;
	}
}
