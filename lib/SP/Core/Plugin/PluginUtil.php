<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Core\Plugin;

use ReflectionClass;
use SP\Core\DiFactory;
use SP\Core\Exceptions\SPException;
use SP\Core\SessionFactory;
use SP\DataModel\PluginData;
use SP\Log\Log;
use SP\Mgmt\Plugins\Plugin;

/**
 * Class PluginUtil
 *
 * @package SP\Core\Plugin
 */
class PluginUtil
{
    /**
     * @var array Plugins habilitados
     */
    private static $enabledPlugins;
    /**
     * @var PluginInterface[] Plugins ya cargados
     */
    private static $loadedPlugins = [];
    /**
     * @var array Plugins deshabilitados
     */
    private static $disabledPlugins = [];

    /**
     * Devuelve la lista de Plugins disponibles
     *
     * @return array
     */
    public static function getPlugins()
    {
        $pluginDirH = opendir(PLUGINS_PATH);
        $plugins = [];

        if ($pluginDirH) {
            while (false !== ($entry = readdir($pluginDirH))) {
                $pluginDir = PLUGINS_PATH . DIRECTORY_SEPARATOR . $entry;

                if (strpos($entry, '.') === false
                    && is_dir($pluginDir)
                    && file_exists($pluginDir . DIRECTORY_SEPARATOR . $entry . 'Plugin.class.php')
                ) {
                    $plugins[] = $entry;
                }
            }

            closedir($pluginDirH);
        }

        return $plugins;
    }

    /**
     * Cargar un plugin
     *
     * @param string $name Nombre del plugin
     * @return bool|PluginInterface
     * @throws \SP\Core\Exceptions\SPException
     */
    public static function loadPlugin($name)
    {
        $name = ucfirst($name);

        if (in_array($name, SessionFactory::getPluginsDisabled(), true)) {
            return false;
        }

        if (isset(self::$loadedPlugins[$name])) {
            return self::$loadedPlugins[$name];
        }

        try {
            $pluginClass = 'Plugins\\' . $name . '\\' . $name . 'Plugin';

            $Reflection = new ReflectionClass($pluginClass);

            /** @var PluginInterface $Plugin */
            $Plugin = $Reflection->newInstance();

            if (PluginDataStore::load($Plugin) === true) {
                self::$loadedPlugins[$name] = $Plugin;

                return $Plugin;
            }

            self::$disabledPlugins[] = $name;
        } catch (\ReflectionException $e) {
            Log::writeNewLog(__FUNCTION__,
                sprintf(__('No es posible cargar el plugin "%s"'), $name));
        } catch (SPException $e) {
            Log::writeNewLog(__FUNCTION__,
                sprintf(__('No es posible cargar el plugin "%s"'), $name));
        }

        return false;
    }

    /**
     * Devolver los plugins cargados
     *
     * @return PluginInterface[]
     */
    public static function getLoadedPlugins()
    {
        return self::$loadedPlugins;
    }

    /**
     * Devolver los plugins deshabilidatos
     *
     * @return string[]
     */
    public static function getDisabledPlugins()
    {
        return self::$disabledPlugins;
    }

    /**
     * Obtener la información de un plugin
     *
     * @param string $name Nombre del plugin
     * @return bool|PluginInterface
     * @throws \SP\Core\Exceptions\SPException
     */
    public static function getPluginInfo($name)
    {
        $name = ucfirst($name);

        $pluginClass = 'Plugins\\' . $name . '\\' . $name . 'Plugin';

        if (isset(self::$loadedPlugins[$name])) {
            return self::$loadedPlugins[$name];
        }

        try {
            $Reflection = new \ReflectionClass($pluginClass);

            /** @var PluginBase $Plugin */
            $Plugin = $Reflection->newInstance();

            self::$loadedPlugins[$name] = $Plugin;

            return $Plugin;
        } catch (\ReflectionException $e) {
            Log::writeNewLog(__FUNCTION__, sprintf(__('No es posible cargar el plugin "%s"'), $name));
        }

        return false;
    }

    /**
     * Comprobar disponibilidad de plugins habilitados
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public static function checkEnabledPlugins()
    {
        $PluginData = new PluginData();
        $PluginData->setAvailable(false);
        $PluginData->setEnabled(false);

        foreach (self::getEnabledPlugins() as $plugin) {
            if (!in_array($plugin, self::$loadedPlugins)) {
                $PluginClone = clone $PluginData;
                $PluginClone->setName($plugin);

                Plugin::getItem($PluginClone)->toggleAvaliableByName();
            }
        }
    }

    /**
     * Devolver los plugins habilitados
     *
     * @return PluginInterface[]
     */
    public static function getEnabledPlugins()
    {
        if (self::$enabledPlugins !== null) {
            return self::$enabledPlugins;
        }

        self::$enabledPlugins = [];

        foreach (Plugin::getItem()->getEnabled() as $plugin) {
            self::$enabledPlugins[] = $plugin->plugin_name;
        }

        return self::$enabledPlugins;
    }

    /**
     * Cargar los Plugins disponibles
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public static function loadPlugins()
    {
        $PluginsLoader = new \SplClassLoader('Plugins', PLUGINS_PATH);
        $PluginsLoader->register();

        foreach (self::getPlugins() as $plugin) {
            $Plugin = self::loadPlugin($plugin);

            if ($Plugin !== false) {
                DiFactory::getEventDispatcher()->attach($Plugin);
            }
        }

        SessionFactory::setPluginsLoaded(PluginUtil::getLoadedPlugins());
        SessionFactory::setPluginsDisabled(PluginUtil::getDisabledPlugins());
    }
}