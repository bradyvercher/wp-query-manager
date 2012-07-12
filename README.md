# WP Query Manager #

*Note:* This plugin is incomplete. Although it should be functional, it lacks polish and documentation at this point. If it looks like it might be useful, feel free to get in touch and I'll try to dedicate some time to finishing it up. I originally considered using this for experimenting and demonstrating various techniques, but have removed much of the code in order to make it functional and push out an early concept.*

**So what exactly is WP Query Manager?**

WP Query Manager started as a simple interface for overriding some WP_Query arguments on archive pages from within the admin panel without having to edit any code. It grew from there to the point where most every argument can be defined from the interface. It's not a replacement for more complex, dynamic queries, but is quite powerful in it's own right.

So what exactly can you do? Some of the more simple options include modifying the number of posts that display on any archive (category, taxonomy, author, etc) or change the order or orderby arguments. Other arguments can be defined in querystring format in a text field. And there's even support for more advanced meta and tax queries.

In addition, you can modify the number of posts included in a feed, whether it should consist of summaries or full content, and even define the summary length--all on a per-feed basis.

The queries actually work like WordPress' template hierarchy as well, allowing you to define general global arguments (Archive) and override them at more specific levels (Taxonomy, Author, Date).

Like I siad, the docs are pretty much non-existent at the moment, so get in touch if you're curious about how anything works or would like to contribute.

Built by Brady Vercher ([@bradyvercher](http://twitter.com/bradyvercher))

Copyright 2012  Blazer Six, Inc.(http://www.blazersix.com/) ([@blazersix](http://twitter.com/BlazerSix))