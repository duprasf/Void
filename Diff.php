<?php
namespace Void;

class Diff
{
    protected $error = '';
    protected $computedDiff;
    protected $mode = 'w';
    protected $beforeString = '';
    protected $afterString = '';
    
    const MODE_CHARACTER = 'c';
    const MODE_WORD = 'w';
    const MODE_LINE = 'l';

    public function __construct()
    {
        $this->reset();
    }
    
    public function __invoke($before, $after, array $options = array()) 
    {
        return $this->diff($before, $after, $options);
    }
    
    public function reset()
    {
        $this->computedDiff = null;
        $this->mode = self::MODE_WORD;
        $this->error = '';
        $this->beforeString = '';
        $this->afterString = '';
        return $this;
    }
    
    protected function split($string, $separators, $end, array &$positions = null)
    {
        $positions = array();
        $l = strlen($string);
        $split = array();
        for($p = 0; $p < $l;)
        {
            $e = strcspn($string, $separators.$end, $p);
            $e += strspn($string, $separators, $p + $e);
            $split[] = substr($string, $p, $e);
            $positions[] = $p;
            $p += $e;
            if(strlen($end)
            && ($e = strspn($string, $end, $p)))
            {
                $split[] = substr($string, $p, $e);
                $positions[] = $p;
                $p += $e;
            }
        }
        $positions[] = $p;
        return $split;
    }

    public function setOptions(array $options)
    {
        if(isset($options['mode'])) $this->mode = $options['mode'];
        
        return $this;
    }
    
    public function diff($before, $after, array $options = array())
    {
        $this->setOptions($options);
        $this->beforeString = $before;
        $this->afterString = $after;
        
        $mode = $this->mode;
        $for_patch = true;
        
        switch($mode)
        {
            case self::MODE_CHARACTER:
                $lb = strlen($before);
                $la = strlen($after);
                break;

            case self::MODE_LINE:
                $before = $this->split($before, "\r\n", '', $posb);
                $lb = count($before);
                $after = $this->split($after, "\r\n", '', $posa);
                $la = count($after);
                break;

            case self::MODE_WORD:
            default:
                $this->mode = self::MODE_WORD;
                $before = $this->split($before, " \t", "\r\n", $posb);
                $lb = count($before);
                $after = $this->split($after, " \t", "\r\n", $posa);
                $la = count($after);
                break;
        }
        
        $diff = array();
        for($b = $a = 0; $b < $lb && $a < $la;)
        {
            for($pb = $b; $a < $la && $pb < $lb && $after[$a] === $before[$pb]; ++$a, ++$pb);
            if($pb !== $b)
            {
                $diff[] = array(
                    'change'=>'=',
                    'position'=>($mode === 'c'  ? $b : $posb[$b]),
                    'length'=>($mode === 'c' ? $pb - $b : $posb[$pb] - $posb[$b])
                );
                $b = $pb;
            }
            if($b === $lb)
                break;
            for($pb = $b; $pb < $lb; ++$pb)
            {
                for($pa = $a ; $pa < $la && $after[$pa] !== $before[$pb]; ++$pa);
                if($pa !== $la)
                    break;
            }
            if($pb !== $b)
            {
                $diff[] = array(
                    'change'=>'-',
                    'position'=>($mode === 'c'  ? $b : $posb[$b]),
                    'length'=>($mode === 'c' ? $pb - $b : $posb[$pb] - $posb[$b])
                );
                $b = $pb;
            }
            if($pa !== $a)
            {
                $position = ($mode === 'c'  ? $a : $posa[$a]);
                $length = ($mode === 'c' ? $pa - $a : $posa[$pa] - $posa[$a]);
                $change = array(
                    'change'=>'+',
                    'position'=>$position,
                    'length'=>$length
                );
                if($for_patch)
                {
                    if($mode === 'c')
                        $patch = substr($after, $position, $length);
                    else
                    {
                        $patch = $after[$a];
                        for(++$a; $a < $pa; ++$a)
                            $patch .= $after[$a];
                    }
                    $change['patch'] = $patch;
                }
                $diff[] = $change;
                $a = $pa;
            }
        }
        if($a < $la)
        {
            $position = ($mode === 'c'  ? $a : $posa[$a]);
            $length = ($mode === 'c' ? $la - $a : $posa[$la] - $posa[$a]);
            $change = array(
                'change'=>'+',
                'position'=>$position,
                'length'=>$length
            );
            if($for_patch)
            {
                if($mode === 'c')
                    $patch = substr($after, $position, $length);
                else
                {
                    $patch = $after[$a];
                    for(++$a; $a < $la; ++$a)
                        $patch .= $after[$a];
                }
                $change['patch'] = $patch;
            }
            $diff[] = $change;
        }
        $this->computedDiff = $diff;
        return $this;
    }

    public function toArray()
    {
        return $this->computedDiff;
    }
    
    public function toHtml($encodeHtmlCharacters = false)
    {
        if(!$this->computedDiff) return false;
        
        $html = '';
        $before = $this->beforeString;
        $after = $this->afterString;
        $td = count($this->computedDiff);
        for($d = 0; $d < $td; ++$d)
        {
            $diff = $this->computedDiff[$d];
            $treatment = function($string) use($encodeHtmlCharacters) { return $encodeHtmlCharacters ? nl2br(htmlspecialchars($string, null, 'UTF-8')) : $string; };
            switch($diff['change'])
            {
                case '=':
                    $html .= $treatment(substr($before, $diff['position'], $diff['length']));
                    break;
                case '-':
                    $html .= '<del>'.$treatment(substr($before, $diff['position'], $diff['length'])).'</del>';
                    break;
                case '+':
                    $html .= '<ins>'.$treatment(substr($after, $diff['position'], $diff['length'])).'</ins>';
                    break;
                default:
                    $this->error = $diff['change'].' is not an expected difference change type';
                    return false;
            }
        }
        return $html;
    }
};
