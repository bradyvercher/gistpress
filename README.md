# Blazer Six Gist oEmbed #

Easily embed Gists in WordPress via oEmbed or shortcode.

GitHub provides a method for embedding Gists on websites, but it requires inserting a `<script>` tag, which can become mangled or stripped from the TinyMCE editor used in WordPress. Instead, this plugin allows you to embed a Gist by simply inserting its URL into the editor for oEmbed-like support, or via a shortcode for more refined control.

## Usage ##

### oEmbed ###

Just insert the URL to a Gist on its own line (don't link it up), like this:

`https://gist.github.com/9b1307f153f4abe352a4`

### Shortcode ###

Using the URL from above as an example, the shortcode equivalent would look like this:

`[gist id="9b1307f153f4abe352a4"]`

In both cases, that would embed all four files that are included in the example Gist, but with the shortcode, you have the option to limit the display to a single file by specifiying its name:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php"]`

Taking it even further, you can limit display to specific lines within a file:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php" lines="2-5"]`

Or even highlight lines:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php" highlight="7"]`

Maybe you want to disable line numbers and the links included below a Gist:

`[gist id="9b1307f153f4abe352a4" file="media-control-snippet.php" show_line_numbers="0" show_meta="0"]`

There are also attributes for disabling the default stylesheet, changing the highlight color, and even specify a starting point for the line numbers.

## Features ##

* Better support with the visual editor in WordPress since `<script>` tags don't need to be used, which also allows visitors without javascript to view your snippets.
* Users viewing your content in a feed reader will see a link directly to the Gist instead of not seeing anything.

### Caching ###

Embedded Gists are cached using a custom algorithm that minimizes HTTP requests when viewed on the front end and ensures your code snippets continue to display even if GitHub is down. If you need to update the snippet with changes made on GitHub, just update the post and the cache will be refreshed.

### Debugging  ###

A custom panel has been included for the Debug Bar plugin to help debug Gist embeds.

## Credits ##

Built by ([Brady Vercher](http://twitter.com/bradyvercher)) & ([Gary Jones](https://twitter.com/GaryJ))  
Copyright 2012  Blazer Six, Inc.(http://www.blazersix.com/) ([@BlazerSix](http://twitter.com/BlazerSix))