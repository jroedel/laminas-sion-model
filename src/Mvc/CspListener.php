<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Mvc;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\MvcEvent;
use Zend\Math\Rand;

class CspListener implements ListenerAggregateInterface
{
    /**
     * @var callable[] An array with callback functions or methods.
     */
    protected $listeners = array();
    
    protected $nonce;
    
    protected $config;

    /**
     * @param string $template name of the template to use on unauthorized requests
     */
    public function __construct($config)
    {
        $this->nonce = Rand::getString(12);
        $this->config = $config;
        $GLOBALS['inline-nonce'] = $this->nonce;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $eventName = $this->config['sion_model']['csp_config']['inject_headers_event'];
        $this->listeners[] = $events->attach($eventName, array($this, 'injectHeader'), 5000);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }
    
    /**
     * Callback used when a dispatch error occurs. Modifies the
     * response object with an according error if the application
     * event contains an exception related with authorization.
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function injectHeader(MvcEvent $event)
    {
        header_remove('Content-Security-Policy');
        $headerString = $this->config['sion_model']['csp_config']['csp_string'];
        $headerString = str_replace('{:nonce}', $this->nonce, $headerString);
        header('Content-Security-Policy: '.$headerString);
    }
    
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
        $GLOBALS['inline-nonce'] = $nonce;
        return $this;
    }
    
    public function getNonce()
    {
        return $this->nonce;
    }
}
