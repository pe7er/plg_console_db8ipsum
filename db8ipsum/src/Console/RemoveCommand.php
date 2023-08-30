<?php
/**
 * @package    db8 ipsum
 *
 * @author     Peter Martin <joomla@db8.nl>
 * @copyright  Copyright 2021 -2023 by Peter Martin
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link       https://db8.nl
 */

namespace Joomla\Plugin\Console\Db8ipsum\Console;

\defined('_JEXEC') or die;

use Exception;
use Faker;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use phpseclib3\Math\PrimeField\Integer;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Create Example Content
 *
 * Create User. Categories, Articles, Menu, Menu Items with example data
 *
 * @since   1.0.0
 */
class RemoveCommand extends AbstractCommand
{
    use DatabaseAwareTrait;

    /**
     * The default command name
     *
     * @var    string
     * @since  1.0.0
     */
    protected static $defaultName = 'db8ipsum:content:remove';

    /**
     * SymfonyStyle Object
     *
     * @var    SymfonyStyle
     * @since  1.0.0
     */
    private SymfonyStyle $ioStyle;

    /**
     * @var     DatabaseInterface
     * @since   1.0.0
     */
    private DatabaseInterface $db;

    /**
     * @var   bool
     * @since 1.0.0
     */
    private bool $isVerbose = false;

    /**
     * @var Registry
     */
    private Registry $params;

    /**
     * @param   DatabaseInterface  $db
     *
     * @since   1.0.0
     */
    public function __construct(DatabaseInterface $db)
    {
        parent::__construct();

        $this->setDatabase($db);

        require_once __DIR__ . '/../../vendor/autoload.php';

        $this->db = $this->getDatabase();

        // Get the plugin parameters
        $plugin = PluginHelper::getPlugin('console', 'db8ipsum');
        if ($plugin)
        {
            $this->params = new Registry($plugin->params);
        }
    }

    /**
     * Initialise the command.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function configure(): void
    {
        $this->setDescription('Remove all db8 Ipsum Example Content (Categories, Articles, Images, Menu Items)');
    }

    /**
     * Internal function to execute the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  int  The command exit code
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $this->ioStyle = new SymfonyStyle($input, $output);
        $this->ioStyle->title('Start removing Example Content');
        $this->isVerbose = $this->ioStyle->isVerbose();

        try
        {
            // Get all CatIDs
            $catIds = self::getCategoryIds();

            // Remove all Articles + WorkFlow
            self::removeArticles($catIds);

            // Remove all categories
            self::removeCategories($catIds);

            // Remove all Menu Items of db8-ipsum-menu
            self::removeMenuItems();

            // Remove db8 Ipsum Menu
            self::removeMenu();

            // Remove db8 Ipsum Admin + mapping
            self::removeAdminUser();

            // Remove all images in db8 Ipsum folder
            self::removeImages();

            $this->ioStyle->success('db8 Ipsum Example Content has been removed');

            return Command::SUCCESS;
        }
        catch (Exception $exception)
        {
            $this->ioStyle->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Retrieve or Add db8ParentCategoryId
     *
     * @return  array  The IDs of the db8 Ipsum Categories
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function getCategoryIds(): array
    {
        $mainDb8IpsumCategory = $this->params->get('mainDb8IpsumCategory', 'db8-ipsum');

        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('parent_id') . ' = 1')
            ->where($this->db->quoteName('level') . ' = 1')
            ->where($this->db->quoteName('path') . ' = ' . $this->db->quote($mainDb8IpsumCategory))
            ->where($this->db->quoteName('alias') . ' = ' . $this->db->quote($mainDb8IpsumCategory))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'));
        $this->db->setQuery($query);
        $parentCategoryId = $this->db->loadResult();

        if ($parentCategoryId > 0)
        {
            $query = $this->db->getQuery(true)
                ->select('id')
                ->from($this->db->quoteName('#__categories'))
                ->where($this->db->quoteName('parent_id') . ' = ' . (int) $parentCategoryId)
                ->where($this->db->quoteName('level') . ' = 2')
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'));
            $this->db->setQuery($query);
            $categoryIds = $this->db->loadColumn();
        }

        $categoryIds[] = $parentCategoryId;

        return $categoryIds;
    }

    /**
     * Remove Articles + their Workflow records
     *
     * @params  array $catIds Category IDs
     *
     * @since   1.0.0
     */
    private function removeArticles(array $catIds): void
    {
        // Only remove when we have some categories
        if (count($catIds) <= 1)
        {
            return;
        }

        // Get Article IDs from db8 Ipsum Categories
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('catid') . ' IN( ' . implode(',', $catIds) . ')');
        $this->db->setQuery($query);
        $articleIds = $this->db->loadColumn();

        // Remove Article IDs
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('id') . ' IN( ' . implode(',', $articleIds) . ')');
        $this->db->setQuery($query);
        $this->db->execute();

        // Remove those Articles from WorkFlow
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__workflow_associations'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content.article'))
            ->where($this->db->quoteName('item_id') . ' IN( ' . implode(',', $articleIds) . ')');
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Remove Articles + their Workflow records
     *
     * @params  array $catIds Category IDs
     *
     * @since   1.0.0
     */
    private function removeCategories($catIds): void
    {
        // Only remove when we have some categories
        if (count($catIds) <= 1)
        {
            return;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' IN( ' . implode(',', $catIds) . ')');
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Remove Menu Items
     *
     * @return  void
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function removeMenuItems(): void
    {
        // Remove Menu Items
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('menutype') . ' = ' . $this->db->quote('db8-ipsum-menu'));
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Remove Menu
     *
     * @return  void
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function removeMenu(): void
    {
        // Remove Menu Items
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__menu_types'))
            ->where($this->db->quoteName('menutype') . ' = ' . $this->db->quote('db8-ipsum-menu'));
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Remove Admin User
     *
     * @return  void
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function removeAdminUser(): void
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('username') . ' = ' . $this->db->quote('db8IpsumAdminUser'));
        $this->db->setQuery($query);
        $adminUser = $this->db->loadResult();

        // Remove Admin User
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = ' . $this->db->quote($adminUser));
        $this->db->setQuery($query);
        $this->db->execute();

        // Remove Admin User from UserGroup Mapping table
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__user_usergroup_map'))
            ->where($this->db->quoteName('user_id') . ' = ' . $this->db->quote($adminUser));
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Remove folder with images
     *
     * @return void
     */
    private function removeImages(): void
    {
        $imageFolder = JPATH_BASE . '/images/' . $this->params->get('imageFolder', 'db8_ipsum');
        $files       = glob($imageFolder . '/*.jpg');

        foreach ($files as $file)
        {
            unlink($file);
        }

        rmdir($imageFolder);
    }
}
