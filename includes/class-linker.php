<?php
/**
 * Content processing — keyword-to-link replacement engine.
 *
 * @package VT_Auto_Internal_Linker
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VTAIL_Linker {

	/** Unique byte-string token used as a placeholder during block protection. */
	private const PLACEHOLDER = "\x00VTAIL_%d\x00";

	/**
	 * Entry point hooked to the_content.
	 * Bails early on non-singular views and empty content to avoid unnecessary work.
	 */
	public function process_content( string $content ): string {
		if ( ! is_singular() || '' === trim( $content ) ) {
			return $content;
		}

		$rules = VTAIL_Rules_DB::get_active_rules_with_keywords();

		if ( empty( $rules ) ) {
			return $content;
		}

		$current_url  = (string) get_permalink();
		$block_tags   = $this->get_block_tags();
		$placeholders = [];
		$content      = $this->protect_blocks( $content, $placeholders, $block_tags );

		foreach ( $rules as $rule ) {
			if ( $this->is_self_link( $rule['url'], $current_url ) ) {
				continue;
			}
			$content = $this->apply_rule( $content, $rule );
		}

		return $this->restore_blocks( $content, $placeholders );
	}

	/**
	 * Merges hardcoded always-protected tags with the user-configured exclude list.
	 * a/pre/code are protected regardless of what the option contains.
	 *
	 * @return list<string>
	 */
	private function get_block_tags(): array {
		$hardcoded = [ 'a', 'pre', 'code' ];
		$option    = (string) get_option( 'vtail_exclude_tags', 'h1,h2,h3,h4,h5,h6' );
		$extra     = array_filter( array_map( 'trim', explode( ',', $option ) ) );
		return array_values( array_unique( array_merge( $hardcoded, $extra ) ) );
	}

	/**
	 * Swaps protected regions for opaque placeholders before replacement runs.
	 * First alternative captures full block-level tag content (inner HTML included).
	 * Second alternative captures every other HTML tag, protecting attributes from replacement.
	 *
	 * @param list<string> $block_tags Tag names whose full content should be protected.
	 */
	private function protect_blocks( string $content, array &$placeholders, array $block_tags ): string {
		$alts    = implode( '|', array_map( fn( string $t ): string => preg_quote( $t, '/' ), $block_tags ) );
		$pattern = '/<(' . $alts . ')(?:\s[^>]*)?>.*?<\/\1>|<[^>]+>/is';
		return preg_replace_callback(
			$pattern,
			function ( array $match ) use ( &$placeholders ): string {
				$index                = count( $placeholders );
				$placeholders[$index] = $match[0];
				return sprintf( self::PLACEHOLDER, $index );
			},
			$content
		) ?? $content;
	}

	private function restore_blocks( string $content, array $placeholders ): string {
		foreach ( $placeholders as $index => $original ) {
			$content = str_replace( sprintf( self::PLACEHOLDER, $index ), $original, $content );
		}
		return $content;
	}

	/**
	 * Applies all keywords for a single rule, respecting the rule-level max_per_post
	 * cap across all keywords combined. Keywords are already sorted by priority ASC.
	 */
	private function apply_rule( string $content, array $rule ): string {
		$links_to_url = 0;
		$rule_max     = max( 1, (int) $rule['max_per_post'] );

		foreach ( $rule['keywords'] as $keyword ) {
			if ( $links_to_url >= $rule_max ) {
				break;
			}
			$content = $this->apply_keyword( $content, $keyword, $rule['url'], $rule_max, $links_to_url );
		}

		return $content;
	}

	/**
	 * Applies a single keyword, respecting its own max_per_post, total_limit, and
	 * the shared rule-level counter $links_to_url (passed by reference).
	 */
	private function apply_keyword(
		string $content,
		array $keyword,
		string $rule_url,
		int $rule_max,
		int &$links_to_url
	): string {
		$kw_text = (string) $keyword['keyword'];
		if ( '' === $kw_text ) {
			return $content;
		}

		if ( $this->is_total_limit_reached( $keyword ) ) {
			return $content;
		}

		$kw_max  = max( 1, (int) $keyword['max_per_post'] );
		$kw_count = 0;
		$pattern = $this->build_pattern( $kw_text, (bool) $keyword['case_sensitive'] );
		$url     = $rule_url . ( '' !== $keyword['anchor'] ? '#' . $keyword['anchor'] : '' );

		return preg_replace_callback(
			$pattern,
			function ( array $match ) use ( $keyword, $url, $kw_max, $rule_max, &$kw_count, &$links_to_url ): string {
				if ( $kw_count >= $kw_max || $links_to_url >= $rule_max ) {
					return $match[0];
				}
				++$kw_count;
				++$links_to_url;
				return $this->build_link( $match[0], $url, $keyword );
			},
			$content
		) ?? $content;
	}

	/**
	 * Checks whether a keyword has reached its site-wide total_limit.
	 * Stats are loaded once per request and held in a static variable.
	 */
	private function is_total_limit_reached( array $keyword ): bool {
		$total_limit = (int) $keyword['total_limit'];
		if ( 0 === $total_limit ) {
			return false;
		}

		static $stats = null;
		if ( null === $stats ) {
			$stats = VTAIL_Rules_DB::get_stats();
		}

		$count = (int) ( $stats[ (string) $keyword['id'] ]['count'] ?? 0 );
		return $count >= $total_limit;
	}

	private function build_pattern( string $keyword, bool $case_sensitive ): string {
		$escaped = preg_quote( $keyword, '/' );
		$flags   = $case_sensitive ? 'u' : 'ui';
		// Lookarounds rather than \b: \b fails when keyword starts/ends with non-word chars (e.g. "C++").
		return '/(?<!\w)' . $escaped . '(?!\w)/' . $flags;
	}

	/**
	 * Returns true when $rule_url points at the page currently being rendered,
	 * preventing a post from linking to itself.
	 */
	private function is_self_link( string $rule_url, string $current_url ): bool {
		if ( '' === $current_url ) {
			return false;
		}
		// Strip anchor from rule_url before comparing — #section is not part of the page URL.
		$bare_url = (string) strtok( $rule_url, '#' );
		return untrailingslashit( $bare_url ) === untrailingslashit( $current_url );
	}

	private function build_link( string $text, string $url, array $keyword ): string {
		$rel = [];

		if ( (int) $keyword['nofollow'] ) {
			$rel[] = 'nofollow';
		}
		if ( (int) $keyword['new_tab'] ) {
			$rel[] = 'noreferrer';
			$rel[] = 'noopener';
		}

		$attrs = 'href="' . esc_url( $url ) . '"';

		if ( ! empty( $rel ) ) {
			$attrs .= ' rel="' . implode( ' ', $rel ) . '"';
		}
		if ( (int) $keyword['new_tab'] ) {
			$attrs .= ' target="_blank"';
		}

		return '<a ' . $attrs . '>' . $text . '</a>';
	}
}
