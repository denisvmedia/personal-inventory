Personal Inventory Web Application
==================================

This is a web application for managing a personal inventory or collection. It's meant to be run on your computer or
home network. It's great for

- Maintaining a home inventory for insurance purposes
- Keeping track of home electronics
- Organizing a coin, stamp, or other collection

Advantages to using this system over a simple spreadsheet:

- Quickly browse by type or location
- Incorporate photos and images
- Comfortable browsing and editing on a mobile device

Screenshots
-----------

Note, these screenshots are outdated.

![View an item](screenshots/view_item.png)
![List of items](screenshots/list_items.png)
![Edit an item](screenshots/edit_item.png)

Photos
------

To take a photo of an item, simply browse to the site on your mobile device.  When editing an item the "upload photo"
button will trigger your device to ask if you'd like to use your camera or pick a photo from your camera roll.

Running the Application
-----------------------

### With Docker

Note, to use the bundled docker-compose configuration you must install [DevEnv](https://github.com/denisvmedia/devenv). 

We include a docker configuration to get up and running quickly and easily.  The only requirement is docker and docker-compose.  To run the personal inventory manager on a single desktop computer:

1. Run `cp docker-compose.override.yml.sample docker-compose.override.yml` to create your own local copy of `docker-compose.override.yml`. 
1. Run `docker-compose up --build`.  Add `-d` to run it in the background.
1. Open [https://inventory.devenv.test](https://inventory.devenv.test) in your browser.

For any other type of setup, such as on a home network server, edit or override the settings in `docker-compose.yml` and `docker/web/Dockerfile`.  To point to a MongoDB server other than the one included, edit the `.env.local` file.

### Without Docker

This personal inventory manager is a standard PHP web application. Requirements:

- A web server running PHP 8.1
- PHP extensions: bcmath, exif, gd, mongodb
- PHP's composer package manager
- MongoDB 4 or 5

Setup:

- Set the `data/images` directory to be writable by the web server.
- Set the web server's document root to be the `public` directory with all requests going to `index.php`.
- Run `composer install`. 

Security
--------

There is no included user authentication, data encryption, or other security. This isn't intended to be run as-is
on the open internet. If you'd like to secure the application and its data you might need editing your web server
configuration to include at least HTTP Basic Authentication and HTTPS. Also block remote connections to MongoDB. 
*Caveat emptor*.

TODO
----

- [x] Archived attribute.
- [ ] Warranty information.
- [ ] PDF invoice support separate from images.
- [ ] PDF manuals attach support.
- [ ] PDF export useful for insurance purposes.
- [ ] Links to various online stores to make it easy to order more of an item.
- [ ] Link to the goods and link to the seller (often it's one and the same link, but sometimes it can differ).
- [ ] Configurable depreciation schedule to estimate current values.
