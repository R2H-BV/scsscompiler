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

    /**
     * onBeforeRender event
     *
     * @return  void
     */
    public function onBeforeRender()
    {

        // Check if client is administrator or view is module.
        if (!$this->app->isClient('site')) {
            return;
        }

        /* INLINE CSS */
        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->app->getDocument()->getWebAssetManager();

        $script = <<<SCRIPT
        document.addEventListener("DOMContentLoaded", function() {
            (function() {
                // Create the button element
                const button = document.createElement('button');

                // Style the button to be in the top-right corner
                button.style.position = 'fixed';
                button.style.top = '10px';
                button.style.right = '10px';
                button.style.zIndex = '9999';
                button.style.padding = '5px 10px';
                button.style.backgroundColor = '#000';
                button.style.color = '#fff';
                button.style.border = 'none';
                button.style.borderRadius = '4px';
                button.style.cursor = 'pointer';
                button.style.fontSize = '12px';

                // Create a URL object from the current location
                const url = new URL(window.location.href);
                const params = url.searchParams;

                // Determine if compile is currently on
                const isCompileOn = params.get('compile') === '1';

                // Set the initial button text based on the compile parameter
                button.innerHTML = isCompileOn ? 'Stop SCSS compiler' : 'Run SCSS compiler';

                // Add click event to toggle compile parameter and reload the page
                button.addEventListener('click', function() {
                    if (params.get('compile') === '1') {
                        // Remove the compile parameter if it exists
                        params.delete('compile');
                    } else {
                        // Otherwise, add compile=1
                        params.set('compile', '1');
                    }
                    // Update the URL's search string and reload the page with the new URL
                    url.search = params.toString();
                    window.location.href = url.toString();
                });

                // Append the button to the body
                document.body.appendChild(button);
            })();
        });
        SCRIPT;

        $wa->addInlineScript($script, ['name' => 'scssloader']);

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
                    // change to your trigger element's ID if available
                    var trigger = document.getElementById('triggerButton');
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
            if (!is_file($file->scssFile)) {
                $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_FILE_ERROR', $file->scssFile);
            }

            if (!isset($file->scssFile) || empty($file->scssFile) || !is_file($file->scssFile)) {
                return;
            }

            if (!is_dir($file->cssFolder)) {
                $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_FOLDER_ERROR', $file->cssFolder);
            }

            if (!isset($file->cssFolder) || empty($file->cssFolder) || !is_dir($file->cssFolder)) {
                return;
            }

            // Compile the default CSS
            $this->compile($file->scssFile, $file->cssFolder, $this->params->get('sourceMap', 1), 0, $this->params->get('gzip', 1));

            $path_parts = pathinfo($file->scssFile);

            // Get the path to the output folder
            $outputFile = JPATH_ROOT . '/' . $file->cssFolder . '/' . $path_parts['filename'];

            // Delete files isn config is set to 0
            if (!$this->params->get('sourceMap', 1) && file_exists($outputFile . '.css.map')) {
                unlink($outputFile . '.css.map');
                $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.css.map');
            }

            // Delete files isn config is set to 0
            if (!$this->params->get('gzip', 1) && file_exists($outputFile . '.css.gz')) {
                unlink($outputFile . '.css.gz');
                $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.css.gz');
            }

            // Compile the minified CSS
            if ($this->params->get('minified', 1)) {
                $this->compile($file->scssFile, $file->cssFolder, $this->params->get('sourceMap', 1), 1, $this->params->get('gzip', 1));

                // Delete files isn config is set to 0
                if (!$this->params->get('sourceMap', 1) && file_exists($outputFile . '.min.css.map')) {
                    unlink($outputFile . '.min.css.map');
                    $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.min.css.map');
                }
                // Delete files isn config is set to 0
                if (!$this->params->get('gzip', 1) && file_exists($outputFile . '.min.css.gz')) {
                    unlink($outputFile . '.min.css.gz');
                    $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.min.css.gz');
                }
            } else {
                // Delete files isn config is set to 0
                if (file_exists($outputFile . '.min.css')) {
                    unlink($outputFile . '.min.css');
                    $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.min.css');
                }

                // Delete files isn config is set to 0
                if (file_exists($outputFile . '.min.css.map')) {
                    unlink($outputFile . '.min.css.map');
                    $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.min.css.map');
                }

                // Delete files isn config is set to 0
                if (file_exists($outputFile . '.min.css.gz')) {
                    unlink($outputFile . '.min.css.gz');
                    $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_TRASH', $outputFile . '.min.css.gz');
                }
            }
        }
    }

    /**
     * onAfterRender event
     *
     * @return  void
     */
    public function onAfterRender()
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

        $body = $this->app->getBody();

        if ($this->SuccessMessage && $this->params->get('showmodal', 1)) {
            $messageContainer = '
            <div
                class="modal fade text-dark"
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

        // Insert the extra HTML before the closing </body> tag
        $body = str_replace('</body>', $messageContainer . '</body>', $body);

        // Set the modified HTML back to the application
        $this->app->setBody($body);
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

        // Bepaal het protocol
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // Haal de host op (bijvoorbeeld "localhost")
        $hostname = $_SERVER['HTTP_HOST'];

        // Combineer protocol en host tot de server root
        $serverSourceRoot = $protocol . '://' . $hostname . '/';

        $compiler = new Compiler();

        try {
            $path_parts = pathinfo($inputFile);

            // Get the path to the output folder
            $outputDir = JPATH_ROOT . '/' . $outputDir;

            $compiler->setImportPaths($path_parts['dirname']);

            // Set CSS output style
            if ($mode) {
                $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);
                $extension = '.min.css';
            } else {
                $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::EXPANDED);
                $extension = '.css';
            }

            if ($sourceMap) {
                $compiler->setSourceMap(Compiler::SOURCE_MAP_FILE);

                $compiler->setSourceMapOptions([
                    // relative or full url to the above .map file
                    // (Added text to the CSS file at the end as: /*# sourceMappingURL=template.css.map */)
                    'sourceMapURL' => $path_parts['filename'] . $extension . '.map',

                    // (optional) relative or full url to the .css
                    // (Added text to the source map as: "file":"template.scss")
                    'sourceMapFilename' => $path_parts['basename'],

                    // partial path (server root) removed (normalized) to create a relative url
                    'sourceMapBasepath' => $serverRoot,

                    // (optional) prepended to 'source' field entries for relocating source files
                    'sourceRoot' => $serverSourceRoot,
                ]);
            } else {
                // Remove source map
            }

            $result = $compiler->compileString("@import \"{$path_parts['basename']}\";");

            file_put_contents($outputDir . '/' . $path_parts['filename'] . $extension, $result->getCss());

            $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG', $outputDir . '/' . $path_parts['filename'] . $extension);

            if ($sourceMap) {
                file_put_contents($outputDir . '/'
                    . $path_parts['filename'] . $extension . '.map', $result->getSourceMap());
                $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG', $outputDir . '/' . $path_parts['filename'] . $extension . '.map');
            }

            if ($gzip) {
                $gzipFile =  $this->gzcompressfile($outputDir . '/' . $path_parts['filename'] . $extension, $level = 9);

                $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG', $gzipFile);
            }
        } catch (\Exception $e) {
            $this->SuccessMessage .= Text::sprintf('PLG_SYSTEM_SCSSCOMPILER_MSG_ERROR', $inputFile . '(' . $e->getMessage() . ')');
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
