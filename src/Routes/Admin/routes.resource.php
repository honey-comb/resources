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

Route::domain(config('hc.admin_domain'))
    ->prefix(config('hc.admin_url'))
    ->namespace('Admin')
    ->middleware(['web', 'auth'])
    ->group(function () {

        Route::get('resource', 'HCResourceController@index')
            ->name('admin.resource.index')
            ->middleware('acl:honey_comb_resources_resource_list');

        Route::prefix('api/resource')->group(function () {

            Route::get('/', 'HCResourceController@getListPaginate')
                ->name('admin.api.resource')
                ->middleware('acl:honey_comb_resources_resource_list');

            Route::get('options', 'HCResourceController@getOptions')
                ->name('admin.api.resource.options');

            Route::delete('/', 'HCResourceController@deleteSoft')
                ->name('admin.api.resource.delete')
                ->middleware('acl:honey_comb_resources_resource_delete');

            Route::delete('force', 'HCResourceController@deleteForce')
                ->name('admin.api.resource.delete.force')
                ->middleware('acl:honey_comb_resources_resource_delete_force');

            Route::post('restore', 'HCResourceController@restore')
                ->name('admin.api.resource.restore')
                ->middleware('acl:honey_comb_resources_resource_restore');


            Route::prefix('{id}')->group(function () {

                Route::get('/', 'HCResourceController@getById')
                    ->name('admin.api.resource.single')
                    ->middleware('acl:honey_comb_resources_resource_list');

                Route::put('/', 'HCResourceController@update')
                    ->name('admin.api.resource.update')
                    ->middleware('acl:honey_comb_resources_resource_update');

            });
        });
    });
