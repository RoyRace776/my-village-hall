<?php

namespace MYVH\Calendar;

use MYVH\Application\Services\EventListRenderer;
use MYVH\Core\Shortcode\ShortcodeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventListShortcode implements ShortcodeInterface {
	private const TAG = 'myvh_event_list';

	private ?EventListRenderer $renderer = null;

	private function renderer(): EventListRenderer {
		if ( $this->renderer instanceof EventListRenderer ) {
			return $this->renderer;
		}

		global $myvh_container;
		if ( isset( $myvh_container ) && is_object( $myvh_container ) && method_exists( $myvh_container, 'get' ) ) {
			$this->renderer = $myvh_container->get( EventListRenderer::class );
		}

		if ( ! $this->renderer instanceof EventListRenderer ) {
			$this->renderer = new EventListRenderer( $myvh_container->get( CalendarService::class ) );
		}

		return $this->renderer;
	}

	public function tag(): string {
		return self::TAG;
	}

	public function init(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	public function render( mixed $atts = [], mixed $content = null ): string {
		return $this->renderer()->render( (array) $atts );
	}
}
