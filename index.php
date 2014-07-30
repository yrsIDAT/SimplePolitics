<?php

//set_error_handler('error_handler');
function error_handler($severity, $message, $file, $line, $args)
{
    throw new ErrorException($message, 1, $severity, $file, $line);
}

require "vendor/autoload.php";
require "config/config.php";
require "corelibs/database.php";
require "corelibs/template.php";

$url = isset($_GET['url']) ? $_GET['url'] : 'home/index';

$parts = explode('/', $url);

if (!isset($parts[0]))
    $parts[] = 'home';

if (!isset($parts[1]))
    $parts[] = 'index';

// Do not differentiate between action/a/b/c and action/a/b/c/
if ($parts[count($parts) - 1] === '')
    array_pop($parts);

class MapDef
{
    private $min;
    private $verbs = 'all';
    private $actions;
    private $curr_type;
    private $sub_controller = false;

    public function __construct($incomming_action)
    {
        $this->min = 0;
        $this->curr_type = 'default';
        $this->actions = array($this->curr_type => $incomming_action);
    }

    public function onto($action)
    {
        $this->actions[$this->curr_type] = $action;
        $this->curr_type = 'default';
        return $this;
    }

    public function when($verb)
    {
        $this->curr_type = strtoupper(str_replace('ing', '', $verb));
        return $this;
    }

    public function getAction($verb = 'GET')
    {
        if (isset($this->actions[$verb]))
            return $this->actions[$verb];
        return $this->actions['default'];
    }

    public function setMinParams($min)
    {
        $this->min = $min;
        return $this;
    }

    public function minParams()
    {
        return $this->min;
    }

    public function setHTTPVerbs()
    {
        $this->verbs = func_get_args();
        return $this;
    }

    public function verbIsAllowed($verb)
    {
        return $this->verbs === 'all' || in_array($verb, $this->verbs);
    }

    public function remove()
    {
        $this->actions = array('default' => null);
    }

    public function useSubController()
    {
        $this->sub_controller = true;
    }

    public function hasSubController()
    {
        return $this->sub_controller;
    }
}

class ReMapper
{
    private $mappings;
    private $map_all;
    private $map_404;

    public function __construct()
    {
        $this->mappings = array();
        $this->map_all = false;
        $this->map_404 = false;
    }

    public function map($action)
    {
        if (!isset($this->mappings[$action])) {
            $this->mappings[$action] = new MapDef($action);
        }
        return $this->mappings[$action];
    }

    public function mapAll($action)
    {
        $this->map_all = new MapDef($action);
        $this->map_all->onto($action);
    }

    public function set404($action)
    {
        $this->map_404 = new MapDef($action);
        $this->map_404->onto($action);
    }

    public function getMappingFor($action)
    {
        if ($this->map_all) {
            return $this->map_all;
        }
        return $this->map($action);
    }

    public function get404()
    {
        if ($this->map_404) {
            return $this->map_404->out_action;
        }
        return null;
    }
}

class ProviderException extends Exception
{
    public function __construct($className, $message, Exception $prev = null)
    {
        parent::__construct("Unable to provide the class {$className}, {$message}", 1, $prev);
    }
}

class Provider
{
    private $dir;
    private $type;

    public function __construct($dir, $type)
    {
        $this->dir = $dir;
        $this->type = $type;
    }

    public function load($name)
    {
        $args = func_get_args();
        array_shift($args);
        try {
            include $this->dir . '/' . strtolower($name) . '.php';
        } catch (ErrorException $e) {
            if ($e->getCode() === E_ERROR) {
                throw new ProviderException($name, 'php file does not exist', $e);
            }
            throw $e;
        }
        if ($this->type === 'model') {
            array_splice($args, 0, 1, array(MySQL::getInstance()));
        }
        try {
            $class = new ReflectionClass($name);
            return $class->newInstanceArgs($args);
        } catch (ReflectionException $e) {
            switch ($e->getCode()) {
                case -1:
                    throw new ProviderException($name, 'class not found (But the file was)', $e);
                case 0:
                    throw new ProviderException($name, 'Bad class constructor', $e);
                default:
                    throw $e;
            }
        }
    }
}

function showError($code, $message)
{
    header('', false, $code);
    try {
        die(Template::getTemplate("_errors:$code")->parse(array('error_message' => $message)));
    } catch (Exception $e) {
        echo "An error occurred with message: $message<br>";
        echo "Additionally, a $code handler page does not exist.";
        die();
    }
}

function load($controller_name, $parts)
{
    if (!file_exists("controller/{$controller_name}.php")) {
        showError(404, "Unknown controller '{$controller_name}'");
    }

    require "controller/{$controller_name}.php";
    if (!class_exists($controller_name)) {
        showError(500, "Missing controller class for '{$controller_name}'");
    }
    $mapper = new ReMapper;
    $controller = new $controller_name($mapper);
    $controller->models = new Provider('models', 'model');
    $controller->libraries = new Provider('libraries', 'lib');
    $in_action = array_shift($parts);
    $controller->suggestedView = "views/{$controller_name}/{$in_action}.html";
    $verb = $_SERVER['REQUEST_METHOD'];
    $map = $mapper->getMappingFor($in_action);
    $action = $map->getAction($verb);
    if ($action === null) {
        showError(403, "Inaccessible function {$in_action}");
    }
    if ($map->hasSubController()) {
        return load("{$controller_name}_{$action}", $parts);
    }
    $minargs = $map->minParams();
    if (method_exists($controller, $action)) {
        if (count($parts) >= $minargs) {
            if ($map->verbIsAllowed($verb)) {
                call_user_func_array(array($controller, $action), $parts);
            } else {
                showError(403, "The HTTP verb '{$verb}' is not allowed for {$action}");
            }
        } else {
            showError(400, "Not enough arguments given to {$action}");
        }
    } else {
        $notFoundAction = $mapper->get404();
        if ($notFoundAction === null) {
            showError(404, "Action '{$action}' not found for controller '{$controller_name}'");
        } else {
            $controller->$notFoundAction();
        }
    }
}
$controller_name = array_shift($parts);
load($controller_name, $parts);
?>