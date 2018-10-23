# honeycomb-resources [![Build Status](https://travis-ci.com/honey-comb/resources.svg?branch=master)](https://travis-ci.com/honey-comb/resources)  
https://github.com/honey-comb/resources

## Description

HoneyComb CMS resources functions

## Attention

This is part resources package for HoneyComb CMS package.

If you want to use laravel version 5.5.* [use resources package version 0.2.*](https://github.com/honey-comb/resources/tree/5.5 "Resources package version 0.2.*")

## Requirement

 - php: `^7.1`
 - laravel: `^5.6`
 - composer
 
 ## Installation

Begin by installing this package through Composer.


```js
	{
	    "require": {
	        "honey-comb/resources": "*"
	    }
	}
```
or
```js
    composer require honey-comb/resources
```

## File not found
 
Run command ```php artisan vendor:publish --tag=hc-resources-assets``` to publish icons for files that can't be found in disk

## Preview Thumbnails
Run command ```php artisan storage:link``` to make preview thumbnails publicly accessible