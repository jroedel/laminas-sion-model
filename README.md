# Sion Model

A simple array-based ORM platform for ZF2. Includes many filters and view helpers for real life ZF2 use. 

## Installation

```bash
./composer.phar require jroedel/zf2-juser
```

## Features

* Simple array-based ORM
* Support for user suggestions upon the entities, required posterior moderation
* Automatic reporting on who changed what, when.

## ORM

Object Relational Mapping. While you should probably take advantage of PHP classes to handle 
database objects, sometimes **arrays just make more sense**. 

Why does this make my life easier? It hides all database code behind the `createEntity` and `updateEntity`
functions, it keeps track of who edited what data, and when, allows for easy data problem management (when
users inserted legal, but clearly wrong information), and provides code for public users **suggestions** 
that will later by looked over by moderators.

### Entities

Entities are defined in configuration under the `['sion_model']['entities']` key. You define a
mapping of the entity field names to database column names, tell the ORM what the table is called 
and how these entities are integrated into the router tree.

### Getting started

How to implement a Book database:

1. Extend the SionModel class, and create a ServiceFactory to build it.

2. Define your entity configuration in the `module.config.php` referencing the example file `sionmodel.global.php.dist` 
and properties from `SionModel\Entity\Entity`.

3. Implement `getBooks()` and `getBook()` using the `fetchSome()` function. 

4. Create a BookForm implementing the `SionForm` class. 

5. Extend the EntityController class, implementing `showAction`, `editAction`, `createAction`, and `indexAction`.
	(The EntityController is not yet created)

## Data problem management

With data problem management you create two classes to detect problems with a certain 
entity. The problems may be submitted to the database to be tracked when they are 
resolved. Also, the user can choose to ignore a particular error. A pre-made GUI is 
included which shows all the collected problems. 

### Steps to use:

1. Define 1 or more problems under the `['sion_model']['problem_specifications']` config key in `module.config.php`:```

	'sion_model' => [
		'problem_providers' => [
	        'Project\Model\ProjectTable',
	    ],
		'problem_specifications' => [
	        'person-no-email' => [
	            'entity'            => 'person',
	            'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
	            'text'              => 'No email associated with person',
	    	],
		],
	],
2. Implement the `ProblemProviderInterface` in the `Project\Model\ProjectTable` class. 

## Building a SionTable

`SionTable::__construct()` takes **four arguments and no container**:

```php
public function __construct(
    AdapterInterface $dbAdapter,
    EntitiesService $entities,
    array $config,                                  // the ['sion_model'] config
    ?ActingUserProviderInterface $actingUserProvider,
)
```

Three optional collaborators are attached afterwards, by the factory, and
`SionModel\Service\SionTableWiring` does all three from a container in one call:

```php
$table = new BookTable($adapter, $entities, $sionModelConfig, $actingUserProvider);
SionTableWiring::apply($container, $table);
```

That covers the persistent cache and its end-of-request flush point, the logger, and the
user-directory resolver.

### The end-of-request flush, and a Symfony host

Nothing is written to the persistent cache at the moment it is cached: the item goes into
memory and onto a queue, and `SionCacheTrait::onFinishWriteCache()` writes the queue out at
the end of the request. So the cache only works if something calls that method, and
`SionTableWiring::wireFlushPoint()` picks one of two:

- a listener on `MvcEvent::FINISH` — the default, and all a laminas host needs;
- **`SionModel\Cache\CacheFlushQueue`**, if the container holds one under that class name.
  Tables enrol themselves into it as they are built, and the host drains it from whatever
  its own end-of-request hook is.

A host with no `MvcEvent::FINISH` — a Symfony front controller, say — **must register a
queue or its persistent cache stores nothing**. That failure is completely silent: within
the request that queued them, items are served back out of `$memoryCache`, so a queued item
and a written one are indistinguishable to the code that queued them. Only the next request
can tell, and it has no way to say so. On schoenstatt.link this went unnoticed for eleven
days after its front-controller cutover, across 0 writes and 0 hits, because not booting
laminas-mvc saved more per request than the cache had been returning and wall time went
*down*.

```php
// once per request, in the host's kernel
$queue = new CacheFlushQueue();
$container->setService(CacheFlushQueue::class, $queue);   // before any table is built
// ... and after the response has been sent:
$queue->flush();
```

Three things follow from the design:

- When a queue is present the MVC `Application` is deliberately **not** resolved.
  `has('Application')` answers *true* under a Symfony front controller — laminas-mvc's
  module config defines the service whether or not anything bootstraps it — so the
  unconditional version built an MVC application per table per request for an event manager
  whose event that request would never fire.
- **Do not register a queue in a console process.** An APCu segment belongs to the SAPI that
  created it, so anything a CLI run writes lands where no web request can read it.
- `flush()` is safe to call twice: the write queue is drained by the pass that writes it.
  Note this also means the per-request `max_items_to_cache` budget is spent once and not
  renewed.

**This is a breaking change (2026-08-22).** The old signature was
`(AdapterInterface, $serviceLocator, ?ActingUserProviderInterface)` and the constructor
pulled six services out of that container. A consuming application updating past this gets a
`TypeError` at construction, per factory, naming the class — which is deliberate: a silent
half-wired table is the failure mode worth avoiding.

Three things a host should know while converting.

**A data class no longer reaches laminas-mvc.** The only reason the constructor wanted the
cache was to then resolve `Application` for its event manager. That lookup lives in
`SionTableWiring` now, where a host can also replace it outright — a factory is allowed to
know about the ServiceManager and the MVC application; the table is not.

**A missed `SionTableWiring::apply()` is silent.** The table reads and writes perfectly and
merely never caches. If your application has an integration suite, sweep every table named
by a `sion_model_class` entity spec and assert `getPersistentCache()` is not null; the
schoenstatt.link suite does exactly that in `SionTableWiringCompletenessTest`.

**Do not do cache work in a subclass constructor.** The cache is attached *after*
construction now, so a constructor-time `fetchCachedEntityObjects()` misses every time and
the matching write is discarded — no error, just work repeated on every build of the table.
Move it to a lazy getter. `Schoenstatt\Model\SchoenstattTable::getCountryNameTranslations()`
is the worked example; it was doing a full ICU pass over five locales.

**`$entityProblemPrototype` is gone from `SionTable`.** Nothing in the class read it — it was
declared and populated for the benefit of `ProblemProviderInterface` implementors that clone
it. Those subclasses take it in their own constructors now, and the parent's
`ProblemService` lookup and its `! $this instanceof ProblemTable` cycle guard went with it.

## Attributing changes and comments to a user

Two screens name a person: the change log renders "who changed this", the comments list
renders "who wrote this". Both go through `SionModel\Service\UserDirectoryInterface`,
resolved from the container by the **service id** in the `user_directory_service` config
key (`dist/sionmodel.global.php` documents it).

It is a string rather than a class constant on purpose. Until 2026-08-22 `SionTable`
carried `use JUser\Model\UserTable`, naming a type from a package this one does not
require -- an undeclared dependency, and a cycle, since that class extends `SionTable`.
Both call sites also dereferenced the getter without a guard while its own docblock
documented a null return, so a host that registered no user table fatalled on its own
change log. Neither showed up here, because this application always registers one.

A service that implements the interface is used directly. One that merely answers
`getUsers()` and `getUsernames()` -- which is what a JUser `UserTable` does -- is wrapped
in `SionModel\Service\Adapter\CallableUserDirectory` automatically, so **no consuming
application has to change anything**. Set the key to `null` if there is no user directory:
both screens then render a blank name, which is already what they do for a user id nobody
can resolve.

`getUserTable()` and `setUserTable()` remain as deprecated shims over
`getUserDirectory()`/`setUserDirectory()`, because they are public API and a consuming
application may still call them.

## Twig form rendering

`SionModel\Form\BootstrapFormRenderer` renders a `Laminas\Form` as Bootstrap 3 markup
without a laminas-mvc view helper, and `SionModel\Twig\FormExtension` binds it to twelve
Twig functions (`form_open`, `form_row`, `form_submit`, ...). Together they are what lets a
Symfony-served -- or any non-MVC -- route render this package's forms.

It is not "equivalent" markup: it emits the same bytes as
`SionModel\Form\View\Helper\SionFormRow`, the TwbBundle-derived helper in this same
package, because the CSS and the selectize/markdown JS bundles are written against that
exact structure. The renderer's class docblock lists the details that are load-bearing.

Wire it with any `callable(string): string` as the translator:

```php
$twig->addExtension(new SionModel\Twig\FormExtension(
    new SionModel\Form\BootstrapFormRenderer($translate)
));
```

Only the element types a ported form has needed are implemented; an unknown one throws
rather than falling back to a text input.

## Coming soon

* Integrated mailing support
* Integrated data problem management (display data errors and warnings to admins through a GUI) (Completed!)
* Support entity-level ACL rules
