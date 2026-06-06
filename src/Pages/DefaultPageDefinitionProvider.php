<?php

declare(strict_types=1);

namespace MYVH\Pages;

use MYVH\Pages\DTO\PageDefinition;

class DefaultPageDefinitionProvider implements \MYVH\Pages\Contracts\PageDefinitionProviderInterface {
    public function getPages(): array {
        return [
            new PageDefinition(
                'create_site',
                'Create Site',
                'create-site',
                'myvh_create_site'
            ),
            new PageDefinition(
                'login',
                'Login',
                'login',
                'myvh_login mode="login" register_page_url="http://myvh-dev.local/register/"'
            ),
            new PageDefinition(
                'register',
                'Register',
                'register',
                'myvh_login mode="register" login_page_url="http://myvh-dev.local/login/"'
            ),
            new PageDefinition(
                'password_reset',
                'Password Reset',
                'password-reset',
                'myvh_password_reset'
            ),
            new PageDefinition(
                'portal',
                'Portal',
                'portal',
                'myvh_portal'
            ),
            new PageDefinition(
                'event_list',
                'Event List',
                'event-list',
                'myvh_event_list'
            ),
            new PageDefinition(
                'event_detail',
                'Event Detail',
                'event-detail',
                'myvh_event_detail'
            ),
            new PageDefinition(
                'public_calendar',
                'Public Calendar',
                'public-calendar',
                'myvh_public_calendar',
                true
            ),
        ];
    }
}
