<?php

namespace SionModel\View\Helper;

use Closure;
use InvalidArgumentException;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use SionModel\View\Escape;

/**
 * An entity rendered as a flag, a name, a link and an edit pencil.
 *
 * A plain class since 2026-09. Everything it used to reach through `$this->view` — the
 * renderer laminas-view handed to every AbstractHelper — is now injected: `flag`,
 * `dateFormat`, `translate`, `url`, `editPencil`, `editPencilNew` and `isAllowed` as
 * closures, `escapeHtml` as {@see Escape::html()}.
 *
 * ## Two dynamic lookups, made explicit
 *
 * The renderer allowed this class to fetch a helper *by name computed at runtime*, and it
 * did so twice. Both are now declared:
 *
 *  - `$this->view->$viewHelperName(...)`, the deferral to whatever helper an entity spec
 *    names in `formatViewHelper`, becomes the `$formatHelpers` map. In this application
 *    that map has exactly one entry, `publication` -> `formatPublication`;
 *  - `$this->view->plugin('isAllowed')` becomes the `$isAllowed` closure. It was wrapped
 *    in a try/catch whose comment reads "if we don't have the isAllowed plugin, just
 *    allow" — a null closure is that same state, said out loud.
 *
 * ## The translate closure must be the helper, not a translator
 *
 * `$name` is looked up with no text domain, so what answers has to be the object the host
 * sets the request's domain on. A raw translator would look in `default`, miss, and return
 * the English source in every locale — invisible in English and wrong in the other four.
 */
class FormatEntity
{
    /**
     * @var Entity[] $entities
     */
    protected $entities = [];

    /**
     * @var bool $routePermissionCheckingEnabled
     */
    protected $routePermissionCheckingEnabled = false;

    /**
     * Every closure is optional and every one of them degrades to the safest reading of
     * the original: no flag, no link, no pencil, untranslated source text. A host that
     * supplies none still renders a name.
     *
     * @param EntitiesService $entityService
     * @param bool $routePermissionCheckingEnabled
     * @param Closure(string): string|null $flag
     * @param Closure(mixed, int, int): (string|false)|null $dateFormat
     * @param Closure(string): string|null $translate the shared `translate` view helper
     * @param Closure(string, array): string|null $url
     * @param Closure(string, mixed): string|null $editPencil
     * @param Closure(string, array): string|null $editPencilNew
     * @param Closure(?string, ?string): bool|null $isAllowed
     * @param array<string, Closure> $formatHelpers keyed by the name an entity spec's
     *        `formatViewHelper` carries
     */
    public function __construct(
        $entityService,
        $routePermissionCheckingEnabled = false,
        protected readonly ?Closure $flag = null,
        protected readonly ?Closure $dateFormat = null,
        protected readonly ?Closure $translate = null,
        protected readonly ?Closure $url = null,
        protected readonly ?Closure $editPencil = null,
        protected readonly ?Closure $editPencilNew = null,
        protected readonly ?Closure $isAllowed = null,
        protected readonly array $formatHelpers = []
    ) {
        $this->entities = $entityService->getEntities();
        $this->setRoutePermissionCheckingEnabled($routePermissionCheckingEnabled);
    }

    /**
     *
     * @param string $entityType
     * @param mixed[] $data
     * @param array $options
     * Available options: displayAsLink(bool), displayEditPencil(bool), displayFlag(bool)
     * @throws InvalidArgumentException
     */
    public function __invoke($entityType, $data, array $options = [])
    {
        if (! isset($entityType) || ! key_exists($entityType, $this->entities)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Unknown entity type passed: ' . $entityType);
            }
        }
        $isDeleted = isset($data['isDeleted']) && $data['isDeleted'];
        $entitySpec = $this->entities[$entityType];

        //forward request to the registered formatter if we have one
        if (isset($entitySpec->formatViewHelper) && ! $isDeleted) {
            $viewHelperName = $entitySpec->formatViewHelper;
            if (isset($this->formatHelpers[$viewHelperName])) {
                return ($this->formatHelpers[$viewHelperName])($entityType, $data, $options);
            }
        }

        //set default options
        $options = [
            'displayFlag' => isset($options['displayFlag']) ? (bool)$options['displayFlag'] : true,
            'displayAsLink' => isset($options['displayAsLink']) ? (bool)$options['displayAsLink'] : true,
            'displayEditPencil' => isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : true,
            'failSilently' => isset($options['failSilently']) ? (bool)$options['failSilently'] : true,
            'displayInactiveLabel' => isset($options['displayInactiveLabel']) ? (bool)$options['displayInactiveLabel'] : false,
        ];
        if ($isDeleted) {
            $options['displayAsLink'] = false;
            $options['displayEditPencil'] - false;
            $options['displayFlag'] = false;
        }

        if (! is_array($data)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('$data should be an array.');
            }
        }
        if (
            ! $entitySpec->entityKeyField ||
            ! isset($data[$entitySpec->entityKeyField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Id field not set for entity ' . $entityType);
            }
        }
        if (
            ! $entitySpec->nameField ||
            ! isset($data[$entitySpec->nameField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Name field not set for entity ' . $entityType);
            }
        }

        $finalMarkup = '';
        if (
            $options['displayFlag'] &&
            $entitySpec->countryField &&
            isset($data[$entitySpec->countryField]) &&
            2 === strlen($data[$entitySpec->countryField])
        ) {
            $finalMarkup .= $this->renderFlag($data[$entitySpec->countryField]) . "&nbsp;";
        }

        //if our name field is a date, format it as a medium date
        if ($data[$entitySpec->nameField] instanceof \DateTime) {
            $name = null !== $this->dateFormat
                ? ($this->dateFormat)(
                    $data[$entitySpec->nameField],
                    \IntlDateFormatter::MEDIUM,
                    \IntlDateFormatter::NONE
                )
                : '';
        } else {
            $name = $data[$entitySpec->nameField];
            if ($entitySpec->nameFieldIsTranslatable) {
                $name = $this->translate($name);
            }
        }

        if ($options['displayAsLink']) {
            $finalMarkup .= $this->wrapAsLink($entityType, $data, Escape::html((string) $name));
        } else {
            $finalMarkup .= Escape::html((string) $name);
        }

        if ($options['displayEditPencil'] && isset($entitySpec->editRoute)) {
            $editRoute = $entitySpec->editRoute;
            if ($entitySpec->editRouteParams) {
                $editParams = [];
                foreach ($entitySpec->editRouteParams as $routeParam => $entityField) {
                    if (! isset($data[$entityField])) {
                        //@todo log this
                    } else {
                        $editParams[$routeParam] = $data[$entityField];
                    }
                }
                if (count($editParams) === count($entitySpec->editRouteParams)) {
                    $finalMarkup .= $this->renderEditPencilNew($editRoute, $editParams);
                }
            } elseif ($entitySpec->editRouteKeyField &&
                isset($data[$entitySpec->editRouteKeyField])
            ) {
                $editId = $data[$entitySpec->editRouteKeyField];
                $finalMarkup .= $this->renderEditPencil($entityType, $editId);
            } elseif ($entitySpec->defaultRouteParams) {
                $editParams = [];
                foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                    if (! isset($data[$entityField])) {
                        //@todo log this
                    } else {
                        $editParams[$routeParam] = $data[$entityField];
                    }
                }
                if (count($editParams) === count($entitySpec->defaultRouteParams)) {
                    $finalMarkup .= $this->renderEditPencilNew($editRoute, $editParams);
                }
            }
        }
        if ($options['displayInactiveLabel'] &&
            (isset($data['isActive']) && is_bool($active = $data['isActive']) ||
            isset($data['active']) && is_bool($active = $data['active']))
        ) {
            if (! $active) {
                $finalMarkup .= ' <span class="label label-warning">' . $this->translate('Inactive') . '</span>';
            }
        }
        return $finalMarkup;
    }

    /** The `flag` view helper, or nothing when the host supplies none. */
    protected function renderFlag($countryCode)
    {
        return null !== $this->flag ? ($this->flag)($countryCode) : '';
    }

    /**
     * The shared `translate` view helper, or the source string. A total catalog miss
     * returns the source string too, so the untranslated host is not a special case.
     */
    protected function translate($message)
    {
        return null !== $this->translate ? ($this->translate)($message) : $message;
    }

    /** The `editPencil` view helper, or nothing. Permissions are checked inside it. */
    protected function renderEditPencil($entityType, $id)
    {
        return null !== $this->editPencil ? ($this->editPencil)($entityType, $id) : '';
    }

    /** The `editPencilNew` view helper, or nothing. */
    protected function renderEditPencilNew($editRoute, array $params)
    {
        return null !== $this->editPencilNew ? ($this->editPencilNew)($editRoute, $params) : '';
    }

    /**
     * @param string $entityType
     * @param array $data
     * @param string $linkText
     * @return string
     */
    protected function wrapAsLink($entityType, $data, $linkText)
    {
        $entitySpec = $this->entities[$entityType];
        $route = $entitySpec->showRoute;
        if (! isset($route) || null === $this->url || ! $this->isActionAllowed('show', $entityType, $data)) {
            return $linkText;
        }
        if (is_array($entitySpec->showRouteParams)) {
            $params = [];
            foreach ($entitySpec->showRouteParams as $routeParam => $entityField) {
                if (! isset($data[$entityField])) {
                    //@todo log this
                } else {
                    $params[$routeParam] = $data[$entityField];
                }
            }
            if (count($params) === count($entitySpec->showRouteParams)) {
                return sprintf('<a href="%s">%s</a>', ($this->url)($route, $params), $linkText);
            }
        }
        if (
            $entitySpec->showRouteKey
            && $entitySpec->showRouteKeyField
            && isset($data[$entitySpec->showRouteKeyField])
            && $this->isActionAllowed('show', $entityType, $data)
        ) {
            $routeKey = $entitySpec->showRouteKey;
            $id = $data[$entitySpec->showRouteKeyField];
            return sprintf('<a href="%s">%s</a>', ($this->url)($route, [$routeKey => $id]), $linkText);
        }
        if (is_array($entitySpec->defaultRouteParams)) {
            $params = [];
            foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                if (! isset($data[$entityField])) {
                    //@todo log this
                } else {
                    $params[$routeParam] = $data[$entityField];
                }
            }
            if (count($params) === count($entitySpec->defaultRouteParams)) {
                return sprintf('<a href="%s">%s</a>', ($this->url)($route, $params), $linkText);
            }
        }
        return $linkText;
    }

    protected function isActionAllowed($action, $entityType, $object)
    {
        if (! $this->getRoutePermissionCheckingEnabled()) {
            return true;
        }
        if (! isset(Entity::$isActionAllowedPermissionProperties[$action])) {
            throw new InvalidArgumentException('Invalid action parameter');
        }
        $entitySpec = $this->entities[$entityType];

        //no isAllowed collaborator is the state the original reached by catching the
        //plugin manager's exception: just allow
        if (null === $this->isAllowed) {
            return true;
        }

        //check the route permissions
        $routeProperty = array_key_exists($action, Entity::$actionRouteProperties) ? Entity::$actionRouteProperties[$action] : null;
        if (
            isset($routeProperty) && isset($entitySpec->$routeProperty) &&
            ! ($this->isAllowed)('route/' . $entitySpec->$routeProperty)
        ) {
            return false;
        }

        if (! isset($entitySpec->aclResourceIdField)) {
            return true;
        }

        $permissionProperty = Entity::$isActionAllowedPermissionProperties[$action];
        if (! isset($entitySpec->$permissionProperty)) {
            //we don't need the permission, just the resourceId
            return ($this->isAllowed)($object[$entitySpec->aclResourceIdField]);
        }

        return ($this->isAllowed)($object[$entitySpec->aclResourceIdField], $entitySpec->$permissionProperty);
    }

    /**
    * Get the routePermissionCheckingEnabled value
    * @return bool
    */
    public function getRoutePermissionCheckingEnabled()
    {
        return $this->routePermissionCheckingEnabled;
    }

    /**
    *
    * @param bool $routePermissionCheckingEnabled
    * @return self
    */
    public function setRoutePermissionCheckingEnabled($routePermissionCheckingEnabled)
    {
        $this->routePermissionCheckingEnabled = $routePermissionCheckingEnabled;
        return $this;
    }
}
