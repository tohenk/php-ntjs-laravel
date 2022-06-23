<?php

/*
 * The MIT License
 *
 * Copyright (c) 2022 Toha <tohenk@yahoo.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
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
 */

namespace NTLAB\JS\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

use NTLAB\JS\Laravel\View\Components\Ntjs;

class Provider extends ServiceProvider
{
    const SET_RE = '#@var\((?<q>\'|")(.*?)\k{q}\,\s?(.*)\)#';
    const SET_REPL = '<?php $$2 = $3; ?>';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Factory::class, function($app) {
            return new Factory($app->make(Request::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // register views resources path
        View::addNamespace('ntjs', __DIR__.'/../resources/views');
        // register components
        Blade::component('ntjs', Ntjs::class);
        // register directives
        $factoryClass = Factory::class;
        Blade::directive('css', function($expression) use ($factoryClass) {
            return <<<EOF
<?php app()->make('$factoryClass')->useStylesheet($expression); ?>
EOF;
        });
        Blade::directive('js', function($expression) use ($factoryClass) {
            return <<<EOF
<?php app()->make('$factoryClass')->useJavascript($expression); ?>
EOF;
        });
        Blade::directive('ntjs', function($expression) use ($factoryClass) {
            return <<<EOF
<?php app()->make('$factoryClass')->useScript($expression); ?>
EOF;
        });
        // https://github.com/alexdover/blade-set
        Blade::extend(function($expression) {
            return preg_replace(static::SET_RE, static::SET_REPL, $expression);
        });
    }
}