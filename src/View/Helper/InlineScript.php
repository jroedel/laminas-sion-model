<?php

namespace SionModel\View\Helper;

class InlineScript extends \Zend\View\Helper\InlineScript
{
    protected $nonce;

    /**
     *
     */
    public function __construct($nonce = null)
    {
        $this->nonce = $nonce;
        if (isset($nonce)) {
            $this->setAllowArbitraryAttributes(true);
        }
    }

    /**
     * {@inheritDoc}
     * @see \Zend\View\Helper\InlineScript::__invoke()
     */
    public function __invoke(
        $mode = self::FILE,
        $spec = null,
        $placement = 'APPEND',
        array $attrs = [],
        $type = 'text/javascript'
    ) {
        return parent::__invoke($mode, $spec, $placement, $attrs, $type);
    }
    /**
     * {@inheritDoc}
     * @see \Zend\View\Helper\HeadScript::captureStart()
     */
    public function captureStart(
        $captureType = \Zend\View\Helper\Placeholder\Container\AbstractContainer::APPEND,
        $type = 'text/javascript',
        $attrs = []
    ) {
        if (isset($this->nonce) && ! isset($attrs['nonce'])) {
            $attrs['nonce'] = $this->nonce;
        }

        return parent::captureStart($captureType, $type, $attrs);
    }

    /**
     * {@inheritDoc}
     * @see \Zend\View\Helper\HeadScript::captureEnd()
     */
    public function captureEnd()
    {
        //@todo calculate sums
        $return = parent::captureEnd();
        return $return;
    }

    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
        if (isset($nonce)) {
            $this->setAllowArbitraryAttributes(true);
        }
        return $this;
    }

    public function getNonce()
    {
        return $this->nonce;
    }
}
