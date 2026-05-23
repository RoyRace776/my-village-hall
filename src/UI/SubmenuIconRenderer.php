<?php

namespace MYVH\UI;

class SubmenuIconRenderer {
    /**
     * Inject submenu icons with JS because WordPress escapes submenu labels.
     */
    public function render(): void {
        $icon_map = [
            'admin.php?page=my-village-hall' => 'dashicons-list-view',
            'admin.php?page=myvh-calendar' => 'dashicons-calendar-alt',
            'admin.php?page=myvh-customers' => 'dashicons-groups',
            'admin.php?page=myvh-organisations' => 'dashicons-admin-multisite',
            'admin.php?page=myvh-org-types' => 'dashicons-category',
            'admin.php?page=myvh-org-members' => 'dashicons-id-alt',
            'admin.php?page=myvh-client-admins-network' => 'dashicons-admin-users',
            'admin.php?page=myvh-venues' => 'dashicons-location-alt',
            'admin.php?page=myvh-rooms' => 'dashicons-admin-home',
            'admin.php?page=myvh-room-rates' => 'dashicons-money-alt',
            'admin.php?page=myvh-addons' => 'dashicons-admin-plugins',
            'admin.php?page=myvh-invoices' => 'dashicons-media-spreadsheet',
            'admin.php?page=myvh-payments' => 'dashicons-money',
            'admin.php?page=myvh-invoice-generate' => 'dashicons-media-document',
            'admin.php?page=myvh-single-booking-invoice-rules' => 'dashicons-filter',
            'admin.php?page=myvh-recurring-booking-invoice-rules' => 'dashicons-filter',
            'admin.php?page=myvh-recurring' => 'dashicons-update-alt',
            'admin.php?page=myvh-audit-log' => 'dashicons-visibility',
            'admin.php?page=myvh-settings' => 'dashicons-admin-generic',
        ];

        $json_map = wp_json_encode( $icon_map );

        echo '<style>.myvh-submenu-icon{font-size:16px;width:18px;height:18px;line-height:18px;margin-right:6px;vertical-align:text-bottom;}</style>';
        echo '<script>(function(){var map=' . $json_map . ';function apply(){if(!map){return;}Object.keys(map).forEach(function(href){var link=document.querySelector("#adminmenu .wp-submenu a[href=\\""+href+"\\"]");if(!link||link.dataset.myvhIconApplied==="1"){return;}var icon=document.createElement("span");icon.className="dashicons "+map[href]+" myvh-submenu-icon";icon.setAttribute("aria-hidden","true");link.insertBefore(icon,link.firstChild);link.dataset.myvhIconApplied="1";});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",apply);}else{apply();}})();</script>';
    }
}
