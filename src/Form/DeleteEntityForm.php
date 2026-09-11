<?php

namespace SionModel\Form;

use SionModel\Form\InputFilterProviderInterface;

class DeleteEntityForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('entity_delete');

        $this->add([
            'name' => 'security',
            'type' => 'csrf',
            'options' => [
                'csrf_options' => [
                     'timeout' => 900,
                ],
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Delete',
                'id' => 'submit',
                'class' => 'btn-danger'
            ],
        ]);
        //`Button`, not the commented-out `Submit` this carried until 2026-08-14, and not a
        //bare element either — which is what it was, and what made this button delete.
        //
        //A `name` with no `type` becomes a plain Laminas\Form\Element, and
        //View\Helper\FormButton defaults an element with no `type` attribute to
        //`type="submit"`. So Cancel rendered as a submit button inside the delete form, and
        //SionController::deleteAction() validated the CSRF token without ever looking at
        //which button was pressed: clicking Cancel deleted the record. Measured against a
        //fixture on 2026-08-14 — POST with `cancel=Cancel` and no `submit` answered
        //302 → the entity index with the row gone.
        //
        //SionModel\Form\Element\Button declares `type => button` in its own $attributes, so
        //naming the type here is the whole fix on the browser's side: the button no longer
        //submits anything, and inside the two delete modals (person-edit, assignment-edit)
        //`data-dismiss` closes the modal on its own, which is what it was always meant to
        //do. deleteAction() carries the server-side half for a hand-crafted POST.
        //
        //JUser\Form\DeleteUserForm already did it this way, which is the precedent rather
        //than an invention. JTranslate\Form\DeletePhraseForm did not, and is fixed with it.
        $this->add([
            'name' => 'cancel',
            'type' => 'Button',
            'attributes' => [
                'value' => 'Cancel',
                //`id="submit"` a second time, duplicating the button above, is left alone
                //deliberately: it is invalid HTML and worth fixing, but it is not what
                //deleted anything, and changing a rendered id belongs in a change that can
                //check the built js bundles for it rather than riding along with a data-loss
                //fix. Nothing in public/js references `#submit` today.
                'id' => 'submit',
                'data-dismiss' => 'modal'
            ],
        ]);
    }

    /**
     * Only the CSRF token: `submit` is a button rather than data, and this form carries
     * no fields of its own.
     *
     * `security` is stated here even though `SionModel\Form\Element\Csrf` supplies the
     * validator itself. That was the reasoning this docblock used to give for returning
     * an empty array, and it stops being safe at step 5:
     * `SionModel\Form\Validation\InputFilter` reads the specification and nothing else,
     * so a check that exists only on the element is a check the cutover removes. See
     * SionModel\Form\CsrfSpec.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
        ];
    }
}
