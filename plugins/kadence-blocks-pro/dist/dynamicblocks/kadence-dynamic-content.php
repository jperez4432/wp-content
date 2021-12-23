<?php
/**
 * Enqueue JS for Custom Icons and build admin for icons.
 *
 * @since   1.4.0
 * @package Kadence Blocks Pro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue JS for Custom Icons and build admin for icons.
 *
 * @category class
 */
class Kadence_Blocks_Pro_Dynamic_Content {
	/**
	 * Instance of this class
	 *
	 * @var null
	 */
	private static $instance = null;

	const POST_GROUP = 'post';

	const ARCHIVE_GROUP = 'archive';

	const AUTHOR_GROUP = 'author';

	const SITE_GROUP = 'site';

	const COMMENTS_GROUP = 'comments';

	const MEDIA_GROUP = 'media';

	const OTHER_GROUP = 'other';

	const TEXT_CATEGORY = 'text';

	const NUMBER_CATEGORY = 'number';

	const IMAGE_CATEGORY = 'image';

	const DATE_CATEGORY = 'date';

	const AUDIO_CATEGORY = 'audio';

	const VIDEO_CATEGORY = 'video';

	const URL_CATEGORY = 'url';

	const HTML_CATEGORY = 'html';

	const EMBED_CATEGORY = 'embed';

	const VALUE_SEPARATOR = '#+*#';

	const CUSTOM_POST_TYPE_REGEXP = '/"(custom_post_type\|[^\|]+\|\d+)"/';

	const SHORTCODE = 'kb-dynamic';

	/**
	 * The post group field options.
	 *
	 * @var array
	 */
	private static $post_group = array(
		'post_title',
		'post_url',
		'post_content',
		'post_excerpt',
		'post_id',
		'post_date',
		'post_date_modified',
		'post_type',
		'post_status',
		'post_custom_field',
		'post_featured_image',
	);

	/**
	 * Block ids to render inline.
	 *
	 * @var array
	 */
	public static $render_inline = array();

	/**
	 * Instance Control
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Class Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'on_init' ) );

	}
	/**
	 * On init
	 */
	public function on_init() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'script_enqueue' ), 30 );
			// This will break blocks :(
			//add_action( 'admin_init', array( $this, 'rest_filter' ), 10 );
		}

		add_shortcode( self::SHORTCODE, array( $this, 'dynamic_shortcode_render' ) );
		add_filter( 'render_block', array( $this, 'render_blocks' ), 10, 2 );
		//add_action( 'wp_enqueue_scripts', array( $this, 'frontend_head_css' ), 5 );
		add_filter( 'kadence_blocks_column_render_block_attributes', array( $this, 'update_background_image' ) );
		add_filter( 'kadence_blocks_rowlayout_render_block_attributes', array( $this, 'update_background_image' ) );
		add_filter( 'kadence_blocks_infobox_render_block_attributes', array( $this, 'update_image_properties' ) );
		add_filter( 'kadence_blocks_image_overlay_render_block_attributes', array( $this, 'update_image_properties' ) );
		add_filter( 'kadence_blocks_split_content_render_block_attributes', array( $this, 'update_image_properties' ) );
		add_filter( 'kadence_blocks_video_render_block_attributes', array( $this, 'update_custom_ratio_video_popup_image' ) );
		add_filter( 'kadence_blocks_render_head_css', array( $this, 'prevent_render_in_head_for_query_blocks' ), 10, 3 );
		add_filter( 'kadence_blocks_force_render_inline_css_in_content', array( $this, 'prevent_css_enqueuing_blocks_in_query' ), 10, 3 );
		if ( ! is_admin() ) {
			add_action( 'render_block', array( $this, 'conditionally_render_block' ), 6, 2 );
		}
	}
	/**
	 * Check for logged in, logged out visibility settings.
	 *
	 * @param mixed $block_content The block content.
	 * @param array $block The block data.
	 *
	 * @return mixed Returns the block content.
	 */
	public function conditionally_render_block( $block_content, $block ) {
		if ( ! empty( $block['attrs']['kadenceConditional']['postData'] ) && isset( $block['attrs']['kadenceConditional']['postData']['enable'] ) && $block['attrs']['kadenceConditional']['postData']['enable'] ) {
			$conditional_data = $block['attrs']['kadenceConditional']['postData'];
			$hide = true;
			if ( ! empty( $conditional_data['field'] ) && strpos( $conditional_data['field'], '|' ) !== false ) {
				$field_split = explode( '|', $conditional_data['field'], 2 );
				$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
				$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
			} else {
				$field = '';
				$group = '';
			}
			$args = array(
				'source'       => $conditional_data['source'],
				'origin'       => 'core',
				'group'        => $group,
				'type'         => 'conditional',
				'field'        => $field,
				'custom'       => $conditional_data['custom'],
				'para'         => $conditional_data['para'],
				'force-string' => true,
			);
			$condition_data = $this->get_content( $args );
			switch ( $conditional_data['compare'] ) {
				case 'not_empty':
					if ( ! empty( $condition_data ) ) {
						$hide = false;
					}
					break;
				case 'is_empty':
					if ( empty( $condition_data ) ) {
						$hide = false;
					}
					break;
				case 'is_true':
					if ( $condition_data == true ) {
						$hide = false;
					}
					break;
				case 'is_false':
					if ( $condition_data == false ) {
						$hide = false;
					}
					break;
				case 'equals':
					if ( $condition_data == $conditional_data['condition'] ) {
						$hide = false;
					}
					break;
				case 'not_equals':
					if ( $condition_data != $conditional_data['condition'] ) {
						$hide = false;
					}
					break;
				case 'equals_or_greater':
					if ( $condition_data >= $conditional_data['condition'] ) {
						$hide = false;
					}
					break;
				case 'equals_or_less':
					if ( $condition_data <= $conditional_data['condition'] ) {
						$hide = false;
					}
					break;
				case 'greater':
					if ( $condition_data > $conditional_data['condition'] ) {
						$hide = false;
					}
					break;
				case 'less':
					if ( $condition_data < $conditional_data['condition'] ) {
						$hide = false;
					}
					break;
			}
			if ( $hide ) {
				return '';
			}
		}
		return $block_content;
	}
	/**
	 * Outputs extra css for blocks.
	 *
	 * @param $post_object object of WP_Post.
	 */
	public function frontend_build_exclude_array( $post_object ) {
		if ( ! is_object( $post_object ) ) {
			return;
		}
		if ( ! method_exists( $post_object, 'post_content' ) ) {
			$blocks = $this->kadence_parse_blocks( $post_object->post_content );
			if ( ! is_array( $blocks ) || empty( $blocks ) ) {
				return;
			}
			foreach ( $blocks as $indexkey => $block ) {
				$block = apply_filters( 'kadence_blocks_frontend_build_css', $block );
				if ( ! is_object( $block ) && is_array( $block ) && isset( $block['blockName'] ) ) {
					if ( 'core/query' === $block['blockName'] ) {
						if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
							$this->blocks_cycle_through_query( $block['innerBlocks'] );
						}
					}
				}
			}
		}
	}
	/**
	 * Builds css for inner blocks
	 *
	 * @param array $inner_blocks array of inner blocks.
	 */
	public function blocks_cycle_through_query( $inner_blocks ) {
		foreach ( $inner_blocks as $in_indexkey => $inner_block ) {
			if ( ! is_object( $inner_block ) && is_array( $inner_block ) && isset( $inner_block['blockName'] ) ) {
				$trigger_blocks = array( 'kadence/videopopup', 'kadence/rowlayout', 'kadence/column', 'kadence/infobox', 'kadence/modal', 'kadence/imageoverlay', 'kadence/splitcontent' );
				if ( in_array( $inner_block['blockName'], $trigger_blocks ) ) {
					if ( isset( $inner_block['attrs'] ) && is_array( $inner_block['attrs'] ) && ! empty( $inner_block['attrs']['uniqueID'] ) ) {
						self::$render_inline[] = $inner_block['attrs']['uniqueID'];
					}
				}
				if ( isset( $inner_block['innerBlocks'] ) && ! empty( $inner_block['innerBlocks'] ) && is_array( $inner_block['innerBlocks'] ) ) {
					$this->blocks_cycle_through_query( $inner_block['innerBlocks'] );
				}
			}
		}
	}
	/**
	 * Gets the parsed blocks, need to use this becuase wordpress 5 doesn't seem to include gutenberg_parse_blocks
	 */
	public function kadence_parse_blocks( $content ) {
		$parser_class = apply_filters( 'block_parser_class', 'WP_Block_Parser' );
		if ( class_exists( $parser_class ) ) {
			$parser = new $parser_class();
			return $parser->parse( $content );
		} elseif ( function_exists( 'gutenberg_parse_blocks' ) ) {
			return gutenberg_parse_blocks( $content );
		} else {
			return false;
		}
	}
	/**
	 * Outputs extra css for blocks.
	 */
	public function frontend_head_css() {
		if ( function_exists( 'has_blocks' ) && has_blocks( get_the_ID() ) ) {
			global $post;
			if ( ! is_object( $post ) ) {
				return;
			}
			$this->frontend_build_exclude_array( $post );
		}
	}
	/**
	 * Prevent rendering CSS in header for some blocks.
	 */
	public function prevent_render_in_head_for_query_blocks( $bool, $name, $attributes ) {
		if ( isset( $attributes['inQueryBlock'] ) && $attributes['inQueryBlock'] && ( ( isset( $attributes['kadenceDynamic'] ) && is_array( $attributes['kadenceDynamic'] ) ) || 'modal' == $name ) ) {
			self::$render_inline[] = $attributes['uniqueID'];
			return false;
		}
		return $bool;
	}
	/**
	 * Prevent enqueuing CSS for some blocks.
	 */
	public function prevent_css_enqueuing_blocks_in_query( $bool, $name, $unique_id ) {
		if ( ! empty( $unique_id ) && in_array( $unique_id, self::$render_inline ) ) {
			// return true so that the css is loaded inline instead of as print.
			return true;
		}
		return $bool;
	}
	/**
	 * Add filter for admin rest calls.
	 */
	public function rest_filter() {
		$args = array(
			'public'       => true,
			'show_in_rest' => true,
		);
		$post_types = get_post_types( $args, 'names' );
		foreach ( $post_types as $post_type ) {
			add_filter( 'rest_prepare_' . $post_type, array( $this, 'update_dynamic_content_on_rest_call' ), 5, 3 );
		}
	}
	/**
	 * Add the dynamic content to blocks.
	 *
	 * @param string $attributes the block attributes.
	 */
	public function update_dynamic_content_on_rest_call( $response, $post, $request ) {
		if ( isset( $response->data ) && is_array( $response->data ) && $response->data['content'] && is_array( $response->data['content'] ) && $response->data['content']['raw'] ) {
			$response->data['content']['raw'] = preg_replace_callback(
				'/<span\s+((?:data-[\w\-]+=["\']+.*["\']+[\s]+)+)class=["\'].*kb-inline-dynamic.*["\']\s*>(.*)<\/span>/U',
				function ( $matches ) {
					$options = explode( ' ', str_replace( 'data-', '', $matches[1] ) );
					$args = array();
					foreach ( $options as $key => $value ) {
						if ( empty( $value ) ) {
							continue;
						}
						$data_split = explode( '=', $value, 2 );
						if ( $data_split[0] === 'field' ) {
							$field_split = explode( '|',  str_replace( '"', '', $data_split[1] ), 2 );
							$args['group'] = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
							$args['field'] = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
						} else {
							$args[ $data_split[0] ] = str_replace( '"', '', $data_split[1] );
						}
					}
					$update = $this->get_content( $args, $post );
					if ( empty ( $update ) ) {
						$update = ( isset( $matches[2] ) && ! empty( $matches[2] ) ? $matches[2] : __( 'No Content' ) );
					}
					return '<span ' . $matches[1] . ' class="kb-inline-dynamic">' . $update . '</span>';
				},
				$response->data['content']['raw']
			);
		}
		return $response;
	}
	/**
	 * This is a special hack for video popup.
	 *
	 * @param string $attributes the block attributes.
	 */
	public function update_custom_ratio_video_popup_image( $attributes ) {
		if ( is_admin() ) {
			return $attributes;
		}
		if ( isset( $attributes ) && isset( $attributes['kadenceDynamic'] ) && is_array( $attributes['kadenceDynamic'] ) ) {
			foreach ( $attributes['kadenceDynamic'] as $attr_slug => $data ) {
				if ( 'background:0:img' !== $attr_slug ) {
					continue;
				}
				if ( isset( $data['enable'] ) && $data['enable'] ) {
					if ( ! empty( $attributes['ratio'] ) && 'custom' === $attributes['ratio'] ) {
						if ( ! empty( $data['field'] ) && strpos( $data['field'], '|' ) !== false ) {
							$field_split = explode( '|', $data['field'], 2 );
							$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
							$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
						} else {
							$field = '';
							$group = '';
						}
						$args = array(
							'source'       => $data['source'],
							'origin'       => 'core',
							'group'        => $group,
							'type'         => 'image',
							'field'        => $field,
							'custom'       => $data['custom'],
							'para'         => $data['para'],
							'force-string' => false,
							'before'       => $data['before'],
							'after'        => $data['after'],
							'fallback'     => $data['fallback'],
						);
						$image_data = $this->get_content( $args );
						if ( $image_data && is_array( $image_data ) ) {
							if ( ! empty( $attr_slug ) && strpos( $attr_slug, ':' ) !== false ) {
								$slug_split = explode( ':', $attr_slug, 3 );
								if ( isset( $attributes[ $slug_split[0] ] ) && is_array( $attributes[ $slug_split[0] ] ) ) {
									$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ][ $slug_split[2] ] = $image_data[0];
									$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ]['imgWidth'] = $image_data[1];
									$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ]['imageHeight'] = $image_data[2];
								}
							}
						}
					}
				}
			}
		}
		return $attributes;
	}
	/**
	 * Add the dynamic content to blocks.
	 *
	 * @param string $attributes the block attributes.
	 */
	public function update_image_properties( $attributes ) {
		if ( is_admin() ) {
			return $attributes;
		}
		if ( isset( $attributes ) && isset( $attributes['kadenceDynamic'] ) && is_array( $attributes['kadenceDynamic'] ) ) {
			foreach ( $attributes['kadenceDynamic'] as $attr_slug => $data ) {
				if ( 'mediaImage:0:url' !== $attr_slug && 'imgURL' !== $attr_slug && 'mediaUrl' !== $attr_slug ) {
					continue;
				}
				if ( isset( $data['enable'] ) && $data['enable'] ) {
					if ( ! empty( $data['field'] ) && strpos( $data['field'], '|' ) !== false ) {
						$field_split = explode( '|', $data['field'], 2 );
						$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
						$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
					} else {
						$field = '';
						$group = '';
					}
					$args = array(
						'source'       => $data['source'],
						'origin'       => 'core',
						'group'        => $group,
						'type'         => 'image',
						'field'        => $field,
						'custom'       => $data['custom'],
						'para'         => $data['para'],
						'force-string' => false,
						'before'       => $data['before'],
						'after'        => $data['after'],
						'fallback'     => $data['fallback'],
					);
					$image_data = $this->get_content( $args );
					if ( $image_data && is_array( $image_data ) ) {
						if ( ! empty( $attr_slug ) && strpos( $attr_slug, ':' ) !== false ) {
							$slug_split = explode( ':', $attr_slug, 3 );
							if ( isset( $attributes[ $slug_split[0] ] ) && is_array( $attributes[ $slug_split[0] ] ) ) {
								$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ][ $slug_split[2] ] = $image_data[0];
								$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ]['width'] = $image_data[1];
								$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ]['height'] = $image_data[2];
							}
						} else if ( ! empty( $attr_slug ) && strpos( $attr_slug, 'media' ) !== false ) {
							$attributes[$attr_slug] = $image_data[0];
							$attributes['mediaWidth'] = $image_data[1];
							$attributes['mediaHeight'] = $image_data[2];
						} else {
							$attributes[$attr_slug] = $image_data[0];
							$attributes['imgWidth'] = $image_data[1];
							$attributes['imgHeight'] = $image_data[2];
						}
					}
				}
			}
		}
		return $attributes;
	}
	/**
	 * Add the dynamic content to blocks.
	 *
	 * @param string $attributes the block attributes.
	 */
	public function update_background_image( $attributes ) {
		if ( is_admin() ) {
			return $attributes;
		}
		if ( isset( $attributes ) && isset( $attributes['kadenceDynamic'] ) && is_array( $attributes['kadenceDynamic'] ) ) {
			foreach ( $attributes['kadenceDynamic'] as $attr_slug => $data ) {
				if ( isset( $data['enable'] ) && $data['enable'] ) {
					if ( ! empty( $data['field'] ) && strpos( $data['field'], '|' ) !== false ) {
						$field_split = explode( '|', $data['field'], 2 );
						$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
						$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
					} else {
						$field = '';
						$group = '';
					}
					$args = array(
						'source'       => $data['source'],
						'origin'       => 'core',
						'group'        => $group,
						'type'         => 'background',
						'field'        => $field,
						'custom'       => $data['custom'],
						'para'         => $data['para'],
						'force-string' => false,
						'before'       => $data['before'],
						'after'        => null,
						'fallback'     => $data['fallback'],
					);
					$image_url = $this->get_content( $args );
					if ( is_array( $image_url ) ) {
						if ( isset( $image_url['url'] ) ) {
							$image_url = $image_url['url'];
						} else if ( isset( $image_url[0] ) ) {
							$image_url = $image_url[0];
						} else {
							$image_url = '';
						}
					}
					if ( ! empty( $attr_slug ) && strpos( $attr_slug, ':' ) !== false ) {
						$slug_split = explode( ':', $attr_slug, 3 );
						$attributes[ $slug_split[0] ][ absint( $slug_split[1] ) ][ $slug_split[2] ] = $image_url;
					} else {
						$attributes[$attr_slug] = $image_url;
					}
				}
			}
		}
		return $attributes;
	}
	/**
	 * Add the dynamic content to blocks.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block info.
	 */
	public function render_blocks( $block_content, $block ) {
		if ( is_admin() ) {
			return $block_content;
		}
		if ( 'kadence/rowlayout' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] && isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) ) {
					$block_content = str_replace( 'kt-layout-id' . $blockattr['uniqueID'], 'kt-layout-id' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
				}
			}
		} else if ( 'kadence/column' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] && isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) ) {
					$block_content = str_replace( 'kadence-column' . $blockattr['uniqueID'], 'kadence-column' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
				}
			}
		} else if ( 'kadence/modal' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] ) {
					$block_content = str_replace( 'kt-modal' . $blockattr['uniqueID'], 'kt-modal' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
					$block_content = str_replace( 'kt-target-modal' . $blockattr['uniqueID'], 'kt-target-modal' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
				}
			}
		} elseif ( 'kadence/imageoverlay' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] && isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) ) {
					$block_content = str_replace( 'kt-img-overlay' . $blockattr['uniqueID'], 'kt-img-overlay' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
				}
				if ( isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) && isset( $blockattr['kadenceDynamic']['imgURL'] ) && is_array( $blockattr['kadenceDynamic']['imgURL'] ) && isset( $blockattr['kadenceDynamic']['imgURL']['enable'] ) && $blockattr['kadenceDynamic']['imgURL']['enable'] ) {
					$block_content = preg_replace_callback(
						'/<img.*?class=["\'].*kt-img-overlay.*["\']\/>/U',
						function ( $matches ) use ( $blockattr ) {
							$content = '';
							if ( ! empty( $blockattr['kadenceDynamic']['imgURL']['field'] ) && strpos( $blockattr['kadenceDynamic']['imgURL']['field'], '|' ) !== false ) {
								$field_split = explode( '|', $blockattr['kadenceDynamic']['imgURL']['field'], 2 );
								$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
								$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
							} else {
								$field = '';
								$group = '';
							}
							$args = array(
								'source'       => $blockattr['kadenceDynamic']['imgURL']['source'],
								'origin'       => 'core',
								'group'        => $group,
								'type'         => 'image',
								'field'        => $field,
								'custom'       => $blockattr['kadenceDynamic']['imgURL']['custom'],
								'para'         => $blockattr['kadenceDynamic']['imgURL']['para'],
								'force-string' => false,
								'before'       => $blockattr['kadenceDynamic']['imgURL']['before'],
								'after'        => null,
								'fallback'     => $blockattr['kadenceDynamic']['imgURL']['fallback'],
							);
							$update = $this->get_content( $args );
							if ( $update ) {
								$content = '<img src="' . $update[0] . '" alt="" width="' . $update[1] . '" height="' . $update[2] . '" class="kt-img-overlay">';
							}
							return $content;
						},
						$block_content
					);
				}
			}
		} elseif ( 'kadence/videopopup' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] && isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) ) {
					$block_content = str_replace( 'kadence-video-popup' . $blockattr['uniqueID'], 'kadence-video-popup' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
				}
				if ( isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) && isset( $blockattr['kadenceDynamic']['background:0:img'] ) && is_array( $blockattr['kadenceDynamic']['background:0:img'] ) && isset( $blockattr['kadenceDynamic']['background:0:img']['enable'] ) && $blockattr['kadenceDynamic']['background:0:img']['enable'] ) {
					$block_content = preg_replace_callback(
						'/<img.*?class=["\'].*kadence-video-poster.*["\']\/>/U',
						function ( $matches ) use ( $blockattr ) {
							$content = '';
							if ( ! empty( $blockattr['kadenceDynamic']['background:0:img']['field'] ) && strpos( $blockattr['kadenceDynamic']['background:0:img']['field'], '|' ) !== false ) {
								$field_split = explode( '|', $blockattr['kadenceDynamic']['background:0:img']['field'], 2 );
								$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
								$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
							} else {
								$field = '';
								$group = '';
							}
							$args = array(
								'source'       => $blockattr['kadenceDynamic']['background:0:img']['source'],
								'origin'       => 'core',
								'group'        => $group,
								'type'         => 'image',
								'field'        => $field,
								'custom'       => $blockattr['kadenceDynamic']['background:0:img']['custom'],
								'para'         => $blockattr['kadenceDynamic']['background:0:img']['para'],
								'force-string' => false,
								'before'       => $blockattr['kadenceDynamic']['background:0:img']['before'],
								'after'        => null,
								'fallback'     => $blockattr['kadenceDynamic']['background:0:img']['fallback'],
							);
							$update = $this->get_content( $args );
							if ( $update ) {
								$content = '<img src="' . $update[0] . '" alt="" width="' . $update[1] . '" height="' . $update[2] . '" class="kadence-video-poster">';
							}
							return $content;
						},
						$block_content
					);
				}
			}
		} elseif ( 'kadence/splitcontent' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) && isset( $blockattr['kadenceDynamic']['mediaUrl'] ) && is_array( $blockattr['kadenceDynamic']['mediaUrl'] ) && isset( $blockattr['kadenceDynamic']['mediaUrl']['enable'] ) && $blockattr['kadenceDynamic']['mediaUrl']['enable'] ) {
					$block_content = preg_replace_callback(
						'/<img.*?class=["\'].*kt-split-content-img.*["\']\/>/U',
						function ( $matches ) use ( $blockattr ) {
							$content = '';
							if ( ! empty( $blockattr['kadenceDynamic']['mediaUrl']['field'] ) && strpos( $blockattr['kadenceDynamic']['mediaUrl']['field'], '|' ) !== false ) {
								$field_split = explode( '|', $blockattr['kadenceDynamic']['mediaUrl']['field'], 2 );
								$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
								$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
							} else {
								$field = '';
								$group = '';
							}
							$args = array(
								'source'       => $blockattr['kadenceDynamic']['mediaUrl']['source'],
								'origin'       => 'core',
								'group'        => $group,
								'type'         => 'image',
								'field'        => $field,
								'custom'       => $blockattr['kadenceDynamic']['mediaUrl']['custom'],
								'para'         => $blockattr['kadenceDynamic']['mediaUrl']['para'],
								'force-string' => false,
								'before'       => $blockattr['kadenceDynamic']['mediaUrl']['before'],
								'after'        => null,
								'fallback'     => $blockattr['kadenceDynamic']['mediaUrl']['fallback'],
							);
							$update = $this->get_content( $args );
							if ( $update ) {
								$content = '<img src="' . $update[0] . '" alt="" width="' . $update[1] . '" height="' . $update[2] . '" class="kt-split-content-img">';
							}
							return $content;
						},
						$block_content
					);
				}
			}
		} elseif ( 'kadence/infobox' === $block['blockName'] ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blockattr = $block['attrs'];
				if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] && isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) ) {
					$block_content = str_replace( 'kt-info-box' . $blockattr['uniqueID'], 'kt-info-box' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
				}
				if ( isset( $blockattr['kadenceDynamic'] ) && is_array( $blockattr['kadenceDynamic'] ) && isset( $blockattr['kadenceDynamic']['mediaImage:0:url'] ) && is_array( $blockattr['kadenceDynamic']['mediaImage:0:url'] ) && isset( $blockattr['kadenceDynamic']['mediaImage:0:url']['enable'] ) && $blockattr['kadenceDynamic']['mediaImage:0:url']['enable'] ) {
					if ( isset( $blockattr['inQueryBlock'] ) && $blockattr['inQueryBlock'] ) {
						$block_content = str_replace( 'kt-info-box' . $blockattr['uniqueID'], 'kt-info-box' . $blockattr['uniqueID'] . get_the_ID(), $block_content );
					}
					$block_content = preg_replace_callback(
						'/<img.*?class=["\'].*kt-info-box-image.*["\']\/>/U',
						function ( $matches ) use ( $blockattr ) {
							$content = '';
							if ( ! empty( $blockattr['kadenceDynamic']['mediaImage:0:url']['field'] ) && strpos( $blockattr['kadenceDynamic']['mediaImage:0:url']['field'], '|' ) !== false ) {
								$field_split = explode( '|', $blockattr['kadenceDynamic']['mediaImage:0:url']['field'], 2 );
								$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
								$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
							} else {
								$field = '';
								$group = '';
							}
							$args = array(
								'source'       => $blockattr['kadenceDynamic']['mediaImage:0:url']['source'],
								'origin'       => 'core',
								'group'        => $group,
								'type'         => 'image',
								'field'        => $field,
								'custom'       => $blockattr['kadenceDynamic']['mediaImage:0:url']['custom'],
								'para'         => $blockattr['kadenceDynamic']['mediaImage:0:url']['para'],
								'force-string' => false,
								'before'       => $blockattr['kadenceDynamic']['mediaImage:0:url']['before'],
								'after'        => null,
								'fallback'     => $blockattr['kadenceDynamic']['mediaImage:0:url']['fallback'],
							);
							$update = $this->get_content( $args );
							if ( $update ) {
								$content = '<img src="' . $update[0] . '" alt="" width="' . $update[1] . '" height="' . $update[2] . '" class="kt-info-box-image wp-image-offsite">';
							}
							return $content;
						},
						$block_content
					);
				}
			}
		}
		// We need to render shortcodes for blocks in query block to get the correct info.
		if ( ! empty( $block['attrs']['kadenceDynamic'] ) ) {
			foreach ( $block['attrs']['kadenceDynamic'] as $dynamic_key => $dynamic_setting ) {
				if ( ! empty( $dynamic_key ) && strpos( $dynamic_key, ':' ) !== false ) {
					$slug_split = explode( ':', $dynamic_key, 3 );
					if ( isset( $slug_split[2] ) ) {
						$slug_key = $slug_split[2];
					} else {
						$slug_key = '';
					}
				} else {
					$slug_key = $dynamic_key;
				}
				if ( 'link' !== $slug_key ) {
					continue;
				}
				if ( isset( $block['attrs']['inQueryBlock'] ) && $block['attrs']['inQueryBlock'] ) {
					$block_content = do_shortcode( $block_content );
				}
			}
		}
		if ( ! empty( $block['attrs']['kadenceAnimation'] ) ) {
			if ( wp_script_is( 'kadence-aos', 'registered' ) && ! wp_script_is( 'kadence-aos', 'enqueued' ) ) {
				wp_enqueue_script( 'kadence-aos' );
			}
			if ( wp_style_is( 'kadence-blocks-pro-aos', 'registered' ) && ! wp_style_is( 'kadence-blocks-pro-aos', 'enqueued' ) ) {
				wp_enqueue_style( 'kadence-blocks-pro-aos' );
			}
		}
		$block_content = preg_replace_callback(
			'/<span\s+((?:data-[\w\-]+=["\']+.*["\']+[\s]+)+)class=["\'].*kb-inline-dynamic.*["\']\s*>(.*)<\/span>/U',
			function ( $matches ) {
				$options = explode( ' ', str_replace( 'data-', '', $matches[1] ) );
				$args = array( 'force-string' => true );
				foreach ( $options as $key => $value ) {
					if ( empty( $value ) ) {
						continue;
					}
					$data_split = explode( '=', $value, 2 );
					if ( $data_split[0] === 'field' ) {
						$field_split = explode( '|',  str_replace( '"', '', $data_split[1] ), 2 );
						$args['group'] = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
						$args['field'] = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
					} else {
						$args[ $data_split[0] ] = str_replace( '"', '', $data_split[1] );
					}
				}
				$update = $this->get_content( $args );
				return $update;
			},
			$block_content
		);
		return $block_content;
	}
	/**
	 * Enqueue Script for Meta options
	 */
	public function script_enqueue() {
		wp_localize_script(
			'kadence-blocks-pro-js',
			'kadenceDynamicParams',
			array(
				'textFields' => $this->get_text_fields(),
				'linkFields' => $this->get_link_fields(),
				'urlFields' => $this->get_url_fields(),
				'backgroundFields' => $this->get_background_fields(),
				'imageFields' => $this->get_image_fields(),
				'conditionalFields' => $this->get_conditional_fields(),
				'dynamicRenderEndpoint' => '/kbp-dynamic/v1/render',
				'dynamicLinkLabelEndpoint' => '/kbp-dynamic/v1/link_label',
				'dynamicBackgroundEndpoint' => '/kbp-dynamic/v1/image_render',
				'dynamicImageEndpoint' => '/kbp-dynamic/v1/image_data',
				'dynamicFieldsEndpoint' => '/kbp-dynamic/v1/custom_fields',
				'dynamicImageFallback'  => apply_filters( 'kadence_blocks_pro_dynamic_image_no_content', KBP_URL . 'dist/assets/images/no-image-found.jpg' ),
			)
		);
	}
	/**
	 * On init
	 */
	public function get_text_fields() {
		$options = array(
			array(
				'label' => __( 'Post', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::POST_GROUP . '|post_title',
						'label' => esc_attr__( 'Post Title', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_url',
						'label' => esc_attr__( 'Post URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_excerpt',
						'label' => esc_attr__( 'Post Excerpt', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_id',
						'label' => esc_attr__( 'Post ID', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_date',
						'label' => esc_attr__( 'Post Date', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_date_modified',
						'label' => esc_attr__( 'Post Last Modified Date', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_featured_image_url',
						'label' => esc_attr__( 'Featured Image URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_type',
						'label' => esc_attr__( 'Post Type', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_status',
						'label' => esc_attr__( 'Post Status', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_custom_field',
						'label' => esc_attr__( 'Post Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Archive', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_title',
						'label' => esc_attr__( 'Archive Title', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_url',
						'label' => esc_attr__( 'Archive URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_description',
						'label' => esc_attr__( 'Archive Description', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_custom_field',
						'label' => esc_attr__( 'Archive Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Site', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::SITE_GROUP . '|site_title',
						'label' => esc_attr__( 'Site Title', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::SITE_GROUP . '|site_tagline',
						'label' => esc_attr__( 'Site Tagline', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::SITE_GROUP . '|site_url',
						'label' => esc_attr__( 'Site URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::SITE_GROUP . '|page_title',
						'label' => esc_attr__( 'Page Title', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::SITE_GROUP . '|user_info',
						'label' => esc_attr__( 'Current User Display Name', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::MEDIA_GROUP => array(
			// 	'label' => __( 'Media', 'kadence-blocks-pro' ),
			// ),
			array(
				'label' => __( 'Author', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::AUTHOR_GROUP . '|author_name',
						'label' => esc_attr__( 'Author Display Name', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_first_name',
						'label' => esc_attr__( 'Author First Name', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_last_name',
						'label' => esc_attr__( 'Author Last Name', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_info',
						'label' => esc_attr__( 'Author Bio Info', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_custom_field',
						'label' => esc_attr__( 'Author Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::COMMENTS_GROUP => array(
			// 	'label' => __( 'Comments', 'kadence-blocks-pro' ),
			// ),
		);
		return apply_filters( 'kadence_block_pro_dynamic_text_fields_options', $options );
	}
	/**
	 * Get conditional fields.
	 */
	public function get_conditional_fields() {
		$options = array(
			array(
				'label' => __( 'Post', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::POST_GROUP . '|post_custom_field',
						'label' => esc_attr__( 'Post Custom Field', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_title',
						'label' => esc_attr__( 'Post Title', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_url',
						'label' => esc_attr__( 'Post URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_id',
						'label' => esc_attr__( 'Post ID', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_date',
						'label' => esc_attr__( 'Post Date', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_date_modified',
						'label' => esc_attr__( 'Post Last Modified Date', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_featured_image_url',
						'label' => esc_attr__( 'Featured Image URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_type',
						'label' => esc_attr__( 'Post Type', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_status',
						'label' => esc_attr__( 'Post Status', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Archive', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_title',
						'label' => esc_attr__( 'Archive Title', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_url',
						'label' => esc_attr__( 'Archive URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_custom_field',
						'label' => esc_attr__( 'Archive Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Author', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::AUTHOR_GROUP . '|author_name',
						'label' => esc_attr__( 'Author Display Name', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_first_name',
						'label' => esc_attr__( 'Author First Name', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_last_name',
						'label' => esc_attr__( 'Author Last Name', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::AUTHOR_GROUP . '|author_custom_field',
						'label' => esc_attr__( 'Author Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Comments', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::COMMENTS_GROUP . '|count',
						'label' => esc_attr__( 'Comments Count', 'kadence-blocks-pro' ),
					),
				),
			),
		);
		return apply_filters( 'kadence_block_pro_dynamic_conditional_fields_options', $options );
	}
	/**
	 * Get the link fields
	 */
	public function get_link_fields() {
		$options = array(
			array(
				'label' => __( 'Post', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::POST_GROUP . '|post_url',
						'label' => esc_attr__( 'Post URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_featured_image_url',
						'label' => esc_attr__( 'Featured Image URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_custom_field',
						'label' => esc_attr__( 'Post Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Archive', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_url',
						'label' => esc_attr__( 'Archive URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_custom_field',
						'label' => esc_attr__( 'Archive Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Site', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::SITE_GROUP . '|site_url',
						'label' => esc_attr__( 'Site URL', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::MEDIA_GROUP => array(
			// 	'label' => __( 'Media', 'kadence-blocks-pro' ),
			// ),
			array(
				'label' => __( 'Author', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::AUTHOR_GROUP . '|author_url',
						'label' => esc_attr__( 'Author Archive URL', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::COMMENTS_GROUP => array(
			// 	'label' => __( 'Comments', 'kadence-blocks-pro' ),
			// ),
		);
		return apply_filters( 'kadence_block_pro_dynamic_link_fields_options', $options );
	}
	/**
	 * Get the link fields
	 */
	public function get_url_fields() {
		$options = array(
			array(
				'label' => __( 'Post', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::POST_GROUP . '|post_custom_field',
						'label' => esc_attr__( 'Post Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Archive', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_custom_field',
						'label' => esc_attr__( 'Archive Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
		);
		return apply_filters( 'kadence_block_pro_dynamic_url_fields_options', $options );
	}
	/**
	 * Get the image background fields
	 */
	public function get_background_fields() {
		$options = array(
			array(
				'label' => __( 'Post', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::POST_GROUP . '|post_featured_image_url',
						'label' => esc_attr__( 'Featured Image URL', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_custom_field',
						'label' => esc_attr__( 'Post Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Archive', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_custom_field',
						'label' => esc_attr__( 'Archive Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Site', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::SITE_GROUP . '|logo_url',
						'label' => esc_attr__( 'Logo Image URL', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::MEDIA_GROUP => array(
			// 	'label' => __( 'Media', 'kadence-blocks-pro' ),
			// ),
			array(
				'label' => __( 'Author', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::AUTHOR_GROUP . '|author_image_url',
						'label' => esc_attr__( 'Author Image URL', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::AUTHOR_GROUP => array(
			// 	'label' => __( 'Author', 'kadence-blocks-pro' ),
			// ),
			// self::COMMENTS_GROUP => array(
			// 	'label' => __( 'Comments', 'kadence-blocks-pro' ),
			// ),
		);
		return apply_filters( 'kadence_block_pro_dynamic_background_field_options', $options );
	}
	/**
	 * Get the image fields
	 */
	public function get_image_fields() {
		$options = array(
			array(
				'label' => __( 'Post', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::POST_GROUP . '|post_featured_image',
						'label' => esc_attr__( 'Featured Image', 'kadence-blocks-pro' ),
					),
					array(
						'value' => self::POST_GROUP . '|post_custom_field',
						'label' => esc_attr__( 'Post Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Archive', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::ARCHIVE_GROUP . '|archive_custom_field',
						'label' => esc_attr__( 'Archive Custom Field', 'kadence-blocks-pro' ),
					),
				),
			),
			array(
				'label' => __( 'Site', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::SITE_GROUP . '|logo',
						'label' => esc_attr__( 'Logo Image', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::MEDIA_GROUP => array(
			// 	'label' => __( 'Media', 'kadence-blocks-pro' ),
			// ),
			array(
				'label' => __( 'Author', 'kadence-blocks-pro' ),
				'options' => array(
					array(
						'value' => self::AUTHOR_GROUP . '|author_image',
						'label' => esc_attr__( 'Author Image', 'kadence-blocks-pro' ),
					),
				),
			),
			// self::AUTHOR_GROUP => array(
			// 	'label' => __( 'Author', 'kadence-blocks-pro' ),
			// ),
			// self::COMMENTS_GROUP => array(
			// 	'label' => __( 'Comments', 'kadence-blocks-pro' ),
			// ),
		);
		return apply_filters( 'kadence_block_pro_dynamic_image_fields_options', $options );
	}
	/**
	 * Render the dynamic content.
	 *
	 * @param array $args the content args
	 */
	public function get_content( $args, $post = null ) {
		$defaults = array(
			'source'       => 'current',
			'origin'       => 'core',
			'group'        => 'post',
			'type'         => 'text',
			'field'        => '',
			'custom'       => '',
			'para'         => '',
			'force-string' => '',
			'before'       => null,
			'after'        => null,
			'fallback'     => null,
		);
		$args           = wp_parse_args( $args, $defaults );
		$args['source'] = apply_filters( 'kadence_dynamic_item_id', $args['source'], $args, $post );
		$output         = $this->get_field_content( $args, $post );
		if ( $args['force-string'] && is_array( $output ) ) {
			if ( 'first' === $args['force-string'] ) {
				$output = reset( $output );
			}
			if ( is_array( $output ) ) {
				$output = implode( ',', $output );
			}
		}
		if ( ! $output && null !== $args['fallback'] ) {
			$output = $args['fallback'];
		}
		if ( ! is_array( $output ) && 'background' !== $args['type'] && 'image' !== $args['type'] && $args['before'] ) {
			$output = $args['before'] . $output;
		}
		if ( ! is_array( $output ) && $args['after'] ) {
			$output = $output . $args['after'];
		}
		return $output;
	}
	/**
	 * Get the content output.
	 *
	 * @param array $args the args.
	 * @param object/null $post the post.
	 */
	public function get_field_content( $args, $post = null ) {
		$defaults = array(
			'source'       => 'current',
			'origin'       => 'core',
			'group'        => 'post',
			'type'         => 'text',
			'field'        => '',
			'custom'       => '',
			'para'         => '',
			'before'       => null,
			'after'        => null,
		);
		$args     = wp_parse_args( $args, $defaults );
		$item_id  = $args['source'];
		$origin   = $args['origin'];
		$group    = $args['group'];
		$field    = $args['field'];
		$para     = $args['para'];
		$custom   = $args['custom'];
		$type     = $args['type'];
		$before   = $args['before'];
		$output   = '';
		if ( 'core' === $origin ) {
			// Render Core.
			if ( self::POST_GROUP === $group ) {
				if ( 'current' === $item_id || '' === $item_id ) {
					if ( $post && is_object( $post ) ) {
						$item_id = $post->ID;
					} else {
						$item_id = get_the_ID();
					}
				} else {
					$item_id = intval( $item_id );
				}
				if ( ! $post ) {
					$post = get_post( $item_id );
				}
				if ( $post && is_object( $post ) && 'publish' === $post->post_status && empty( $post->post_password ) ) {
					switch ( $field ) {
						case 'post_title':
							$output = wp_kses_post( get_the_title( $post ) );
							break;
						case 'post_date':
							$output = get_the_date( '', $post );
							break;
						case 'post_date_modified':
							$output = get_the_modified_date( '', $post );
							break;
						case 'post_type':
							$output = get_post_type( $post );
							break;
						case 'post_status':
							$output = get_post_status( $post );
							break;
						case 'post_id':
							$output = $post->ID;
							break;
						case 'post_url':
							$output = get_permalink( $post );
							break;
						case 'post_excerpt':
							// Perhaps a way to prevent excerpt inside excerpt endless loop.
							if ( ! doing_filter( 'get_the_excerpt' ) ) {
								$output = get_the_excerpt( $post );
							}
							break;
						case 'post_content':
							$output = get_the_content( $post );
							break;
						case 'post_custom_field':
							$output = '';
							if ( ! empty( $para ) ) {
								if ( 'kb_custom_input' === $para ) {
									if ( ! empty( $custom ) ) {
										$output = get_post_meta( $post->ID, $custom, true );
									}
								} else if ( strpos( $para, '|' ) !== false ) {
									list( $meta_type, $actual_key ) = explode( '|', $para );
									switch ( $meta_type ) {
										case 'acf_meta':
										case 'acf_option':
											if ( function_exists( 'get_field' ) ) {
												$post_ref = ( 'acf_option' === $meta_type ? 'option' : $post->ID );
												$output = get_field( $actual_key, $post_ref );
												if ( 'background' === $type ) {
													// Prep for Background.
													if ( $output && is_array( $output ) && isset( $output['url'] ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$output = ( $output['sizes'] && $output['sizes'][ $before ] ? $output['sizes'][ $before ] : $output['url'] );
														} else {
															$output = $output['url'];
														}
													} elseif ( $output === absint( $output ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$image     = wp_get_attachment_image_src( $output, $before );
															$output = ( $image && $image[0] ? $image[0] : '' );
														} else {
															$image     = wp_get_attachment_image_src( $output, 'full' );
															$output = ( $image && $image[0] ? $image[0] : '' );
														}
													}
												} elseif ( 'image' === $type ) {
													// Prep for Image.
													if ( $output && is_array( $output ) && isset( $output['id'] ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$output = wp_get_attachment_image_src( $output['id'], $before );
														} else {
															$output = wp_get_attachment_image_src( $output['id'], 'full' );
														}
													} elseif ( $output === absint( $output ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$output = wp_get_attachment_image_src( $output, $before );
														} else {
															$output = wp_get_attachment_image_src( $output, 'full' );
														}
													} elseif ( is_string( $output ) ) {
														$attachment_id = attachment_url_to_postid( $output );
														if ( $attachment_id ) {
															if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																$output = wp_get_attachment_image_src( $attachment_id, $before );
															} else {
																$output = wp_get_attachment_image_src( $attachment_id, 'full' );
															}
														}
													}
												}
											}
											break;
									}
								} else {
									$output = get_post_meta( $post->ID, $para, true );
								}
							}
							break;
						case 'post_featured_image_url':
							if ( 'background' === $type && $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
								$output = get_the_post_thumbnail_url( $post, $before );
							} else {
								$output = get_the_post_thumbnail_url( $post, 'full' );
							}
							break;
						case 'post_featured_image':
							if ( 'image' === $type && $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
								$output = ( has_post_thumbnail( $post ) ? wp_get_attachment_image_src( get_post_thumbnail_id( $post ), $before ) : array() );
							} else {
								$output = ( has_post_thumbnail( $post ) ? wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' ) : array() );
							}
							break;
						default:
							$output = apply_filters( 'kadence_dynamic_content_core_post_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
							break;
					}
				} else {
					$output = apply_filters( 'kadence_dynamic_content_core_post_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
				}
			} elseif ( self::AUTHOR_GROUP === $group ) {
				if ( 'current' === $item_id || '' === $item_id ) {
					if ( $post && is_object( $post ) ) {
						$item_id = $post->ID;
					} else {
						$item_id = get_the_ID();
					}
				} else {
					$item_id = intval( $item_id );
				}
				if ( ! $post ) {
					$post = get_post( $item_id );
				}
				$post_type_obj = get_post_type_object( get_post_type( $post ) );
				if ( $post && is_object( $post ) && 'publish' === $post->post_status && empty( $post->post_password ) && post_type_supports( $post_type_obj->name, 'author' ) ) {
					$author_id = get_post_field( 'post_author', $item_id );
					if ( $author_id ) {
						switch ( $field ) {
							case 'author_name':
								$output = esc_html( get_the_author_meta( 'display_name', $author_id ) );
								break;
							case 'author_first_name':
								$output = esc_html( get_the_author_meta( 'first_name', $author_id ) );
								break;
							case 'author_last_name':
								$output = esc_html( get_the_author_meta( 'last_name', $author_id ) );
								break;
							case 'author_info':
								$output = esc_html( get_the_author_meta( 'description', $author_id ) );
								break;
							case 'author_url':
								$output = esc_url( get_author_posts_url( $author_id ) );
								break;
							case 'author_image_url':
								if ( 'background' === $type && $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
									$args = array( 'size' => 96 );
									if ( 'large' === $before ) {
										$args['size'] = 1024;
									} elseif ( 'medium_large' === $before ) {
										$args['size'] = 768;
									} elseif ( 'medium_large' === $before ) {
										$args['size'] = 300;
									} elseif ( 'medium_large' === $before ) {
										$args['size'] = 150;
									}
									$output = get_avatar_url( $author_id, $args );
								} else {
									$output = get_avatar_url( $author_id );
								}
								break;
							case 'author_image':
								if ( 'image' === $type && $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
									$args = array( 'size' => 96 );
									if ( 'large' === $before ) {
										$args['size'] = 1024;
									} elseif ( 'medium_large' === $before ) {
										$args['size'] = 768;
									} elseif ( 'medium_large' === $before ) {
										$args['size'] = 300;
									} elseif ( 'medium_large' === $before ) {
										$args['size'] = 150;
									}
									$output = array( get_avatar_url( $author_id, $args ), $args['size'], $args['size'], true );
								} else {
									$output = array( get_avatar_url( $author_id ), 96, 96, true );
								}
								break;
							case 'author_custom_field':
								$output = '';
								if ( ! empty( $para ) ) {
									if ( 'kb_custom_input' === $para ) {
										if ( ! empty( $custom ) ) {
											$output = get_the_author_meta( $custom, $author_id );
										}
									} else if ( strpos( $para, '|' ) !== false ) {
										list( $meta_type, $actual_key ) = explode( '|', $para );
										switch ( $meta_type ) {
											case 'acf_meta':
												if ( function_exists( 'get_field' ) ) {
													$output = get_field( $actual_key, 'user_' . $author_id );
													if ( 'background' === $type ) {
														// Prep for Background.
														if ( $output && is_array( $output ) && isset( $output['url'] ) ) {
															if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																$output = ( $output['sizes'] && $output['sizes'][ $before ] ? $output['sizes'][ $before ] : $output['url'] );
															} else {
																$output = $output['url'];
															}
														} elseif ( $output === absint( $output ) ) {
															if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																$image     = wp_get_attachment_image_src( $output, $before );
																$output = ( $image && $image[0] ? $image[0] : '' );
															} else {
																$image     = wp_get_attachment_image_src( $output, 'full' );
																$output = ( $image && $image[0] ? $image[0] : '' );
															}
														}
													} elseif ( 'image' === $type ) {
														// Prep for Image.
														if ( $output && is_array( $output ) && isset( $output['id'] ) ) {
															if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																$output = wp_get_attachment_image_src( $output['id'], $before );
															} else {
																$output = wp_get_attachment_image_src( $output['id'], 'full' );
															}
														} elseif ( $output === absint( $output ) ) {
															if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																$output = wp_get_attachment_image_src( $output, $before );
															} else {
																$output = wp_get_attachment_image_src( $output, 'full' );
															}
														} elseif ( is_string( $output ) ) {
															$attachment_id = attachment_url_to_postid( $output );
															if ( $attachment_id ) {
																if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																	$output = wp_get_attachment_image_src( $attachment_id, $before );
																} else {
																	$output = wp_get_attachment_image_src( $attachment_id, 'full' );
																}
															}
														}
													}
												}
												break;
										}
									} else {
										$output = get_the_author_meta( $para, $author_id );
									}
								}
								break;
							default:
								$output = apply_filters( 'kadence_dynamic_content_core_author_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
								break;
						}
					} else {
						$output = apply_filters( 'kadence_dynamic_content_core_author_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
					}
				} else {
					$output = apply_filters( 'kadence_dynamic_content_core_author_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
				}
			} elseif ( self::ARCHIVE_GROUP === $group ) {
				if ( 'current' === $item_id || '' === $item_id ) {
					$item_id = get_queried_object_id();
				} else {
					$item_id = intval( $item_id );
				}
				switch ( $field ) {
					case 'archive_title':
						// This needs updated, won't get anything but the current archive title.
						$output = wp_kses_post( get_the_archive_title() );
						break;
					case 'archive_description':
						remove_filter( 'term_description', 'wpautop' );
						$output = wp_kses_post( get_the_archive_description() );
						add_filter( 'term_description', 'wpautop' );
						break;
					case 'archive_url':
						$output = get_the_permalink( $item_id );
						break;
					case 'archive_custom_field':
						$output = '';
						if ( ! empty( $para ) ) {
							if ( 'kb_custom_input' === $para ) {
								if ( ! empty( $custom ) ) {
									$output = get_term_meta( $item_id, $custom, true );
								}
							} else if ( strpos( $para, '|' ) !== false ) {
								list( $meta_type, $actual_key ) = explode( '|', $para );
								switch ( $meta_type ) {
									case 'acf_meta':
										if ( function_exists( 'get_field' ) ) {
											$term = get_queried_object();
											if ( is_object( $term ) && isset( $term->taxonomy ) ) {
												$output = get_field( $actual_key, $term->taxonomy . '_' . $item_id );
												if ( 'background' === $type ) {
													// Prep for Background.
													if ( $output && is_array( $output ) && isset( $output['url'] ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$output = ( $output['sizes'] && $output['sizes'][ $before ] ? $output['sizes'][ $before ] : $output['url'] );
														} else {
															$output = $output['url'];
														}
													} elseif ( $output === absint( $output ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$image     = wp_get_attachment_image_src( $output, $before );
															$output = ( $image && $image[0] ? $image[0] : '' );
														} else {
															$image     = wp_get_attachment_image_src( $output, 'full' );
															$output = ( $image && $image[0] ? $image[0] : '' );
														}
													}
												} elseif ( 'image' === $type ) {
													// Prep for Image.
													if ( $output && is_array( $output ) && isset( $output['id'] ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$output = wp_get_attachment_image_src( $output['id'], $before );
														} else {
															$output = wp_get_attachment_image_src( $output['id'], 'full' );
														}
													} elseif ( $output === absint( $output ) ) {
														if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
															$output = wp_get_attachment_image_src( $output, $before );
														} else {
															$output = wp_get_attachment_image_src( $output, 'full' );
														}
													} elseif ( is_string( $output ) ) {
														$attachment_id = attachment_url_to_postid( $output );
														if ( $attachment_id ) {
															if ( $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
																$output = wp_get_attachment_image_src( $attachment_id, $before );
															} else {
																$output = wp_get_attachment_image_src( $attachment_id, 'full' );
															}
														}
													}
												} elseif ( 'url' === $type ) {
													// Prep for URL.
													if ( $output && is_array( $output ) && isset( $output['url'] ) ) {
														$output = $output['url'];
													} elseif ( $output === absint( $output ) ) {
														$image  = wp_get_attachment_image_src( $output, 'full' );
														$output = ( $image && $image[0] ? $image[0] : '' );
													}
												}
											}
										}
										break;
								}
							} else {
								$output = get_term_meta( $item_id, $para, true );
							}
						}
						break;
					default:
						$output = apply_filters( 'kadence_dynamic_content_core_archive_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
						break;
				}
			} elseif ( self::SITE_GROUP === $group ) {
				switch ( $field ) {
					case 'site_title':
						$output = wp_kses_post( get_bloginfo( 'name' ) );
						break;
					case 'site_tagline':
						$output = wp_kses_post( get_bloginfo( 'description' ) );
						break;
					case 'logo_url':
						$logo      = get_theme_mod( 'custom_logo' );
						if ( 'background' === $type && $before && in_array( $before, array( 'large', 'medium', 'thumbnail', 'medium_large' ), true ) ) {
							$image     = wp_get_attachment_image_src( $logo, $before );
							$image_url = ( $image && $image[0] ? $image[0] : '' );
						} else {
							$image     = wp_get_attachment_image_src( $logo, 'full' );
							$image_url = ( $image && $image[0] ? $image[0] : '' );
						}
						$output = $image_url;
						break;
					case 'logo':
						$logo   = get_theme_mod( 'custom_logo' );
						$image  = wp_get_attachment_image_src( $logo, 'full' );
						$output = $image;
						break;
					case 'site_url':
						$output = get_home_url();
						break;
					case 'page_title':
						$output = wp_kses_post( $this->get_the_title() );
						break;
					case 'user_info':
						$user = wp_get_current_user();
						if ( 0 === $user->ID ) {
							$output = '';
							break;
						}
						if ( empty( $custom ) ) {
							$output = isset( $user->display_name ) ? $user->display_name : '';
							break;
						}
						switch ( $custom ) {
							case 'id':
								$output = isset( $user->ID ) ? $user->ID : '';
								break;
							case 'username':
								$output = isset( $user->user_login ) ? $user->user_login : '';
								break;
							case 'first_name':
								$output = isset( $user->first_name ) ? $user->first_name : '';
								break;
							case 'last_name':
								$output = isset( $user->last_name ) ? $user->last_name : '';
								break;
							case 'bio':
								$output = isset( $user->description ) ? $user->description : '';
								break;
							case 'email':
								$output = isset( $user->user_email ) ? $user->user_email : '';
								break;
							case 'website':
								$output = isset( $user->user_url ) ? $user->user_url : '';
								break;
							case 'meta':
								if ( ! empty( $key ) ) {
									$output = get_user_meta( $user->ID, $key, true );
								} else {
									$output = '';
								}
								break;
							default:
								// display name.
								$output = isset( $user->display_name ) ? $user->display_name : '';
								break;
						}
						break;
					default:
						$output = apply_filters( 'kadence_dynamic_content_core_site_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
						break;
				}
			} elseif ( self::COMMENTS_GROUP === $group ) {
				if ( 'current' === $item_id || '' === $item_id ) {
					if ( $post && is_object( $post ) ) {
						$item_id = $post->ID;
					} else {
						$item_id = get_the_ID();
					}
				} else {
					$item_id = intval( $item_id );
				}
				if ( ! $post ) {
					$post = get_post( $item_id );
				}
				switch ( $field ) {
					case 'comments_count':
						$output = get_comments_number( $post );
						break;
					default:
						$output = apply_filters( 'kadence_dynamic_content_core_comments_{$field}_render', '', $item_id, $origin, $group, $field, $para, $custom );
						break;
				}
			}
		} else {
			$output = apply_filters( 'kadence_dynamic_content_{$origin}_render', $item_id, $origin, $group, $field, $para, $custom );
		}
		return apply_filters( 'kadence_dynamic_content_render', $output, $item_id, $origin, $group, $field, $para, $custom );
	}
	/**
	 * Get the title output.
	 */
	public function get_the_title() {
		$output = '';
		if ( is_404() ) {
			$output = esc_html_e( 'Oops! That page can&rsquo;t be found.', 'kadence-blocks-pro' );
		} elseif ( is_home() && ! have_posts() ) {
			$output = esc_html_e( 'Nothing Found', 'kadence-blocks-pro' );
		} elseif ( is_home() && ! is_front_page() ) {
			$output = single_post_title();
		} elseif ( is_search() ) {
			$output = sprintf(
				/* translators: %s: search query */
				esc_html__( 'Search Results for: %s', 'kadence-blocks-pro' ),
				'<span>' . get_search_query() . '</span>'
			);
		} elseif ( is_archive() || is_home() ) {
			$output = get_the_archive_title();
		}
		return $output;
	}
	/**
	 * Render the dynamic shortcode.
	 *
	 * @param array $attributes the shortcode attributes.
	 */
	public function dynamic_shortcode_render( $attributes ) {
		$atts = shortcode_atts(
			array(
				'source'       => 'current',
				'origin'       => 'core',
				'type'         => 'text',
				'field'        => '',
				'custom'       => '',
				'para'         => '',
				'force-string' => true,
				'before'       => null,
				'after'        => null,
				'fallback'     => null,
			),
			$attributes
		);
		// Sanitize Attributes.
		$field = sanitize_text_field( $atts['field'] );
		$group = 'post';
		if ( ! empty( $field ) && strpos( $field, '|' ) !== false ) {
			$field_split = explode( '|', $field, 2 );
			$group = ( isset( $field_split[0] ) && ! empty( $field_split[0] ) ? $field_split[0] : 'post' );
			$field = ( isset( $field_split[1] ) && ! empty( $field_split[1] ) ? $field_split[1] : '' );
		}
		$args = array(
			'source'       => ! empty( $atts['source'] ) ? sanitize_text_field( $atts['source'] ) : 'current',
			'origin'       => ! empty( $atts['origin'] ) ? sanitize_text_field( $atts['origin'] ) : 'core',
			'group'        => $group,
			'type'         => sanitize_text_field( $atts['type'] ),
			'field'        => $field,
			'custom'       => sanitize_text_field( $atts['custom'] ),
			'para'         => sanitize_text_field( $atts['para'] ),
			'before'       => sanitize_text_field( $atts['before'] ),
			'after'        => sanitize_text_field( $atts['after'] ),
		);

		$fallback       = sanitize_text_field( $atts['fallback'] );
		$args['source'] = apply_filters( 'kadence_dynamic_item_id', $args['source'], $args );
		$output         = $this->get_field_content( $args );
		if ( $atts['force-string'] && is_array( $output ) ) {
			if ( 'first' === $atts['force-string'] ) {
				$output = reset( $output );
			}
			if ( is_array( $output ) ) {
				$output = implode( ',', $output );
			}
		}
		if ( ! $output && null !== $fallback ) {
			$output = $fallback;
		}
		if ( ! is_array( $output ) && 'background' !== $args['type'] && $args['before'] ) {
			$output = $args['before'] . $output;
		}
		if ( ! is_array( $output ) && $args['after'] ) {
			$output = $output . $args['after'];
		}
		return $output;
	}
}
Kadence_Blocks_Pro_Dynamic_Content::get_instance();
