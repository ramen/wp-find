<?php
/*
Plugin Name: WP_Find
Plugin URI: http://github.com/ramen/wp-find
Description: Alternative query interface for WordPress content
Version: 1.0
Author: Dave Benjamin
Author URI: http://ramenlabs.com/
*/

class WP_Find {
    function WP_Find($post_type, $fields=null, $options=array()) {
        $this->post_type = $post_type;
        $this->fields = $fields;
        $this->options = $options;
    }

    function pages($fields=null) {
        return new WP_Find('page', $fields);
    }

    function posts($fields=null) {
        return new WP_Find('post', $fields);
    }

    function attachments($fields=null) {
        return new WP_Find('attachment', $fields);
    }

    function tags($fields=null) {
        return new WP_FindTerms('post_tag', $fields);
    }

    function categories($fields=null) {
        return new WP_FindTerms('category', $fields);
    }

    function link_categories($fields=null) {
        return new WP_FindTerms('link_category', $fields);
    }

    function post_tags($fields=null) {
        return new WP_FindPostTerms('post_tag', $fields);
    }

    function post_categories($fields=null) {
        return new WP_FindPostTerms('category', $fields);
    }

    function links() {
        return new WP_FindLinks();
    }

    function filter($key, $value=null) {
        $options = $this->options;
        if (is_array($key)) {
            if (!isset($options['filter'])) {
                $options['filter'] = array();
            }
            $options['filter'] = array_merge($options['filter'], $key);
        } else {
            $options['filter'][$key] = $value;
        }
        return new WP_Find($this->post_type, $this->fields, $options);
    }

    function meta($key, $value, $compare='=') {
        $options = $this->options;
        $options['filter']['meta_key'] = $key;
        $options['filter']['meta_value'] = $value;
        $options['filter']['meta_compare'] = $compare;
        return new WP_Find($this->post_type, $this->fields, $options);
    }

    function search($text) {
        $options = $this->options;
        $options['filter']['s'] = $text;
        return new WP_Find($this->post_type, $this->fields, $options);
    }

    function order_by($field, $sort='ASC') {
        $options = $this->options;
        $options['order_by'] = array($field, $sort);
        return new WP_Find($this->post_type, $this->fields, $options);
    }

    function limit($limit, $offset=null) {
        $options = $this->options;
        $options['limit'] = array($limit, $offset);
        return new WP_Find($this->post_type, $this->fields, $options);
    }

    function all() {
        $filters = $this->_start_filters();
        $query = new WP_Query();
        $results = $query->query($this->_build_args());
        $this->_end_filters($filters);
        if ($this->fields == 'ids') {
            $ids = array();
            foreach ($results as $result) {
                $ids[] = $result->ID;
            }
            return $ids;
        }
        if ($this->fields == 'names') {
            $names = array();
            foreach ($results as $result) {
                $names[] = $result->post_name;
            }
            return $names;
        }
        return $results;
    }

    function one() {
        $results = $this->all();
        return isset($results[0]) ? $results[0] : null;
    }

    function sql() {
        $filters = $this->_start_filters();
        $query = new WP_Query();
        $query->query($this->_build_args());
        $this->_end_filters($filters);
        return $query->request;
    }

    function _build_args() {
        $args = array(
            'post_type' => $this->post_type,
            'nopaging' => true,
            'caller_get_posts' => true,
            'suppress_filters' => true,
        );
        if ($this->fields) {
            $args['suppress_filters'] = false;
        }
        if (isset($this->options['limit'])) {
            list($limit, $offset) = $this->options['limit'];
            $args['nopaging'] = false;
            $args['posts_per_page'] = $limit;
            $args['offset'] = $offset;
        }
        if (isset($this->options['order_by'])) {
            list($field, $sort) = $this->options['order_by'];
            $args['orderby'] = $field;
            $args['order'] = $sort;
        }
        if (isset($this->options['filter'])) {
            $args = array_merge($args, $this->options['filter']);
        }
        if (isset($args['ID'])) {
            $args[$this->post_type == 'page' ? 'page_id' : 'p'] = $args['ID'];
            unset($args['ID']);
        }
        if (isset($args['post_name'])) {
            $args['name'] = $args['post_name'];
            unset($args['post_name']);
        }
        if (isset($args['name']) && $this->post_type == 'page') {
            $args['pagename'] = $args['name'];
            unset($args['name']);
        }
        return $args;
    }

    function _start_filters() {
        global $wpdb;
        $filters = array();
        if ($this->fields) {
            $fields = array();
            if (is_array($this->fields)) {
                foreach ($this->fields as $field) {
                    $field = strtolower($field);
                    if ($field == 'id') $field = 'ID';
                    $fields[] = "$wpdb->posts.$field";
                }
            } else {
                switch ($this->fields) {
                    case 'names':
                        $fields[] = "$wpdb->posts.post_name";
                        // fall-through
                    case 'ids':
                        $fields[] = "$wpdb->posts.ID";
                        $fields[] = "$wpdb->posts.post_type";
                        $fields[] = "$wpdb->posts.post_status";
                        break;
                    case 'all':
                    default:
                        $fields[] = "$wpdb->posts.*";
                        break;
                }
            }
            $fields = implode(', ', $fields);
            $filter = create_function('', "return '$fields';");
            add_filter('posts_fields', $filter);
            $filters['posts_fields'] = $filter;
        }
        return $filters;
    }

    function _end_filters($filters) {
        foreach ($filters as $hook => $callback) {
            remove_filter($hook, $callback);
        }
    }
}

class WP_FindTerms {
    function WP_FindTerms($taxonomy, $fields=null, $options=array()) {
        $this->taxonomy = $taxonomy;
        $this->fields = $fields;
        $this->options = $options;
    }

    function filter($key, $value=null) {
        $options = $this->options;
        if (is_array($key)) {
            if (!isset($options['filter'])) {
                $options['filter'] = array();
            }
            $options['filter'] = array_merge($options['filter'], $key);
        } else {
            $options['filter'][$key] = $value;
        }
        return new WP_FindTerms($this->taxonomy, $this->fields, $options);
    }

    function search($text) {
        $options = $this->options;
        $options['filter']['search'] = $text;
        return new WP_FindTerms($this->taxonomy, $this->fields, $options);
    }

    function order_by($field, $sort='ASC') {
        $options = $this->options;
        $options['order_by'] = array($field, $sort);
        return new WP_FindTerms($this->taxonomy, $this->fields, $options);
    }

    function limit($limit, $offset=null) {
        $options = $this->options;
        $options['limit'] = array($limit, $offset);
        return new WP_FindTerms($this->taxonomy, $this->fields, $options);
    }

    function all() {
        return get_terms($this->taxonomy, $this->_build_args());
    }

    function one() {
        $results = $this->all();
        return isset($results[0]) ? $results[0] : null;
    }

    function sql() {
        $this->all();
        global $wpdb;
        return $wpdb->last_query;
    }

    function _build_args() {
        $args = array();
        if ($this->fields) {
            $args['fields'] = $this->fields;
        }
        if (isset($this->options['limit'])) {
            list($limit, $offset) = $this->options['limit'];
            $args['number'] = $limit;
            $args['offset'] = $offset;
        }
        if (isset($this->options['order_by'])) {
            list($field, $sort) = $this->options['order_by'];
            $args['orderby'] = $field;
            $args['order'] = $sort;
        }
        if (isset($this->options['filter'])) {
            $args = array_merge($args, $this->options['filter']);
        }
        return $args;
    }
}

class WP_FindPostTerms {
    function WP_FindPostTerms($taxonomy, $fields=null, $options=array()) {
        $this->taxonomy = $taxonomy;
        $this->fields = $fields;
        $this->options = $options;
    }

    function order_by($field, $sort='ASC') {
        $options = $this->options;
        $options['order_by'] = array($field, $sort);
        return new WP_FindPostTerms($this->taxonomy, $this->fields, $options);
    }

    function get($id) {
        return wp_get_object_terms($id, $this->taxonomy, $this->_build_args());
    }

    function sql($id) {
        $this->get($id);
        global $wpdb;
        return $wpdb->last_query;
    }

    function _build_args() {
        $args = array();
        if ($this->fields) {
            $args['fields'] = $this->fields;
        }
        if (isset($this->options['order_by'])) {
            list($field, $sort) = $this->options['order_by'];
            $args['orderby'] = $field;
            $args['order'] = $sort;
        }
        return $args;
    }
}

class WP_FindLinks {
    function WP_FindLinks($options=array()) {
        $this->options = $options;
    }

    function filter($key, $value=null) {
        $options = $this->options;
        if (is_array($key)) {
            if (!isset($options['filter'])) {
                $options['filter'] = array();
            }
            $options['filter'] = array_merge($options['filter'], $key);
        } else {
            $options['filter'][$key] = $value;
        }
        return new WP_FindLinks($options);
    }

    function search($text) {
        $options = $this->options;
        $options['filter']['search'] = $text;
        return new WP_FindLinks($options);
    }

    function order_by($field, $sort='ASC') {
        $options = $this->options;
        $options['order_by'] = array($field, $sort);
        return new WP_FindLinks($options);
    }

    function limit($limit) {
        $options = $this->options;
        $options['limit'] = $limit;
        return new WP_FindLinks($options);
    }

    function all() {
        return get_bookmarks($this->_build_args());
    }

    function one() {
        $results = $this->all();
        return isset($results[0]) ? $results[0] : null;
    }

    function sql() {
        $this->all();
        global $wpdb;
        return $wpdb->last_query;
    }

    function _build_args() {
        $args = array();
        if (isset($this->options['order_by'])) {
            list($field, $sort) = $this->options['order_by'];
            $args['orderby'] = $field;
            $args['order'] = $sort;
        }
        if (isset($this->options['limit'])) {
            $args['limit'] = $this->options['limit'];
        }
        if (isset($this->options['filter'])) {
            $args = array_merge($args, $this->options['filter']);
        }
        return $args;
    }
}
?>
