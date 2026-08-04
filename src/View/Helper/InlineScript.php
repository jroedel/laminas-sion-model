<?php

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\HeadScript;
use Laminas\View\Helper\InlineScript as LaminasInlineScript;
use Laminas\View\Helper\Placeholder\Container\AbstractContainer;
use Laminas\View\Renderer\RendererInterface;

/**
 * The inlineScript view helper, with a Content-Security-Policy nonce attached
 * to every captured inline script.
 *
 * This used to extend Laminas\View\Helper\InlineScript. laminas marked both
 * that class and its parent HeadScript `@final`, closing the whole chain, so
 * the helper now wraps a stock instance and forwards to it.
 *
 * Templates use a narrow surface — captureStart()/captureEnd() around inline
 * blocks, appendFile() for external scripts, and `echo $this->inlineScript()`
 * in layout.phtml — all of which is forwarded explicitly below. Anything else
 * reaches the wrapped helper through __call().
 *
 * Behaviour is pinned by test/Integration/InlineScriptNonceContractTest.php in
 * the application repo.
 */
class InlineScript extends AbstractHelper
{
    private LaminasInlineScript $inlineScript;

    /** @var string|null */
    protected $nonce;

    /**
     * @param string|null $nonce
     */
    public function __construct($nonce = null)
    {
        $this->inlineScript = new LaminasInlineScript();

        // The class this replaced overrode __construct() without calling
        // parent::__construct(), so HeadScript's setSeparator(PHP_EOL) never
        // ran and the container kept its default ''. Reproduce that rather
        // than silently introducing newlines between every <script> tag.
        $this->inlineScript->getContainer()->setSeparator('');

        $this->setNonce($nonce);
    }

    /**
     * @param  string $mode
     * @param  string|null $spec
     * @param  string $placement
     * @param  array<string, mixed> $attrs
     * @param  string $type
     * @return $this
     */
    public function __invoke(
        $mode = HeadScript::FILE,
        $spec = null,
        $placement = 'APPEND',
        array $attrs = [],
        $type = HeadScript::DEFAULT_SCRIPT_TYPE
    ) {
        $this->inlineScript->__invoke($mode, $spec, $placement, $attrs, $type);

        return $this;
    }

    /**
     * Begin capturing an inline script, tagging it with the nonce.
     *
     * An explicit nonce passed by the caller wins.
     *
     * @param  string $captureType
     * @param  string $type
     * @param  array<string, mixed> $attrs
     * @return void
     */
    public function captureStart(
        $captureType = AbstractContainer::APPEND,
        $type = HeadScript::DEFAULT_SCRIPT_TYPE,
        $attrs = []
    ) {
        if (isset($this->nonce) && ! isset($attrs['nonce'])) {
            $attrs['nonce'] = $this->nonce;
        }

        $this->inlineScript->captureStart($captureType, $type, $attrs);
    }

    /**
     * @return void
     */
    public function captureEnd()
    {
        //@todo calculate sums
        $this->inlineScript->captureEnd();
    }

    /**
     * @param  string|null $nonce
     * @return $this
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
        if (isset($nonce)) {
            $this->inlineScript->setAllowArbitraryAttributes(true);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getNonce()
    {
        return $this->nonce;
    }

    /**
     * The wrapped helper renders the markup, so it needs the view too.
     *
     * @return $this
     */
    public function setView(RendererInterface $view)
    {
        parent::setView($view);
        $this->inlineScript->setView($view);

        return $this;
    }

    /**
     * @param  string|int|null $indent
     * @return string
     */
    public function toString($indent = null)
    {
        return $this->inlineScript->toString($indent);
    }

    public function __toString(): string
    {
        return $this->inlineScript->toString();
    }

    /**
     * Forward the rest of HeadScript's API — appendFile(), prependScript(),
     * setAllowArbitraryAttributes() and friends — to the wrapped helper.
     *
     * When the wrapped helper returns itself for chaining, hand back this
     * wrapper instead, so a chained call still lands on the nonce-aware
     * captureStart() above.
     *
     * @param  string $method
     * @param  array<int, mixed> $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $result = $this->inlineScript->$method(...$args);

        return $result === $this->inlineScript ? $this : $result;
    }
}
