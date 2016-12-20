# Sion Model

Some support database, filter and form classes for Sion projects.

## Installation

```bash
./composer.phar require jroedel/zf2-juser
```

## Features

* Simple array-based ORM
* Support for user suggestions upon the entities, required posterior moderation
* Automatic reporting on who changed what, when.

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