[![Yii2](https://img.shields.io/badge/required-Yii2_v2.0.20-blue.svg)](https://packagist.org/packages/yiisoft/yii2)
[![Github all releases](https://img.shields.io/github/downloads/wdmg/yii2-search/total.svg)](https://GitHub.com/wdmg/yii2-search/releases/)
![Progress](https://img.shields.io/badge/progress-in_development-red.svg)
[![GitHub license](https://img.shields.io/github/license/wdmg/yii2-search.svg)](https://github.com/wdmg/yii2-search/blob/master/LICENSE)
![GitHub release](https://img.shields.io/github/release/wdmg/yii2-search/all.svg)

# Yii2 Search
Search module for Yii2.

The module implements indexed search using morphology (phpMorphy) or Porter's stemmer algorithm (LinguaStem). A live full-text search by data model is also provided.

# Requirements 
* PHP 5.6 or higher
* Yii2 v.2.0.20 and newest
* [Yii2 Base](https://github.com/wdmg/yii2-base) module (required)
* [phpMorphy](https://github.com/wdmg/phpmorphy) library
* [Yii2 Helpers](https://github.com/wdmg/yii2-helpers)

# Installation
To install the module, run the following command in the console:

`$ composer require "wdmg/yii2-search"`

After configure db connection, run the following command in the console:

`$ php yii search/init`

And select the operation you want to perform:
  1) Apply all module migrations
  2) Revert all module migrations

# Migrations
In any case, you can execute the migration and create the initial data, run the following command in the console:

`$ php yii migrate --migrationPath=@vendor/wdmg/yii2-search/migrations`

# Configure
To add a module to the project, add the following data in your configuration file:

    'modules' => [
        ...
        'search' => [
            'class' => 'wdmg\search\Module',
            'routePrefix' => 'admin',
        ],
        ...
    ],


# Routing
Use the `Module::dashboardNavItems()` method of the module to generate a navigation items list for NavBar, like this:

    <?php
        echo Nav::widget([
        'options' => ['class' => 'navbar-nav navbar-right'],
            'label' => 'Modules',
            'items' => [
                Yii::$app->getModule('search')->dashboardNavItems(),
                ...
            ]
        ]);
    ?>

# Status and version [in progress development]
* v.1.0.0 - Added search models