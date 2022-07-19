# Sion Model

An array-based ORM platform for Laminas. Includes many filters and view helpers. Requires jtranslate and juser.

## Installation

```bash
composer require jroedel/laminas-sion-model
```

## Features

* Simple array-based ORM
* Automatic caching
* Automatic logging
* Automatic reporting on who changed what, when.
* Integrated mailing support
* Integrated data problem management

### Entities

Entities are defined in configuration under the `['sion_model']['entities']` key. You define a
mapping of the entity field names to database column names, tell the ORM what the table is called 
and how these entities are integrated into the router tree.

### Getting started

How to implement a Book database:

1. Extend the SionTable class, and create a ServiceFactory to build it.

2. Define your entity configuration in the `module.config.php` referencing the example file `sionmodel.global.php.dist` 
and properties from `SionModel\Entity\Entity`.

3. Implement the `processBookRow` function and register the row processor function in the config. 

4. Create a BookForm extending the `SionForm` class. 

5. Extend the SionController class

## Data problem management

With data problem management you create two classes to detect problems with a certain 
entity type. The user can choose to ignore a particular error. A pre-made GUI is 
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

## Coming soon

* Support entity-level ACL rules