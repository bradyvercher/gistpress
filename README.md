# GistPress #

A WordPress plugin to easily embed Gists via oEmbed or shortcode.

__Contributors:__ [Brady Vercher](https://github.com/bradyvercher), [Gary Jones](https://github.com/GaryJones)  
__Requires:__ May work on WordPress versions as far back as 2.9.0.  
__Tested up to:__ 3.5.0  
__License:__ [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.html)

GitHub provides a method for embedding Gists on websites, but it requires inserting a `<script>` tag, which can become mangled or stripped from the TinyMCE editor used in WordPress. Instead, this plugin allows you to embed a Gist by simply inserting its URL into the editor for oEmbed-like support, or via a shortcode for more refined control.

## Features ##

* Better integration with the visual editor in WordPress since `<script>` tags aren't used, which also allows visitors without javascript to view your code snippets.
* Users viewing your content in a feed reader will see a link directly to the Gist instead of nothing.
* Limit which lines from a Gist are displayed. Helpful for breaking down code so it can be easily explained.
* Highlight specific lines within a Gist to call attention to them.
* Easily debug embedded Gists using a custom panel for the [Debug Bar](http://wordpress.org/extend/plugins/debug-bar/) plugin.
* A few additional CSS classes are inserted for better styling hooks.

![Embedded Gist Screenshot](https://raw.github.com/bradyvercher/wp-blazer-six-gist-oembed/master/screenshot-1.png)  
_Example Gist embed with line number restrictions, a highlighted line, and meta links disabled._

### Caching ###

Embedded Gists are cached using a custom algorithm that minimizes HTTP requests and ensures your code snippets continue to display even if GitHub is down. If you need to update the snippet with changes made on GitHub, just update the post and the cache will be refreshed.

If you decide you don't want to use the plugin, simply uninstall using the "Delete" link on the Plugins screen, and all cached data and options will be cleaned up. Like it never even existed.

## Installation ##

### Upload ###

1. Download the latest tagged archive (choose the "zip" option).
2. Go to the __Plugins -> Add New__ screen and click the __Upload__ tab.
3. Upload the zipped archive directly.
4. Go to the Plugins screen and click __Activate__.

### Manual ###

1. Download the latest tagged archive (choose the "zip" option).
2. Unzip the archive.
3. Copy the folder to your `/wp-content/plugins/` directory.
4. Go to the Plugins screen and click __Activate__.

Check out the Codex for more information [installing plugins manually](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

### Git ###

Using git, browse to your `/wp-content/plugins/` directory and clone this repository:

`git clone git@github.com:bradyvercher/wp-blazer-six-gist-oembed.git`

Then go to your Plugins screen and click __Activate__.

## Usage ##

### oEmbed ###

Insert the URL to a Gist on its own line like this (don't link it up):

`https://gist.github.com/9b1307f153f4abe352a4`

That's it!

_Notice that is a URL for a secret Gist? Of course URLs for public Gists work, too._

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

## Notes ##

Some themes may include styles that interfere with the default rules for embedded Gists. You can override the conflicting styles in your theme's (or [child theme's](http://codex.wordpress.org/Child_Themes)) style sheet with more specific rules targetting the embed HTML. Typically, this might include removing margins on `<pre>` elements, changing padding on the table cells, and ensuring the `line-height` and `font-size` properties for line numbers and code match so they align properly.

### Highlighting ###

To support line highlighting, an inline style is added by the plugin, however, a class is also added to the line element. Developers can add a CSS rule similar to the following to their theme style sheet in order to change the color:

```css
.pre-line-highlight {
    background-color: #ffc !important;
}
```

And the following would go in the theme's functions.php to disable the `style` attribute.

```php
add_filter( 'gistpress_highlight_color', '__return_false' );
```

## Credits ##

Built by [Brady Vercher](https://twitter.com/bradyvercher) & [Gary Jones](https://twitter.com/GaryJ)