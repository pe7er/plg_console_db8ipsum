<?php
/**
 * @package    db8 ipsum
 *
 * @author     Peter Martin <joomla@db8.nl>
 * @copyright  Copyright 2021 -2023 by Peter Martin
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link       https://db8.nl
 */

namespace Joomla\Plugin\Console\Db8ipsum\Extension;

\defined('_JEXEC') or die;

use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Console\Db8ipsum\Console\CreateCommand;
use Joomla\Plugin\Console\Db8ipsum\Console\RemoveCommand;

class Db8ipsum extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
        ];
    }

    /**
     * Register the commands that can be executed via the CLI.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function registerCLICommands(): void
    {
        $commandObject = new CreateCommand($this->getDatabase());
        $this->getApplication()->addCommand($commandObject);

        $commandObject = new RemoveCommand($this->getDatabase());
        $this->getApplication()->addCommand($commandObject);
    }
}
