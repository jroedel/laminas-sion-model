<?php

declare(strict_types=1);

namespace SionModel\Form;

use SionModel\View\Escape;
use SionModel\Form\Element\Checkbox;
use SionModel\Form\Element\Csrf;
use SionModel\Form\Element\Select;
use SionModel\Form\Element\Submit;
use SionModel\Form\Element\Textarea;
use Laminas\Form\ElementInterface;
use Laminas\Form\FormInterface;

use function array_filter;
use function array_flip;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strip_tags;
use function trim;

/**
 * The form layer a Symfony-served route did not have.
 *
 * A host migrating off laminas-mvc hits this wall first — schoenstatt.link's
 * `docs/strangler.md` calls it the single largest thing between the migration and its
 * end: every create/edit/delete page renders a `Laminas\Form` through
 * laminas' form view helpers, and a Symfony-served route cannot call a view helper —
 * the helper plugin manager is built from the MVC event. So the markup has to be
 * produced by something else, and this is that something.
 *
 * ## It reproduces TwbBundle, deliberately and exactly
 *
 * The laminas side renders through `SionModel\Form\View\Helper\SionFormRow` — a sibling
 * in this package since this class moved here on 2026-08-21 — which extends
 * `TwbBundle\Form\View\Helper\TwbBundleFormRow`, Bootstrap 3 markup. This
 * class emits the same bytes rather than "equivalent" markup, for two reasons that
 * are really one: the host's CSS and its selectize/markdown JS bundle are written
 * against that exact structure, and **both renderings are live at the same time** —
 * a ported route renders through this class while an unported one still renders
 * through the helper. A page that differs structurally is a page that looks different
 * depending on which front controller answered it.
 *
 * The details that are not obvious and are all load-bearing:
 *
 * - **Attribute order is `type`, `name`, the element's declared attributes in
 *   declaration order, `class`, `value`.** That falls out of how
 *   `Laminas\Form\View\Helper\FormInput` builds its array, and it is the order the
 *   captured baseline has.
 * - **A false boolean attribute is omitted, a true one is rendered bare** —
 *   `required`, not `required="required"`.
 * - **`<div class="form-group ">` carries a trailing space.** TwbBundle concatenates
 *   an (empty) state class onto the group class. It is in the baseline, so it is
 *   here.
 * - **`formHidden()` emits no `class`, but a hidden rendered through a row does.**
 *   On the association form, `associationId` goes through the first and the CSRF
 *   token through the second, so its two hidden inputs genuinely differ.
 * - **Escaping is {@see \SionModel\View\Escape}**, not Twig's, so `&#x20;` and friends appear in
 *   attribute values exactly where the baseline has them.
 *
 * ## What it does not do
 *
 * Only the element types a ported form has needed are implemented, and an unknown
 * one throws rather than falling back to a plain text input — a silent fallback would
 * render a `Select` as an empty box and lose the value on save. `Collection`,
 * `File`, `DateSelect` and the rest are absent because no ported form has one yet;
 * add them when a form that needs them moves.
 *
 * Translation is a callable rather than a translator instance, so this class needs
 * nothing from laminas-i18n — which is also what lets it live in a shared package: the
 * host passes any `callable(string): string`. On schoenstatt.link that is
 * `App\Twig\LaminasExtension::translate()`, page-aware and text-domain-aware; a host
 * without a translator passes `static fn (string $m): string => $m`, which is what the
 * parity tests do.
 */
final class BootstrapFormRenderer
{
    /** @param callable(string): string $translate */
    public function __construct(private readonly mixed $translate)
    {
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     * @param string $class the form's own class attribute. `form-horizontal` is what
     *        TwbBundle\Form\View\Helper\TwbBundleForm::openTag() emits and what every
     *        form on the site got until the comment form needed otherwise: its
     *        .phtml renders the open tag and then does
     *        `str_replace("form-horizontal", "form", $openTag)`, calling that in its
     *        own comment "an ugly hack to get around limitations in TwbBundleForm".
     *        The hack exists because a horizontal form puts the textarea in a
     *        10-column offset gutter inside an already-narrow panel. Reproduced as a
     *        parameter rather than as a string replacement, because the replacement
     *        would also rewrite the *name* and *id* attributes of any form whose name
     *        happened to contain `form-horizontal` — and because a caller asking for a
     *        class reads as what it is.
     */
    public function open(FormInterface $form, string $action, string $class = 'form-horizontal'): string
    {
        $name = (string) $form->getName();

        /**
         * **The method comes from the form, not from here.** It was hardcoded `POST`
         * while the only ported form was an edit form. Both contact-search forms are
         * `method="GET"` — their data is the query string — and a hardcoded POST would
         * have turned the navbar search box into a form that posts to a route with no
         * POST handling. `Laminas\Form\Form` defaults the attribute to POST, so the
         * edit forms are unaffected.
         */
        $method = $form->getAttribute('method');
        $method = is_scalar($method) && '' !== (string) $method ? (string) $method : 'POST';

        /**
         * **An empty action emits no attribute at all**, which is what laminas does with
         * a form nobody called `setAttribute('action', …)` on: `openTag()` renders the
         * attributes the form actually has. The advanced search is that case — it posts
         * to its own URL — and `action=""` is not the same thing to a browser resolving
         * a relative reference.
         */
        $actionAttribute = '' === $action
            ? ''
            : sprintf(' action="%s"', Escape::htmlAttr($action));

        /**
         * **`enctype` is emitted when the form declares one**, and no form did until the
         * import upload. Without it a browser posts `application/x-www-form-urlencoded`,
         * PHP populates no `$_FILES`, and the file simply is not there — a failure with
         * no error anywhere, on the one form whose entire purpose is the file.
         */
        $enctype          = $form->getAttribute('enctype');
        $enctypeAttribute = is_scalar($enctype) && '' !== (string) $enctype
            ? sprintf(' enctype="%s"', Escape::htmlAttr((string) $enctype))
            : '';

        return sprintf(
            '<form method="%s" name="%s"%s%s class="%s" id="%s">',
            Escape::htmlAttr($method),
            Escape::htmlAttr($name),
            $actionAttribute,
            $enctypeAttribute,
            Escape::htmlAttr($class),
            Escape::htmlAttr($name)
        );
    }

    public function close(): string
    {
        return '</form>';
    }

    /**
     * A whole labelled row: the shape `formRow()` produces for everything the
     * association form renders through it.
     */
    public function row(ElementInterface $element, bool $translateOptions = true): string
    {
        //TwbBundle wraps nothing around a hidden input — there is no label, no help and
        //nothing to lay out. The CSRF token is the one element on this form that
        //reaches row() and is hidden, and a stray form-group around it adds vertical
        //space above the submit button.
        if ($element instanceof Csrf || 'hidden' === $element->getAttribute('type')) {
            return $this->element($element);
        }

        /**
         * **A checkbox row is `<div class="checkbox">` and carries no form-group at all.**
         *
         * `TwbBundleFormRow::render()` has a `switch (true)` whose first case is
         * `$type === 'checkbox' && $layout !== LAYOUT_HORIZONTAL && ! $element->getOption('form-group')`,
         * and that case `return`s the element content *without* calling
         * `renderElementFormGroup()`. The content itself has already been wrapped by
         * `$checkboxFormat`, which is the literal `<div class="checkbox">%s</div>`.
         *
         * The layout test looks like it should exclude these forms — every one of them
         * renders `class="form-horizontal"` — and it does not: `$layout` comes from the
         * form's **`layout` option**, not from its CSS class, and none of these forms sets
         * it. So `$layout` is null, `null !== LAYOUT_HORIZONTAL` holds, and every checkbox
         * takes the first case.
         *
         * This method emitted `<div class="form-group ">` here until the batch-7 baseline,
         * and it was wrong on every checkbox of all eight ported forms — `isDraft`,
         * `isActive`, `requireCallNumbers`, the four on the role form, and so on. Bootstrap
         * 3 styles `.checkbox` and `.form-group` differently, so it was a visible
         * misalignment rather than a byte-level nicety. Nothing caught it earlier because
         * `association-edit`, the only form ported before this batch, renders its checkboxes
         * through `form_element` inside hand-built groups and never through a row.
         *
         * Help block and errors go *outside* the div, which is the order the `case null`
         * branch appends them in.
         */
        if ($element instanceof Checkbox) {
            return '<div class="checkbox">' . $this->element($element) . '</div>'
                . $this->rowHelpBlock($element) . $this->errors($element);
        }

        $group = sprintf('<div class="form-group %s">', self::rowClass($element));

        //**`for` only when the element has an id.** TwbBundleFormRow::render() picks
        //between the element and a bare attribute array on exactly that test —
        //`$labelHelper->openTag($element->getAttribute('id') ? $element : $attributes)`
        //— so a field with no id gets `<label>` and one with an id gets
        //`<label for="…">`. Every element on the association form was in the first
        //group, which is why this looked like "a row label never carries for".
        return $group
            . $this->label($element, withFor: null !== $element->getAttribute('id'))
            . $this->element($element, $translateOptions)
            . $this->errors($element)
            . $this->rowHelpBlock($element)
            . '</div>';
    }

    /**
     * `TwbBundleFormRow::getRowClassFromElement()`, restricted to the one branch any
     * form here uses: `column-size`, a string or a list, each entry prefixed `col-`.
     *
     * **The leading space is the original's and is kept.** The row class is
     * concatenated as `' col-' . $item` onto an empty string and then interpolated into
     * `'<div class="form-group %s">'`, so a sized row really does carry two spaces:
     * `<div class="form-group  col-md-4">`. Verified against the raw capture rather than
     * a whitespace-collapsed reading of it — the first reading of this markup collapsed
     * the runs and made it look like one space.
     *
     * The other four branches (`twb-form-group-size`, `validation-state`, the
     * `has-error` from messages, `feedback`, `twb-row-class`) are absent because no form
     * on this side sets any of them. `has-error` is the one to add first when a ported
     * form starts re-rendering itself after a failed validation.
     */
    private static function rowClass(ElementInterface $element): string
    {
        $size = $element->getOption('column-size');
        if (null === $size || '' === $size) {
            return '';
        }

        $class = '';
        foreach (is_array($size) ? $size : [$size] as $item) {
            if (is_scalar($item)) {
                $class .= ' col-' . (string) $item;
            }
        }

        return $class;
    }

    /**
     * TwbBundle's help block, which is **not** SionModel's — the two differ and both
     * appear on this page.
     *
     * `TwbBundleFormRow::renderHelpBlock()` translates first, then escapes *only if
     * the text contains no tags at all* (`strip_tags($t) === $t`). So `phone1`'s help
     * arrives with `&#039;` for its apostrophe while
     * `openingHoursSpecificationJson`'s keeps a live `<a href>`. Reproducing the rule
     * rather than picking one behaviour is what makes the two renderings match; it is
     * also, for the record, an XSS footgun in the original — a help block is authored
     * in PHP, so nothing user-supplied reaches it today.
     */
    private function rowHelpBlock(ElementInterface $element): string
    {
        $text = $element->getOption('help-block');
        if (! is_scalar($text) || '' === (string) $text) {
            return '';
        }

        $translated = ($this->translate)((string) $text);
        if (strip_tags($translated) === $translated) {
            $translated = Escape::html($translated);
        }

        return sprintf('<p class="help-block">%s</p>', $translated);
    }

    /**
     * `formLabel()`'s output, which carries a `for` — unlike the label `formRow()`
     * writes. The association form's hand-built first two groups use this one and the
     * rest use the row, so both forms of label appear on the page.
     */
    public function label(ElementInterface $element, bool $withFor = true): string
    {
        $label = $element->getLabel();
        if (null === $label || '' === $label) {
            return '';
        }

        $text = Escape::html(($this->translate)($label));

        if (! $withFor) {
            return '<label>' . $text . '</label>';
        }

        //`FormLabel::openTag()` reads the **id** and falls back to the name — which is
        //why this used to be the name alone and looked right: no element on the
        //association form declares an id. `roleTitleSelect` is the first that does.
        $id  = $element->getAttribute('id');
        $for = is_scalar($id) && '' !== (string) $id ? (string) $id : (string) $element->getName();

        return sprintf(
            '<label for="%s">%s</label>',
            Escape::htmlAttr($for),
            $text
        );
    }

    /** The control itself, with no label, errors or help block around it. */
    /**
     * @param bool $withClass whether the Bootstrap `form-control` class is added. True
     *        everywhere it is rendered through a row, which is where TwbBundle adds it;
     *        the literature search bar renders its language select **directly**, as the
     *        .phtml does with `$this->formSelect(...)`, and the plain laminas helper adds
     *        no class at all.
     */
    public function element(
        ElementInterface $element,
        bool $translateOptions = true,
        bool $withClass = true
    ): string {
        if ($element instanceof Checkbox) {
            return $this->checkbox($element);
        }
        if ($element instanceof Select) {
            return $this->select($element, $translateOptions, $withClass);
        }
        if ($element instanceof Textarea) {
            return $this->textarea($element);
        }
        if ($element instanceof Submit) {
            return $this->submit($element);
        }
        if ($element instanceof Csrf) {
            return $this->input($element, 'hidden', withClass: true);
        }

        $type = $element->getAttribute('type');

        return $this->input($element, is_scalar($type) ? (string) $type : 'text', withClass: true);
    }

    /**
     * `formText()`: a bare `<input type="text">`, whatever the element's own type says.
     *
     * The distinction matters where a template calls `formText()` on a `Date` element,
     * which mass-checkout.phtml does for `checkedOutOn`: the generic path would render
     * `type="date"` with the element's `min` and `step`, and the browser would give the
     * librarian a date picker where the original gives a text box the barcode scanner can
     * be tabbed out of. Also no `class`, matching the helper.
     */
    public function text(ElementInterface $element): string
    {
        return $this->input($element, 'text', withClass: false);
    }

    /** `formHidden()`: no `class`, unlike a hidden rendered through a row. */
    public function hidden(ElementInterface $element): string
    {
        return $this->input($element, 'hidden', withClass: false);
    }

    /** `<button type="submit">`. See button(), which does the work for both types. */
    public function submit(ElementInterface $element): string
    {
        return $this->button($element, 'submit');
    }

    /**
     * `<button type="button">` — `formButton()` rather than `formSubmit()`. The advanced
     * contact search has one (`clear`), and it is the first ported form that does.
     */
    public function button(ElementInterface $element, string $type = 'button'): string
    {
        /**
         * **The label first, the value second** — `TwbBundleFormButton::render()` is
         * `$content = $element->getLabel() ?: $element->getValue()`. This method used to
         * read `value` out of `getAttributes()` and fall back to the literal 'Submit',
         * which produced the right bytes for exactly one form by coincidence:
         * `Laminas\Form\Element::setAttribute()` diverts the `value` key to `setValue()`
         * and keeps it *out* of the attribute list, so that lookup never found anything.
         * AssociationForm declares `'value' => 'Submit'` and no label, so 'Submit' was
         * both the fallback and the correct answer. AdvancedSearchForm declares a label
         * of 'Search' and no value, and would have rendered a button reading "Submit"
         * with `value="Submit"` where laminas emits "Search" with `value=""`.
         */
        $label   = $element->getLabel();
        $value   = self::asString($element->getValue());
        $content = null !== $label && '' !== $label ? $label : $value;

        $declared    = $this->declaredAttributes($element);
        $class       = isset($declared['class']) ? (string) $declared['class'] : '';
        $hasOwnClass = isset($declared['class']);

        /**
         * **`class` keeps the position the element declared it in**, and only a button
         * with no class of its own gets one appended at the end.
         *
         * This used to append unconditionally, which is right for a button whose class is
         * added by TwbBundle and wrong for one that declares its own: laminas renders the
         * element's attributes in declaration order, so `CheckoutForm`'s submit comes out
         * `id`, `class`, `tabindex` and this emitted `id`, `tabindex`, `class`. Invisible
         * until batch 11b, because it takes a button that declares *both* a class and a
         * later attribute, and the checkout form is the first ported page with one.
         */
        /**
         * **Declared first, then `type` and `name` assigned over it** — the same order
         * `FormButton::openTag()` builds: `$element->getAttributes()`, then `name`, then
         * `type`, then `value`. This built the seeds first until 2026-08-21 and matched
         * every earlier ported form by accident, because a button declared as
         * `'type' => 'Submit'` gets `['type' => 'submit']` seeded by its own class and so
         * really does start with `type`. `JUser\Form\EditUserForm` declares its submit as
         * a plain element with `'type' => 'submit'` *inside* `attributes`, which leaves the
         * attribute array starting at `name` — laminas rendered
         * `<button name="submit" class="btn-primary btn" type="submit" …>` and this
         * rendered `<button type="submit" name="submit" …>`. See {@see attributes()},
         * which had the identical defect for `<input>` and `<select>`.
         */
        /** @var array<string, scalar> $attributes */
        $attributes = [];
        foreach ($declared as $key => $declaredValue) {
            if ('class' === $key) {
                $attributes['class'] = self::buttonClass($class);
                continue;
            }
            if (is_scalar($declaredValue)) {
                $attributes[(string) $key] = $declaredValue;
            }
        }
        if (! $hasOwnClass) {
            $attributes['class'] = self::buttonClass($class);
        }
        $attributes['name'] = (string) $element->getName();
        $attributes['type'] = $type;
        //rendered even when empty: FormButton::openTag() always sets it from
        //getValue(), so the baseline carries `value=""` on both buttons of the
        //advanced search
        $attributes['value'] = $value;

        return sprintf(
            '<button %s>%s</button>',
            $this->attributeString($attributes),
            Escape::html(($this->translate)($content))
        );
    }

    /**
     * TwbBundleFormButton's class rule, transcribed from its `render()` rather than
     * guessed: append `btn` unless the class already carries it as a whole word, then
     * append `btn-default` unless it already carries some `btn-<known option>`. An empty
     * class becomes `btn btn-default` outright.
     *
     * Both halves are measured on the advanced search, which has one button of each
     * shape: `btn-primary` renders as `btn-primary btn` (bare `btn` missing, option
     * present), and `btn btn-default` renders unchanged. A plain `$class . ' btn'` — what
     * this class did before — gets the first right and turns the second into
     * `btn btn-default btn`.
     */
    private static function buttonClass(string $class): string
    {
        if ('' === $class) {
            return 'btn btn-default';
        }

        if (! preg_match('/(\s|^)btn(\s|$)/', $class)) {
            $class .= ' btn';
        }

        $known = ['default', 'primary', 'success', 'info', 'warning', 'danger', 'link'];
        $hasOption = false;
        foreach ($known as $option) {
            if (preg_match('/(\s|^)btn-' . $option . '.*(\s|$)/', $class)) {
                $hasOption = true;
                break;
            }
        }
        if (! $hasOption) {
            $class .= ' btn-default';
        }

        return trim($class);
    }

    /**
     * A value as laminas would print it: a scalar directly, and an object through its
     * `__toString()`. The second case is `geoPoint`, whose stored value is a
     * `SionModel\Db\GeoPoint` — treating it as unprintable renders the field empty
     * and a moderator who saves the form then clears the shrine's coordinates.
     */
    private static function asString(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return is_object($value) && method_exists($value, '__toString') ? (string) $value : '';
    }

    /** @return array<string, bool|float|int|string|null> */
    private function declaredAttributes(ElementInterface $element): array
    {
        return $element->getAttributes();
    }

    /** TwbBundle's `<p class="help-block">`, via SionModel's HelpBlock helper. */
    public function helpBlock(ElementInterface $element): string
    {
        $text = $element->getOption('help-block');
        if (! is_scalar($text) || '' === (string) $text) {
            return '';
        }

        //Escape then translate, in that order — SionModel\View\Helper\HelpBlock does
        //exactly this, which is why the baseline's help blocks contain `&#039;` inside
        //otherwise untranslated English. Reversing it would change the bytes.
        return sprintf('<p class="help-block">%s</p>', ($this->translate)(
            Escape::html((string) $text)
        ));
    }

    /**
     * Validation messages, as `formElementErrors()` renders them.
     *
     * Not translated here, and `formElementErrors()` no longer translates either —
     * both were switched off together. A message arrives already interpolated,
     * because laminas substitutes `%value%`, `%hostname%` and `%min%` inside the
     * validator, so translating at this point looked up the *user's input*: a
     * translator miss is what files a phrase, and six strangers' mistyped email
     * hostnames ended up as permanent rows in a table other accounts can read.
     * `AbstractValidator`'s default translator now translates the message templates
     * instead, before interpolation — see JTranslate\Module::onBootstrap and, on
     * schoenstatt.link, App\Laminas\TranslatorConfigurator. Translating in both places
     * would translate twice.
     */
    public function errors(ElementInterface $element): string
    {
        $messages = $element->getMessages();
        if ([] === $messages) {
            return '';
        }

        $items = '';
        foreach ($messages as $message) {
            if (! is_scalar($message)) {
                continue;
            }
            $items .= '<li>' . Escape::html((string) $message) . '</li>';
        }

        return '' === $items ? '' : '<ul class="help-block">' . $items . '</ul>';
    }

    // --------------------------------------------------------------- controls

    /**
     * **Attributes are filtered by input type**, the way laminas filters them.
     *
     * `Laminas\Form\View\Helper\FormText` declares its own `$validTagAttributes` and
     * `AbstractHelper::createAttributesString()` drops anything outside it, the globals,
     * and a `data-` prefix. `min` is the case that found this: `AdvancedSearchForm`
     * declares `'min' => 3` on both its text fields, laminas silently drops it — `min`
     * belongs to number, range and date inputs, not text — and this class rendered
     * `min="3"`. Invisible in a browser, and a difference in the bytes.
     *
     * Every type `input()` serves is listed. Adding an element type means adding its
     * helper's list here; falling back to text's for an unknown type would silently drop
     * `min`/`max`/`step` from a number input, which is the same class of bug from the
     * other direction, so an unlisted type keeps everything and the next port has to look.
     *
     * Batch 7 is the port that had to look: `number`, `email`, `url` and `date` all appear
     * in its ten forms and none of them was here. The host's `BootstrapFormRendererTest`
     * pins each list against the laminas helper it was transcribed from, so a wrong
     * transcription fails rather than showing up as a handful of bytes in a diff.
     */
    private function input(ElementInterface $element, string $type, bool $withClass): string
    {
        $attributes = $this->attributes(
            $element,
            ['type' => $type, 'name' => (string) $element->getName()],
            $withClass
        );

        $attributes = self::filteredByInputType($attributes, $type);

        if ('file' !== $type) {
            $attributes['value'] = self::asString($element->getValue());
        }

        return '<input ' . $this->attributeString($attributes) . '>';
    }

    /**
     * @param array<string, scalar> $attributes
     * @return array<string, scalar>
     */
    private static function filteredByInputType(array $attributes, string $type): array
    {
        /** FormText::$validTagAttributes, verbatim. */
        $text = ['name', 'autocomplete', 'autofocus', 'dirname', 'disabled', 'form', 'inputmode',
                 'list', 'maxlength', 'minlength', 'pattern', 'placeholder', 'readonly',
                 'required', 'size', 'type', 'value'];
        /** FormHidden's is FormInput's, which is far wider; `value` and `name` are the whole of it in practice. */
        $hidden = $text;

        /**
         * The four types batch 7's forms add, each transcribed from its own laminas
         * helper's `$validTagAttributes` rather than adapted from text's — which is the
         * whole point of this table. `number` differs from `text` in both directions: it
         * gains `max`/`min`/`step` and *loses* `maxlength`, `minlength`, `pattern`,
         * `size`, `dirname` and `inputmode`. Guessing would have kept `maxlength` on the
         * seven number fields in this batch, which laminas drops.
         */
        $number = ['name', 'autocomplete', 'autofocus', 'disabled', 'form', 'list', 'max',
                   'min', 'step', 'placeholder', 'readonly', 'required', 'type', 'value'];
        /** FormEmail: text's list plus `multiple`, minus `dirname` and `inputmode`. */
        $email = ['name', 'autocomplete', 'autofocus', 'disabled', 'form', 'list', 'maxlength',
                  'minlength', 'multiple', 'pattern', 'placeholder', 'readonly', 'required',
                  'size', 'type', 'value'];
        /** FormUrl: FormEmail's without `multiple`. */
        $url = ['name', 'autocomplete', 'autofocus', 'disabled', 'form', 'list', 'maxlength',
                'minlength', 'pattern', 'placeholder', 'readonly', 'required', 'size', 'type',
                'value'];
        /**
         * FormDate declares none of its own — it extends AbstractFormDateTime, and this is
         * that class's list. Note the absence of `placeholder`, which PersonForm declares
         * on `birthDate`: laminas drops it and so must this.
         */
        $date = ['name', 'autocomplete', 'autofocus', 'disabled', 'form', 'list', 'max',
                 'min', 'readonly', 'required', 'step', 'type', 'value'];

        /**
         * FormFile::$validTagAttributes, verbatim — and notably short. No `value`, which
         * is why input() suppresses it for this type: a file input's value is not
         * settable from markup, and laminas does not emit one.
         */
        $file = ['name', 'accept', 'autofocus', 'disabled', 'form', 'multiple', 'required', 'type'];

        $perType = [
            'text'   => $text,
            'file'   => $file,
            'hidden' => $hidden,
            'number' => $number,
            'email'  => $email,
            'url'    => $url,
            'date'   => $date,
            //FormTel's list is byte-for-byte FormUrl's, so it shares the array rather
            //than repeating it. `tel` is what SionModel\Form\Element\Phone declares —
            //the custom element PersonForm uses four times — and without an entry here
            //its attributes passed through unfiltered, which is the `maxlength` bug the
            //select branch already carries a note about, in the other direction.
            'tel'    => $url,
        ];
        if (! isset($perType[$type])) {
            return $attributes;
        }

        //the presentational globals, as in select() above — the `on*` handlers are left
        //out because no element declares one and the CSP forbids them anyway
        $allowed = array_flip([
            ...$perType[$type],
            'accesskey', 'class', 'contenteditable', 'dir', 'draggable', 'hidden', 'id',
            'lang', 'spellcheck', 'style', 'tabindex', 'title',
        ]);

        return array_filter(
            $attributes,
            static fn (string $key): bool => isset($allowed[$key]) || str_starts_with($key, 'data-'),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function textarea(ElementInterface $element): string
    {
        $attributes = $this->attributes($element, ['name' => (string) $element->getName()]);
        /**
         * **`type` is filtered out, and until 2026-08-21 it was dropped by accident.**
         * `SionModel\Form\Element\Textarea` seeds its own attribute array with
         * `['type' => 'textarea']` — a `<textarea>` has no `type` attribute, and
         * `FormTextarea::$validTagAttributes` does not list one, so laminas never emits it.
         * The old ordering code unset `type` from the declared set as a side effect of
         * putting the seeds first; once the order was corrected to match laminas, three
         * `<textarea type="textarea">` appeared on the collection and library forms. So the
         * filter is transcribed rather than inferred, the way {@see select()}'s is.
         */
        $allowed = array_flip([
            //FormTextarea::$validTagAttributes
            'autocomplete', 'autofocus', 'cols', 'dirname', 'disabled', 'form', 'inputmode',
            'maxlength', 'minlength', 'name', 'placeholder', 'readonly', 'required', 'rows', 'wrap',
            //the globals a form here can declare, the same subset select() allows
            'accesskey', 'class', 'contenteditable', 'dir', 'draggable', 'hidden', 'id',
            'lang', 'spellcheck', 'style', 'tabindex', 'title',
        ]);
        $attributes = array_filter(
            $attributes,
            static fn (string $key): bool => isset($allowed[$key]) || str_starts_with($key, 'data-'),
            ARRAY_FILTER_USE_KEY
        );

        return sprintf(
            '<textarea %s>%s</textarea>',
            $this->attributeString($attributes),
            Escape::html(self::asString($element->getValue()))
        );
    }

    /**
     * A `Checkbox` with `use_hidden_element`, which every checkbox on this form has:
     * a hidden input carrying the unchecked value, then the box inside its own label.
     */
    private function checkbox(Checkbox $element): string
    {
        $name      = (string) $element->getName();
        $unchecked = $element->getUncheckedValue();
        $checked   = $element->getCheckedValue();

        $markup = '';
        if ($element->useHiddenElement()) {
            $markup .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                Escape::htmlAttr($name),
                Escape::htmlAttr((string) $unchecked)
            );
        }

        $attributes = $this->attributes(
            $element,
            ['type' => 'checkbox', 'name' => $name],
            withClass: false
        );
        //`value` is the checked value, not the current one, and `checked` is what
        //carries the state.
        $attributes['value'] = (string) $checked;
        unset($attributes['required']);

        $current = $element->getValue();
        if ((string) $current === (string) $checked) {
            $attributes['checked'] = true;
        }

        return $markup . sprintf(
            '<label><input %s> %s</label>',
            $this->attributeString($attributes),
            Escape::html(($this->translate)((string) $element->getLabel()))
        );
    }

    /**
     * A `<select>` carrying only the options that are currently selected.
     *
     * `Books\View\Helper\FormSelectWithoutOptions` reproduced — that helper extended a
     * laminas-form view helper and was deleted with laminas-i18n in 2026-09, so this is the
     * only implementation now. The publication form has
     * five pickers — `authorsAll`, `editorsAll`, `translatorsAll`, `mainPublicationId`
     * and `translatedFromPublicationId` — whose full option lists are the entire person
     * and publication tables. Shipping them as `<option>` elements would be enormous, so
     * the markup carries only what is already chosen and selectize fetches the rest from
     * the JSON blobs the host's edit controller hands the template
     * (`App\Controller\EntityEditController::publicationValueOptions()` on
     * schoenstatt.link).
     *
     * Two details of the original that are easy to lose and both visible on the page:
     *
     * - **The result follows the order of the selected values, not of the options.** The
     *   helper builds its narrowed list by walking `$selectedOptions`, so a publication
     *   whose authors were chosen out of table order renders them in the order they were
     *   chosen. Reproducing the option order instead would reorder author lists.
     * - **The empty option is dropped unless `''` is itself selected.** In laminas that
     *   falls out of *where* the old subclass filtered — `FormSelect::render()` prepends
     *   the empty option after the narrowing had already happened — rather than from an
     *   explicit decision, but it is the observable behaviour and the current helper
     *   preserves it deliberately.
     *
     * Non-Select elements are passed to the ordinary element path, which raises the more
     * informative error, exactly as the helper defers to a stock FormSelect for them.
     */
    public function selectWithoutOptions(ElementInterface $element, bool $translateOptions = true): string
    {
        if (! $element instanceof Select) {
            return $this->element($element);
        }

        /**
         * `(array)`, exactly as the helper casts it, and the difference is not cosmetic.
         * `(array) null` is the **empty array**, where `[$raw]` would be `[null]` — and
         * `in_array('', [null])` is true under the loose comparison below, so a
         * never-set picker would keep an empty option laminas drops. Caught by the
         * baseline on `mainPublicationId` and `translatedFromPublicationId`, the two
         * pickers of the five that declare one.
         *
         * @var array<array-key, mixed> $selected
         */
        $selected = (array) $element->getValue();

        $narrowed = [];
        foreach ($selected as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            foreach ($element->getValueOptions() as $optionValue => $label) {
                if ((string) $optionValue === (string) $value) {
                    $narrowed[$optionValue] = $label;
                    break;
                }
            }
        }

        $clone = clone $element;
        $clone->setValueOptions($narrowed);

        // in_array loosely, as the original does: a selected `0` and an empty option are
        // the case this distinguishes, and both arrive as strings from a POST.
        if (null !== $clone->getEmptyOption() && ! in_array('', $selected)) {
            $clone->setEmptyOption(null);
        }

        /**
         * **No `form-control` class**, which is what `$withClass = false` buys.
         *
         * TwbBundle adds that class in `formRow`, and none of the five call sites is a
         * row — `fields-partial.phtml` builds their `form-group` by hand and calls the
         * helper directly, so laminas emits `<select name="authorsAll[]" multiple>` with
         * no class at all. Defaulting to true put one on all five.
         */
        return $this->select($clone, $translateOptions, false);
    }

    private function select(Select $element, bool $translateOptions = true, bool $withClass = true): string
    {
        //**`name="inLanguage[]"` when the select is multiple.**
        //`Laminas\Form\View\Helper\FormSelect::render()` appends the brackets itself, and
        //without them a browser posts only the *last* selected option — so the literature
        //search box would have silently searched one language where the visitor picked
        //three. The association form has no multiple select, which is why this survived
        //the first form port; found by the byte diff on /literature/search.
        //**Truthy, not `=== true`.** `FormSelect::render()` asks
        //`array_key_exists('multiple', $attributes) && $attributes['multiple']`, and the
        //difference is not academic: `JUser\Form\EditUserForm` declares
        //`'multiple' => 'multiple'` — the string, which is what the HTML attribute's value
        //actually is — where the literature search box that first found this bug declares
        //`true`. With the strict check the roles select rendered `name="rolesList"`, so a
        //browser posted `rolesList=1&rolesList=41&…`, PHP kept **only the last**, and
        //saving an account would have cut it down to one role. Found by the byte diff on
        ///users/5/edit; nothing about it is visible on the page.
        $name = (string) $element->getName();
        if ($element->getAttribute('multiple')) {
            $name .= '[]';
        }

        $attributes = $this->attributes($element, ['name' => $name], $withClass);
        /**
         * What `Laminas\Form\View\Helper\FormSelect` will actually render: its own
         * `$validTagAttributes`, **plus** `AbstractHelper::$validGlobalAttributes`. Both
         * halves are load-bearing and the second was missing — `maxlength`, which twenty
         * select definitions in this application declare, is in neither list and is
         * correctly dropped, but `id` is a *global* attribute and was being dropped with
         * it. That cost the advanced search its `id="roleTitleSelect"`, and with it the
         * `for` on its label and the hook `gen-schoenstatt-advanced-search.js` uses to
         * turn the select into a selectize widget — so the field would have rendered as
         * a bare multi-select where every other environment shows tag chips.
         *
         * The global list is transcribed selectively: the presentational and structural
         * attributes, not the sixty-odd `on*` event handlers, because a form element in
         * this codebase declares none and the CSP forbids inline handlers anyway. `data-*`
         * is allowed by prefix in the original and reproduced the same way below.
         */
        $allowed = array_flip([
            //FormSelect::$validTagAttributes
            'name', 'autocomplete', 'autofocus', 'disabled', 'form', 'multiple', 'required', 'size',
            //the globals a form here can declare
            'accesskey', 'class', 'contenteditable', 'dir', 'draggable', 'hidden', 'id',
            'lang', 'spellcheck', 'style', 'tabindex', 'title',
        ]);
        $attributes = array_filter(
            $attributes,
            static fn (string $key): bool => isset($allowed[$key]) || str_starts_with($key, 'data-'),
            ARRAY_FILTER_USE_KEY
        );

        /**
         * `getEmptyOption()`, not `getOption('empty_option')`, because the two are not
         * the same lookup and only the first is what `Laminas\Form\View\Helper\FormSelect`
         * reads.
         *
         * `Select::setOptions(['empty_option' => …])` — how every form here declares it —
         * populates both the options array *and* the dedicated property, so the two agreed
         * for the whole of batches 4 through 7 and nothing showed the difference.
         * `setEmptyOption()` called directly sets only the property, and that is the path
         * `selectWithOutOptions()` uses when it hands a narrowed clone back: the empty
         * option a visitor had actually selected vanished from the markup.
         */
        /**
         * **Merged into the options array, not emitted beside it**, because that is what
         * `FormSelect::render()` does:
         *
         *     $options = ['' => $emptyOption] + $options;
         *
         * The `+` is the whole point. It is a union, so when the value options *already*
         * carry a `''` key the existing entry wins and the select ends up with exactly one
         * empty option. Emitting the empty option separately and then iterating the value
         * options — which is what this did until 2026-08-15 — renders **two**.
         *
         * Invisible for four batches because it needs a form that declares both, and only
         * `AssociationForm::timeZoneId` does: `'empty_option' => ''` next to a
         * `$timeZoneOptions` list whose first key is `''`. It surfaced on
         * `/associations/create` rather than on the edit page for a second reason, below.
         *
         * @var array<array-key, mixed>|string|null $empty laminas annotates it this wide
         */
        $empty      = $element->getEmptyOption();
        $allOptions = $element->getValueOptions();
        if (null !== $empty) {
            $allOptions = ['' => is_string($empty) ? $empty : ''] + $allOptions;
        }

        /**
         * **A multiple select's value is an array, and this compared it as a scalar.**
         *
         * `is_scalar($selected)` is false for an array, so the comparison fell back to `''`
         * and *no option was ever marked selected on a multiple select*. Caught by the
         * batch-7 baseline on a composition's `tags` and a dictionary entry's `links`,
         * where laminas renders `<option value="Hinos-salmos" selected>` and this rendered
         * the same option unselected.
         *
         * That is not a rendering nicety, it is **data loss on save**: the moderator opens
         * the form, the multi-select shows nothing chosen, they change something else and
         * submit — and the browser posts no values for `tags[]`, so `getData()` contributes
         * an empty array and `updateEntity()` writes it over the stored tags. Every
         * multiple select in the batch was affected: `tags` on text and composition,
         * `composersAll`, `lyricistsAll`, `links`, and `authors`, `inLanguage`, `keywords`
         * and `adminTags` on the book form.
         *
         * It survived the earlier form ports because `AssociationForm` has no multiple
         * select — the same reason the missing `[]` on the name went unnoticed until batch
         * 5, which is the *other* half of this element type being wrong.
         *
         * The scalar branch is unchanged, deliberately: it reproduces laminas' behaviour
         * for a null value, where the cast to `''` is what an `empty_option` relies on.
         */
        /**
         * **A null value selects nothing**, which is `FormSelect::validateMultiValue()`:
         *
         *     if (null === $value) { return []; }
         *     if (! is_array($value)) { return [$value]; }
         *
         * This used to cast null to `''` and select on that. Harmless while the empty
         * option was emitted separately — nothing else could match `''` — and wrong the
         * moment the merge above put a real `''` option into the list, which is why the two
         * fixes belong in one commit. It is also why the defect showed on a *create* page:
         * an edit form's select has a value, and only an empty form leaves it null.
         *
         * @var mixed $selected
         */
        $selected       = $element->getValue();
        $selectedValues = [];
        if (is_array($selected)) {
            foreach ($selected as $one) {
                if (is_scalar($one)) {
                    $selectedValues[] = (string) $one;
                }
            }
        } elseif (null !== $selected) {
            $selectedValues[] = (string) (is_scalar($selected) ? $selected : '');
        }

        $options = '';
        foreach ($allOptions as $value => $label) {
            if (is_array($label)) {
                //Option groups: no association-form select uses one, and rendering it
                //wrongly would silently drop every option inside. Skipped loudly rather
                //than half-rendered.
                continue;
            }
            $options .= sprintf(
                '<option value="%s"%s>%s</option>' . "\n",
                Escape::htmlAttr((string) $value),
                in_array((string) $value, $selectedValues, true) ? ' selected' : '',
                Escape::html($translateOptions ? ($this->translate)((string) $label) : (string) $label)
            );
        }

        return sprintf('<select %s>%s</select>', $this->attributeString($attributes), $options);
    }

    // -------------------------------------------------------------- plumbing

    /**
     * The element's attributes, in the order laminas renders them.
     *
     * **The order is the element's own, and the seeds are assigned *over* it.** That is
     * exactly what the laminas helpers do — `FormInput::render()` takes
     * `$element->getAttributes()` and then assigns `name`, `type` and `value` into it, so
     * an attribute the element already declares keeps its position and only a genuinely
     * new one is appended. `class` sits between the two because TwbBundle's row sets it on
     * the *element* before any helper runs.
     *
     * This read `$attributes = $seed;` first until 2026-08-21, i.e. it put `type` and
     * `name` in front of everything, and every form ported before then agreed with it by
     * accident: an element declared as `'type' => 'Text'` gets `['type' => 'text']` seeded
     * by its own class, so `type` really was first and `name` really was second.
     * `JUser\Form\EditUserForm` is the first ported form to declare the type *inside*
     * `attributes` — which leaves the element a plain `Laminas\Form\Element`, whose
     * attribute array starts empty and so begins with `name`. laminas rendered
     * `name="username" type="text" size="30"`, this rendered
     * `type="text" name="username" size="30"`, and the two documents are equivalent to a
     * browser and unequal to a diff. Fixing the order rather than teaching the harness to
     * ignore it, because "the bytes match" is the only claim this renderer can actually
     * make.
     *
     * @param array<string, string> $seed `name`/`type`, assigned last
     * @return array<string, scalar>
     */
    private function attributes(ElementInterface $element, array $seed, bool $withClass = true): array
    {
        //`name` is always in here, because `Element::setName()` writes it — which is
        //precisely why the seeds have to be assigned over the declared set rather than
        //prepended to it.
        //
        //**`value` is the one key that is dropped**, and it is dropped because of *when*
        //laminas would have produced it rather than because it is unwanted. A plain
        //element never carries one (`Element::setAttribute()` diverts that key to
        //`setValue()`), but `SionModel\Form\Element\Csrf` materialises `value` in its
        //attribute array the first time its hash is asked for — and TwbBundle's row has
        //already set `class` on the element by then, so laminas renders
        //`type name class value` and not `type name value class`. Removing it here and
        //letting the caller append it after `class` reproduces that for every element at
        //once, instead of encoding one element's laziness.
        $declared = $element->getAttributes();
        unset($declared['value']);

        /** @var array<string, scalar> $attributes */
        $attributes = [];
        $hasOwnClass = false;

        //**An element's own class keeps its declared position and `form-control` is
        //appended to it**, rather than being dropped in favour of a `form-control`
        //written last. Until the literature search box was ported no element on a ported
        //form declared a class at all, so overwriting looked correct in every direction.
        //That input declares `class="input-lg search-query"` and lost both the classes
        //*and* the attribute's position — the field went on working and rendered at the
        //wrong size, which is the kind of difference only a byte comparison finds.
        foreach ($declared as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            if ('class' === $key) {
                $own = trim((string) $value);
                if ('' === $own) {
                    continue;
                }
                $hasOwnClass          = true;
                $attributes['class'] = $withClass ? $own . ' form-control' : $own;
                continue;
            }
            $attributes[(string) $key] = self::isTranslatable((string) $key) && is_string($value) && '' !== $value
                ? ($this->translate)($value)
                : $value;
        }

        if ($withClass && ! $hasOwnClass) {
            $attributes['class'] = 'form-control';
        }

        foreach ($seed as $key => $value) {
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Whether an attribute's *value* is translated before it is escaped.
     *
     * Two are, and this is not a convention invented here:
     * `Laminas\Form\View\Helper\AbstractHelper::translateHtmlAttributeValue()` translates
     * `placeholder` (its `$translatableAttributes`) and `title` (its static
     * `$defaultTranslatableHtmlAttributes`) on every element rendered through a form view
     * helper, using the helper's own text domain. Neither this class nor any Twig template
     * did, so the literature search box read `placeholder="Search Catalogs"` in all five
     * locales while laminas rendered "Busca nos catálogos" — on the one page of the batch
     * whose *only* visible untranslated string it was. Found by tools/port-baseline.php,
     * not by looking.
     *
     * The empty-value guard is the helper's own: it returns early rather than asking the
     * translator for '', which would file an empty phrase.
     */
    private static function isTranslatable(string $attribute): bool
    {
        return 'placeholder' === $attribute || 'title' === $attribute;
    }

    /**
     * The nine attributes laminas renders as HTML5 boolean attributes.
     *
     * Transcribed from `Laminas\Form\View\Helper\AbstractHelper::$booleanAttributes`,
     * whose `off` value is the empty string for every one of them — which is what makes
     * "falsy means omit the attribute entirely" right rather than a shortcut.
     *
     * Keyed on the attribute **name**, not on the PHP type of its value, and that is the
     * whole point. This class used to switch on `is_bool()`, which is correct for the
     * `checked` a checkbox row synthesises and wrong for anything a form *declares*: an
     * HTML boolean attribute's idiomatic spelling is `multiple="multiple"`, and
     * `JUser\Form\EditUserForm` spells it that way. laminas rendered `<select … multiple>`
     * and this rendered `<select … multiple="multiple">`.
     *
     * @var array<string, true>
     */
    private const BOOLEAN_ATTRIBUTES = [
        'autofocus'  => true,
        'checked'    => true,
        'disabled'   => true,
        'itemscope'  => true,
        'multiple'   => true,
        'readonly'   => true,
        'required'   => true,
        'selected'   => true,
        'novalidate' => true,
    ];

    /**
     * A boolean attribute renders bare when truthy and vanishes when falsy; `false` on any
     * other attribute is dropped; everything else is escaped as an attribute value.
     *
     * @param array<string, scalar> $attributes
     */
    private function attributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if (isset(self::BOOLEAN_ATTRIBUTES[$key])) {
                if ($value) {
                    $parts[] = Escape::htmlAttr($key);
                }
                continue;
            }
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = Escape::htmlAttr($key);
                }
                continue;
            }
            $parts[] = sprintf(
                '%s="%s"',
                Escape::htmlAttr($key),
                Escape::htmlAttr((string) $value)
            );
        }

        return implode(' ', $parts);
    }
}
