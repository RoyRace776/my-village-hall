# MYVH Hallflow Theme

A purpose-built classic WordPress theme for My Village Hall plugin sites.

## Included Templates

- MYVH Portal (`templates/template-portal.php`) renders `[myvh_portal]`
- MYVH Login (`templates/template-login.php`) renders `[myvh_login]`
- MYVH Public Calendar (`templates/template-public-calendar.php`) renders `[myvh_public_calendar]`

## Install

1. Copy the `myvh-hallflow` folder to `wp-content/themes/`.
2. In WordPress admin, go to Appearance > Themes and activate **MYVH Hallflow**.
3. Create three pages and assign templates:
   - Portal page -> **MYVH Portal**
   - Login page -> **MYVH Login**
   - Calendar page -> **MYVH Public Calendar**
4. Add those pages to your primary menu.

## Notes

- If the My Village Hall plugin is inactive, template pages show a helpful notice.
- The theme disables duplicate plugin font loading on portal pages to avoid style collisions.
