# Changelog

## 3.0.1 (2016-02-16)

* Explicitly declared main instance variables as global to prevent fatal errors when using WP-CLI. [See #61](https://github.com/bradyvercher/gistpress/issues/61).

## 3.0.0 (2015-08-11)

* Fixed processing due to a change in the structure of the Gist HTML. `DOMDocument` is now used for parsing HTML. If shortcode attributes don't seem to be working, be sure the [DOM extension](http://php.net/manual/en/intro.dom.php) is available on your server.

## 2.0.3 (2014-04-17)

* Fixed the reference to the style sheet broken by a change in the API - props @robneu
