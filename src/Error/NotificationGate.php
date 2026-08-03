<?php

namespace SionModel\Error;

/**
 * Decides whether an occurrence deserves an email.
 *
 * The whole value of this feature is that the mail stays worth reading, so the
 * gate is deliberately strict: one email the first time a fingerprint is seen,
 * and then nothing until the volume crosses a threshold that says "this stopped
 * being a curiosity and became an outage".
 *
 * The ignore list exists because dispatch.error is not a bug channel. An
 * unauthenticated visitor touching an admin route raises
 * BjyAuthorize\Exception\UnAuthorizedException through exactly the same event —
 * that is ordinary traffic, and notifying on it would bury the real signal on
 * day one. Such exceptions are still logged and still recorded in the store;
 * they simply do not mail.
 *
 * Dependency-free so it is unit-testable without a booted application.
 */
class NotificationGate
{
    /** @var bool */
    private $enabled;

    /** @var string[] */
    private $ignoreClasses;

    /** @var int[] */
    private $spikeCounts;

    /**
     * @param bool     $enabled
     * @param string[] $ignoreClasses exact class names, or a namespace prefix
     *                                ending in `*`
     * @param int[]    $spikeCounts   occurrence counts that re-notify
     */
    public function __construct($enabled = true, array $ignoreClasses = [], array $spikeCounts = [])
    {
        $this->enabled       = (bool) $enabled;
        $this->ignoreClasses = array_values(array_filter(array_map('strval', $ignoreClasses)));
        $this->spikeCounts   = $this->normaliseSpikeCounts($spikeCounts);
    }

    /**
     * @param string[]      $classChain every class in the exception chain
     * @param RecordOutcome $outcome
     * @return array|null null to stay silent, otherwise keys reason, label, mark
     */
    public function decide(array $classChain, RecordOutcome $outcome)
    {
        if (! $this->enabled) {
            return null;
        }
        if ($this->isIgnored($classChain)) {
            return null;
        }

        if (! $outcome->hasBeenNotified()) {
            return [
                'reason' => 'first',
                'label'  => 'NEW',
                'mark'   => [$outcome->getCount()],
            ];
        }

        $crossed = $this->crossedThresholds($outcome);
        if ([] === $crossed) {
            return null;
        }

        //mark every threshold crossed, not just the one being reported: marking
        //only the highest would leave a lower one perpetually unmarked, and it
        //would then re-fire on every subsequent occurrence
        return [
            'reason' => 'spike',
            'label'  => 'SPIKE ' . max($crossed),
            'mark'   => $crossed,
        ];
    }

    /**
     * Thresholds this occurrence count has reached that have not yet been
     * reported.
     *
     * @param RecordOutcome $outcome
     * @return int[]
     */
    public function crossedThresholds(RecordOutcome $outcome)
    {
        $count    = $outcome->getCount();
        $notified = $outcome->getNotifiedCounts();
        $crossed  = [];
        foreach ($this->spikeCounts as $threshold) {
            if ($count >= $threshold && ! in_array($threshold, $notified, true)) {
                $crossed[] = $threshold;
            }
        }
        return $crossed;
    }

    /**
     * @param string[] $classChain
     * @return bool
     */
    public function isIgnored(array $classChain)
    {
        foreach ($classChain as $class) {
            foreach ($this->ignoreClasses as $pattern) {
                if ($this->matches($pattern, (string) $class)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Exact class name, or a namespace prefix ending in `*`.
     *
     * Matching is done on strings rather than with is_a() on purpose: this
     * class must not autoload anything while the application is mid-failure.
     *
     * @param string $pattern
     * @param string $class
     * @return bool
     */
    private function matches($pattern, $class)
    {
        $pattern = ltrim($pattern, '\\');
        $class   = ltrim($class, '\\');
        if ('*' === substr($pattern, -1)) {
            $prefix = substr($pattern, 0, -1);
            return '' === $prefix || 0 === strpos($class, $prefix);
        }
        return $pattern === $class;
    }

    /**
     * @param int[] $counts
     * @return int[] ascending, positive, unique
     */
    private function normaliseSpikeCounts(array $counts)
    {
        $clean = [];
        foreach ($counts as $count) {
            $count = (int) $count;
            if ($count > 1) {
                $clean[] = $count;
            }
        }
        $clean = array_values(array_unique($clean));
        sort($clean);
        return $clean;
    }
}
