<?php
/*
Plugin Name: WP-Find
Plugin URI: http://github.com/ramen/wp-find
Description: Alternative query interface for WordPress content
Version: 1.0.1
Author: Dave Benjamin
Author URI: http://ramenlabs.com/
*/

/*
Copyright 2009 Dave Benjamin. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY DAVE BENJAMIN ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL DAVE BENJAMIN OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are
those of the authors and should not be interpreted as representing official
policies, either expressed or implied, of Dave Benjamin.
*/

if (!class_exists('WP_Find')):

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

    function custom($post_type, $fields=null) {
        return new WP_Find($post_type, $fields);        
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

    function terms($taxonomy, $fields=null) {
        return new WP_FindTerms($taxonomy, $fields);        
    }

    function post_tags($fields=null) {
        return new WP_FindPostTerms('post_tag', $fields);
    }

    function post_categories($fields=null) {
        return new WP_FindPostTerms('category', $fields);
    }

    function post_terms($taxonomy, $fields=null) {
        return new WP_FindPostTerms($taxonomy, $fields);        
    }

    function comments() {
        return new WP_FindComments();
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
        if ($results instanceof WP_Error) {
            return $results;
        }
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
        return array_map(array('WP_FindResult', 'post'), $results);
    }

    function one() {
        if (isset($this->options['limit'])) {
            $results = $this->all();
        } else {
            $results = $this->limit(1)->all();
        }
        if ($results instanceof WP_Error) {
            return $results;
        } else {
            return isset($results[0]) ? $results[0] : null;
        }
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
            'no_found_rows' => true,
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
            if (strtoupper($sort) == 'SQL') {
                $args['suppress_filters'] = false;
            } else {
                $args['orderby'] = $field;
                $args['order'] = $sort;
            }
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
        if (!isset($args['post_status'])
            && in_array($this->post_type, array('attachment', 'revision'))) {
            $args['post_status'] = 'inherit';
        }
        return $args;
    }

    function _start_filters() {
        $filters = array();
        if ($this->fields) {
            $filter = array(&$this, '_custom_fields');
            add_filter('posts_fields', $filter);
            $filters['posts_fields'] = $filter;
        }
        if (isset($this->options['order_by'])) {
            list($field, $sort) = $this->options['order_by'];
            if (strtoupper($sort) == 'SQL') {
                $filter = array(&$this, '_order_by_sql');
                add_filter('posts_orderby', $filter);
                $filters['posts_orderby'] = $filter;
            }
        }
        return $filters;
    }

    function _custom_fields() {
        global $wpdb;
        $fields = array();
        if (is_array($this->fields)) {
            foreach ($this->fields as $field) {
                if (preg_match('/^[A-Za-z_]+$/', $field)) {
                    $field = strtolower($field);
                    if ($field == 'id') $field = 'ID';
                    $fields[] = "$wpdb->posts.$field";
                } else {
                    $fields[] = $field;
                }
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
        return implode(', ', $fields);        
    }

    function _order_by_sql() {
        return $this->options['order_by'][0];
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
        $results = get_terms($this->taxonomy, $this->_build_args());
        if ($results instanceof WP_Error) {
            return $results;
        } elseif (isset($results[0]) && is_object($results[0])) {
            return array_map(array('WP_FindResult', 'term'), $results);
        } else {
            return $results;
        }
    }

    function one() {
        if (isset($this->options['limit'])) {
            $results = $this->all();
        } else {
            $results = $this->limit(1)->all();
        }
        if ($results instanceof WP_Error) {
            return $results;
        } else {
            return isset($results[0]) ? $results[0] : null;
        }
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
        $results =
            wp_get_object_terms($id, $this->taxonomy, $this->_build_args());
        if ($results instanceof WP_Error) {
            return $results;
        } elseif (isset($results[0]) && is_object($results[0])) {
            return array_map(array('WP_FindResult', 'term'), $results);
        } else {
            return $results;
        }
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

class WP_FindComments {
    function WP_FindComments($options=array()) {
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
        return new WP_FindComments($options);
    }

    function order_by($field, $sort='ASC') {
        $options = $this->options;
        $options['order_by'] = array($field, $sort);
        return new WP_FindComments($options);
    }

    function limit($limit, $offset=null) {
        $options = $this->options;
        $options['limit'] = array($limit, $offset);
        return new WP_FindComments($options);
    }

    function all() {
        $results = get_comments($this->_build_args());
        if ($results instanceof WP_Error) {
            return $results;
        } elseif (isset($results[0]) && is_object($results[0])) {
            return array_map(array('WP_FindResult', 'comment'), $results);
        } else {
            return $results;
        }
    }

    function one() {
        if (isset($this->options['limit'])) {
            $results = $this->all();
        } else {
            $results = $this->limit(1)->all();
        }
        if ($results instanceof WP_Error) {
            return $results;
        } else {
            return isset($results[0]) ? $results[0] : null;
        }
    }

    function sql() {
        $this->all();
        global $wpdb;
        return $wpdb->last_query;
    }

    function _build_args() {
        $args = array();
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
        $results = get_bookmarks($this->_build_args());
        if ($results instanceof WP_Error) {
            return $results;
        } elseif (isset($results[0]) && is_object($results[0])) {
            return array_map(array('WP_FindResult', 'link'), $results);
        } else {
            return $results;
        }
    }

    function one() {
        if (isset($this->options['limit'])) {
            $results = $this->all();
        } else {
            $results = $this->limit(1)->all();
        }
        if ($results instanceof WP_Error) {
            return $results;
        } else {
            return isset($results[0]) ? $results[0] : null;
        }
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

class WP_FindResult {
    function WP_FindResult($data) {
        foreach ((array) $data as $key => $value) {
            $this->$key = $value;
        }
    }

    function __toString() {
        return print_r($this, true);
    }

    function post($post) {
        return new WP_FindResultPost($post);
    }

    function term($term) {
        return new WP_FindResultTerm($term);
    }

    function comment($comment) {
        return new WP_FindResultComment($comment);
    }

    function link($link) {
        return new WP_FindResultLink($link);
    }
}

class WP_FindResultPost extends WP_FindResult {
    function url() {
        return get_permalink($this->ID);
    }

    function admin_url() {
        return get_edit_post_link($this->ID, '');
    }

    function meta($key='', $single=false) {
        return get_post_meta($this->ID, $key, $single);
    }

    function title() {
        return apply_filters('the_title', $this->post_title);
    }

    function image($size='medium') {
        $image = image_downsize($this->ID, $size);
        if (!$image) return $image;
        return array(
            'url' => $image[0],
            'width' => $image[1],
            'height' => $image[2],
            'is_intermediate' => $image[3],
        );
    }
}

class WP_FindResultTerm extends WP_FindResult {
    function url() {
        return get_term_link($this, $this->taxonomy);
    }
}

class WP_FindResultComment extends WP_FindResult {
    function url() {
        return get_comment_link($this);
    }

    function meta($key='', $single=false) {
        return get_comment_meta($this->comment_ID, $key, $single);
    }
}

class WP_FindResultLink extends WP_FindResult {
    function url() {
        return $this->link_url;
    }
}

endif; // !class_exists('WP_Find')
?>
