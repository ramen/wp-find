# WP_Find

`WP_Find` is an interface to WordPress content that aims to be more consistent
and flexible than the standard query API. WordPress comes with a variety of
query tools such as the `WP_Query` class and the "template tags"
`get_posts()`, `get_terms()`, `get_bookmarks()`, and so on. `WP_Find` wraps
these tools with a uniform interface.

`WP_Find` can be loaded as a plugin or just included from a theme. In either
case, all of its functionality is provided through a single class called
`WP_Find`, which serves as a namespace.

## Building queries

`WP_Find` provides the following factory methods, which create objects that
can query for several types of WordPress data:

### Posts, pages, and attachments

* `WP_Find::posts()`
* `WP_Find::pages()`
* `WP_Find::attachments()`

### Tags and categories

* `WP_Find::tags()`
* `WP_Find::categories()`
* `WP_Find::link_categories()`

### Post/page tags and categories

* `WP_Find::post_tags()`
* `WP_Find::post_categories()`

### Links

* `WP_Find::links()`

Calling each of these methods results in a query object with the following
methods:

* `filter(key, value)`: pass a filter parameter to the underlying API function
* `filter(array)`: as above, but pass an array of filter parameters
* `search(text)`: perform a full-text search
* `order_by(field[, sort])`: specify an order field and sort direction ("ASC" or "DESC", defaults to "ASC")
* `limit(limit[, offset])`: limit the number of results with an optional offset
* `all()`: return all results as an array of objects
* `one()`: return a single result object or null if there are no results
* `sql()`: return a string containing the SQL that this query will run

Content query objects (`posts()`, `pages()`, and `attachements()`) provide the
following additional methods:

* `meta(key, value[, compare])`: restrict to content with a given meta key (also known as a custom field) set to a given value, with an optional comparison operator ('=', '!=', '>', '>=', '<', '<='; defaults to '=')

Queries for individual post tags and categories (`post_tags()` and
`post_categories()`) have a slightly different interface:

* `order_by(field[, sort])`: specify an order field and sort direction ("ASC" or "DESC", defaults to "ASC")
* `get(id)`: return an array of results for a given ID
* `sql(id)`: return the SQL that this query will run for a given ID

## Examples

It is perhaps easiest to understand how these objects work with a few
examples. We'll start with queries for posts, since these are the most common:

    // Create a query object for posts.
    $find_posts = WP_Find::posts();

    // Find the five most recent posts tagged "trendy".
    $trendy_posts = $find_posts->filter('tag', 'trendy')->limit(5);

    // Run the query and output the results as a list.
    echo "<ul>\n";
    foreach ($trendy_posts->all() as $trendy_post) {
        echo "<li>" . esc_html($trendy_post->post_title) . "</li>\n";
    }
    echo "</ul>\n";

Given a particular post, we can load its tags and categories as follows:

    // Create query objects for post tags and categories.
    $find_post_tags = WP_Find::post_tags();
    $find_post_categories = WP_Find::post_categories();

    // Load the tags and categories for $post.
    $post_tags = $find_post_tags->get($post->ID);
    $post_categories = $find_post_categories->get($post->ID);

We can also query for tags and categories in general, not just those related
to a particular post:

    // Create a query object for tags.
    $find_tags = WP_Find::tags();

    // Find the five most popular tags.
    $popular_tags = $find_tags->order_by('count', 'DESC')->limit(5)->all();

The `search()` method lets us do a full-text search:

    // Create a query object for links.
    $find_links = WP_Find::links();

    // Search for links containing the text "blogspot".
    $find_blogspot_links = $find_links->search('blogspot');
    $blogspot_links = $find_blogspot_links->all();

To find out what SQL is actually executing for these queries, call the `sql()`
method:

    // Print out the generated SQL.
    echo $find_blogspot_links->sql();

## Filters

Filters are passed directly to the $args parameter of each underlying
WordPress API function. For more complete details on the behavior of these
arguments, see the following documentation from the WordPress site:

* Posts, pages, and attachments: [query_posts](http://codex.wordpress.org/Template_Tags/query_posts)
* Tags and categories: [get_terms](http://codex.wordpress.org/Function_Reference/get_terms)
* Links: [get_bookmarks](http://codex.wordpress.org/Function_Reference/get_bookmarks)

Note that some of the arguments, such as "orderby", "order", "limit",
"meta\_key/value/compare", and "search", are provided by `WP_Find` as methods
instead, so although they can also be passed to the `filter()` method, it is
better to use these more formal methods as they provide a nicer syntax, more
consistent naming convention, and helpful defaults.

Also note that WordPress's URL argument-passing style (`wp_parse_args`) is not
supported. With `WP_Find`, multiple filters can instead be specified by
calling `filter()` multiple times or by passing an array:

    // Does not work!
    $jan_pages = WP_Find::pages()->filter('year=2010&monthnum=1')->all();

    // Do this instead:
    $jan_pages = WP_Find::pages()
        ->filter('year', '2010')
        ->filter('monthnum', '1')
        ->all();

    // Or this:
    $jan_filter = array(
        'year' => '2010',
        'monthnum' => '1',
    );
    $jan_pages = WP_Find::pages->filter($jan_filter)->all();

Here is a brief summary of the filters available to each type of query and the
type of arguments they expect.

### Posts, pages, and attachments

* `cat`: ID or comma-separated list of IDs of categories (if negative, posts belonging to category ID will be excluded)
* `category_name`: name of category
* `category__and`: array of category IDs, all of which must be present
* `category__in`: array of category IDs, one of which must be present
* `category__not_in`: array of category IDs, none of which may be present
* `tag`: name of a tag; separate multiple tags with ',' for "or", '+' for "and"
* `tag_id`: ID of a tag
* `tag__and`: array of tag IDs, all of which must be present
* `tag__in`: array of tag IDs, one of which must be present
* `tag__not_in`: array of tag IDs, none of which may be present
* `tag_slug__and`: array of tag slugs, all of which must be present
* `tag_slug__in`: array of tag slugs, one of which must be present
* `author`: ID of author (if negative, posts belonging to author ID will be excluded)
* `author_name`: name of author (`user_nicename`)
* `ID`: ID of post or page (use this instead of `p` or `page_id`)
* `name`: name of post or page (use this instead of `pagename`)
* `post_name`: alias for `name`
* `post__in`: array of post IDs to include
* `post__not_in`: array of post IDs to exclude
* `post_status`: one of "publish", "pending", "draft", "future", "private", "trash", "inherit"
* `post_parent`: ID of parent page
* `hour`: hour (from 0 to 23)
* `minute`: minute (from 0 to 60)
* `second`: second (0 to 60)
* `day`: day of the month (from 1 to 31)
* `monthnum`: month number (from 1 to 12)
* `year`: 4 digit year (e.g. 2009)
* `w`: week of the year (from 0 to 53)
* `meta_key`, `meta_value`, `meta_compare`: use `meta()` method instead

### Tags and categories

* `hide_empty`: boolean; if true, empty terms will be hidden (defaults to true)
* `slug`: term slug for tag or category
* `hierarchical`: boolean; if true, hierarchical taxonomy is returned (defaults to true)
* `name__like`: string that term name must start with (prefix match)
* `pad_counts`: if true, children will be included in parent terms' counts (defaults to false)
* `get`: if set to "all", all terms are returned regardless of `hide_empty` and `hierarchical` settings
* `child_of`: ID of parent term that results must be descendents of
* `parent`: ID of parent term that results must be direct children of

### Links

* `category`: comma-separated list of link category IDs
* `category_name`: name of link category
* `hide_invisible`: boolean; if true, only links with `link_visible` set to 'Y' are returned (defaults to true)
* `show_updated`: boolean; if true, an extra column called `link_category_f` is inserted with a UNIX timestamp version of `link_updated` (defaults to false)
* `include`: comma-separated list of link IDs to include
* `exclude`: comma-separated list of link IDs to exclude

## Sorting

Sort order is specified by calling the `order()` method on the query object.
If this is not done, the default will be the same as the underlying WordPress
API function. If `order()` is called with a single argument, that argument is
the sort field, and sorting will be done in ascending order. The second
argument can be "ASC" or "DESC" to specifiy ascending or descending order.

The fields available for sorting depend on the type of query. Here is a
summary:

### Posts, pages, and attachments

* `author`
* `date` (default, descending order)
* `title`
* `modified`
* `menu_order`
* `parent`
* `ID`
* `rand`
* `meta_value` (must be used with `meta()`)
* `none` (WP 2.8+)
* `comment_count` (WP 2.9+)

### Tags and categories

* `name` (default, ascending order)
* `count`
* `none` (uses `term_id`)

### Post/page tags and categories

* `name` (default, ascending order)
* `count`

### Links

* `id`
* `url`
* `name` (default)
* `target`
* `description`
* `owner`
* `rating`
* `updated`
* `rel`
* `notes`
* `rss`
* `length`
* `rand`

## Fields

Each of the factory functions accept an optional argument that specifies what
fields to return when a query is executed. This helps to conserve I/O and
memory consumption and make results simpler and easier to inspect.

Many WordPress users are unaware of the fact that they are loading the entire
contents of many pages when they are only trying to build a few links -- in
order to render their navigation, for instance. This is a result of the fact
that WordPress provides no easy way to exclude post content from the results
of calls to `WP_Query`, `get_posts`, or `query_posts`. For pages, posts, and
attachments, `WP_Find` lets you explicitly specify the fields you want in your
result by passing an array of field names to their factory functions:

    $pages_brief = WP_Find::pages(array('ID', 'post_name'))
        ->limit(5)
        ->all();

This is made possible by temporarily registering a `posts_fields` filter,
invoking `WP_Query`, and then unregistering the filter. Supplying an array of
fields also causes the `suppress_filters` option to `WP_Query` to be disabled,
which is necessary for the filter to work. This may produce surprising effects
if you are using a plugin that also registers filters that affect `WP_Query`.
As a general rule, you can expect that `suppress_filters` will be false if you
specify the fields and true if you don't.

If you specify fields for `posts()`, `pages()`, or `attachments()` queries,
you will want to run your code with error reporting turned up high enough to
include notices:

    error_reporting(E_ALL);

This way, you will be aware of any fields that are being referenced by your
code as well as code internal to WordPress. You may find that you need to
include a few more fields to avoid breaking certain assumptions about the
presence of fields in the result.

Fields can also be passed to the other factory methods, but only as a string
from a predefined set, which typically includes "all", "ids", and "names" as
options. The posts, pages, and attachments queries support this parameter
style as well, for the sake of consistency. The available options are listed
below:

### Posts, pages, and attachments

* `WP_Find::posts(array('ID', 'post_name', ...))`: specify fields as an array
* `WP_Find::posts('all')`: return all fields (also has the effect of disabling `suppress_filters`)
* `WP_Find::posts('ids')`: return IDs only; results will be integers instead of objects
* `WP_Find::posts('names')`: return names only; results will be strings instead of objects

### Tags and categories

* `WP_Find::tags('all')`: return all fields
* `WP_Find::tags('ids')`: return IDs only; results will be integers instead of objects
* `WP_Find::tags('names')`: return names only; results will be strings instead of objects

### Post/page tags and categories

* `WP_Find::post_tags('all')`: return all fields
* `WP_Find::post_tags('ids')`: return IDs only; results will be integers instead of objects
* `WP_Find::post_tags('names')`: return names only; results will be strings instead of objects
* `WP_Find::post_tags('all')`: return all fields, plus the `object_id` field from the term relationships table

### Links

_This feature is not available for links._

## Notes

### Immutability

Query objects are stateless and immutable. This means you can run the same
query multiple times without side-effects, and you can build queries piece by
piece, reusing portions as needed. For example:

    $recent_posts = WP_Find::posts()->order_by('date', 'DESC')->limit(5);
    $recent_news = $recent_posts->filter('tag', 'news');
    $recent_weather = $recent_posts->filter('tag', 'weather');
    $more_weather = $recent_weather->limit(10, 5);

The database will not be queried until a call to `all()`, `one()`, or `get()`,
so these intermediate queries can be reused and combined at no cost of
efficiency. This type of interface may be surprising to those used to a
mutable style:

    // This doesn't do what you might expect.
    $recent_posts = WP_Find::posts();
    $recent_posts->order_by('date', 'DESC');
    $recent_posts->limit(5);

    // Do this instead:
    $recent_posts = WP_Find::posts();
    $recent_posts = $recent_posts->order_by('date', 'DESC');
    $recent_posts = $recent_posts->limit(5);

### Limits

By default, limiting/pagination is turned off on all queries. This means that
the simple call of:

    WP_Find::posts()->all(); // HUGE!

will return every post in the database. Make sure you specify a limit if the
query could potentially return a large amount of data:

    WP_Find::posts()->limit(50)->all();

### SQL Debugging

Any query object can be asked for the SQL it would generate by calling the
`sql()` method. In the case of posts, pages, and attachments queries, this is
determined by inspecting the "request" property of a `WP_Query` instance. For
other queries, the underlying API function is called and `$wpdb->last_query`
is returned.

Due to implementation details, `sql()` always results in the query being run
on the database and the results discarded; as such, it is mainly useful for
debugging or generation of canned SQL queries to be pasted into phpMyAdmin,
etc.
