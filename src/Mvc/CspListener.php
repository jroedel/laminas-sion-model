<?php

declare(strict_types=1);

namespace SionModel\Mvc;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Math\Rand;
use Laminas\Mvc\MvcEvent;

use function header;
use function header_remove;
use function str_replace;

class CspListener implements ListenerAggregateInterface
{
    /** @var callable[] An array with callback functions or methods. */
    protected array $listeners = [];

    protected string $nonce;

    /**
     * @param string $template name of the template to use on unauthorized requests
     */
    public function __construct(private array $config)
    {
        //@todo make nonce length configurable
        $this->nonce  = Rand::getString(12);
        $this->config = $config;
        //@todo propagate nonce through a service injected into corresponding view helpers
        $GLOBALS['inline-nonce'] = $this->nonce;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        if (
            isset($this->config['sion_model']['csp_config'])
            && isset($this->config['sion_model']['csp_config']['inject_headers_event'])
            && isset($this->config['sion_model']['csp_config']['csp_string'])
        ) {
            $eventName         = $this->config['sion_model']['csp_config']['inject_headers_event'];
            $this->listeners[] = $events->attach($eventName, [$this, 'injectHeader'], 5000);
        }
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
     * Callback used
     *
     * @return void
     */
    public function injectHeader(MvcEvent $event)
    {
        header_remove('Content-Security-Policy');
        $headerString = $this->config['sion_model']['csp_config']['csp_string'];
        $headerString = str_replace('{:nonce}', $this->nonce, $headerString);
        header('Content-Security-Policy: ' . $headerString);
    }

    public function setNonce($nonce): static
    {
        $this->nonce             = $nonce;
        $GLOBALS['inline-nonce'] = $nonce;
        return $this;
    }

    public function getNonce()
    {
        return $this->nonce;
    }
}
