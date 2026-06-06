<?php

namespace MYVH\Calendar;

use MYVH\Application\Services\EventDetailRenderer;
use MYVH\Bookings\BookingService;
use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\BookingAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventDetailShortcode implements ShortcodeInterface {
	private const TAG = 'myvh_event_detail';
	private const LEGACY_TAGS = [ 'event_deatils', 'event_details' ];

	private ?EventDetailRenderer $renderer = null;

	private function renderer(): EventDetailRenderer {
		if ( $this->renderer instanceof EventDetailRenderer ) {
			return $this->renderer;
		}

		global $myvh_container;
		if ( isset( $myvh_container ) && is_object( $myvh_container ) && method_exists( $myvh_container, 'get' ) ) {
			$this->renderer = $myvh_container->get( EventDetailRenderer::class );
		}

		if ( ! $this->renderer instanceof EventDetailRenderer ) {
			$this->renderer = new EventDetailRenderer(
				$myvh_container->get( BookingService::class ),
				$myvh_container->get( CustomerService::class ),
				$myvh_container->get( OrganisationService::class ),
				$myvh_container->get( OrganisationTypeService::class ),
				$myvh_container->get( ClientAdminService::class ),
				$myvh_container->get( OrganisationMemberRepository::class )
			);
		}

		return $this->renderer;
	}

	public function tag(): string {
		return self::TAG;
	}

	public function init(): void {
		foreach ( self::all_tags() as $tag ) {
			add_shortcode( $tag, [ $this, 'render' ] );
		}
	}

	public static function all_tags(): array {
		return array_values( array_unique( array_merge( [ self::TAG ], self::LEGACY_TAGS ) ) );
	}

	public function render( mixed $atts = [], mixed $content = null ): string {
		return $this->renderer()->render( (array) $atts );
	}
}
