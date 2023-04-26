<?php

/**
 * @package    scssCompiler
 * @subpackage System.scssCompiler
 * @author     R2H BV
 * @license    GNU/GPL
 */

use ScssPhp\ScssPhp\Compiler;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

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

    protected $SuccessMessage = '';

    public function onBeforeRender() {

        // Check if client is administrator or view is module.
        if (!$this->app->isClient('site')) {
            return;
        }

        /* INLINE CSS */
        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->app->getDocument()->getWebAssetManager();

        $style = <<<CSS
        dialog.scss-dialog {
            margin: 2rem auto;
            border: none !important;
            border-radius: 1rem;
            box-shadow: 0 0 #0000, 0 0 #0000, 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 1.5rem;
            width: 800px;
            max-width: 95%;
            z-index: 10000;
            overflow-wrap: break-word;
        }
        CSS;
        $wa->addInlineStyle($style, ['name' => 'scsscompiler']);

        // Check for GET parameter
        $input = $this->app->input;
        $getCompile = $input->get('compile', '', 'string');

        // Return if URL not contains: ?compile=1
        if ($getCompile <> 1) {
            return;
        }

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

            // Compile the CSS
            $this->compile($file->scssFile, $file->cssFolder, $file->sourceMap, $file->minified, $file->gzip);

        }

        if ($this->SuccessMessage) {
            echo '
            <dialog class="scss-dialog mw-75" open>
            <div class="alert alert-warning" role="alert">
            '. $this->SuccessMessage . '
            </div>
            <form method="dialog">
            <button class="btn btn-primary">' . Text::_('JCLOSE') . '</button>
            </form>
            </dialog>';
        }
    }

    /**
	 * Compile the file to the given location with a mode
	 * @param 	String 	$inputFile
	 * @param 	String 	$outputDir
     * @param 	Integer sourceMap
	 * @param 	Integer $mode
	 * @return 	bool
	 */
	private function compile(string $inputFile, string $outputDir, bool $sourceMap, bool $mode, bool $gzip): bool
	{
        $serverRoot             = $_SERVER['DOCUMENT_ROOT'];
        $serverPathFull         = str_replace('\\', '/', JPATH_ROOT); // no trailing /
        $serverSourceRoot       = str_replace($serverRoot, '', $serverPathFull);

        if (empty($serverSourceRoot)) {
            $serverSourceRoot = '/';
        }

        $compiler               = new Compiler();

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

            file_put_contents($outputDir . '/'. $path_parts['filename'] . $extension, $result->getCss());

            $textMsg = Text::_('PLG_SYSTEM_SCSSCOMPILER_MSG');

            $this->SuccessMessage .= $textMsg . $outputDir . '/'. $path_parts['filename'] . $extension . '<br>';

            if ($sourceMap) {
                file_put_contents($outputDir . '/'. $path_parts['filename'] . $extension .'.map', $result->getSourceMap());
                $this->SuccessMessage .= $textMsg . $outputDir . '/'. $path_parts['filename'] . $extension . '.map<br>';
            }

            if ($gzip) {
                $gzipFile =  $this->gzcompressfile($outputDir . '/'. $path_parts['filename'] . $extension, $level = 9);

                $this->SuccessMessage .= $textMsg . $gzipFile . '<br>';
            }

        } catch (\Exception $e) {
            $this->SuccessMessage .= Text::_('PLG_SYSTEM_SCSSCOMPILER_MSG_ERROR') . $e->getMessage() . '<br>';
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
        $gzFilename = $inFilename.".gz";
        $mode = "wb".$level;
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
