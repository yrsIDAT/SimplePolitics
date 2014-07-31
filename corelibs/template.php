<?php
class CodeBlock
{
    private $text;
    private $globals;
    private $modifiers = array("var", "if", "for", "insert");

    public function __construct($text, array $globals = array())
    {
        $this->text = $text;
        $this->globals = $globals;
    }

    public function parse()
    {
        // echo "CodeBlock->parse: text =";
        // var_dump($this->text);
        $text = $this->text;
        foreach ($this->modifiers as $modifier) {
            $text = preg_replace_callback(
            '#\{(?:\(<.*?>\))?%' . $modifier . '(\(\d+\)|)\s+(.*?)\s*?%(?:\(</.*?>\))?end' . $modifier . '\1(?!\(\d+\))\}\s*?(?:[\r\n]+)?#s',
            array($this, "parse_$modifier"), $text);
        }
        $text = preg_replace_callback("#\{%\{\s*([\w.]+)\s*\}%\}#", array($this, "get_global_match"), $text);
        return $text;
    }

    private function get_global_match($matches)
    {
        $varname = $matches[1];
        $parts = explode('.', $varname);
        $last = array_pop($parts);
        $arr = $this->globals;
        foreach ($parts as $part) {
            if (isset($arr[$part])) {
                if (gettype($arr[$part]) === 'array') {
                    $arr = $arr[$part];
                } elseif (gettype($arr[$part]) === 'object') {
                    $arr = (array) $arr[$part];
                }
                continue;
            }
            return "";
        }
        return $arr[$last];
    }

    private function parse_var($m)
    {
        $matches = array();
        preg_match("/(\w+)\s*=\s*(.*)/s", $m[2], $matches);
        $this->setGlobal($matches[1], new CodeBlock($matches[2], $this->globals));
    }

    private function parse_if($m)
    {
        $matches = array();
		//var_dump($m);
        $i = '\?';//str_replace("(", "", str_replace(")", "", $m[1]));
        preg_match("/(not)?\s*(\w+)\s*\{$i(.*?)$i\}(?:\s*else\s*\{$i(.*?)$i\})?/s", $m[2], $matches);
        $true = $matches[1] != "not";
        $isvar = isset($this->globals[$matches[2]]) && $this->globals[$matches[2]];
        if (($isvar && $true) || (!$isvar && !$true)) {
            $block = new CodeBlock($matches[3], $this->globals);
        } else {
            $block = new CodeBlock(count($matches) >= 5 ? $matches[4] : "", $this->globals);
        }
        return $block->parse();
    }

    private function parse_for($m)
    {
        $matches = array();
        preg_match("/(\w+)(?:\s*,\s*(\w+))?\s+in\s+(\w+)\s*\{[\r\n]*(.*)[\r\n]*\}/s", $m[2], $matches);
        $text = "";
        if (isset($this->globals[$matches[3]]) && is_array($this->globals[$matches[3]])) {
            foreach($this->globals[$matches[3]] as $k => $v) {
                $loopvars = array($matches[1] => $v);
                if ($matches[2]) {
                    $loopvars[$matches[1]] = $k;
                    $loopvars[$matches[2]] = $v;
                }
                $block = new CodeBlock($matches[4], array_merge($this->globals, $loopvars));
                $text .= $block->parse();
            }
        }
        return $text;
    }

    private function parse_insert($m)
    {
        try {
            $t = Template::getTemplate($m[2], $this->globals);
            return $t->parse();
        } catch (Exception $e) {
            return '';
        }
    }

    public function setGlobal($name, $data)
    {
        if ($data instanceof CodeBlock) {
            $this->globals[$name] = $data->parse();
        } else {
            $this->globals[$name] = $data;
        }
    }

    public function setGlobals(array $globals) {
        foreach($globals as $name => $data)
          $this->setGlobal($name, $data);
    }
}

class Template
{
    public function __construct($filename, $vars = array())
    {
        if ($filename != null) {
            if ($filename{0} == ":") {
                $text = substr($filename, 1);
            } else {
                $file = fopen($filename, "r");
                $text = fread($file, filesize($filename));
                fclose($file);
            }
        } else {
            $text = '';
        }
        $this->block = new CodeBlock($text, $vars);
    }

    public function parse(array $variables = array())
    {
        $this->block->setGlobals($variables);
        return $this->block->parse();
    }

    public static function resolveName($name)
    {
        if (count(($parts = explode(":", $name))) > 1) {
            $ns = $parts[0];
            $file = $parts[1];
        } elseif (count(($parts = explode(".", $name))) > 1) {
            $ns = $parts[1];
            $file = $parts[0];
        } else {
            throw new Exception("Invalid template name '$name'");
        }
        $ext = '.html';
        if (strpos($file, '.') !== false) {
            $ext = '';
        }
        $path = 'views/' . $ns . '/' . $file . $ext;
        if (!file_exists($path)) {
            throw new Exception("No template called '$name'");
        }
        return $path;
    }

    public static function getTemplate($name, $vars = array())
    {
        $path = self::resolveName($name);
        return new self($path, $vars);
    }
}
?>