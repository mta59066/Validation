<?php

namespace Respect\Validation\Exceptions;

use \InvalidArgumentException;
use \Exception;
use \RecursiveIteratorIterator;
use \RecursiveTreeIterator;
use Respect\Validation\ExceptionIterator;
use Respect\Validation\Reportable;

class ValidationException extends InvalidArgumentException
{
    const ITERATE_TREE = 1;
    const ITERATE_ALL = 2;
    public static $defaultTemplates = array(
        'Data validation failed: "%s"'
    );
    protected $related = array();
    protected $params = array();
    protected $id = '';
    protected $template = '';

    public function getParams()
    {
        return $this->params;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public static function create($input=null)
    {
        $i = new static;
        if (func_get_args() > 0)
            return call_user_func_array(array($i, 'configure'), func_get_args());
        else
            return $i;
    }

    public function configure($input=null)
    {
        $this->message = $this->getMainMessage();
        $this->params = func_get_args();
        $this->stringifyInput();
        $this->guessId();
        return $this;
    }

    protected function guessId()
    {
        if (!empty($this->id))
            return;
        $id = end(explode('\\', get_called_class()));
        $id = lcfirst(str_replace('Exception', '', $id));
        $this->setId($id);
    }

    protected function stringifyInput()
    {
        $param = &$this->params[0];
        if (!is_object($param) || method_exists($param, '__toString'))
            $param = (string) $param;
        else
            $param = get_class($param);
    }

    public function chooseTemplate()
    {
        return key(static::$defaultTemplates);
    }

    public function getFullMessage()
    {
        $message = array();
        foreach ($this->getIterator(false, self::ITERATE_TREE) as $m)
            $message[] = $m;
        return implode(PHP_EOL, $message);
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRelatedByName($name)
    {
        foreach ($this->getIterator(true) as $e)
            if ($e->getId() === $name)
                return $e;
        return false;
    }

    public function getIterator($full=false, $mode=self::ITERATE_ALL)
    {
        $exceptionIterator = new ExceptionIterator($this, $full);
        if ($mode == self::ITERATE_ALL)
            return new RecursiveIteratorIterator($exceptionIterator, 1);
        else
            return new RecursiveTreeIterator($exceptionIterator);
    }

    public function findRelated()
    {
        $target = $this;
        $path = func_get_args();
        while (!empty($path) && $target !== false)
            $target = $this->getRelatedByName(array_shift($path));
        return $target;
    }

    public function getMainMessage()
    {
        $sprintfParams = $this->params;
        if (empty($sprintfParams))
            return $this->message;
        array_unshift($sprintfParams, $this->getTemplate());
        return call_user_func_array('sprintf', $sprintfParams);
    }

    public function getRelated()
    {
        return $this->related;
    }

    public function setRelated(array $relatedExceptions)
    {
        foreach ($relatedExceptions as $related)
            $this->addRelated($related);
        return $this;
    }

    public function addRelated(ValidationException $related)
    {
        $this->related[] = $related;
        return $this;
    }

    public function getTemplate()
    {
        if (!empty($this->template))
            return $this->template;
        $templateKey = call_user_func_array(
            array($this, 'chooseTemplate'), $this->params
        );
        return $this->template = static::$defaultTemplates[$templateKey];
    }

    public function __toString()
    {
        return $this->getMainMessage();
    }

}