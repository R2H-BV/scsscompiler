<?php

/**
 * @package    scssCompiler
 * @subpackage System.scssCompiler
 * @author     R2H BV
 * @license    GNU/GPL
 */

use ScssPhp\ScssPhp\Compiler;
use Joomla\CMS\Plugin\CMSPlugin;
use \Joomla\CMS\Uri\Uri;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * System Plugin.
 *
 * @package    scssCompiler
 * @subpackage Plugin
 */
class plgSystemscssCompiler extends CMSPlugin {

    /**
     * Application object.
     *
     * @var    CMSApplicationInterface
     * @since  4.1.0
     */
    protected $app;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     * @since  4.1.0
     */
    protected $autoloadLanguage = true;

    public function onBeforeRender() {

        // Check if client is administrator or view is module.
        if ($this->app->isClient('administrator')) {
            return;
        }

        // Check for GET parameter
        $input = $this->app->input;
        $getCompile = $input->get('compile', '', 'string');

        // Return if URL not contains: ?compile=1
        if ($getCompile <> 1) {
            return;
        }

        $serverRoot             = $_SERVER['DOCUMENT_ROOT'];
        $serverPathFull         = str_replace('\\', '/', JPATH_ROOT); // no trailing /
        $serverSourceRoot       = str_replace($serverRoot, '', $serverPathFull);
        $source_map             = $this->params->get('source_map', true);
        $scssFiles              = $this->params->get('scssFiles', '');

        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            require __DIR__ . '/vendor/autoload.php';
        }

        foreach ($scssFiles as $file) {

            if (!isset($file->scssFile) || empty($file->scssFile) || !File::exists($file->scssFile)) {
                return;
            }

            if (!isset($file->cssFolder) || empty($file->cssFolder) || !Folder::exists($file->cssFolder)) {
                return;
            }

            $compiler = new Compiler();

            try {

                // Get file info from SCSS file
                //    "dirname" => "media/templates/site/cassiopeia_rbs5/scss"
                //    "basename" => "template.scss"
                //    "extension" => "scss"
                //    "filename" => "template"
                $path_parts = pathinfo($file->scssFile);

                $compiler->setImportPaths($path_parts['dirname']);

                // Set CSS output style
                $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);
                //$compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::EXPANDED);

                $compiler->setSourceMap(Compiler::SOURCE_MAP_FILE);

                $compiler->setSourceMapOptions([
                    // relative or full url to the above .map file (Added text to the CSS file at the end as: /*# sourceMappingURL=template.css.map */)
                    'sourceMapURL' => $path_parts['filename'] . '.css.map',

                    // (optional) relative or full url to the .css (Added text to the source map as: "file":"template.scss")
                    'sourceMapFilename' => $path_parts['basename'],

                    // partial path (server root) removed (normalized) to create a relative url
                    'sourceMapBasepath' => $serverRoot,

                    // (optional) prepended to 'source' field entries for relocating source files
                    'sourceRoot' => $serverSourceRoot,
                ]);

                $result = $compiler->compileString("@import \"{$path_parts['basename']}\";");

                file_put_contents($file->cssFolder . '/'. $path_parts['filename'] . '.css.map', $result->getSourceMap());
                file_put_contents($file->cssFolder . '/'. $path_parts['filename'] . '.css', $result->getCss());

            } catch (\Exception $e) {
                //syslog(LOG_ERR, 'scssphp: Unable to compile content');
                echo '
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Caught exception:</strong> '. $e->getMessage() . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            }
        }
    }
}
