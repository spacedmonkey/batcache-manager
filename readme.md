Batcache Manager
================

Batcache manager is a drop-in solution, that adds cache clearing the popular caching [Batcache](https://github.com/Automattic/batcache) plugin by [Automattic](http://automattic.com/). This plugin is based on the work by [Andy Skelton](http://andyskelton.com/) and expands upon it, clearing archive pages, author pages and feeds. Optionally the batcache-stats.php can be installed, adding flush all functionality, which is missing from the batcache drop-in.

If you wish to follow the development of this plugin, view the code on the official plugin [website](http://www.jonathandavidharris.co.uk "website") or follow me on twitter [@thespacedmonkey](https://twitter.com/thespacedmonkey)


## Installation

It is worth noting that this plugin requires [batcache](https://github.com/Automattic/batcache) to be installed and the advanced-cache.php file to be in place. If batcache is not setup then, installing this code may break your site. 

1. Download batcache manager
2. Extract the `batcache-manager` directory to your computer
3. Upload the `batcache-manager.php` directory to the `/wp-content/mu-plugins/` directory
4. Upload the `batcache-stats.php` directory to the `/wp-content/` directory

## License

The Batcache Manager is licensed under the GPL v2 or later.

> This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

> This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

> You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


## Contributions

Anyone is welcome to contribute to Batcache Manager

There are various ways you can contribute:

* Raise an issue on GitHub.
* Send us a Pull Request with your bug fixes and/or new features.
* Provide feedback and suggestions on enhancements.

## Future development 

* Add WP-CLI commands
* Add unit tests