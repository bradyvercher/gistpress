# Changelog

## [Unreleased]

* Remove `Doctype`, `html` and `body` elements added with DOMDocument.
* Fix some code standards.
* Updated change log.

## [v3.0.1] - 2016-02-16

* Explicitly declared main instance variables as global to prevent fatal errors when using WP-CLI. [See #61](https://github.com/bradyvercher/gistpress/issues/61).

## [v3.0.0] - 2015-08-11

* Fixed processing due to a change in the structure of the Gist HTML. `DOMDocument` is now used for parsing HTML. If shortcode attributes don't seem to be working, be sure the [DOM extension](http://php.net/manual/en/intro.dom.php) is available on your server.
* Removed globals in favor of API wrappers.
* Used time constants.
* Updated minimum WP requirement to 3.5.0.
* Created `CHANGELOG.md` and linked README to it.
* Created `CONTRIBUTING.md`.
* Created `LICENSE`.
* Created `composer.json`
* Added hook documentation.
* Fixed `show_line_numbers`.
* Fixed processing of new Gist HTML.


## [v2.0.3] - 2014-04-17

* Fixed the broken style sheet URL.

## [v2.0.2] - 2013-12-10

* Escape regex replacement string in `process_gist_html()`.
* Added support for GitHub Updater.
* Fixed for oEmbed cache not cleared on update.
* Fixed incorrect output from `rebuild_shortcode()`.
* Fixed path to text domain directory.
* Improved load text domain.
* Moved classes into includes directory.
* Improved Readme with a new section on updating.
* Added oEmbed support for bookmark-format URLs.
* Cache the remote API call in the file lookup method.

## [v2.0.1] - 2013-07-25

* Update path to the remote style sheet.

## [v2.0.0] - 2013-05-25

* Rename from "Blazer Six Gist oEmbed" to "GistPress".
* Added `.gitignore` file.
* Documentation cleanup.
* Use short form of hex color code.
* Added filter for line classes.
* Fixed `line_start` counting bug.
* Refactored `render()` method into two additional protected methods.
* Refactored `shortcode()` method into two additional protected methods.
* Improved file-level and method-level documentation.
* Improved README.
* Improved i18n, updated `.pot` file.
* Updated German translations.
* Fixed bug on line-splitting.
* Added support for namespaced Gists.
* Added uninstall procedure for old style sheet option.



## [v1.1.0] - 2013-01-03

* Rework after Gist refresh. New caching implementation and features.
* Comments and formatting tweaks.
* Removed the ability to wrap URLs in `[gist]` shortcodes in order to allow self-closing shortcodes without a closing slash. Either a URL for simple oEmbed-like functionality or a shortcode with the required 'id' attribute should be used.
* Improved the caching algorithm. Hang on to raw source for a period of time so post updates don't hammer GitHub.
* Converted to a singleton instead of static methods.
* Added Debug Bar plugin support.
* Refactor main plugin file.
* Code standards improvements.
* Rename functions, text domains and filter names. Technically breaking BC.
* Use `delete_transient()` instead of `set_transient()` with a negative timeout.
* Updated the uninstall file with new key names and to use the API to delete cache entries so filters hooked into them will fire.
* Refactor creation of transient key name.
* Allow raw transients to be deleted on post save so changes to the Gist can be fetched.
* Fixed issue with raw HTML not getting processed if it came from the transient.
* Updated priority for the style sheet registration hook.
* Added text domain plugin header so that WP can internationalize plugin meta data on admin screens.
* Improved consistency of file-level documentation.
* Added a `lines_start` attribute to specify an arbitrary line number to start counting from.
* Added a simple link to the Gist when viewed in a feed.
* Added `README.md`.
* Added languags directory and `.pot` file.

## [v1.0.1] - 2012-12-11

* Fixed issue with `update_metadata()` stripping slashes.
* Fixed debug notices
* Added more comments.
* Added documentation for weirdness with dashes in Gist filenames.

## v1.0.0 - 2012-07-16

* Initial release.

[Unreleased]: https://github.com/bradyvercher/gistpress/compare/v3.0.1...HEAD
[v3.0.1]: https://github.com/bradyvercher/gistpress/compare/v3.0.0...v3.0.1
[v3.0.0]: https://github.com/bradyvercher/gistpress/compare/v2.0.3...v3.0.0
[v2.0.3]: https://github.com/bradyvercher/gistpress/compare/v2.0.2...v2.0.3
[v2.0.2]: https://github.com/bradyvercher/gistpress/compare/v2.0.1...v2.0.2
[v2.0.1]: https://github.com/bradyvercher/gistpress/compare/v2.0.0...v2.0.1
[v2.0.0]: https://github.com/bradyvercher/gistpress/compare/v1.1.0...v2.0.0
[v1.1.0]: https://github.com/bradyvercher/gistpress/compare/v1.0.1...v1.1.0
[v1.0.1]: https://github.com/bradyvercher/gistpress/compare/v1.0.0...v1.0.1
