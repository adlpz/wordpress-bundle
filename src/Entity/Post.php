<?php

namespace Metabolism\WordpressBundle\Entity;

use Metabolism\WordpressBundle\Factory\Factory;
use Metabolism\WordpressBundle\Factory\PostFactory;
use Metabolism\WordpressBundle\Factory\TermFactory;
use Metabolism\WordpressBundle\Repository\PostRepository;

/**
 * Class Post
 *
 * @package Metabolism\WordpressBundle\Entity
 */
class Post extends Entity
{
	public $entity = 'post';

    public $comment_status;
    public $comment_count;
    public $menu_order;
    public $password;
    public $slug;
	public $status;
    public $title;
	public $type;
	public $public;

	/** @var Image|bool */
	protected $thumbnail;
	/** @var Post[] */
	protected $ancestors;
	/** @var Post[] */
	protected $children;
	/** @var Post[] */
	protected $siblings;
	/** @var Post */
	protected $parent;
	/** @var User */
	protected $author;
	protected $template;
	protected $content;
	protected $class;
	protected $classes;
	protected $link;
	protected $sticky;
	protected $excerpt;
	/** @var Post */
	protected $next;
	/** @var Post */
	protected $prev;
	protected $date;
	protected $date_gmt;
	protected $modified;
	protected $modified_gmt;

	/** @var \WP_Post|bool */
	protected $post;

    public function __toString(){

        return $this->title??'Invalid post';
    }

	/**
	 * Post constructor.
	 *
	 * @param null $id
	 */
	public function __construct($id = null) {

		if( $post = $this->get($id) ) {

			$this->ID = $post->ID;
			$this->comment_status = $post->comment_status;
			$this->comment_count = $post->comment_count;
			$this->menu_order = $post->menu_order;
			$this->password = $post->post_password;
			$this->slug = $post->post_name;
			$this->status = $post->post_status;
			$this->title = $post->post_title;
			$this->type = $post->post_type;
			$this->public = is_post_type_viewable($post->post_type);

			$this->loadMetafields($this->ID, 'post');
        }
	}


	/**
	 * @param $pid
	 * @return \WP_Post|false
	 */
	protected function get($pid) {

		if( $post = get_post($pid) ) {

			if( is_wp_error($post) || !$post )
				return false;

			$this->post = $post;
		}

		return $post;
	}

	/**
	 * Get post date
	 *
	 * @param $format
	 * @return mixed|string|void
	 */
	public function getDate($format=true){

		if( is_null($this->date) && $format )
			$this->date = $this->formatDate($this->post->post_date);

		return $format ? $this->date : $this->post->post_date;
	}

	/**
	 * Get post modified date
	 *
	 * @param $format
	 * @return mixed|string|void
	 */
	public function getModified($format=true){

		if( is_null($this->modified) && $format )
			$this->modified = $this->formatDate($this->post->post_modified);

		return $format ? $this->modified : $this->post->post_modified;
	}

	/**
	 * Get post date gmt
	 *
	 * @param $format
	 * @return mixed|string|void
	 */
	public function getDateGmt($format=true){

		if( is_null($this->date_gmt) && $format )
			$this->date_gmt = $this->formatDate($this->post->post_date_gmt);

		return $format ? $this->date_gmt : $this->post->post_date_gmt;
	}

	/**
	 * Get post modified date gmt
	 *
	 * @param $format
	 * @return string
	 */
	public function getModifiedGmt($format=true){

		if( is_null($this->modified_gmt) && $format )
			$this->modified_gmt = $this->formatDate($this->post->post_modified_gmt);

		return $format ? $this->modified_gmt : $this->post->post_modified_gmt;
	}

	/**
	 * Get excerpt
	 *
	 * @return string
	 */
	public function getExcerpt(){

		if( is_null($this->excerpt) )
			$this->excerpt = apply_filters( 'get_the_excerpt', $this->post->post_excerpt, $this->post );

		return $this->excerpt;
	}

	/**
	 * Get post author
	 *
	 * @return User
	 */
	public function getAuthor(){

		if( is_null($this->author) )
			$this->author = Factory::create($this->post->post_author, 'user');

		return $this->author;
	}

	/**
	 * Get post class
	 *
	 * @return string
	 */
	public function getClass(){

		if( is_null($this->class) )
			$this->class = implode(' ', $this->getClasses());

		return $this->class;
	}

	/**
	 * Get post classes
	 *
	 * @return string[]
	 */
	public function getClasses(){

		if( is_null($this->classes) )
			$this->classes = get_post_class('', $this->post);

		return $this->classes;
	}

	/**
	 * Get is sticky
	 *
	 * @return bool
	 */
	public function getSticky(){

		if( is_null($this->sticky) )
			$this->sticky = is_sticky($this->post->ID);

		return $this->sticky;
	}

	/**
	 * Get post link
	 *
	 * @return false|string
	 */
	public function getLink(){

		if( is_null($this->link) && $this->public )
			$this->link = get_permalink( $this->post );

		return $this->link;
	}

	/**
	 * Get filtered content
	 *
	 * @return string
	 */
	public function getContent(){

		if( is_null($this->content) ){

			$post_content = get_the_content(null, false, $this->post);
			$post_content = apply_filters( 'the_content', $post_content );
			$post_content = str_replace( ']]>', ']]&gt;', $post_content );

			$this->content = $post_content;
		}

		return $this->content;

	}

	/**
	 * Get template
	 *
	 * @return false|string
	 */
	public function getTemplate(){

		if( is_null($this->template) )
			$this->template = get_page_template_slug( $this->post );

		return $this->template;
	}

	/**
	 * Get thumbnail
	 *
	 * @return false|Image
	 */
	public function getThumbnail(){

		if( is_null($this->thumbnail) ){

			$post_thumbnail_id = get_post_thumbnail_id( $this->post );

			if( $post_thumbnail_id )
				$this->thumbnail = Factory::create($post_thumbnail_id, 'image');
		}

		return $this->thumbnail;
	}


	/**
	 * Get sibling post using date order
	 *
	 * @param $direction
	 * @param $in_same_term
	 * @param $excluded_terms
	 * @param $taxonomy
	 * @return Post|false
	 */
	protected function getSibling($direction, $in_same_term = false , $excluded_terms = '', $taxonomy = 'category'){

		global $post;

		$old_global = $post;
		$post = $this->post;

		if( $direction === 'prev')
			$sibling = get_previous_post($in_same_term , $excluded_terms, $taxonomy);
		else
			$sibling = get_next_post($in_same_term , $excluded_terms, $taxonomy);

		$post = $old_global;

		if( $sibling instanceof \WP_Post)
			return PostFactory::create($sibling->ID);
		else
			return false;
	}


	/**
	 * Get next post
	 *
	 * See: https://developer.wordpress.org/reference/functions/get_next_post/
	 *
	 * @param bool $in_same_term
	 * @param string $excluded_terms
	 * @param string $taxonomy
	 * @return Post|false
	 */
	public function getNext($in_same_term = false, $excluded_terms = '', $taxonomy = 'category') {

		if( is_null($this->next) )
			$this->next = $this->getSibling('next', $in_same_term , $excluded_terms, $taxonomy);

		return $this->next;
	}


	/**
	 * Get child posts
	 *
	 * @return Post[]|false
	 */
	public function getChildren() {

        if( is_null($this->children) ){

            $postRepository = new PostRepository();
            $this->children = $postRepository->findBy(['post_parent'=>$this->ID, 'post_type'=>$this->type],null, -1);
        }

		return $this->children;
	}


	/**
	 * Get siblings
	 *
	 * @return Post[]|false
	 */
	public function getSiblings() {

        if( is_null($this->siblings) ){

            $postRepository = new PostRepository();
            $this->siblings = $postRepository->findBy(['post_parent'=>$this->post->post_parent, 'post_type'=>$this->type, 'post__not_in'=>[$this->ID]], null, -1);
        }

		return $this->siblings;
	}


	/**
	 * Has parent post
	 *
	 * @return bool
	 */
	public function hasParent() {

		return $this->post->post_parent > 0;
	}


	/**
	 * Get parent post
	 *
	 * @return Post|false
	 */
	public function getParent() {

		if( is_null($this->parent) )
			$this->parent = PostFactory::create($this->post->post_parent);

		return $this->parent;
	}

    /**
     * Get post ancestors
     *
     * @param $reverse
     * @return array|Post[]
     */
    public function getAncestors($reverse=true){

		if( is_null($this->ancestors) ){

			$parents_id = get_post_ancestors($this->ID);

			if( $reverse )
				$parents_id = array_reverse($parents_id);

			$ancestors = [];

			foreach ($parents_id as $post_id)
				$ancestors[] = PostFactory::create($post_id);

			$this->ancestors = $ancestors;
		}

		return $this->ancestors;
	}


	/**
	 * Get post comments
	 *
	 * @param array $args
	 * @return Comment[]|[]
	 */
	public function getComments($args=[]) {

		$default_args = [
			'status'=> 'approve',
			'number' => '5'
		];

		$args = array_merge($default_args, $args);

		$args['post_id'] = $this->ID;
		$args['type'] = 'comment';
		$args['parent'] = 0;
		$args['fields'] = 'ids';

		$comments_id = get_comments($args);

		$comments = [];

		foreach ($comments_id as $comment_id)
		{
            /** @var Comment $comment */
            $comment = Factory::create($comment_id, 'comment');
			$comments[$comment_id] = $comment;
		}

		return $comments;
	}


	/**
	 * Get previous post
	 * See: https://developer.wordpress.org/reference/functions/get_previous_post/
	 *
	 * @param bool $in_same_term
	 * @param string $excluded_terms
	 * @param string $taxonomy
	 * @return Post|false
	 */
	public function getPrev($in_same_term = false, $excluded_terms = '', $taxonomy = 'category') {

		if( is_null($this->prev) )
			$this->prev = $this->getSibling('prev', $in_same_term , $excluded_terms, $taxonomy);

		return $this->prev;
	}


	/**
	 * Get primary term
	 * See: https://codex.wordpress.org/Function_Reference/wp_get_post_terms
	 *
	 * @param string $tax
	 * @param array $args
	 * @return Term|bool
	 */
	public function getTerm( $tax='category', $args=[] ) {

		$args['number'] = 1;
		$terms = $this->getTerms($tax, $args);

		if( is_array($terms) && count($terms) )
			return end($terms);
		else
			return false;
	}

	/**
	 * Get term list
	 * See : https://developer.wordpress.org/reference/classes/wp_term_query/__construct/
	 *
	 * @param string $tax
	 * @param array $args
	 * @return Term[]|[]
	 */
	public function getTerms( $tax='category', $args=[] ) {

		$args['fields'] = 'ids';

		$taxonomies = array();

		if ( is_array($tax) )
		{
			$taxonomies = $tax;
		}
		if ( is_string($tax) )
		{
			if ( in_array($tax, ['all', 'any', '']) )
				$taxonomies = get_object_taxonomies($this->type);
			else
				$taxonomies = [$tax];
		}

		$term_array = [];

		foreach ( $taxonomies as $taxonomy )
		{
			if ( in_array($taxonomy, ['tag', 'tags']) )
				$taxonomy = 'post_tag';

			if ( $taxonomy == 'categories' )
				$taxonomy = 'category';

			$terms = wp_get_post_terms($this->ID, $taxonomy, $args);

			if( is_wp_error($terms) ){

                if( (!isset($args['hierarchical']) || $args['hierarchical']) && count($taxonomies)>1 )
                    $term_array[$taxonomy][] = $terms->get_error_message();
                else
                    $term_array[] = $terms->get_error_message();
			}
			else
			{
				foreach ($terms as $term){

					if( (!isset($args['hierarchical']) || $args['hierarchical']) && count($taxonomies)>1 )
						$term_array[$taxonomy][] = TermFactory::create($term);
					else
						$term_array[] = TermFactory::create($term);
				}
			}
		}

		return $term_array;
	}

	/**
	 * @param string $tax
	 * @param bool $args
	 * @return Term[]
	 * @deprecated
	 */
	public function get_terms( $tax='', $args=false ) { return $this->getTerms($tax, $args); }

	/**
	 * @param string $tax
	 * @param bool $args
	 * @return bool|Term
	 * @deprecated
	 */
	public function get_term( $tax='', $args=false ) { return $this->getTerm($tax, $args); }
}
