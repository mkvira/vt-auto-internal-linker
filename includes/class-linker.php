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
	 * Entry point hooked to the_content. Fetches active rules and applies each.
	 */
	public function process_content( string $content ): string {
		$rules = array_filter(
			VTAIL_Rules_DB::get_all(),
			static fn( array $r ): bool => 1 === (int) $r['active']
		);

		if ( empty( $rules ) ) {
			return $content;
		}

		$current_url  = (string) get_permalink();
		$placeholders = [];
		$content      = $this->protect_blocks( $content, $placeholders );

		foreach ( $rules as $rule ) {
			if ( $this->is_self_link( (string) $rule['url'], $current_url ) ) {
				continue;
			}
			$content = $this->apply_rule( $content, $rule );
		}

		return $this->restore_blocks( $content, $placeholders );
	}

	/**
	 * Swaps protected regions for opaque placeholders before replacement runs.
	 * First alternative captures full <a>/<pre>/<code> blocks (including inner HTML).
	 * Second alternative captures every other HTML tag, protecting attributes from replacement.
	 */
	private function protect_blocks( string $content, array &$placeholders ): string {
		return preg_replace_callback(
			'/<(a|pre|code)(?:\s[^>]*)?>.*?<\/\1>|<[^>]+>/is',
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

	private function apply_rule( string $content, array $rule ): string {
		$keyword = (string) $rule['keyword'];
		if ( '' === $keyword ) {
			return $content;
		}

		$max     = max( 1, (int) $rule['max_per_post'] );
		$count   = 0;
		$pattern = $this->build_pattern( $keyword, (bool) $rule['case_sensitive'] );

		return preg_replace_callback(
			$pattern,
			function ( array $match ) use ( $rule, $max, &$count ): string {
				if ( $count >= $max ) {
					return $match[0];
				}
				++$count;
				return $this->build_link( $match[0], $rule );
			},
			$content
		) ?? $content;
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
		return untrailingslashit( $rule_url ) === untrailingslashit( $current_url );
	}

	private function build_link( string $text, array $rule ): string {
		$rel = [];

		if ( (int) $rule['nofollow'] ) {
			$rel[] = 'nofollow';
		}
		if ( (int) $rule['new_tab'] ) {
			$rel[] = 'noreferrer';
			$rel[] = 'noopener';
		}

		$attrs = 'href="' . esc_url( (string) $rule['url'] ) . '"';

		if ( ! empty( $rel ) ) {
			$attrs .= ' rel="' . implode( ' ', $rel ) . '"';
		}
		if ( (int) $rule['new_tab'] ) {
			$attrs .= ' target="_blank"';
		}

		return '<a ' . $attrs . '>' . $text . '</a>';
	}
}
