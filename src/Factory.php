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

use NTLAB\JS\Backend as Base;
use NTLAB\JS\DependencyResolverInterface;
use NTLAB\JS\Manager;
use NTLAB\JS\Script;
use NTLAB\JS\Util\Asset;
use NTLAB\JS\Util\Loader;

use Illuminate\Http\Request;

class Factory extends Base implements DependencyResolverInterface
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Loader
     */
    protected $loader;

    /**
     * @var bool
     */
    protected $useCDN = false;

    /**
     * @var bool
     */
    protected $debugScript = true;

    /**
     * @var string
     */
    protected $script = null;

    /**
     * @var array
     */
    protected $js = [
        'first' => [],
        'default' => [],
    ];

    /**
     * @var array
     */
    protected $css = [
        'first' => [],
        'default' => [],
    ];

    /**
     * Constructor.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->loader = new Loader();
        $this->initialize();
    }

    protected function initialize()
    {
        $manager = Manager::getInstance();
        // set script backend
        $manager->setBackend($this);
        // register script resolver
        $manager->addResolver($this);
        // set script debug information
        if ($this->debugScript) {
            Script::setDebug(true);
        }
        if ($this->useCDN) {
            $manager = Manager::getInstance();
            $manager->parseCdn(json_decode(file_get_contents($manager->getCdnInfoFile()), true));
        }
        // initialize Bootstrap
        // @todo: move it somewhere
        Script::create('JQuery')
            ->includeDependency(['Bootstrap', 'BootstrapIcons'])
            ->includeScript();
    }

    /**
     * {@inheritDoc}
     * @see \NTLAB\JS\Backend::trans()
     */
    public function trans($text, $vars = [], $domain = null)
    {
        return __($text, $vars);
    }

    /**
     * {@inheritDoc}
     * @see \NTLAB\JS\Backend::url()
     */
    public function url($url, $options = [])
    {
        return route($url, $options);
    }

    /**
     * {@inheritDoc}
     * @see \NTLAB\JS\Backend::addAsset()
     */
    public function addAsset($asset, $type = self::ASSET_JS, $priority = self::ASSET_PRIORITY_DEFAULT, $attributes = null)
    {
        switch ($type) {
            case static::ASSET_JS:
                if (!in_array($asset, array_merge($this->js['first'], $this->js['default']))) {
                    if ($priority === static::ASSET_PRIORITY_FIRST) {
                        $this->js['first'][] = $asset;
                    } else {
                        $this->js['default'][] = $asset;
                    }
                }
                break;
            case static::ASSET_CSS:
                if (!in_array($asset, array_merge($this->css['first'], $this->css['default']))) {
                    if ($priority === static::ASSET_PRIORITY_FIRST) {
                        $this->css['first'][] = $asset;
                    } else {
                        $this->css['default'][] = $asset;
                    }
                }
                break;
        }
    }

    /**
     * {@inheritDoc}
     * @see \NTLAB\JS\Backend::generateAsset()
     */
    public function generateAsset(Asset $asset, $name, $type = self::ASSET_JS)
    {
        $dir = $this->request->getBaseUrl();
        $dir = $this->addSlash($dir, '/cdn');
        if ($repo = $asset->getRepository()) {
            $dir = $this->addSlash($dir, '/'.$repo);
        }
        if (strlen($assetDir = (string) $asset->getDirName($type))) {
            $dir = $this->addSlash($dir, '/'.$assetDir);
        }
        return $this->addSlash($dir, '/'.$name);
    }

    protected function addSlash(...$dirs)
    {
        $result = null;
        foreach ($dirs as $dir) {
            if (null === $result) {
                $result = $dir;
            } else {
                if (substr($result, -1) !== '/') {
                    if (substr($dir, 0, 1) !== '/') {
                        $result .= '/';
                    }
                } else {
                    if (substr($dir, 0, 1) === '/') {
                        $dir = substr($dir, 1);
                    }
                }
                $result .= $dir;
            }
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     * @see \NTLAB\JS\DependencyResolverInterface::resolve()
     */
    public function resolve($dep)
    {
        return sprintf('App\\Script\\%s', str_replace('.', '\\', $dep));
    }

    /**
     * Create script helper.
     *
     * @param string $scriptName
     * @return \NTLAB\JS\Script
     */
    public function createScript($scriptName)
    {
        return Script::create($scriptName);
    }

    public function useStylesheet($stylesheet)
    {
        $this->addAsset($stylesheet, static::ASSET_CSS);
    }

    public function useJavascript($javascript)
    {
        $this->addAsset($javascript, static::ASSET_JS);
    }

    public function getStylesheets()
    {
        return array_merge($this->css['first'], $this->css['default']);
    }

    public function getJavascripts()
    {
        return array_merge($this->js['first'], $this->js['default']);
    }

    public function getScript()
    {
        if (null === $this->script) {
            $this->script = Manager::getInstance()->getScript(true);
        }
        return $this->script;
    }

    public function useScript($name, $content = null, $dependencies = [])
    {
        if (is_array($content)) {
            $xcontent = $dependencies;
            $dependencies = $content;
            $content = $xcontent;
        }
        $script = Script::create($name)
            ->includeScript();
        if (count($dependencies)) {
            $script->includeDependency($dependencies);
        }
        if ($content) {
            $script->add($content);
        }
    }

    public function getScriptAutoload()
    {
        foreach ($this->getStylesheets() as $css) {
            $this->loader->addStylesheet($css);
        }
        foreach ($this->getJavascripts() as $js) {
            $this->loader->addJavascript($js);
        }
        return $this->loader->autoload();
    }
}