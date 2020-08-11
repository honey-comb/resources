<?php

Route::prefix('api/resources')
    ->middleware('api')
    ->namespace('\\HoneyComb\\Resources\\Http\\Controllers')
    ->group(function () {

        Route::get('/', 'HCResourceController@getListPaginate');
        Route::delete('/', 'HCResourceController@deleteSoft');
        Route::post('/upload', 'HCResourceController@store');
    });

Route::prefix('resource')
    ->middleware('web')
    ->namespace('\\HoneyComb\\Resources\\Http\\Controllers\\Frontend')
    ->group(function () {
        Route::get('/{id}/rotated/{transponse}', 'HCResourceController@rotated')->name('rotated.video');
    });

Route::prefix('resource')
    ->middleware('web')
    ->namespace('\\HoneyComb\\Resources\\Http\\Controllers\\Frontend')
    ->group(function () {
        Route::get('/{id}/{width?}/{height?}', 'HCResourceController@show')->name('resource.get');
    });
