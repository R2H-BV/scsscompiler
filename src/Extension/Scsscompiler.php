<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.loadmodule
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Scsscompiler\Extension;

use ScssPhp\ScssPhp\Compiler;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Plugin to compile SCSS to CSS
 *
 * @package    scssCompiler
 * @subpackage Plugin
 */
final class Scsscompiler extends CMSPlugin
{
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

    // Set the variable to hold the messages
    protected $SuccessMessage = '';

    public function onBeforeRender()
    {

        // Check if client is administrator or view is module.
        if (!$this->app->isClient('site')) {
            return;
        }

        // Check for GET parameter
        $input = $this->app->input;
        $getCompile = $input->get('compile', '', 'string');

        // Return if URL not contains: ?compile=1
        if ($getCompile <> 1) {
            return;
        }

        // Load the SCSS files
        $scssFiles = $this->params->get('scssFiles', '');

        if ($this->params->get('showmodal', 1)) {
            $modalTimeout = $this->params->get('modal_timeout', 3000);

            HTMLHelper::_('bootstrap.modal', '.selector', []);

            /* INLINE CSS */
            /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
            $wa = $this->app->getDocument()->getWebAssetManager();

            $script = <<<SCRIPT
            document.addEventListener("DOMContentLoaded", function() {
            var modalElement = document.getElementById('successModal');
            var modalInstance = new bootstrap.Modal(modalElement, {
                backdrop: true,  // Allows closing when clicking outside
                keyboard: true   // Allows closing with the ESC key
            });

            // Open the modal immediately
            modalInstance.show();

            // When closing the modal (e.g., clicking the close button), remove focus from any element inside it
            modalElement.addEventListener('hide.bs.modal', function() {
                if(document.activeElement && modalElement.contains(document.activeElement)){
                document.activeElement.blur();
                }
            });

            // Optionally, after the modal is hidden, move focus to a safe element (e.g., the element that triggered it)
            modalElement.addEventListener('hidden.bs.modal', function () {
                var trigger = document.getElementById('triggerButton'); // change to your trigger element's ID if available
                if (trigger) {
                trigger.focus();
                }
            });

            // Automatically close the modal after 3 seconds
            setTimeout(function(){
                modalInstance.hide();
            }, $modalTimeout);
            });
            SCRIPT;

            $wa->addInlineScript($script, ['name' => 'scsscompiler']);
        }

        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            require dirname(__DIR__, 1) . '/vendor/autoload.php';
        }

        foreach ($scssFiles as $file) {
            if (!isset($file->scssFile) || empty($file->scssFile) || !is_file($file->scssFile)) {
                return;
            }

            if (!isset($file->cssFolder) || empty($file->cssFolder) || !is_dir($file->cssFolder)) {
                return;
            }

            // Compile the default CSS
            $this->compile($file->scssFile, $file->cssFolder, $file->sourceMap, 0, $file->gzip);

            // Compile the Minified CSS
            if ($file->minified) {
                $this->compile($file->scssFile, $file->cssFolder, $file->sourceMap, $file->minified, $file->gzip);
            }
        }

        if ($this->SuccessMessage && $this->params->get('showmodal', 1)) {
            echo '
            <div
                class="modal fade"
                id="successModal" tabindex="-1"
                aria-labelledby="successModalLabel"
                aria-hidden="true"
                style="display: none;">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <!-- Header with title and close button -->
                    <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">' . Text::_('JCLOSE') . '</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <!-- Modal body with success alert -->
                    <div class="modal-body">
                        ' . $this->SuccessMessage . '

                    </div>
                </div>
                </div>
            </div>';
        }
    }

    /**
     * Compile the file to the given location with a mode
     * @param    String    $inputFile
     * @param    String    $outputDir
     * @param    Integer   sourceMap
     * @param    Integer   $mode
     * @return   bool
     */
    private function compile(string $inputFile, string $outputDir, bool $sourceMap, bool $mode, bool $gzip): bool
    {
        $serverRoot             = $_SERVER['DOCUMENT_ROOT'];
        $serverPathFull         = str_replace('\\', '/', JPATH_ROOT); // no trailing /
        $serverSourceRoot       = str_replace($serverRoot, '', $serverPathFull);

        if (empty($serverSourceRoot)) {
            $serverSourceRoot = '/';
        }

        $compiler = new Compiler();

        try {
            $path_parts = pathinfo($inputFile);
            $extension = '.css';

            $compiler->setImportPaths($path_parts['dirname']);

            // Set CSS output style
            if ($mode) {
                $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);
                $extension = '.min.css';
            } else {
                $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::EXPANDED);
            }

            if ($sourceMap) {
                $compiler->setSourceMap(Compiler::SOURCE_MAP_FILE);

                $compiler->setSourceMapOptions([
                    // relative or full url to the above .map file (Added text to the CSS file at the end as: /*# sourceMappingURL=template.css.map */)
                    'sourceMapURL' => $path_parts['filename'] . $extension . '.map',

                    // (optional) relative or full url to the .css (Added text to the source map as: "file":"template.scss")
                    'sourceMapFilename' => $path_parts['basename'],

                    // partial path (server root) removed (normalized) to create a relative url
                    'sourceMapBasepath' => $serverRoot,

                    // (optional) prepended to 'source' field entries for relocating source files
                    'sourceRoot' => $serverSourceRoot,
                ]);
            }

            $result = $compiler->compileString("@import \"{$path_parts['basename']}\";");

            file_put_contents($outputDir . '/' . $path_parts['filename'] . $extension, $result->getCss());

            $textMsg = Text::_('PLG_SYSTEM_SCSSCOMPILER_MSG');

            $this->SuccessMessage .= $textMsg . $outputDir . '/' . $path_parts['filename'] . $extension . '<br>';

            if ($sourceMap) {
                file_put_contents($outputDir . '/' . $path_parts['filename'] . $extension . '.map', $result->getSourceMap());
                $this->SuccessMessage .= $textMsg . $outputDir . '/' . $path_parts['filename'] . $extension . '.map<br>';
            }

            if ($gzip) {
                $gzipFile =  $this->gzcompressfile($outputDir . '/' . $path_parts['filename'] . $extension, $level = 9);

                $this->SuccessMessage .= $textMsg . $gzipFile . '<br>';
            }
        } catch (\Exception $e) {
            $this->SuccessMessage .= Text::_('PLG_SYSTEM_SCSSCOMPILER_MSG_ERROR') . '<strong>' . $inputFile . '</strong>' . ' (' . $e->getMessage() . ')<br>';
        }

        return true;
    }

    /**
     * Compress a file using gzip
     *
     * @param string $inFilename Input filename
     * @param int    $level      Compression level (default: 9)
     *
     * @throws Exception if the input or output file can not be opened
     *
     * @return string Output filename
     */
    private function gzcompressfile(string $inFilename, int $level = 9): string
    {
        // Open input file
        $inFile = fopen($inFilename, "rb");
        if ($inFile === false) {
            throw new \Exception("Unable to open input file: $inFilename");
        }

        // Open output file
        $gzFilename = $inFilename . ".gz";
        $mode = "wb" . $level;
        $gzFile = gzopen($gzFilename, $mode);
        if ($gzFile === false) {
            fclose($inFile);
            throw new \Exception("Unable to open output file: $gzFilename");
        }

        // Stream copy
        $length = 512 * 1024; // 512 kB
        while (!feof($inFile)) {
            gzwrite($gzFile, fread($inFile, $length));
        }

        // Close files
        fclose($inFile);
        gzclose($gzFile);

        // Return the new filename
        return $gzFilename;
    }
}
