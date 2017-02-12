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

Steps to use:

* Extend `SionModel\Problem\EntityProblem` for each of the entities you wish to detect problems.
In the `__construct` function make sure to call `addEntitySpecifications` and 
`addProblemSpecifications`.
* Implement the `ProblemProviderInterface`. 
* Register the implemented `ProblemProviderInterface` in the config under the 
`problem_providers` key.
```
    'sion_model' => [
	'problem_providers' => [
	    'Patres\Problem\PersonProblemProvider',
	],
    ],
```

## Coming soon

* Integrated mailing support
* Integrated data problem management (display data errors and warnings to admins through a GUI)
* Support entity-level ACL rules
