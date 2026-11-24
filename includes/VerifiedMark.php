<?php
/**
 * A small mark after the author's name on comments by users linked to a
 * provider, saying which provider verified their email address.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * VerifiedMark Class
 *
 * Plain HTML and CSS, in the theme's text colour; the tooltip is a title.
 * Classic themes print the name with get_comment_author_link(), block
 * themes with the Comment Author Name block.
 */
class VerifiedMark {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'get_comment_author_link', [ $this, 'mark' ], 10, 3 );
		add_filter( 'render_block_core/comment-author-name', [ $this, 'mark_block' ], 10, 3 );
	}

	/**
	 * Add the mark inside the Comment Author Name block.
	 *
	 * @param string    $content  Block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance, with the comment in its context.
	 * @return string
	 */
	public function mark_block( $content, $block, $instance ) {
		$comment_id = isset( $instance->context['commentId'] ) ? (int) $instance->context['commentId'] : 0;
		$position   = strrpos( $content, '</div>' );

		if ( ! $comment_id || false === $position ) {
			return $content;
		}

		return substr_replace( $content, $this->mark( '', '', $comment_id ), $position, 0 );
	}

	/**
	 * Whether the mark is on.
	 *
	 * @return bool
	 */
	public static function on() {
		return (bool) Settings::value( 'verified_mark' );
	}

	/**
	 * Add the mark after the author's name.
	 *
	 * @param string     $link       The author's name, linked or not.
	 * @param string     $author     The author's name.
	 * @param int|string $comment_id Comment ID.
	 * @return string
	 */
	public function mark( $link, $author, $comment_id ) {
		if ( ! self::on() || is_admin() ) {
			return $link;
		}

		$comment = get_comment( $comment_id );
		$linked  = $comment && $comment->user_id ? Providers::linked( (int) $comment->user_id ) : [];

		if ( ! $linked ) {
			return $link;
		}

		$provider = Providers::get( (string) key( $linked ) );
		/* translators: %s: provider name, such as Google. */
		$text = sprintf( __( 'Email verified by %s', 'nhrrob-social-login' ), $provider->label() );

		return $link . sprintf(
			' <span class="nhrrob-social-login-verified" title="%1$s"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"><path fill="currentColor" d="M12 1l2.6 2.2 3.4-.4.9 3.3 3 1.7-1.2 3.2 1.2 3.2-3 1.7-.9 3.3-3.4-.4L12 23l-2.6-2.2-3.4.4-.9-3.3-3-1.7 1.2-3.2L2.1 9.8l3-1.7.9-3.3 3.4.4z"/><path class="nhrrob-social-login-verified__tick" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M7.5 12.3l3 3 6-6"/></svg><span class="nhrrob-social-login-verified__text">%1$s</span></span>',
			esc_attr( $text )
		);
	}
}
