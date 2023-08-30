# db8 Ipsum
## Joomla 4 Console Plugin

This Console Plugin works on the command line. It generates Categories, Articles, Menu Items and Category/Article Images.

I've developed this Joomla 4 Console Plugin when preparing a presentation called "No More Lorem Ipsum"
https://petermartin.nl/en/presentations/joomla/no-more-lorem-ipsum

## Instructions
- After installation, under System > Plugins, select db8ipsum
- Enable the plugin
- Configure what it should create:
  - \# of Categories
  - A menu item for each Category
  - \# Articles per Category
  - A menu item for each Article
  - Should it generate an image for each Article/Category
  - configure the image folder (which will be created under /images/)

### Generate Content
- On the command line in /cli/ do
```bash
php joomla.php db8ipsum:create:content
```

### Remove the generate Content
- On the command line in /cli/ do
```bash
php joomla.php db8ipsum:remove:content
```

## External libraries
- Faker

## Requirements
- Your server needs to have the PHP module GD installed and enabled so that it can generate images