<?php

declare(strict_types=1);

namespace SionModel\Form;

use SionModel\Form\Element\Registry;
use SionModel\Form\Exception\ElementNotFound;

use function implode;
use function in_array;
use function is_iterable;
use function is_string;
use function sprintf;

/**
 * Builds an element, a fieldset or a form from the array every form writes.
 *
 *     $this->add(['name' => 'title', 'type' => 'Text', 'options' => [...], 'attributes' => [...]]);
 *
 * ## What it replaces, and what it leaves behind
 *
 * `Laminas\Form\Factory` is 600 lines over a `FormElementManager`, and most of that serves
 * keys no form here writes: `hydrator`, `object`, `input_filter`, `validation_group`,
 * `elements` and `fieldsets` as nested specification arrays. Measured across all 402
 * `add()` calls in the application — the keys in use are `name`, `type`, `options` and
 * `attributes`, and nothing else. A specification carrying anything else is refused by
 * name rather than ignored, because a silently dropped `hydrator` key would be a form that
 * looks configured and is not.
 *
 * The plugin manager goes with it. Resolving `'Select'` is a lookup in
 * {@see Registry}, which is where the list already lived, and a `'type'` naming a class
 * directly is built directly. There is nothing to configure and nothing to inject, which
 * is what lets the associations API validate a payload with no module loading, no merged
 * config and no container at all.
 *
 * `init()` is called on everything this builds, because `FormElementManager` called it and
 * `SionModel\Form\Element\Element::init()` exists for the subclass that needs it.
 */
final class Factory
{
    /** The specification keys this factory understands. */
    private const KNOWN_KEYS = ['name', 'type', 'options', 'attributes'];

    /**
     * A factory for a form nobody built through the container.
     *
     * Not memoised: it holds nothing per form today, and handing one shared instance
     * around is how a future per-form setting would silently reach every other form.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $spec
     */
    public function create(array $spec): ElementInterface
    {
        foreach ($spec as $key => $_) {
            if (! is_string($key) || ! in_array($key, self::KNOWN_KEYS, true)) {
                throw new ElementNotFound(sprintf(
                    'A form specification here may carry %s and nothing else; received "%s". '
                    . 'laminas accepted a dozen more keys, all of them for machinery this '
                    . 'application does not use — and an ignored key is a form that looks '
                    . 'configured and is not.',
                    implode(', ', self::KNOWN_KEYS),
                    (string) $key
                ));
            }
        }

        $element = $this->instantiate($spec['type'] ?? Element\Element::class);

        if (isset($spec['name']) && is_string($spec['name'])) {
            $element->setName($spec['name']);
        }

        //Options before attributes, which is laminas' order and not arbitrary: `setOptions()`
        //promotes `label` and, on a `Select`, builds the value options that a later
        //`setAttribute('multiple', …)` is read against.
        if (isset($spec['options']) && is_iterable($spec['options'])) {
            $element->setOptions($spec['options']);
        }

        if (isset($spec['attributes']) && is_iterable($spec['attributes'])) {
            $element->setAttributes($spec['attributes']);
        }

        $element->init();

        return $element;
    }

    /**
     * The class behind a `type`, which may be a short name, a class of ours, or a class of
     * laminas' that a form copied from an old example.
     */
    private function instantiate(mixed $type): ElementInterface
    {
        if ($type instanceof ElementInterface) {
            //A specification may carry a built element as its type; `ImportMappingForm`
            //passes fieldsets around that way.
            return $type;
        }

        if (! is_string($type) || '' === $type) {
            throw new ElementNotFound('A form specification needs a `type` naming an element.');
        }

        $class = Registry::classFor($type);

        if (null === $class) {
            throw new ElementNotFound(sprintf(
                'No element type named "%s". Add it to %s, which is the one list the form '
                . 'factory, the container and the associations API all read.',
                $type,
                Registry::class
            ));
        }

        $element = new $class();

        if (! $element instanceof ElementInterface) {
            throw new ElementNotFound(sprintf(
                '"%s" builds %s, which is not a form element.',
                $type,
                $class
            ));
        }

        return $element;
    }
}
