<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\Form\SuggestForm;

/**
 * Factory responsible of priming the SuggestForm
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class SuggestFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return CreateTimelineEventForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		$form = new SuggestForm();
        $form->prepareForSuggestion($serviceLocator);

		return $form;
    }
}
