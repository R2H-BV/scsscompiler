<?php

/**
 * @package    scssCompiler
 * @subpackage System.scssCompiler
 * @author     R2H BV
 * @license    GNU/GPL
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/*
 *  Update de SCSSCompiler :
 *
 */
class plgSystemScssCompilerInstallerScript {

    /**
     * Method to run before an install/update/uninstall method
     * $parent is the class calling this method
     * $type is the type of change (install, update or discover_install)
     *
     * @return void
     */
    public function preflight($type, $parent) {
        // supprimer tous les fichiers de scssphp pour éviter conflits
        $path = JPATH_ROOT . '/plugins/system/scsscompiler/vendor/';
		if (file_exists($path)) {
			$dir_iterator = new RecursiveDirectoryIterator($path);
			$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::CHILD_FIRST);

			foreach ($iterator as $file) {
				if (is_file($file) === true) {
					unlink($file);
				} elseif (substr($file,-1,1)!='.') {
					rmdir($file);
				}
			}
			rmdir($path);
		}
	}
}
