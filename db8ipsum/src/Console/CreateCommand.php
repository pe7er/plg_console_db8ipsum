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
class CreateCommand extends AbstractCommand
{
    use DatabaseAwareTrait;

    /**
     * The default command name
     *
     * @var    string
     * @since  1.0.0
     */
    protected static $defaultName = 'db8ipsum:content:create';

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
        $this->setDescription('Create db8 Ipsum Example Content: Create Categories, Articles, Images, Menu Items');
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
        $this->ioStyle->title('Start adding Content to database');
        $this->isVerbose = $this->ioStyle->isVerbose();
        $content         = [];

        try
        {
            // Get the UserId, ParentCategory and MenuId to use when creating sample content
            $content['adminUserId']      = $this->getAdminUserId();
            $content['parentCategoryId'] = $this->getParentCategoryId($content['adminUserId']);
            $content['menuTypeId']       = $this->getMenuTypeId();
            $this->createContent($content);

            $this->ioStyle->success('Content Creation has been finished');

            return Command::SUCCESS;
        }
        catch (Exception $exception)
        {
            $this->ioStyle->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Create  Content
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function createContent($content): void
    {
        // Create and populate an object.
        //$category = new stdClass('en_EN');

        // use the factory to create a Faker\Generator instance
        $faker = Faker\Factory::create();
        // generate data by calling methods

        $numberOfCategories           = $this->params->get('numberOfCategories', 4);
        $mainDb8IpsumCategory         = $this->params->get('mainDb8IpsumCategory', 'db8-ipsum');
        $numberOfArticles             = $this->params->get('numberOfArticles', 8);
        $createImages                 = $this->params->get('createImages', 1);
        $imageFolder                  = $this->params->get('imageFolder', 'db8_ipsum');
        $createCategoryBlogMenuItems  = $this->params->get('createCategoryBlogMenuItems', 1);
        $createSingleArticleMenuItems = $this->params->get('createSingleArticleMenuItems', 1);

        $imagePath = JPATH_BASE . '/images/' . $imageFolder;
        if (!file_exists($imagePath))
        {
            mkdir($imagePath, 0755, true);
        }

        for ($i = 0; $i < $numberOfCategories; ++$i)
        {
            $dateTime = HtmlHelper::date('now', Text::_('DATE_FORMAT_FILTER_DATETIME'), 'utc');
            $title    = $faker->catchPhrase();
            $alias    = OutputFilter::stringURLSafe(trim($title));
            $text     = $faker->realText($maxNbChars = 200, $indexSize = 2);

            $categoryContent = [
                'id'              => '0',
                'parent_id'       => $content['parentCategoryId'],
                'level'           => '2',
                'path'            => $mainDb8IpsumCategory . '/' . $alias,
                'extension'       => 'com_content',
                'title'           => $title,
                'alias'           => $alias,
                'description'     => $text,
                'published'       => '1',
                'access'          => '1',
                'params'          => '{"category_layout":"","image":"","image_alt":""}',
                'metadata'        => '{}',
                'created_user_id' => $content['adminUserId'],
                'created_time'    => $dateTime,
                'modified_time'   => $dateTime,
                'language'        => '*',
                'version'         => '1',
            ];

            // Create a Sample Image for every Category
            if ($createImages)
            {
                $bgHex = self::generateRandomColor();
                self::generateImage('category', $title, $alias, $bgHex);
                $categoryContent['params'] = '{"category_layout":"","image":"images\/' . $imageFolder . '\/' . $alias . '.jpg","image_alt":"' . $alias . '"}';
            }

            $category = (object) $categoryContent;
            $this->getDatabase()->insertObject('#__categories', $category, 'id');
            $categoryInsertId = $this->getDatabase()->insertid();

            // Create a Category Blog Menu Item for every Category
            if ($createCategoryBlogMenuItems)
            {
                $menuItemCategory['title']     = $title;
                $menuItemCategory['alias']     = $alias;
                $menuItemCategory['path']      = $alias;
                $menuItemCategory['parent_id'] = 1;
                $menuItemCategory['level']     = 1;
                $menuItemCategory['link']      = 'index.php?option=com_content&view=category&layout=blog&id=' . $categoryInsertId;
                $menuItemCategory['params']    = '{"layout_type":"blog","show_category_title":"","show_description":"","show_description_image":"","maxLevel":"","show_empty_categories":"",'
                    . '"show_no_articles":"","show_category_heading_title_text":"","show_subcat_desc":"","show_cat_num_articles":"","show_cat_tags":"","num_leading_articles":"",'
                    . '"blog_class_leading":"","num_intro_articles":"","blog_class":"","num_columns":"","multi_column_order":"","num_links":"","show_featured":"","link_intro_image":"",'
                    . '"show_subcategory_content":"","orderby_pri":"","orderby_sec":"","order_date":"","show_pagination":"","show_pagination_results":"","article_layout":"_:default",'
                    . '"show_title":"","link_titles":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"",'
                    . '"link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_readmore":"",'
                    . '"show_readmore_title":"","show_hits":"","show_tags":"","show_noauth":"","show_feed_link":"","feed_summary":"","menu-anchor_title":"","menu-anchor_css":"","menu_icon_css":"",'
                    . '"menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","robots":""}';

                $menuItemCategory = self::createMenuItem($menuItemCategory);
            }

            for ($j = 0; $j < $numberOfArticles; ++$j)
            {
                $dateTime  = HtmlHelper::date('now', Text::_('DATE_FORMAT_FILTER_DATETIME'), 'utc');
                $title     = $faker->catchPhrase();
                $alias     = OutputFilter::stringURLSafe(trim($title));
                $introText = $faker->realText($maxNbChars = 100, $indexSize = 1);
                $fullText  = $faker->realText($maxNbChars = 600, $indexSize = 2);

                $articleContent = [
                    'id'               => 0,
                    'asset_id'         => 0,
                    'title'            => $title,
                    'alias'            => $alias,
                    'introtext'        => $introText,
                    'fulltext'         => $fullText,
                    'state'            => 1,
                    'catid'            => $categoryInsertId,
                    'created'          => $dateTime,
                    'created_by'       => $content['adminUserId'],
                    'created_by_alias' => '',
                    'modified'         => $dateTime,
                    'modified_by'      => $content['adminUserId'],
                    'checked_out'      => '',
                    'checked_out_time' => '',
                    'publish_up'       => $dateTime,
                    'publish_down'     => '',
                    'images'           => '{"image_intro":"","image_intro_alt":"","float_intro":"","image_intro_caption":"","image_fulltext":"","image_fulltext_alt":"","float_fulltext":"","image_fulltext_caption":""}',
                    'urls'             => '{"urla":"","urlatext":"","targeta":"","urlb":"","urlbtext":"","targetb":"","urlc":"","urlctext":"","targetc":""}',
                    'attribs'          => '{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"",'
                        . '"show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"",'
                        . '"show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"",'
                        . '"article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}',
                    'version'          => 1,
                    'ordering'         => 0,
                    'metakey'          => '',
                    'metadesc'         => '',
                    'access'           => 1,
                    'hits'             => 0,
                    'metadata'         => '{"robots":"","author":"","rights":""}',
                    'featured'         => 0,
                    'language'         => '*',
                    'note'             => ''
                ];

                // Create a Sample Image for every Article
                if ($createImages)
                {
                    self::generateImage('article', $title, $alias, $bgHex);
                    $articleContent['images']
                        = '{"image_intro":"images\/' . $imageFolder . '\/' . $alias . '.jpg","image_intro_alt":"' . $alias . '",'
                        . '"float_intro":"","image_intro_caption":"",'
                        . '"image_fulltext":"images\/' . $imageFolder . '\/' . $alias . '.jpg","image_fulltext_alt":"' . $alias . '",'
                        . '"float_fulltext":"","image_fulltext_caption":""}';
                }

                $article = (object) $articleContent;
                $this->getDatabase()->insertObject('#__content', $article, 'id');
                $articleInsertId = $this->getDatabase()->insertid();

                $workFlowContent = [
                    'item_id'   => $articleInsertId,
                    'stage_id'  => 1,
                    'extension' => 'com_content.article'
                ];

                $workFlow = (object) $workFlowContent;
                $this->getDatabase()->insertObject('#__workflow_associations', $workFlow, 'id');

                // Create a Category Blog Menu Item for every Category
                if ($createSingleArticleMenuItems)
                {
                    $menuItemArticle['title']     = $title;
                    $menuItemArticle['alias']     = $alias;
                    $menuItemArticle['path']      = $menuItemCategory['alias'] . '/' . $alias;
                    $menuItemArticle['parent_id'] = $menuItemCategory['insertId'];
                    $menuItemArticle['level']     = 2;
                    $menuItemArticle['link']      = 'index.php?option=com_content&view=article&id=' . $articleInsertId;
                    $menuItemArticle['params']
                                                  = '{"show_title":"","link_titles":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_tags":"","show_noauth":"","urls_position":"","menu
                - anchor_title":"","menu - anchor_css":"","menu_icon_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu
                - meta_description":"","robots":""}';

                    $menuItemArticle = self::createMenuItem($menuItemArticle);
                }
            }
        }

        // Todo: Rebuild Category Table
        // Todo: Add new Remove Example Content class

        echo 'New category, menu item, articles, and submenu items have been created successfully.';
    }

    /**
     * @param $menuItem
     *
     * @return array
     */
    private function createMenuItem($menuItem): array
    {
        $menuItemContent = [
            'id'                => 0,
            'menutype'          => 'db8-ipsum-menu',
            'title'             => $menuItem['title'],
            'alias'             => $menuItem['alias'],
            'note'              => '',
            'path'              => $menuItem['path'],
            'link'              => $menuItem['link'],
            'type'              => 'component',
            'published'         => 1,
            'parent_id'         => $menuItem['parent_id'],
            'level'             => $menuItem['level'],
            'component_id'      => 19,
            'checked_out'       => '',
            'checked_out_time'  => '',
            'browserNav'        => 0,
            'access'            => 1,
            'img'               => '',
            'template_style_id' => 0,
            'params'            => $menuItem['params'],
            'lft'               => 0,
            'rgt'               => 0,
            'home'              => 0,
            'language'          => '*',
            'client_id'         => 0,
            'publish_up'        => '',
            'publish_down'      => ''
        ];

        $menuItem = (object) $menuItemContent;
        $this->getDatabase()->insertObject('#__menu', $menuItem);

        $menuItem             = (array) $menuItem;
        $menuItem['insertId'] = $this->getDatabase()->insertid();

        return $menuItem;
    }

    /**
     * @param   string  $type   Category or Article
     * @param   string  $title  Title of Category or Article
     * @param   string  $alias  Alias of Category or Article
     * @param   string  $bgHex  Background color
     *
     * @return void
     */
    private function generateImage(string $type, string $title, string $alias, string $bgHex): void
    {
        $width       = 1920;
        $height      = 1280;
        $imageFolder = $this->params->get('imageFolder', 'db8_ipsum');
        $im          = imagecreatetruecolor($width, $height);

        // Background color
        $bgColor = imagecolorallocate(
            $im,
            hexdec(substr($bgHex, 0, 2)),
            hexdec(substr($bgHex, 2, 2)),
            hexdec(substr($bgHex, 4, 2))
        );
        imagefill($im, 0, 0, $bgColor);

        // Text color
        $fgHex     = self::generateContrastColor($bgHex);
        $textColor = imagecolorallocate(
            $im,
            hexdec(substr($fgHex, 0, 2)),
            hexdec(substr($fgHex, 2, 2)),
            hexdec(substr($fgHex, 4, 2))
        );

        if ($type === 'category')
        {
            for ($i = 0; $i <= 1920; $i += 20)
            {
                $r          = rand(1, 255);
                $g          = rand(1, 255);
                $b          = rand(1, 255);
                $text_color = ImageColorAllocate($im, $r, $g, $b);
                imageline($im, $i, 0, $i, 1280, $text_color);
            }
            // Horizontal lines
            for ($i = 0; $i <= 1280; $i += 20)
            {
                $r          = rand(1, 255);
                $g          = rand(1, 255);
                $b          = rand(1, 255);
                $text_color = ImageColorAllocate($im, $r, $g, $b);
                imageline($im, 0, $i, 1920, $i, $text_color);
            }
        }

        if ($type === 'article')
        {

            // randoms coords for polygons
            $coords = [];
            foreach (range(0, 127) as $p)
            {
                $coords[] = rand(0, $width);
                $coords[] = rand(0, $height);
            }

            // fill the background
            imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));

            // draw some polygons
            @imagefilledpolygon($im, $coords, 48, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            @imagefilledpolygon($im, $coords, 24, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }

        $fontFile = JPATH_BASE . '/plugins/console/db8ipsum/assets/fonts/OpenSans-SemiBold.ttf';
        $fontSize = 50;
        $textX    = 200;
        $textY    = 200;
        imagettftext($im, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $title);

        // Save the image as a JPEG
        imagejpeg($im, JPATH_BASE . '/images/' . $imageFolder . '/' . $alias . '.jpg');
        imagedestroy($im);
    }

    /**
     * @return string
     */
    private function generateRandomColor(): string
    {
        $characters = '0123456789ABCDEF';
        $color      = '';

        for ($i = 0; $i < 6; $i++)
        {
            $color .= $characters[rand(0, 15)];
        }

        return $color;
    }

    /**
     * @param   string  $hexColor  Color code in Hex format
     *
     * @return string
     */
    private function generateContrastColor(string $hexColor): string
    {
        // Remove the '#' symbol from the hex color
        $hexColor = str_replace('#', '', $hexColor);

        // Convert hex color to RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Calculate the luminance of the color
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Determine whether to use black or white text based on the luminance
        return ($luminance > 0.5) ? '000000' : 'FFFFFF';
    }


    /**
     * Retrieve or Add db8AdminUserId
     *
     * @return  int  The command exit code
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function getAdminUserId(): int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('username') . ' = ' . $this->db->quote('db8IpsumAdminUser'));
        $this->db->setQuery($query);
        $db8AdminUserId = $this->db->loadResult();

        if ($db8AdminUserId > 0)
        {
            return $db8AdminUserId;
        }

        // Create db8AdminUser
        $columns = ['name', 'username', 'email', 'password', 'block', 'registerDate', 'lastvisitDate', 'params'];
        $values  = [
            $this->db->quote('db8 Ipsum Admin User'),
            $this->db->quote('db8IpsumAdminUser'),
            $this->db->quote('db8IpsumAdminUser@example.com'),
            $this->db->quote('$2y$10$R7GXO4BvGceE9on1WCvjqecxxxz6p7NL4R1SK4v9aZS.fs8JRnuWi'),
            $this->db->quote('1'),
            $this->db->quote(HtmlHelper::date('now', Text::_('DATE_FORMAT_FILTER_DATETIME'), 'utc')), 'null',
            $this->db->quote('{}'),
        ];

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__users'))
            ->columns($this->db->quoteName($columns))
            ->values(implode(',', $values));
        $this->db->setQuery($query)
            ->execute();

        return $this->db->insertid();
    }

    /**
     * Retrieve or Add db8ParentCategoryId
     *
     * @return  int  The command exit code
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function getParentCategoryId($db8AdminUserId): int
    {
        $mainDb8IpsumCategory = $this->params->get('mainDb8IpsumCategory', 'db8-ipsum');

        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('parent_id') . ' = 1')// . $this->db->quote('1'))
            ->where($this->db->quoteName('level') . ' = 1')// . $this->db->quote('1'))
            ->where($this->db->quoteName('path') . ' = ' . $this->db->quote($mainDb8IpsumCategory))
            ->where($this->db->quoteName('alias') . ' = ' . $this->db->quote($mainDb8IpsumCategory))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'));
        $this->db->setQuery($query);
        $db8ParentCategoryId = $this->db->loadResult();

        if ($db8ParentCategoryId > 0)
        {
            return $db8ParentCategoryId;
        }

        // Create db8ParentCategory
        $columns = [
            'parent_id', 'level',
            'path', 'extension',
            'title', 'alias',
            'description',
            'published', 'access',
            'params', 'metadata',
            'created_user_id', 'created_time', 'modified_time',
            'language', 'version'
        ];
        $values  = [
            $this->db->quote('1'), $this->db->quote('1'),
            $this->db->quote($mainDb8IpsumCategory), $this->db->quote('com_content'),
            $this->db->quote($mainDb8IpsumCategory), $this->db->quote($mainDb8IpsumCategory),
            $this->db->quote('<p>' . Text::_('PLG_CONSOLE_DB8IPSUM_MAIN_CATEGORY') . '</p>'),
            $this->db->quote('1'), $this->db->quote('1'),
            $this->db->quote('{"category_layout":"","image":"","image_alt":""}'), $this->db->quote('{"author":"","robots":""}'),
            $this->db->quote($db8AdminUserId), $this->db->quote(HtmlHelper::date('now', Text::_('DATE_FORMAT_FILTER_DATETIME'), 'utc')),
            $this->db->quote(HtmlHelper::date('now', Text::_('DATE_FORMAT_FILTER_DATETIME'), 'utc')),
            $this->db->quote('*'), $this->db->quote('1')
        ];

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__categories'))
            ->columns($this->db->quoteName($columns))
            ->values(implode(',', $values));
        $this->db->setQuery($query)
            ->execute();

        return $this->db->insertid();
    }

    /**
     * Retrieve or Add db8-ipsum-menu Menu
     *
     * @return  int  The command exit code
     *
     * @throws  Exception
     * @since   1.0.0
     */
    protected function getMenuTypeId(): int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__menu_types'))
            ->where($this->db->quoteName('menutype') . ' = ' . $this->db->quote('db8-ipsum-menu'))
            ->where($this->db->quoteName('client_id') . ' = ' . $this->db->quote('0'));
        $this->db->setQuery($query);
        $db8MenuTypeId = $this->db->loadResult();

        if ($db8MenuTypeId > 0)
        {
            return $db8MenuTypeId;
        }

        // Create MenuType: db8-ipsum-menu
        $columns = ['menutype', 'title', 'description', 'client_id'];
        $values  = [
            $this->db->quote('db8-ipsum-menu'),
            $this->db->quote('db8 Ipsum Menu'),
            $this->db->quote('Menu for Menu Items to display db8 Ipsum Example Content'),
            $this->db->quote('0'),
        ];

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__menu_types'))
            ->columns($this->db->quoteName($columns))
            ->values(implode(',', $values));
        $this->db->setQuery($query)
            ->execute();

        return $this->db->insertid();
    }
}
