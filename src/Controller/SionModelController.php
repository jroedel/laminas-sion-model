<?php

declare(strict_types=1);

namespace SionModel\Controller;

use BjyAuthorize\Exception\UnAuthorizedException;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use SionModel\Form\ConfirmForm;
use SionModel\Service\ChangesCollector;
use SionModel\Service\ProblemService;
use SionModel\Service\SionCacheService;
use Webmozart\Assert\Assert;

use function in_array;
use function is_numeric;

class SionModelController extends AbstractActionController
{
    public function __construct(
        private ProblemService $problemService,
        private ChangesCollector $changesCollector,
        private SionCacheService $sionCacheService,
        private array $config
    ) {
        Assert::keyExists($config, 'sion_model');
    }

    public function clearPersistentCacheAction(): JsonModel
    {
        $key = $this->params()->fromQuery('key');
        if (! isset($key)) {
            throw new UnAuthorizedException();
        }
        $config = $this->getSionModelConfig();
        Assert::keyExists($config, 'api_keys');
        Assert::isArray($config['api_keys']);
        Assert::allStringNotEmpty($config['api_keys']);
        if (! in_array($key, $config['api_keys'])) {
            throw new UnAuthorizedException();
        }
        if (! $this->sionCacheService->flush()) {
            $this->getResponse()->setStatusCode(401);
            $message = 'Unsuccessful flush';
        } else {
            $message = 'Success';
        }
        return new JsonModel(['message' => $message]);
    }

    public function dataProblemsAction(): ModelInterface|ResponseInterface
    {
        $problems = $this->problemService->getCurrentProblems();

        return new ViewModel([
            'problems' => $problems,
        ]);
    }

    /**
     * Autofix data problems. User must accept the changes to be applied.
     */
    public function autoFixDataProblemsAction(): ModelInterface|ResponseInterface
    {
        $simulate = true;

        $form    = new ConfirmForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) { //check the CSRF value
                $simulate = false;
            }
        }
        $problems = $this->problemService->autoFixProblems($simulate);

        $view = new ViewModel([
            'problems'     => $problems,
            'isSimulation' => $simulate,
            'form'         => $form,
        ]);
        $view->setTemplate('sion-model/sion-model/data-problems');
        return $view;
    }

    /**
     * @todo The logic here doesn't add up
     */
    public function viewChangesAction(): ModelInterface|ResponseInterface
    {
        $config  = $this->getSionModelConfig();
        $maxRows = isset($config['changes_max_rows']) && (is_numeric($config['changes_max_rows']))
            ? (int) $config['changes_max_rows']
            : 500;
        $results = $this->changesCollector->getAllChanges();
        return new ViewModel([
            'changes'    => $results,
            'maxRows'    => $maxRows,
            'showEntity' => true,
        ]);
    }

    public function phpInfoAction(): ModelInterface|ResponseInterface
    {
        return new ViewModel();
    }

    protected function getSionModelConfig(): array
    {
        return $this->config['sion_model'];
    }
}
