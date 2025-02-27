<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.loadmodule
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Scsscompiler\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\PluginHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Plugin to ...
 * This uses the ...
 *
 * @since  1.5
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
     * Listener for the `onAfterInitialise` event
     *
     * @return  void
     *
     * @since   1.0
     */
    public function onAfterInitialise()
    {
        // Do something onAfterInitialise
        $app = Factory::getApplication();

        // Check if administrator of site
        if ($app->getName() == 'administrator') {
            echo 'Client is administrator';
        }

        // Check if administrator of site
        if ($app->getName() == 'site') {
            echo 'Client is site';
        }

        dump($this);
    }

    public function __construct($subject, $config)
    {
        // Calling the parent Constructor
        parent::__construct($subject, $config);

        // Do some extra initialisation in this constructor if required
    }

    /**
     * Has the user has access to see and alter the fieldsets.
     *
     * @return boolean
     */
    protected function hasAccessToForm(): bool
    {
        // get the user object
        $user = Factory::getApplication()->getIdentity();

        // Get the plugin settings
        $groups = $this->params->get('usergroup', []);

        // get the user object
        $user = Factory::getApplication()->getIdentity();

        // Check if user is Super Admin
        $isAdmin = (bool) Factory::getApplication()->getIdentity()->authorise('core.admin');

        // Check is the user has right to view
        $hasRightToView = $this->checkViewingRights($groups, $user->groups);

        return $isAdmin || $hasRightToView;
    }

    public function onBeforeRender()
    {
        $app = Factory::getApplication();

        // Ensure we only load assets in the site frontend
        if ($app->isClient('site')) {

            // Just an alert to check if media folder is present for the module in media/module-name
            if (is_dir('media/plg_system_scsscompiler')) {
                echo 'Media folder exists';

                /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
                $wa = $app->getDocument()->getWebAssetManager();

                $wa->registerAndUseStyle('plg_system_scsscompiler.style', 'plg_system_scsscompiler/style.css', [], []);
                $wa->registerAndUseScript('plg_system_scsscompiler.script', 'plg_system_scsscompiler/script.js', [], ['type' => 'module']);
            } else {
                echo 'Mediafolder does not exist so media is loaded from plugin root folder.';
                // Copy the module media folder to the main media folder:
                // media/plg_system_scsscompiler/css/style.css
                // media/plg_system_scsscompiler/js/script.js

                /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
                $wa = $app->getDocument()->getWebAssetManager();

                $wa->registerAndUseStyle('plg_system_scsscompiler.style', 'plugins/system/scsscompiler/media/css/style.css');
                $wa->registerAndUseScript('plg_system_scsscompiler.script', 'plugins/system/scsscompiler/media/js/script.js');
            }
        }
    }

    /**
     * Check is the user has rights to access
     *
     * @param  array $accessGroups The Selected Access Groups.
     * @param  array $userGroups   The User Object Access Groups.
     * @return boolean
     */
    private function checkViewingRights($accessGroups, $userGroups): bool
    {
        return count(array_intersect($accessGroups, $userGroups)) !== 0;
    }

    /**
     * Plugin that loads module positions within content
     *
     * @param   string   $context   The context of the content being passed to the plugin.
     * @param   object   &$article  The article object.  Note $article->text is also available
     * @param   mixed    &$params   The article params
     * @param   integer  $page      The 'page' number
     *
     * @return  void
     *
     * @since   1.6
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {

        $app = $this->getApplication();
    }

    /**
     * onContentPrepareForm
     *
     * @param  Form  $form The form reference.
     * @param  mixed $data Dataset.
     * @return void
     */
    public function onContentPrepareForm(Form $form, $data): void
    {
        // $app = Factory::getApplication();

        Form::addFormPath(dirname(__DIR__, 2) . '/forms');

        // Detect module type: mod_***_***
        /*
        if(isset($data->module)) {
            $moduleType = $data->module;
        }
        */

        /*
        // Add the fieldset for the modules.
        if ($form->getName() === 'com_plugins.plugin') {
            $form->loadFile('extrafields', true);
        }
        */

        /*
        // Add the fieldset for the modules.
        if (
            $form->getName() === 'com_modules.module' ||
            $form->getName() === 'com_advancedmodules.module' ||
            $form->getName() === 'com_config.modules'
        ) {
            $form->loadFile('extrafields', true);
        }
        */

        /*
        // Check is we are in a menu item.
        if ($form->getName() === 'com_menus.item') {
            $form->loadFile('extrafields', true);
        }
        */

        /*
        // Add the fieldset for the template.
        if ($form->getName() === 'com_templates.style') {
            $form->loadFile('extrafields', true);
        }
        */
    }
}
