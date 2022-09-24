<?php
namespace Void\StringTable;
class Array2 implements \ArrayAccess {
    public function replaceFile($file) {
        if(file_exists($file)) {
            $this->__str = include($file);
            if(!is_array($this->__str)) $this->__str = array();
        }
        return $this;
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
            $newFile = include($file);
            if(is_array($newFile)) {
                if($insertBefore) {
                    $this->__str+= $newFile;
                }
                else {
                    $this->__str = array_merge($this->__str, $newFile);
                }
            }
        }
        return $this;
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
        $options = array_intersect_key($options, $defaultOptions)+$defaultOptions;

        $return = "";
        if(isset($this->__str[$options["section"]][$var])) {
            $return = $this->__str[$options["section"]][$var];
            if($autoEncode) {
                $return = htmlentities($return, ENT_QUOTES, 'UTF-8', false);
            }
            if($utf8Decode) {
                $return = utf8_decode($return);
            }
        }
        else if(isset($this->__str[$var])) {
            $return = $this->getSection($var);
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

        $value = array();
        foreach($this->__str[$options["section"]] as $var=>$string) {
            $value[$var] = $string[$options["lang"]];
        }
        return $value;
    }

    public function sectionExists($section) {
        return isset($this->__str[$section]);
        return false;
    }
}
