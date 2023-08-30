<?php
/**
 * @package    db8 ipsum
 *
 * @author     Peter Martin <joomla@db8.nl>
 * @copyright  Copyright 2021 -2023 by Peter Martin
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link       https://db8.nl
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Console\Db8ipsum\Extension\Db8ipsum;

return new class implements ServiceProviderInterface
{
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,

            function (Container $container) {
                $subject = $container->get(DispatcherInterface::class);
                $config  = (array) PluginHelper::getPlugin('console', 'db8ipsum');

                $plugin = new Db8ipsum($subject, $config);
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );

    }
};
