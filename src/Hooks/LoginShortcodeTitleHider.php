<?php

namespace MYVH\Hooks;

class LoginShortcodeTitleHider {
    private int $page_id = 0;

    public function on_wp(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post = get_post();
        if ( ! $post || ! has_shortcode( $post->post_content, 'myvh_login' ) ) {
            return;
        }

        $this->page_id = (int) $post->ID;
        add_filter( 'the_title', [ $this, 'filter_title' ], 10, 2 );
    }

    public function filter_title( string $title, int $id = 0 ): string {
        return ( (int) $id === $this->page_id ) ? '' : $title;
    }
}
