<?php
/**
 * The SEO Framework plugin
 * Copyright (C) 2015 - 2016 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class AutoDescription_Generate_Title
 *
 * Generates title SEO data based on content.
 *
 * @since 2.6.0
 */
class AutoDescription_Generate_Title extends AutoDescription_Generate_Description {

	/**
	 * Constructor, load parent constructor
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Get the title. God function.
	 * Always use this function for the title unless you're absolutely sure what you're doing.
	 *
	 * This function is used for all these: Taxonomies and Terms, Posts, Pages, Blog, front page, front-end, back-end.
	 *
	 * @since 1.0.0
	 *
	 * Params required wp_title filter :
	 * @param string $title The Title to return
	 * @param string $sep The Title sepeartor
	 * @param string $seplocation The Title sepeartor location ( accepts 'left' or 'right' )
	 *
	 * @since 2.4.0:
	 * @param array $args : accepted args : {
	 * 		@param int term_id The Taxonomy Term ID when taxonomy is also filled in. Else post ID.
	 * 		@param string taxonomy The Taxonomy name.
	 * 		@param bool page_on_front Page on front condition for example generation.
	 * 		@param bool placeholder Generate placeholder, ignoring options.
	 * 		@param bool notagline Generate title without tagline.
	 * 		@param bool meta Ignore doing_it_wrong. Used in og:title/twitter:title
	 * 		@param bool get_custom_field Do not fetch custom title when false.
	 * 		@param bool description_title Fetch title for description.
	 * 		@param bool is_front_page Fetch front page title.
	 * }
	 *
	 * @return string $title Title
	 */
	public function title( $title = '', $sep = '', $seplocation = '', $args = array() ) {

		//* Use WordPress default feed title.
		if ( $this->is_feed() )
			return trim( $title );

		$args = $this->reparse_title_args( $args );

		/**
		 * Return early if the request is the Title only (without tagline/blogname).
		 */
		if ( $args['notagline'] )
			return $this->build_title_notagline( $args );

		/**
		 * Add doing it wrong notice for better SEO consistency.
		 * Only when in wp_title.
		 *
		 * @since 2.2.5
		 */
		if ( false === $args['meta'] && false === $this->is_admin() ) {
			if ( false === $this->current_theme_supports_title_tag() && doing_filter( 'wp_title' ) ) {
				if ( $seplocation ) {
					//* Set doing it wrong parameters.
					$this->tell_title_doing_it_wrong( $title, $sep, $seplocation, false );
					//* And echo them.
					add_action( 'wp_footer', array( $this, 'tell_title_doing_it_wrong' ), 20 );

					//* Notify cache.
					$this->title_doing_it_wrong = true;

					//* Notify transients
					$this->set_theme_dir_transient( false );

					return $this->build_title_doingitwrong( $title, $sep, $seplocation, $args );
				} else if ( $sep ) {
					//* Set doing it wrong parameters.
					$this->tell_title_doing_it_wrong( $title, $sep, $seplocation, false );
					//* And echo them.
					add_action( 'wp_footer', array( $this, 'tell_title_doing_it_wrong' ), 20 );

					//* Notify cache.
					$this->title_doing_it_wrong = true;

					//* Notify transients
					$this->set_theme_dir_transient( false );

					//* Title is empty.
					$args['empty_title'] = true;

					return $this->build_title_doingitwrong( $title, $sep, $seplocation, $args );
				}
			}
		}

		//* Notify cache to keep using the same output. We're doing it right :).
		if ( ! isset( $this->title_doing_it_wrong ) )
			$this->title_doing_it_wrong = false;

		//* Set transient to true if the theme is doing it right.
		if ( false === $this->title_doing_it_wrong )
			$this->set_theme_dir_transient( true );

		//* Empty title and rebuild it.
		return $this->build_title( $title = '', $seplocation, $args );
	}

	/**
	 * Escapes and beautifies title.
	 *
	 * @param string $title The title to escape and beautify.
	 * @param bool $trim Whether to trim the title from whitespaces.
	 *
	 * @since 2.5.2
	 *
	 * @return string Escaped and beautified title.
	 */
	public function escape_title( $title = '', $trim = true ) {

		$title = wptexturize( $title );
		$title = convert_chars( $title );
		$title = esc_html( $title );
		$title = capital_P_dangit( $title );
		$title = $trim ? trim( $title ) : $title;

		return $title;
	}

	/**
	 * Parse and sanitize title args.
	 *
	 * @param array $args required The passed arguments.
	 * @param array $defaults The default arguments.
	 * @param bool $get_defaults Return the default arguments. Ignoring $args.
	 *
	 * @applies filters the_seo_framework_title_args : {
	 * 		@param int term_id The Taxonomy Term ID when taxonomy is also filled in. Else post ID.
	 * 		@param string taxonomy The Taxonomy name.
	 * 		@param bool page_on_front Page on front condition for example generation.
	 * 		@param bool notagline Generate title without tagline.
	 * 		@param bool meta Ignore doing_it_wrong. Used in og:title/twitter:title
	 * 		@param bool get_custom_field Do not fetch custom title when false.
	 * 		@param bool description_title Fetch title for description.
	 * 		@param bool is_front_page Fetch front page title.
	 * }
	 *
	 * @since 2.4.0
	 * @return array $args parsed args.
	 */
	public function parse_title_args( $args = array(), $defaults = array(), $get_defaults = false ) {

		//* Passing back the defaults reduces the memory usage.
		if ( empty( $defaults ) ) {
			$defaults = array(
				'term_id' 			=> $this->get_the_real_ID(),
				'taxonomy' 			=> '',
				'page_on_front'		=> false,
				'notagline' 		=> false,
				'meta' 				=> false,
				'get_custom_field'	=> true,
				'description_title'	=> false,
				'is_front_page'		=> false,
				'escape'			=> true,
			);

			//* @since 2.5.0
			$defaults = (array) apply_filters( 'the_seo_framework_title_args', $defaults, $args );
		}

		//* Return early if it's only a default args request.
		if ( $get_defaults )
			return $defaults;

		//* Array merge doesn't support sanitation. We're simply type casting here.
		$args['term_id'] 			= isset( $args['term_id'] ) 			? (int) $args['term_id'] 			: $defaults['term_id'];
		$args['taxonomy'] 			= isset( $args['taxonomy'] ) 			? (string) $args['taxonomy'] 		: $defaults['taxonomy'];
		$args['page_on_front'] 		= isset( $args['page_on_front'] ) 		? (bool) $args['page_on_front'] 	: $defaults['page_on_front'];
		$args['notagline'] 			= isset( $args['notagline'] ) 			? (bool) $args['notagline'] 		: $defaults['notagline'];
		$args['meta'] 				= isset( $args['meta'] ) 				? (bool) $args['meta'] 				: $defaults['meta'];
		$args['get_custom_field'] 	= isset( $args['get_custom_field'] ) 	? (bool) $args['get_custom_field'] 	: $defaults['get_custom_field'];
		$args['description_title'] 	= isset( $args['description_title'] ) 	? (bool) $args['description_title'] : $defaults['description_title'];
		$args['is_front_page'] 		= isset( $args['is_front_page'] ) 		? (bool) $args['is_front_page'] 	: $defaults['is_front_page'];
		$args['escape'] 			= isset( $args['escape'] ) 				? (bool) $args['escape'] 			: $defaults['escape'];

		return $args;
	}

	/**
	 * Reparse title args.
	 *
	 * @param array $args required The passed arguments.
	 *
	 * @since 2.6.0
	 * @return array $args parsed args.
	 */
	public function reparse_title_args( $args = array() ) {

		$default_args = $this->parse_title_args( '', '', true );

		if ( is_array( $args ) ) {
			if ( empty( $args ) ) {
				$args = $default_args;
			} else {
				$args = $this->parse_title_args( $args, $default_args );
			}
		} else {
			//* Old style parameters are used. Doing it wrong.
			$this->_doing_it_wrong( __CLASS__ . '::' . __FUNCTION__, 'Use $args = array() for parameters.', '2.5.0' );
			$args = $default_args;
		}

		return $args;
	}

	/**
	 * Build the title based on input, without tagline.
	 *
	 * @param array $args : accepted args : {
	 * 		@param int term_id The Taxonomy Term ID
	 * 		@param bool placeholder Generate placeholder, ignoring options.
	 * 		@param bool page_on_front Page on front condition for example generation
	 * }
	 *
	 * @since 2.4.0
	 *
	 * @return string Title without tagline.
	 */
	protected function build_title_notagline( $args = array() ) {

		$title = $this->do_title_pre_filter( '', $args, false );

		if ( empty( $title ) )
			$title = $this->get_notagline_title( $args );

		if ( empty( $title ) )
			$title = $this->untitled();

		$title = $this->do_title_pro_filter( $title, $args, false );

		if ( $args['escape'] )
			$title = $this->escape_title( $title );

		return $title;
	}

	/**
	 * Build the title based on input, without tagline.
	 * Note: Not escaped.
	 *
	 * @param array $args : accepted args : {
	 * 		@param int term_id The Taxonomy Term ID
	 * 		@param bool placeholder Generate placeholder, ignoring options.
	 * 		@param bool page_on_front Page on front condition for example generation
	 * }
	 *
	 * @since 2.6.0
	 *
	 * @return string Title without tagline.
	 */
	protected function get_notagline_title( $args = array() ) {

		$title = '';

		//* Fetch title from custom fields.
		if ( $args['get_custom_field'] )
			$title = $this->get_custom_field_title( $title, $args['term_id'], $args['taxonomy'] );

		//* Generate the Title if empty or if home.
		if ( empty( $title ) )
			$title = (string) $this->generate_title( $args, false );

		return $title;
	}

	/**
	 * Build the title based on input for themes that are doing it wrong.
	 * Pretty much a duplicate of build_title but contains many more variables.
	 * Keep this in mind.
	 *
	 * @param string $title The Title to return
	 * @param string $sep The Title sepeartor
	 * @param string $seplocation The Title sepeartor location ( accepts 'left' or 'right' )
	 * @param array $args : accepted args : {
	 * 		@param int term_id The Taxonomy Term ID
	 * 		@param string taxonomy The Taxonomy name
	 * 		@param bool placeholder Generate placeholder, ignoring options.
	 * 		@param bool get_custom_field Do not fetch custom title when false.
	 * }
	 *
	 * @since 2.4.0
	 *
	 * @return string $title Title
	 */
	public function build_title_doingitwrong( $title = '', $sep = '', $seplocation = '', $args = array() ) {

		if ( $this->the_seo_framework_debug ) $this->debug_init( __CLASS__, __FUNCTION__, true, $debug_key = microtime(true), get_defined_vars() );

		/**
		 * Empty the title, because most themes think they 'know' how to SEO the front page.
		 * Because, most themes know how to make the title 'pretty'.
		 * And therefor add all kinds of stuff.
		 *
		 * Moved up and return early to reduce processing.
		 * @since 2.3.8
		 */
		if ( $this->is_front_page() )
			return $title = '';

		$args = $this->reparse_title_args( $args );

		/**
		 * When using an empty wp_title() function, outputs are unexpected.
		 * This small piece of code will fix all that.
		 * By removing the separator from the title and adding the blog name always to the right.
		 * Which is always the case with doing_it_wrong.
		 *
		 * @thanks JW_ https://wordpress.org/support/topic/wp_title-problem-bug
		 * @since 2.4.3
		 */
		if ( isset( $args['empty_title'] ) ) {
			$title = trim( str_replace( $sep, '', $title ) );
			$seplocation = 'right';
		}

		$blogname = $this->get_blogname();

		/**
		 * Don't add/replace separator when false.
		 *
		 * @applies filters the_seo_framework_doingitwrong_add_sep
		 *
		 * @since 2.4.2
		 */
		$add_sep = (bool) apply_filters( 'the_seo_framework_doingitwrong_add_sep', true );

		$sep_replace = false;
		//* Maybe remove separator.
		if ( $add_sep && ( $sep || $title ) ) {
			$sep_replace = true;
			$sep_to_replace = (string) $sep;
		}

		//* Fetch the title as is.
		$title = $this->get_notagline_title( $args );

		/**
		 * Applies filters the_seo_framework_title_separator : String The title separator
		 */
		if ( $add_sep )
			$sep = $this->get_title_separator();

		/**
		 * Add $sep_to_replace
		 *
		 * @since 2.3.8
		 */
		if ( $sep_replace ) {
			//* Title always contains something at this point.
			$tit_len = mb_strlen( $title );

			/**
			 * Prevent double separator on date archives.
			 * This will cause manual titles with the same separator at the end to be removed.
			 * Then again, update your theme. D:
			 *
			 * A separator is at least 2 long (space + separator).
			 *
			 * @param string $sep_to_replace Already confirmed to contain the old sep string.
			 *
			 * Now also considers seplocation.
			 * @since 2.4.1
			 */
			if ( $sep_to_replace ) {

				$sep_to_replace_length = mb_strlen( $sep_to_replace );

				if ( 'right' === $seplocation ) {
					if ( $tit_len > $sep_to_replace_length && ! mb_strpos( $title, $sep_to_replace, $tit_len - $sep_to_replace_length ) )
						$title = $title . ' ' . $sep_to_replace;
				} else {
					if ( $tit_len > $sep_to_replace_length && ! mb_strpos( $title, $sep_to_replace, $sep_to_replace_length ) )
						$title = $sep_to_replace . ' ' . $title;
				}
			}

			/**
			 * Convert characters to easier match and prevent removal of matching entities and title characters.
			 * Reported by Riccardo: https://wordpress.org/support/topic/problem-with-post-titles
			 * @since 2.5.2
			 */
			$sep_to_replace = html_entity_decode( $sep_to_replace );
			$title = html_entity_decode( $title );

			/**
			 * Now also considers seplocation.
			 * @since 2.4.1
			 */
			if ( 'right' === $seplocation ) {
				$title = trim( rtrim( $title, "$sep_to_replace " ) ) . " $sep ";
			} else {
				$title = " $sep " . trim( ltrim( $title, " $sep_to_replace" ) );
			}

		} else {
			$title = trim( $title ) . " $sep ";
		}

		if ( ! $args['description_title'] )
			$title = $this->add_title_protection( $title, $args['term_id'] );

		if ( $args['escape'] )
			$title = $this->escape_title( $title, false );

		if ( $this->the_seo_framework_debug ) $this->debug_init( __CLASS__, __FUNCTION__, false, $debug_key, array( 'title_output' => $title ) );

		return $title;
	}

	/**
	 * Build the title based on input.
	 *
	 * @param string $title The Title to return
	 * @param string $seplocation The Title sepeartor location ( accepts 'left' or 'right' )
	 * @param array $args : accepted args : {
	 * 		@param int 		term_id The Taxonomy Term ID
	 * 		@param string 	taxonomy The Taxonomy name
	 * 		@param bool 	page_on_front Page on front condition for example generation
	 * 		@param bool 	placeholder Generate placeholder, ignoring options.
	 * 		@param bool 	get_custom_field Do not fetch custom title when false.
	 * 		@param bool 	is_front_page Fetch front page title.
	 * }
	 *
	 * @since 2.4.0
	 *
	 * @return string $title Title
	 */
	public function build_title( $title = '', $seplocation = '', $args = array() ) {

		if ( $this->the_seo_framework_debug ) $this->debug_init( __CLASS__, __FUNCTION__, true, $debug_key = microtime(true), get_defined_vars() );

		$args = $this->reparse_title_args( $args );

		/**
		 * Overwrite title here, prevents duplicate title issues, since we're working with a filter.
		 * @since 2.2.2
		 * Use filter title.
		 * @since 2.6.0
		 */
		$title = $this->do_title_pre_filter( '', $args, false );
		$blogname = '';

		$is_front_page = $this->is_front_page() || $args['page_on_front'] || $this->is_static_frontpage( $args['term_id'] ) ? true : false;

		$seplocation = $this->get_title_seplocation( $seplocation );

		/**
		 * Generate the Title if empty or if home.
		 *
		 * Generation of title has acquired its own functions.
		 * @since 2.3.4
		 */
		if ( $is_front_page ) {
			$generated = (array) $this->generate_home_title( $args['get_custom_field'], $seplocation, '', false );

			if ( $generated && is_array( $generated ) ) {
				if ( empty( $title ) )
					$title = $generated['title'] ? (string) $generated['title'] : $title;

				$blogname = $generated['blogname'] ? (string) $generated['blogname'] : $blogname;
				$seplocation = $generated['seplocation'] ? (string) $generated['seplocation'] : $seplocation;
			}
		} else {
			//* Fetch the title as is.
			if ( empty( $title ) )
				$title = $this->get_notagline_title( $args );

			$blogname = $this->get_blogname();
		}

		/**
		 * From WordPress core get_the_title.
		 * Bypasses get_post() function object which causes conflict with some themes and plugins.
		 *
		 * Also bypasses the_title filters.
		 * And now also works in admin. It gives you a true representation of its output.
		 *
		 * Title for the description bypasses sanitation and additions.
		 *
		 * @since 2.4.1
		 */
		if ( ! $args['description_title'] ) {

			$title = $this->add_title_protection( $title, $args['term_id'] );
			$title = $this->add_title_pagination( $title );

			if ( $is_front_page ) {
				if ( $this->home_page_add_title_tagline() )
					$title = $this->process_title_additions( $blogname, $title, $seplocation );
			} else {
				if ( $this->add_title_additions() )
					$title = $this->process_title_additions( $title, $blogname, $seplocation );
			}

		}

		$title = $this->do_title_pro_filter( $title, $args, false );

		if ( $args['escape'] )
			$title = $this->escape_title( $title );

		if ( $this->the_seo_framework_debug ) $this->debug_init( __CLASS__, __FUNCTION__, false, $debug_key, array( 'title_output' => $title ) );

		return $title;
	}

	/**
	 * Fetches title from special fields, like other plugins with special queries.
	 * Used before and has priority over custom fields.
	 * Front end only.
	 *
	 * @since 2.5.2
	 *
	 * @return string $title Title from Special Field.
	 */
	public function title_from_special_fields() {

		$title = '';

		if ( false === $this->is_admin() ) {
			if ( $this->is_ultimate_member_user_page() && um_is_core_page( 'user' ) && um_get_requested_user() ) {
				$title = um_user( 'display_name' );
			}
		}

		return $title;
	}

	/**
	 * Generate the title based on query conditions.
	 *
	 * @since 2.3.4
	 *
	 * @param array $args The Title Args.
	 * @param bool $escape Parse Title through saninitation calls.
	 *
	 * @staticvar array $cache : contains $title strings.
	 * @since 2.6.0
	 *
	 * @return string $title The Generated Title.
	 */
	public function generate_title( $args = array(), $escape = true ) {

		$args = $this->reparse_title_args( $args );

		$title = '';
		$id = $args['term_id'];
		$taxonomny = $args['taxonomy'];

		static $cache = array();

		if ( isset( $cache[$id][$taxonomny] ) )
			$title = $cache[$id][$taxonomny];

		if ( empty( $title ) ) {

			if ( $this->is_archive() ) {
				if ( ( $id && $taxonomny ) || $this->is_category() || $this->is_tag() || $this->is_tax() ) {
					$title = $this->title_for_terms( $args, false );
				} else {
					$term = get_queried_object();
					/**
					 * Get all other archive titles
					 * @since 2.5.2
					 */
					$title = $this->get_the_real_archive_title( $term, $args );
				}

			}

			$title = $this->get_the_404_title( $title );
			$title = $this->get_the_search_title( $title, false );

			//* Fetch the post title if no title is found.
			if ( empty( $title ) )
				$title = $this->post_title_from_ID( $id );

			//* You forgot to enter a title "anywhere"!
			if ( empty( $title ) )
				$title = $this->untitled();

		}

		if ( $escape )
			$title = $this->escape_title( $title, false );

		return $title;
	}

	/**
	 * Generate the title based on conditions for the home page.
	 *
	 * @since 2.3.4
	 * @access private
	 *
	 * @param bool $get_custom_field Fetch Title from Custom Fields.
	 * @param string $seplocation The separator location
	 * @param string $deprecated Deprecated: The Home Page separator location
	 * @param bool $escape Parse Title through saninitation calls.
	 * @param bool $get_option Whether to fetch the SEO Settings option.
	 *
	 * @return array {
	 *		'title' => (string) $title : The Generated Title
	 *		'blogname' => (string) $blogname : The Generated Blogname
	 *		'add_tagline' => (bool) $add_tagline : Whether to add the tagline
	 *		'seplocation' => (string) $seplocation : The Separator Location
	 *	}
	 */
	public function generate_home_title( $get_custom_field = true, $seplocation = '', $deprecated = '', $escape = true, $get_option = true ) {

		$add_tagline = $this->home_page_add_title_tagline();

		/**
		 * Add tagline or not based on option
		 *
		 * @since 2.2.2
		 */
		if ( $add_tagline ) {
			/**
			 * Tagline based on option.
			 * @since 2.3.8
			 */
			$blogname = $this->get_option( 'homepage_title_tagline' );
			$blogname = $blogname ? $blogname : $this->get_blogdescription();
		} else {
			$blogname = '';
		}

		/**
		 * Render from function
		 * @since 2.2.8
		 */
		$title = $this->title_for_home( '', $get_custom_field, false, $get_option );
		$seplocation = $this->get_home_title_seplocation( $seplocation );

		if ( $escape ) {
			$title = $this->escape_title( $title, false );
			$blogname = $this->escape_title( $blogname, false );
		}

		return array(
			'title' => $title,
			'blogname' => $blogname,
			'add_tagline' => $add_tagline,
			'seplocation' => $seplocation
		);
	}

	/**
	 * Gets the title for the static home page.
	 * Essentially falling back to the blogname. Not to be confused with $blogname.
	 *
	 * @since 2.2.8
	 * @access private
	 * @see $this->generate_home_title()
	 *
	 * @param string $home_title The fallback title.
	 * @param bool $get_custom_field Fetch Title from InPost Custom Fields.
	 * @param bool $escape Parse Title through saninitation calls.
	 * @param bool $get_option Whether to fetch the SEO Settings option.
	 *
	 * @return string The Title.
	 */
	public function title_for_home( $home_title = '', $get_custom_field = true, $escape = false, $get_option = true ) {

		/**
		 * Get blogname title based on option
		 * @since 2.2.2
		 */
		if ( $get_option ) {
			$home_title_option = $this->get_option( 'homepage_title' ) ? $this->get_option( 'homepage_title' ) : $home_title;
			$home_title = $home_title_option ? $home_title_option : $home_title;
		}

		/**
		 * Fetch from Home Page InPost SEO Box if available.
		 * Only from page on front.
		 */
		if ( $get_custom_field && empty( $home_title ) && $this->has_page_on_front() ) {
			$custom_field = $this->get_custom_field( '_genesis_title', $this->get_the_front_page_ID() );
			$home_title = $custom_field ? $custom_field : $this->get_blogname();
		} else {
			$home_title = $home_title ? $home_title : $this->get_blogname();
		}

		if ( $escape )
			$home_title = $this->escape_title( $home_title, false );

		return (string) $home_title;
	}

	/**
	 * Gets the title for Category, Tag or Taxonomy
	 *
	 * @since 2.2.8
	 *
	 * @param array $args The Title arguments.
	 * @param bool $escape Parse Title through saninitation calls.
	 *
	 * @todo put args in array.
	 *
	 * @return string The Title.
	 */
	public function title_for_terms( $args = array(), $escape = false ) {

		$args = $this->reparse_title_args( $args );

		$title = '';
		$term = null;

		if ( $args['term_id'] && $args['taxonomy'] )
			$term = get_term( $args['term_id'], $args['taxonomy'], OBJECT, 'raw' );

		if ( $this->is_category() || $this->is_tag() ) {

			if ( $args['get_custom_field'] ) {
				if ( ! isset( $term ) )
					$term = $this->fetch_the_term( $args['term_id'] );

				$title = empty( $term->admeta['doctitle'] ) ? $title : $term->admeta['doctitle'];

				$flag = isset( $term->admeta['saved_flag'] ) && $this->is_checked( $term->admeta['saved_flag'] ) ? true : false;
				if ( false === $flag && empty( $title ) && isset( $term->meta['doctitle'] ) )
					$title = empty( $term->meta['doctitle'] ) ? $title : $term->meta['doctitle'];
			}

			if ( empty( $title ) )
				$title = $this->get_the_real_archive_title( $term, $args );

		} else {
			if ( ! isset( $term ) && $this->is_tax() )
				$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );

			if ( $args['get_custom_field'] && isset( $term ) ) {
				$title = empty( $term->admeta['doctitle'] ) ? $title : wp_kses_stripslashes( wp_kses_decode_entities( $term->admeta['doctitle'] ) );

				$flag = isset( $term->admeta['saved_flag'] ) && $this->is_checked( $term->admeta['saved_flag'] );
				if ( false === $flag && empty( $title ) && isset( $term->meta['doctitle'] ) )
					$title = empty( $term->meta['doctitle'] ) ? $title : wp_kses_stripslashes( wp_kses_decode_entities( $term->meta['doctitle'] ) );
			}

			if ( empty( $title ) )
				$title = $this->get_the_real_archive_title( $term, $args );

		}

		if ( $escape )
			$title = $this->escape_title( $title, false );

		return (string) $title;
	}

	/**
	 * Gets the title from custom field
	 *
	 * @since 2.2.8
	 *
	 * @param string $title the fallback title.
	 * @param bool $escape Parse Title through saninitation calls.
	 * @param int $id The Post ID.
	 * @param string $taxonomy The term name.
	 *
	 * @return string The Title.
	 */
	public function title_from_custom_field( $title = '', $escape = false, $id = null, $taxonomy = null ) {

		$id = isset( $id ) ? $id : null;

		/**
		 * Create something special for blog page. Only if it's not the home page.
		 * @since 2.2.8
		 */
		if ( $this->is_singular() ) {
			//* Get title from custom field, empty it if it's not there to override the default title
			$title = $this->get_custom_field( '_genesis_title', $id ) ? $this->get_custom_field( '_genesis_title', $id ) : $title;
		} else if ( $this->is_blog_page( $id ) ) {
			//* Posts page title.
			$title = $this->get_custom_field( '_genesis_title', $id ) ? $this->get_custom_field( '_genesis_title', $id ) : get_the_title( $id );
		} else if ( $this->is_archive() || ( $id && $taxonomy ) ) {
			//* Get the custom title for terms.
			$term = get_term( $id, $taxonomy, OBJECT, 'raw' );
			$title = isset( $term->admeta['doctitle'] ) ? $term->admeta['doctitle'] : $title;
		}

		if ( $escape )
			$title = $this->escape_title( $title, false );

		return (string) $title;
	}

	/**
	 * Get the archive Title, including filter. Also works in admin.
	 * @NOTE Taken from WordPress core. Altered to work in the Admin area.
	 *
	 * @param object $term The Term object.
	 * @param array $args The Title arguments.
	 *
	 * @since 2.6.0
	 */
	public function get_the_real_archive_title( $term = null, $args = array() ) {

		if ( empty( $term ) )
			$term = get_queried_object();

		/**
		 * Applies filters the_seo_framework_the_archive_title : {
		 *		@param string empty short circuit the function.
		 * 		@param object $term The Term object.
		 *	}
		 *
		 * @since 2.6.0
		 */
		$title = (string) apply_filters( 'the_seo_framework_the_archive_title', '', $term );

		if ( $title )
			return $title;

		/**
		 * @since 2.6.0
		 */
		$use_prefix = $this->use_archive_prefix( $term, $args );

		if ( $this->is_category() || $this->is_tag() || $this->is_tax() ) {
			$title = $this->single_term_title( '', false, $term );
			/* translators: Front-end output. 1: Taxonomy singular name, 2: Current taxonomy term */
			$title = $use_prefix ? sprintf( __( '%1$s: %2$s', 'autodescription' ), $this->get_the_term_name( $term ), $title ) : $title;
		} else if ( $this->is_author() ) {
			$title = get_the_author();
				/* translators: Front-end output. */
			$title = $use_prefix ? sprintf( __( 'Author: %s', 'autodescription' ), $title ) : $title;
		} else if ( $this->is_date() ) {
			if ( $this->is_year() ) {
				/* translators: Front-end output. */
				$title = get_the_date( _x( 'Y', 'yearly archives date format', 'autodescription' ) );
				/* translators: Front-end output. */
				$title = $use_prefix ? sprintf( __( 'Year: %s', 'autodescription' ), $title ) : $title;
			} else if ( $this->is_month() ) {
				/* translators: Front-end output. */
				$title = get_the_date( _x( 'F Y', 'monthly archives date format', 'autodescription' ) );
				/* translators: Front-end output. */
				$title = $use_prefix ? sprintf( __( 'Month: %s', 'autodescription' ), $title ) : $title;
			} else if ( $this->is_day() ) {
				/* translators: Front-end output. */
				$title = get_the_date( _x( 'F j, Y', 'daily archives date format', 'autodescription' ) );
				/* translators: Front-end output. */
				$title = $use_prefix ? sprintf( __( 'Day: %s', 'autodescription' ), $title ) : $title;
			}
		} else if ( $this->is_tax( 'post_format' ) ) {
			if ( is_tax( 'post_format', 'post-format-aside' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Asides', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-gallery' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Galleries', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-image' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Images', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-video' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Videos', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-quote' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Quotes', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-link' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Links', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-status' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Statuses', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-audio' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Audio', 'post format archive title', 'autodescription' );
			} else if ( $this->is_tax( 'post_format', 'post-format-chat' ) ) {
				/* translators: Front-end output. */
				$title = _x( 'Chats', 'post format archive title', 'autodescription' );
			}
		} else if ( is_post_type_archive() ) {
			$title = post_type_archive_title( '', false );
			/* translators: Front-end output. */
			$title = $use_prefix ? sprintf( __( 'Archives: %s' ), $title ) : $title;
		} else if ( isset( $term ) ) {
			$title = $this->single_term_title( '', false, $term );

			if ( $use_prefix ) {
				/* translators: Front-end output. 1: Taxonomy singular name, 2: Current taxonomy term */
				$title = sprintf( __( '%1$s: %2$s', 'autodescription' ),  $this->get_the_term_name( $term, true, false ), $title );
			}
		} else {
			/* translators: Front-end output. */
			$title = __( 'Archives', 'autodescription' );
		}

		return $title;
	}

	/**
	 * Fetch single term title.
	 * @NOTE Taken from WordPress core. Altered to work in the Admin area.
	 *
	 * @since 2.6.0
	 *
	 * @return string.
	 */
	public function single_term_title( $prefix = '', $display = true, $term = null ) {

		if ( is_null( $term ) )
			$term = get_queried_object();

		if ( ! $term )
			return;

		$term_name = '';

		if ( isset( $term->name ) ) {
			if ( $this->is_category() ) {
				/**
				* Filter the category archive page title.
				*
				* @since 2.0.10 WP CORE
				*
				* @param string $term_name Category name for archive being displayed.
				*/
				$term_name = apply_filters( 'single_cat_title', $term->name );
			} else if ( $this->is_tag() ) {
				/**
				* Filter the tag archive page title.
				*
				* @since 2.3.0 WP CORE
				*
				* @param string $term_name Tag name for archive being displayed.
				*/
				$term_name = apply_filters( 'single_tag_title', $term->name );
			} else if ( $this->is_tax() || $this->is_admin() ) {
				/**
				* Filter the custom taxonomy archive page title.
				*
				* @since 3.1.0 WP CORE
				*
				* @param string $term_name Term name for archive being displayed.
				*/
				$term_name = apply_filters( 'single_term_title', $term->name );
			} else {
				return '';
			}
		}

		//* Impossible through WordPress interface. Possible through filters.
		if ( empty( $term_name ) )
			$term_name = $this->untitled();

		if ( $display )
			echo $prefix . $term_name;
		else
			return $prefix . $term_name;
	}

	/**
	 * Return custom field title.
	 *
	 * @since 2.6.0
	 *
	 * @param string $title The current title.
	 * @param int $id The post or TT ID.
	 * @param string $taxonomy The TT name.
	 *
	 * @return string $title The custom field title.
	 */
	public function get_custom_field_title( $title = '', $id = '', $taxonomy = '' ) {

		$title_special = '';

		if ( $this->is_singular() )
			$title_special = $this->title_from_special_fields();

		if ( $title_special ) {
			$title = $title_special;
		} else {
			$title_from_custom_field = $this->title_from_custom_field( $title, false, $id, $taxonomy );
			$title = $title_from_custom_field ? $title_from_custom_field : $title;
		}

		return $title;
	}

	/**
	 * Untitled title.
	 *
	 * @since 2.6.0
	 *
	 * @return string Untitled.
	 */
	public function untitled() {
		/* translators: Front-end output. */
		return __( 'Untitled', 'autodescription' );
	}

	/**
	 * Return Post Title from ID.
	 *
	 * @since 2.6.0
	 *
	 * @param int $id The Post ID.
	 * @param string $title Optional. The current Title.
	 *
	 * @return string Post Title
	 */
	public function post_title_from_ID( $id = 0, $title = '' ) {

		if ( $this->is_archive() )
			return $title;

		$post = get_post( $id, OBJECT );

		return $title = isset( $post->post_title ) ? $post->post_title : $title;
	}

	/**
	 * Return search title.
	 *
	 * @since 2.6.0
	 *
	 * @param string $title the current title.
	 * @param bool $escape Whether to escape attributes from query.
	 *
	 * @return string Search Title
	 */
	public function get_the_search_title( $title, $escape = true ) {

		if ( $this->is_search() ) {
			/* translators: Front-end output. */
			$search_title = (string) apply_filters( 'the_seo_framework_search_title', __( 'Search results for:', 'autodescription' ) );

			return $search_title . ' ' . trim( get_search_query( $escape ) );
		}

		return $title;
	}

	/**
	 * Return 404 title.
	 *
	 * @since 2.6.0
	 *
	 * Applies filters string the_seo_framework_404_title
	 * @since 2.5.2
	 *
	 * @param string $title The current Title
	 *
	 * @return string 404 Title
	 */
	public function get_the_404_title( $title ) {

		if ( $this->is_404() )
			return (string) apply_filters( 'the_seo_framework_404_title', '404' );

		return $title;
	}

	/**
	 * Get Title Separator.
	 *
	 * @since 2.6.0
	 * @staticvar string $sep
	 *
	 * Applies filters the_seo_framework_title_separator
	 * @since 2.3.9
	 *
	 * @return string The Separator
	 */
	public function get_title_separator() {

		static $sep = null;

		if ( isset( $sep ) )
			return $sep;

		return $sep = (string) apply_filters( 'the_seo_framework_title_separator', $this->get_separator( 'title' ) );
	}

	/**
	 * Get Title Seplocation.
	 *
	 * Applies filters the_seo_framework_title_seplocation : string the title location.
	 * Applies filters the_seo_framework_title_seplocation_front : string the home page title location.
	 * @since 2.3.9
	 *
	 * @access private
	 * @since 2.6.0
	 * @staticvar string $cache
	 *
	 * @param string $seplocation The current seplocation.
	 * @param bool $home The home seplocation.
	 *
	 * @return string The Seplocation
	 */
	public function get_title_seplocation( $seplocation = '', $home = false ) {

		static $cache = array();

		if ( isset( $cache[$seplocation][$home] ) )
			return $cache[$seplocation][$home];

		if ( empty( $seplocation ) || 'right' !== $seplocation || 'left' !== $seplocation ) {
			if ( $home ) {
				return $cache[$seplocation][$home] = (string) apply_filters( 'the_seo_framework_title_seplocation_front', $this->get_option( 'home_title_location' ) );
			} else {
				return $cache[$seplocation][$home] = (string) apply_filters( 'the_seo_framework_title_seplocation', $this->get_option( 'title_location' ) );
			}
		}

		return $cache[$seplocation][$home] = $seplocation;
	}

	/**
	 * Get Title Seplocation for the homepage.
	 *
	 * @since 2.6.0
	 *
	 * @param string $seplocation The current seplocation.
	 *
	 * @return string The Seplocation for the homepage.
	 */
	public function get_home_title_seplocation( $seplocation = '' ) {
		return $this->get_title_seplocation( $seplocation, true );
	}

	/**
	 * Determines whether to add or remove title additions.
	 *
	 * Applies filters the_seo_framework_add_blogname_to_title : boolean
	 * @since 2.4.3
	 *
	 * @since 2.6.0
	 * @staticvar bool $add
	 *
	 * @return bool True when additions are allowed.
	 */
	public function add_title_additions() {

		static $add = null;

		if ( isset( $add ) )
			return $add;

		if ( $this->can_manipulate_title() )
			if ( $this->is_option_checked( 'title_rem_additions' ) || false === (bool) apply_filters( 'the_seo_framework_add_blogname_to_title', true ) )
				return $add = false;

		return $add = true;
	}

	/**
	 * Add the title additions to the title.
	 *
	 * @param string $title The tite.
	 * @param string $blogname The blogname.
	 * @param string $seplocation The separator location.
	 *
	 * @since 2.6.0
	 */
	public function process_title_additions( $title = '', $blogname = '', $seplocation = '' ) {

		$sep = $this->get_title_separator();

		$title = trim( $title );
		$blogname = trim( $blogname );

		if ( $blogname && $title ) {
			if ( 'left' === $seplocation ) {
				$title = $blogname . " $sep " . $title;
			} else {
				$title = $title . " $sep " . $blogname;
			}
		}

		return $title;
	}

	/**
	 * Adds title protection prefixes.
	 *
	 * @since 2.6.0
	 *
	 * @param $title The current Title.
	 * @param $id The page ID.
	 *
	 * @return string $title with possible affixes.
	 */
	public function add_title_protection( $title, $id ) {

		/**
		 * From WordPress core get_the_title.
		 * Bypasses get_post() function object which causes conflict with some themes and plugins.
		 *
		 * Also bypasses the_title filters.
		 * And now also works in admin. It gives you a true representation of its output.
		 *
		 * @since 2.4.1
		 *
		 * @applies filters core : protected_title_format
		 * @applies filters core : private_title_format
		 */
		$post = get_post( $id, OBJECT );

		if ( isset( $post->post_password ) && '' !== $post->post_password ) {
			/* translators: Front-end output */
			$protected_title_format = apply_filters( 'protected_title_format', __( 'Protected: %s', 'autodescription' ), $post );
			$title = sprintf( $protected_title_format, $title );
		} else if ( isset( $post->post_status ) && 'private' === $post->post_status ) {
			/* translators: Front-end output */
			$private_title_format = apply_filters( 'private_title_format', __( 'Private: %s', 'autodescription' ), $post );
			$title = sprintf( $private_title_format, $title );
		}

		return $title;
	}

	/**
	 * Adds title pagination, if paginated.
	 *
	 * @since 2.6.0
	 *
	 * @param string $title The current Title.
	 *
	 * @return string Title with maybe pagination added.
	 */
	public function add_title_pagination( $title ) {

		if ( $this->is_404() || $this->is_admin() )
			return $title;

		$page = $this->page();
		$paged = $this->paged();

		if ( $page && $paged ) {
			/**
			 * @since 2.4.3
			 * Adds page numbering within the title.
			 */
			if ( $paged >= 2 || $page >= 2 ) {
				$sep = $this->get_title_separator();

				/* translators: Front-end output. */
				$title .= " $sep " . sprintf( __( 'Page %s', 'autodescription' ), max( $paged, $page ) );
			}
		}

		return $title;
	}

	/**
	 * Whether to use a title prefix or not.
	 *
	 * @since 2.6.0
	 * @staticvar bool $cache
	 *
	 * @param object $term The Term object.
	 * @param array $args The title arguments.
	 *
	 * @return bool
	 */
	public function use_archive_prefix( $term = null, $args = array() ) {

		//* Don't add prefix in meta.
		if ( $args['meta'] )
			return false;

		static $cache = null;

		if ( isset( $cache ) )
			return $cache;

		/**
		 * Applies filters the_seo_framework_use_archive_title_prefix : {
		 *		@param bool true to add prefix.
		 * 		@param object $term The Term object.
		 *	}
		 *
		 * @since 2.6.0
		 */
		$filter = (bool) apply_filters( 'the_seo_framework_use_archive_title_prefix', true, $term );
		$option = ! $this->get_option( 'title_rem_prefixes' );

		return $cache = $option && $filter ? true : false;
	}

	/**
	 * Filter the title prior to output.
	 *
	 * @since 2.6.0
	 * @access private
	 *
	 * @param string $title The current title.
	 * @param array $args The title args.
	 * @param bool $escape Whether to escape the title.
	 *
	 * @return string $title
	 */
	public function do_title_pre_filter( $title, $args, $escape = true ) {

		/**
		 * Applies filters 'the_seo_framework_pre_add_title' : string
		 * @since 2.6.0
		 * @param string $title
		 * @param array $args
		 * @param bool $escape
		 */
		$title = (string) apply_filters( 'the_seo_framework_pre_add_title', $title, $args, $escape );

		if ( $escape )
			$title = $this->escape_title( $title );

		return $title;
	}

	/**
	 * Filter the title prior to output.
	 *
	 * @since 2.6.0
	 * @access private
	 *
	 * @param string $title The current title.
	 * @param array $args The title args.
	 * @param bool $escape Whether to escape the title.
	 *
	 * @return string $title
	 */
	public function do_title_pro_filter( $title, $args, $escape = true ) {

		/**
		 * Applies filters 'the_seo_framework_pro_add_title' : string
		 * @since 2.6.0
		 * @param string $title
		 * @param array $args
		 * @param bool $escape
		 */
		$title = (string) apply_filters( 'the_seo_framework_pro_add_title', $title, $args, $escape );

		if ( $escape )
			$title = $this->escape_title( $title );

		return $title;
	}

	/**
	 * Whether to add home page tagline.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function home_page_add_title_tagline() {
		return $this->is_option_checked( 'homepage_tagline' );
	}

}