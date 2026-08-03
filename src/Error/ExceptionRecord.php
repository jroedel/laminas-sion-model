<?php

namespace SionModel\Error;

use Throwable;

/**
 * One occurrence of a failure, flattened into plain data.
 *
 * Built once at capture time and then used for three things: the write-up
 * files in the store, the JSON metadata, and the body of the notification
 * email. Holding a Throwable would defeat all three, so the chain is flattened
 * to strings here and the original is not retained.
 *
 * Dependency-free (Throwable is core PHP) so it is unit-testable without a
 * booted application.
 */
class ExceptionRecord
{
    /** @var string */
    private $fingerprint;

    /**
     * Flattened exception chain, outermost first. Each entry has keys
     * class, message, file, line, trace.
     *
     * @var array[]
     */
    private $chain;

    /** @var array */
    private $attributes;

    /**
     * @param string  $fingerprint
     * @param array[] $chain
     * @param array   $attributes
     */
    public function __construct($fingerprint, array $chain, array $attributes = [])
    {
        $this->fingerprint = (string) $fingerprint;
        $this->chain       = $chain;
        $this->attributes  = $attributes + [
            'origin'      => Fingerprinter::UNKNOWN_ORIGIN,
            'route'       => Fingerprinter::NO_ROUTE,
            'controller'  => null,
            'action'      => null,
            'occurred_at' => null,
            'revision'    => null,
            'php'         => PHP_VERSION,
            'context'     => [],
        ];
    }

    /**
     * Flatten a Throwable into a record.
     *
     * Paths inside every message and trace are rewritten relative to the
     * application root: absolute server paths are noise in a write-up and
     * disclose the hosting layout in an email.
     *
     * @param Throwable     $e
     * @param Fingerprinter $fingerprinter
     * @param array         $attributes route, controller, action, occurred_at,
     *                                  revision, context
     * @return self
     */
    public static function fromThrowable(Throwable $e, Fingerprinter $fingerprinter, array $attributes = [])
    {
        $route = isset($attributes['route']) ? $attributes['route'] : null;
        $chain = [];
        $current = $e;
        for ($depth = 0; null !== $current && $depth < 20; $depth++) {
            $chain[] = [
                'class'   => get_class($current),
                //messages and traces get the app root stripped but must NOT have
                //their backslashes touched: those are namespace separators
                'message' => $fingerprinter->stripAppRoot($current->getMessage()),
                'file'    => $fingerprinter->relativePath($current->getFile()),
                'line'    => $current->getLine(),
                'trace'   => $fingerprinter->stripAppRoot($current->getTraceAsString()),
            ];
            $current = $current->getPrevious();
        }

        //an explicit origin wins: the fatal-error path knows where PHP died,
        //which a synthesised ErrorException's trace cannot tell us
        if (! isset($attributes['origin']) || '' === $attributes['origin']) {
            $attributes['origin'] = $fingerprinter->origin($e);
        }
        if (! isset($attributes['occurred_at'])) {
            $attributes['occurred_at'] = date('c');
        }

        return new self(
            $fingerprinter->fingerprint($e, $route, $attributes['origin']),
            $chain,
            $attributes
        );
    }

    /** @return string */
    public function getFingerprint()
    {
        return $this->fingerprint;
    }

    /**
     * The outermost exception class — what a reader sees first.
     *
     * @return string
     */
    public function getClass()
    {
        return isset($this->chain[0]['class']) ? $this->chain[0]['class'] : 'UnknownException';
    }

    /** @return string */
    public function getMessage()
    {
        return isset($this->chain[0]['message']) ? $this->chain[0]['message'] : '';
    }

    /**
     * Every class in the chain, outermost first. The notification gate matches
     * its ignore list against all of them, not just the outermost, because the
     * interesting class is usually the one that got wrapped.
     *
     * @return string[]
     */
    public function getClassChain()
    {
        $classes = [];
        foreach ($this->chain as $link) {
            $classes[] = $link['class'];
        }
        return $classes;
    }

    /** @return array[] */
    public function getChain()
    {
        return $this->chain;
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /** @return string */
    public function getRoute()
    {
        $route = $this->getAttribute('route');
        return null === $route || '' === $route ? Fingerprinter::NO_ROUTE : $route;
    }

    /** @return string */
    public function getOccurredAt()
    {
        $at = $this->getAttribute('occurred_at');
        return null === $at ? date('c') : $at;
    }

    /**
     * The JSON metadata for this fingerprint. Counters and notification
     * bookkeeping are the store's business and are merged in there.
     *
     * @return array
     */
    public function toMeta()
    {
        $root = end($this->chain);
        return [
            'fingerprint' => $this->fingerprint,
            'class'       => $this->getClass(),
            'chain'       => $this->getClassChain(),
            'message'     => $this->getMessage(),
            'route'       => $this->getRoute(),
            'controller'  => $this->getAttribute('controller'),
            'action'      => $this->getAttribute('action'),
            'origin'      => $this->getAttribute('origin'),
            'thrown_at'   => false === $root ? null : $root['file'] . ':' . $root['line'],
            'revision'    => $this->getAttribute('revision'),
            'php'         => $this->getAttribute('php'),
        ];
    }

    /**
     * The full human-readable write-up. This is both what lands in the store's
     * .txt files and what the notification email carries as its body — there
     * is deliberately only one format to read and maintain.
     *
     * @return string
     */
    public function toWriteUp()
    {
        $out = [];
        $out[] = 'Fingerprint: ' . $this->fingerprint;
        $out[] = 'Occurred:    ' . $this->getOccurredAt();
        $out[] = 'Revision:    ' . $this->describe($this->getAttribute('revision'));
        $out[] = 'PHP:         ' . $this->describe($this->getAttribute('php'));
        $out[] = 'Origin:      ' . $this->getAttribute('origin');
        $out[] = '';
        $out[] = 'Request';
        $out[] = $this->label('route') . $this->getRoute();
        $controller = $this->getAttribute('controller');
        $action     = $this->getAttribute('action');
        if (null !== $controller) {
            $out[] = $this->label('controller')
                . $controller . (null === $action ? '' : '::' . $action . 'Action');
        }
        foreach ($this->contextLines() as $line) {
            $out[] = $line;
        }
        $out[] = '';
        $out[] = 'Exception chain';
        foreach ($this->chain as $i => $link) {
            $out[] = '  [' . ($i + 1) . '] ' . $link['class'];
            $out[] = '      ' . $this->indentContinuation($link['message']);
            $out[] = '      at ' . $link['file'] . ':' . $link['line'];
        }
        $out[] = '';
        $out[] = 'Traces';
        foreach ($this->chain as $i => $link) {
            $out[] = '  [' . ($i + 1) . '] ' . $link['class'];
            $out[] = $link['trace'];
            $out[] = '';
        }
        return implode("\n", $out) . "\n";
    }

    /**
     * Render the captured request state, which is already redacted by the time
     * it reaches here.
     *
     * @return string[]
     */
    private function contextLines()
    {
        $context = $this->getAttribute('context', []);
        if (! is_array($context) || [] === $context) {
            return [];
        }
        $lines = [];
        foreach ($context as $key => $value) {
            $label = $this->label($key);
            if (is_array($value)) {
                if ([] === $value) {
                    $lines[] = $label . '(none)';
                    continue;
                }
                $lines[] = rtrim($label);
                foreach ($value as $subKey => $subValue) {
                    $lines[] = '    ' . $subKey . ' = ' . $this->describe($subValue);
                }
                continue;
            }
            $lines[] = $label . $this->describe($value);
        }
        return $lines;
    }

    /**
     * An indented, column-aligned label, so the hand-written lines and the
     * captured context line up as one block.
     *
     * @param string $key
     * @return string
     */
    private function label($key)
    {
        return '  ' . str_pad(ucfirst(str_replace('_', ' ', (string) $key)) . ':', 12) . ' ';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function describe($value)
    {
        if (null === $value || '' === $value) {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'describe'], $value));
        }
        return (string) $value;
    }

    /**
     * Keep multi-line exception messages inside the indented block rather than
     * breaking the layout.
     *
     * @param string $message
     * @return string
     */
    private function indentContinuation($message)
    {
        return str_replace("\n", "\n      ", (string) $message);
    }
}
