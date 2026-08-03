<?php

namespace SionModel\Error;

/**
 * What the store did with an occurrence: whether this fingerprint had never
 * been seen before, how many times it has now been seen, and which occurrence
 * counts have already triggered a notification.
 *
 * The notification gate decides purely from these three facts, which is why
 * they are carried in a value object rather than re-read from disk.
 */
class RecordOutcome
{
    /** @var string */
    private $fingerprint;

    /** @var bool */
    private $isNew;

    /** @var int */
    private $count;

    /** @var int[] */
    private $notifiedCounts;

    /**
     * @param string $fingerprint
     * @param bool   $isNew
     * @param int    $count
     * @param int[]  $notifiedCounts
     */
    public function __construct($fingerprint, $isNew, $count, array $notifiedCounts = [])
    {
        $this->fingerprint    = (string) $fingerprint;
        $this->isNew          = (bool) $isNew;
        $this->count          = (int) $count;
        $this->notifiedCounts = array_values(array_map('intval', $notifiedCounts));
    }

    /** @return string */
    public function getFingerprint()
    {
        return $this->fingerprint;
    }

    /** @return bool */
    public function isNew()
    {
        return $this->isNew;
    }

    /** @return int */
    public function getCount()
    {
        return $this->count;
    }

    /** @return int[] */
    public function getNotifiedCounts()
    {
        return $this->notifiedCounts;
    }

    /**
     * Whether any notification has ever been sent for this fingerprint.
     *
     * This — not isNew() — is what suppresses duplicate mail. The store marks
     * a fingerprint notified *before* attempting the send, so a mail server
     * that hangs cannot make every subsequent request retry it.
     *
     * @return bool
     */
    public function hasBeenNotified()
    {
        return [] !== $this->notifiedCounts;
    }
}
