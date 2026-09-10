<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use Laminas\Form\ElementPrepareAwareInterface;
use Laminas\Form\FormInterface;

/**
 * A file input. One of them: `Books\Form\ImportForm::file`.
 *
 * `prepareElement()` is the whole reason this is a class rather than a type attribute. A
 * form containing a file input must be submitted as `multipart/form-data` or the browser
 * sends the filename and not the file, and no form here sets that enctype itself — the
 * element does it when the form is prepared, and `BootstrapFormRenderer::open()` reads the
 * attribute back. Drop this method and the spreadsheet import silently uploads nothing.
 *
 * What is *not* here is laminas' `getInputSpecification()`, which typed the input as a
 * `FileInput` and so injected an `UploadFile` validator. Nothing in this application
 * validated a file through the form: the pages are Symfony-served, an upload arrives in
 * `$request->files` and never in the data the form is given, and
 * `App\Books\Import\SpreadsheetUpload` is what actually judges it. That was already
 * recorded as a known difference in the engine parity test before this class existed.
 */
class File extends Element implements ElementPrepareAwareInterface
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'file',
    ];

    public function prepareElement(FormInterface $form): void
    {
        $form->setAttribute('enctype', 'multipart/form-data');
    }
}
