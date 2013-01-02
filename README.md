# Blazer Six Gist oEmbed #

Easily embed Gists in WordPress via oEmbed or shortcode.

GitHub provides a method for embedding Gists on websites, but it requires inserting a `<script>` tag, which can become mangled or stripped from the TinyMCE editor used in WordPress. Instead, this plugin allows you to embed a Gist by simply inserting its URL into the editor for oEmbed-like support, or via a shortcode for more refined control.

## Features ##

* Better integration with the visual editor in WordPress since `<script>` tags don't need to be used, which also allows visitors without javascript to view your code snippets.
* Users viewing your content in a feed reader will see a link directly to the Gist instead of nothing.
* Limit which lines from a Gist are displayed. Helpful for breaking down code so it can be easily explained.
* Highlight specific lines within a Gist to call attention to them.
* Easily debug embedded Gists using a custom panel for the [Debug Bar](http://wordpress.org/extend/plugins/debug-bar/) plugin.
* A few additional CSS classes are inserted very better styling hooks.

![Example Gist embed with line number restrictions, a highlighted line, and meta links disabled](https://github.com/bradyvercher/wp-blazer-six-gist-oembed/blob/master/screenshot-1.png)

### Caching ###

Embedded Gists are cached using a custom algorithm that minimizes HTTP requests and ensures your code snippets continue to display even if GitHub is down. If you need to update the snippet with changes made on GitHub, just update the post and the cache will be refreshed.

If you decide you don't want to use the plugin, simply uninstall using the "Delete" link on the Plugins screen, and all cached data and options will be cleaned up. Like it never even existed.

## Usage ##

### oEmbed ###

Just insert the URL to a Gist on its own line (don't link it up), like this:

`https://gist.github.com/9b1307f153f4abe352a4`

That's it!

_(Notice that's a URL for a secret Gist? Of course URLs for public Gists work, too.)_

### Shortcode ###

Using the URL from above as an example, the shortcode equivalent would look like this:

`[gist id="9b1307f153f4abe352a4"]`

In both cases, that would embed all four files that are included in the example Gist, but with the shortcode, you have the option to limit the display to a single file by specifiying its name:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php"]`

Taking it further, you can limit display to specific lines within a file:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php" lines="2-5"]`

Or even highlight lines:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php" highlight="7"]`

Maybe you want to disable line numbers and the links included below a Gist:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php" show_line_numbers="0" show_meta="0"]`

There are also attributes for disabling the default stylesheet, changing the highlight color, and specifying a starting point for the line numbers.

## Credits ##

Built by [Brady Vercher](https://twitter.com/bradyvercher) & [Gary Jones](https://twitter.com/GaryJ)  
Copyright 2012 [Blazer Six, Inc.](http://www.blazersix.com/) ([@BlazerSix](https://twitter.com/BlazerSix))