<?php

declare(strict_types=1);

namespace SionModel\Form;

/**
 * An element that has something to do before the form is rendered.
 *
 * `Laminas\Form\ElementPrepareAwareInterface` under a shorter name. Three elements
 * implement it and each does something a renderer cannot do for itself: `Csrf` materialises
 * its token, `File` sets the form's enctype, and `DateSelect` names its three sub-elements.
 * `Fieldset` implements it too, which is what renames a fieldset's children to
 * `fieldset[element]`, and `Collection` uses it to materialise its rows.
 */
interface PrepareAwareInterface
{
    public function prepareElement(FormInterface $form): void;
}
