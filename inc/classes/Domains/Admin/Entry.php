<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\Admin;

use J7\PowerFunnel\Bootstrap;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\App;
use J7\Powerhouse\Utils\Base as PowerhouseBase;

/** Class Entry */
final class Entry {

	/** Register hooks */
	public static function register_hooks(): void {
		// Add the admin page for full-screen.
		\add_action('current_screen', [ __CLASS__, 'maybe_output_admin_page' ], 10);
	}

	/** Output the dashboard admin page. */
	public static function maybe_output_admin_page(): void {
		// Exit if not in admin.
		if (!\is_admin()) {
			return;
		}

		// Make sure we're on the right screen.
		$screen = \get_current_screen();

		if (Plugin::$kebab !== $screen?->id) {
			return;
		}

		self::render_page();

		exit;
	}

	/**
	 * Output landing page header.
	 *
	 * Credit: SliceWP Setup Wizard.
	 */
	public static function render_page(): void {
		// Output header HTML.
		Bootstrap::enqueue_script();
		$blog_name = \get_bloginfo('name');
		$id        = substr( App::APP1_SELECTOR, 1);
		PowerhouseBase::render_admin_layout(
			[
				'title' => "Power Funnel | {$blog_name}",
				'id'    => $id,
			]
			);
	}
}
