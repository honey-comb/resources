<?php
/**
 * @copyright 2019 innovationbase
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Contact InnovationBase:
 * E-mail: hello@innovationbase.eu
 * https://innovationbase.eu
 */

declare(strict_types = 1);

Route::prefix(config('hc.admin_url'))
    ->namespace('Admin')
    ->middleware(['web', 'auth'])
    ->group(function () {

        Route::get('resource/thumb', 'HCResourceThumbController@index')
            ->name('admin.resource.thumb.index')
            ->middleware('acl:honey_comb_resources_resource_thumb_admin_list');

        Route::prefix('api/resource/thumb')->group(function () {

            Route::get('/', 'HCResourceThumbController@getListPaginate')
                ->name('admin.api.resource.thumb')
                ->middleware('acl:honey_comb_resources_resource_thumb_admin_list');

            Route::get('list', 'HCResourceThumbController@getList')
                ->name('admin.api.resource.thumb.list')
                ->middleware('acl:honey_comb_resources_resource_thumb_admin_list');

            Route::get('options', 'HCResourceThumbController@getOptions')
                ->name('admin.api.resource.thumb.list');

            Route::prefix('{id}')->group(function () {

                Route::get('/', 'HCResourceThumbController@getById')
                    ->name('admin.api.resource.thumb.single')
                    ->middleware('acl:honey_comb_resources_resource_thumb_admin_list');

                Route::put('/', 'HCResourceThumbController@update')
                    ->name('admin.api.resource.thumb.update')
                    ->middleware('acl:honey_comb_resources_resource_thumb_admin_update');

                Route::patch('/', 'HCResourceThumbController@patch')
                    ->name('admin.api.resource.thumb.patch')
                    ->middleware('acl:honey_comb_resources_resource_thumb_admin_update');
            });
        });

    });
