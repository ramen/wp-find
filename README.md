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
