# Portal Calendar Access Documentation

## Overview
The My Village Hall plugin provides a customer portal with hash-based routing for different pages including the calendar.

---

## 1. Portal Shortcode & URL Pattern

### Shortcode
**`[myvh_portal]`**

Located in: [`modules/portal/class-myvh-portal-shortcode.php`](modules/portal/class-myvh-portal-shortcode.php)

### Portal Page
Create a WordPress page and add the `[myvh_portal]` shortcode to display the portal.

Typical URL pattern:
```
https://example.com/portal
```

(Exact URL depends on your WordPress page slug)

---

## 2. Hash-Based Routing System

The portal uses **client-side hash routing** to navigate between pages.

### Navigation Links
Located in: [`modules/portal/templates/portal-shell.php`](modules/portal/templates/portal-shell.php)

Available routes:
- `#dashboard` - Dashboard (default)
- `#bookings` - My Bookings
- `#calendar` - Calendar
- `#account` - Account

### Navigation HTML
```html
<a href="#dashboard">Dashboard</a>
<a href="#bookings">My Bookings</a>
<a href="#calendar">Calendar</a>
<a href="#account">Account</a>
```

---

## 3. How Portal Routing Works

Located in: [`assets/js/portal-app.js`](assets/js/portal-app.js)

### Router Flow

1. **Extract hash from URL**
   ```javascript
   let page = location.hash.replace("#", "") || "dashboard";
   ```

2. **Fetch page content via AJAX**
   ```javascript
   fetch(myvhPortal.ajax_url + "?action=myvh_portal_page&page=" + page)
       .then(r => r.text())
       .then(html => {
           document.getElementById("portal-content").innerHTML = html;
       });
   ```

3. **Listen for hash changes**
   ```javascript
   window.addEventListener("hashchange", router);
   ```

4. **Controller handles requests**
   Located in: [`modules/portal/class-myvh-portal-controller.php`](modules/portal/class-myvh-portal-controller.php)

   - AJAX action: `myvh_portal_page`
   - Query parameter: `page` (dashboard, bookings, calendar, account)
   - Returns appropriate template

---

## 4. Calendar Page Access From Portal

### Complete Portal Calendar URL
```
https://example.com/portal/#calendar
```

### How It Works
1. User clicks "Calendar" link in portal navigation
2. Hash changes to `#calendar`
3. Portal app detects hash change
4. AJAX request sent: `/wp-admin/admin-ajax.php?action=myvh_portal_page&page=calendar`
5. Controller tries to load: `modules/portal/templates/calendar.php`

   **⚠️ Note:** This file currently does not exist in the codebase. The implementation appears incomplete.

### Alternative Calendar Options

#### Option A: Separate Calendar Page
There's a public calendar shortcode: **`[myvh_calendar]`**
- Location: [`modules/calendar/class-myvh-calendar-shortcode.php`](modules/calendar/class-myvh-calendar-shortcode.php)
- Can be placed on a separate page (e.g., `/calendar`)
- Supports parameters: `venue_id`, `room_id`, `view`

#### Option B: Portal Calendar Shortcode
There's a portal-specific calendar shortcode: **`[myvh_portal_calendar]`**
- Location: [`modules/calendar/class-myvh-portal-calendar-shortcode.php`](modules/calendar/class-myvh-portal-calendar-shortcode.php)
- Uses AJAX endpoint: `myvh_portal_calendar_events`

---

## 5. Portal Theme Template

The portal is displayed using a custom page template:
- Location: [`my-village-hall-theme/templates/page-portal.php`](../themes/my-village-hall-theme/templates/page-portal.php)
- Template Name: "My Village Hall – Portal"
- Renders the `[myvh_portal]` shortcode

---

## Summary

| Item | Value |
|------|-------|
| **Shortcode** | `[myvh_portal]` |
| **Base Portal URL** | `https://example.com/portal` (depends on page slug) |
| **Calendar Hash Route** | `#calendar` |
| **Complete Calendar URL** | `https://example.com/portal/#calendar` |
| **Routing Method** | Client-side hash routing |
| **AJAX Action** | `myvh_portal_page` |
| **AJAX Parameter** | `?page=calendar` |

---

## Implementation Status

- ✅ Portal shortcode (`[myvh_portal]`) - Implemented
- ✅ Hash routing system - Implemented
- ✅ Navigation links - Implemented
- ⚠️ Calendar page template - **Missing** (modules/portal/templates/calendar.php not found)
- ✅ Alternative: Portal calendar shortcode (`[myvh_portal_calendar]`) - Implemented
- ✅ Alternative: Public calendar shortcode (`[myvh_calendar]`) - Implemented
