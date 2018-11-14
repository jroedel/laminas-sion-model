<?php
// SionModel/View/Helper/Email.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\Validator\EmailAddress;
use Zend\Filter\StringTrim;
use Zend\Form\ElementInterface;

class HelpBlock extends AbstractHelper
{
    protected $pattern;

    public function __construct()
    {
        $this->pattern = '<p class="help-block">%s</p>';
    }
    /**
     * @param ElementInterface $element
     * @return string
     */
    public function __invoke($element, $options = [])
    {
        if (!$element instanceof ElementInterface) {
            return '';
        }
        $text = $element->getOption('help-block');
        if (!isset($text) || !is_string($text)) {
            return '';
        }

        $shouldEscape = isset($options['shouldEscape']) ? (bool)$options['shouldEscape'] : true;
        $shouldTranslate = isset($options['shouldTranslate']) ? (bool)$options['shouldTranslate'] : true;

        if ($shouldEscape) {
            $text = $this->view->escapeHtml($text);
        }
        if ($shouldTranslate) {
            $text = $this->view->translate($text);
        }

        return sprintf($this->pattern, $text);
    }

    /**
     * Get the pattern value
     * @return string
     */
    public function getPattern()
    {
        if (!isset($this->pattern)) {
            throw new \Exception('Something went wrong, no pattern available');
        }
        return $this->pattern;
    }

    /**
     * Set the pattern value
     * @param string $pattern
     * @return self
     */
    public function setPattern(?string $pattern)
    {
        $this->pattern = $pattern;
        return $this;
    }
}
