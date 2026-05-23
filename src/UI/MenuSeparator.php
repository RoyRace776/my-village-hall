<?php

namespace MYVH\UI;

class MenuSeparator {
    /** @var int */
    private int $separator_count = 0;

    /** @var array<int, string> */
    private array $separator_slugs = [];

    public function __construct() {
        add_action( 'admin_head', [ $this, 'render_styles' ] );
    }

    public function add( string $parent_slug ): void {
        $this->separator_count++;
        $slug = 'myvh-separator-' . $this->separator_count;

        add_submenu_page( $parent_slug, '', ' ', 'manage_options', $slug, '__return_null' );
        $this->separator_slugs[] = $slug;
    }

    public function render_styles(): void {
        foreach ( $this->separator_slugs as $slug ) {
            $safe = esc_attr( $slug );
            echo "<style>
li a[href=\"admin.php?page={$safe}\"] {
    pointer-events: none;
    cursor: default;
    height: 10px;
    margin: 6px 0;
    padding: 0;
    border-top: 1px solid rgba(255,255,255,0.2);
}
li a[href=\"admin.php?page={$safe}\"] span { display: none; }
</style>";
        }
    }
}
