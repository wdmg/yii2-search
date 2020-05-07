[![Yii2](https://img.shields.io/badge/required-Yii2_v2.0.33-blue.svg)](https://packagist.org/packages/yiisoft/yii2)
[![Downloads](https://img.shields.io/packagist/dt/wdmg/yii2-search.svg)](https://packagist.org/packages/wdmg/yii2-search)
[![Packagist Version](https://img.shields.io/packagist/v/wdmg/yii2-search.svg)](https://packagist.org/packages/wdmg/yii2-search)
![Progress](https://img.shields.io/badge/progress-ready_to_use-green.svg)
[![GitHub license](https://img.shields.io/github/license/wdmg/yii2-search.svg)](https://github.com/wdmg/yii2-search/blob/master/LICENSE)

# Yii2 Search
Search module for Yii2.

The module implements indexed search using morphology (phpMorphy) or Porter's stemmer algorithm (LinguaStem). A live full-text search by data model is also provided.

# Requirements 
* PHP 5.6 or higher
* Yii2 v.2.0.33 and newest
* [Yii2 Base](https://github.com/wdmg/yii2-base) module (required)
* [phpMorphy](https://github.com/wdmg/phpmorphy) library
* [LinguaStem](https://github.com/wdmg/lingua-stem) library
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
        'search' => [ // list of supported models for live search or/and indexation
            'class' => 'wdmg\search\Module',
            'routePrefix' => 'admin',
            'supportModels' => [
               'news' => [
                   'class' => 'wdmg\news\models\News',
                   'indexing' => [
                       'on_insert' => true,
                       'on_update' => true,
                       'on_delete' => true
                   ],
                   'options' => [
                       'title' => 'title', // attr name (string) of model or function($model)
                       'url' => 'url', // attr name (string) of model or function($model)
                       'fields' => [
                           'title',
                           'keywords',
                           'description',
                           'content'
                       ],
                       'conditions' => [
                           'status' => 1
                       ]
                   ]
               ],
               ...
           ],
           'cacheExpire' = 86400, // live search cache lifetime, `0` - for not use cache
           'indexingOptions' = [ // indexation options
               'processing' => 'phpMorphy', //  Set `phpMorphy` or `LinguaStem`
               'language' => 'ru-RU', // Support 'ru-RU', 'uk-UA', 'de-DE'
               'analyze_by' => 'relevance',
               'max_execution_time' => 0, // max execution time in sec. for indexing process
               'memory_limit' => null, // max operating memory in Mb for indexing process
               'max_words' => 50,
           ],
           'analyzerOptions' = [ // text analyzer options, see \wdmg\helpers\TextAnalyzer
               'min_length' => 3,
               'stop_words' => [],
               'weights' => []
           ],
           'snippetOptions' = [ // build search snippet options
               'max_words_before' => 6,
               'max_words_after' => 4,
               'bolder_tag' => 'strong',
               'max_length' => 255,
               'delimiter' => '…'
           ],
           'searchAccuracy' = 90, // search accuracy
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

# Status and version [ready to use]
* v.1.1.0 - Added LinguaStem() (support for Porter Stemmer)
* v.1.0.10 - Update README.md and dependencies
* v.1.0.9 - Checking module is loaded
* v.1.0.8 - Up to date dependencies, bugfix migrations
* v.1.0.7 - Added support for Blog module
* v.1.0.6 - Added no caching parameter for search method
* v.1.0.5 - Added ignored patterns, URL for snippets as closure object