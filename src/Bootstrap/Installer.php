<?php
/**
 * Installer
 *
 * Handles all database table creation and schema migrations.
 * Called once during plugin activation via Installer::run().
 *
 * Tables are created with WordPress's dbDelta() function, which compares
 * the desired schema against existing tables and applies only the
 * necessary changes — making it safe to re-run on updates.
 *
 * Table inventory:
 *   Venues & Rooms        myvh_venues, myvh_rooms
 *   Customers             myvh_customers
 *   Bookings              myvh_bookings, myvh_recurring_patterns
 *   Pricing               myvh_room_rates, myvh_addons
 *   Booking line items    myvh_booking_charges, myvh_booking_addons, myvh_booking_discounts
 *   Discounts             myvh_discounts
 *   Invoices & Payments   myvh_invoices, myvh_invoice_items, myvh_payments
 *
 * @since 0.1.0
 */
namespace MYVH\Bootstrap;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Installer {

    /**
     * Entry point: create all tables then apply foreign-key constraints.
     *
     * @global wpdb $wpdb
     */
    public static function run(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = $wpdb->get_charset_collate();

        self::create_tables( $wpdb, $collate );
        self::backfill_opening_hours_by_day( $wpdb );
        self::add_foreign_keys( $wpdb );
        self::set_default_data( $wpdb );
    }

    // ── Table definitions ─────────────────────────────────────────────────────

    /**
     * Run dbDelta() for every table in the plugin.
     *
     * @param wpdb   $wpdb
     * @param string $collate  e.g. "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
     */
    private static function create_tables( wpdb $wpdb, string $collate ): void {

        $p = $wpdb->prefix; // shorthand

        // ── Venues ────────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_venues (
            Id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name          VARCHAR(100) NOT NULL,
            ShortName     VARCHAR(100),
            PostCode      VARCHAR(100),
            AddressLine1  VARCHAR(100),
            OpeningTime   TIME DEFAULT '09:00:00',
            ClosingTime   TIME DEFAULT '17:00:00',
            INDEX idx_name (Name)
        ) {$collate};" );

        // ── Rooms ─────────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_rooms (
            Id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            VenueId                 INT UNSIGNED NOT NULL,
            Name                    VARCHAR(100) NOT NULL,
            Colour                  VARCHAR(7),
            Description             VARCHAR(255),
            BufferBefore            INT UNSIGNED DEFAULT 0,
            BufferAfter             INT UNSIGNED DEFAULT 0,
            Capacity                INT UNSIGNED DEFAULT 0,
            OpeningTime             TIME,
            ClosingTime             TIME,
            AllowMultiDayBookings   TINYINT(1) DEFAULT 0 NOT NULL,
            CalcClosedHours         TINYINT(1) DEFAULT 0 NOT NULL,
            IsPublic                TINYINT(1) DEFAULT 1 NOT NULL,
            INDEX idx_venue (VenueId),
            INDEX idx_name  (Name),
            INDEX idx_public (IsPublic)
        ) {$collate};" );

        // ── Venue Opening Hours By Day ─────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_venue_hours (
            Id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            VenueId       INT UNSIGNED NOT NULL,
            DayOfWeek     TINYINT UNSIGNED NOT NULL,
            IsClosed      TINYINT(1) NOT NULL DEFAULT 0,
            OpeningTime   TIME NULL,
            ClosingTime   TIME NULL,
            UNIQUE KEY uq_venue_day (VenueId, DayOfWeek),
            INDEX idx_venue (VenueId)
        ) {$collate};" );

        // ── Room Opening Hours By Day ──────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_room_hours (
            Id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            RoomId         INT UNSIGNED NOT NULL,
            DayOfWeek      TINYINT UNSIGNED NOT NULL,
            UseVenueHours  TINYINT(1) NOT NULL DEFAULT 1,
            IsClosed       TINYINT(1) NOT NULL DEFAULT 0,
            OpeningTime    TIME NULL,
            ClosingTime    TIME NULL,
            UNIQUE KEY uq_room_day (RoomId, DayOfWeek),
            INDEX idx_room (RoomId)
        ) {$collate};" );

        // ── Organisations  ───────────────────────────────────────────────────
        //
        dbDelta( "CREATE TABLE {$p}myvh_organisations (
            Id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name               VARCHAR(100) NOT NULL,
            OrganisationTypeId  INT UNSIGNED NOT NULL,
            ContactEmail       VARCHAR(100),
            ContactPhone       VARCHAR(100),
            WebsiteUrl         VARCHAR(255),
            InvoiceOrganisationBookings TINYINT(1) NOT NULL DEFAULT 0,
            SendBookingEmailsToOrganisation TINYINT(1) NOT NULL DEFAULT 0,
            AllowAutoConfirm TINYINT(1) NOT NULL DEFAULT 0,
            BillingContactName VARCHAR(150) NULL,
            BillingEmail       VARCHAR(150) NULL,
            BillingAddressLine1 VARCHAR(255) NULL,
            BillingAddressLine2 VARCHAR(255) NULL,
            BillingTownCity    VARCHAR(120) NULL,
            BillingPostcode    VARCHAR(30) NULL,
            BillingReference   VARCHAR(100) NULL,
            IsActive           TINYINT(1)    DEFAULT 1,
            IsDefault          TINYINT(1)    DEFAULT 0,
            IsSystem           TINYINT(1)    NOT NULL DEFAULT 0,
            DefaultPublic      TINYINT(1)    DEFAULT 0,
            Created            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_name    (Name),
            INDEX      idx_org_type (OrganisationTypeId),
            INDEX      idx_active  (IsActive),
            INDEX      idx_default (IsDefault),
            INDEX      idx_system (IsSystem),
            INDEX      idx_default_public (DefaultPublic),
            INDEX      idx_invoice_org (InvoiceOrganisationBookings),
            INDEX      idx_send_booking_emails_org (SendBookingEmailsToOrganisation)
        ) {$collate};" );

        // ── Organisation Types ───────────────────────────────────────────────────
        //
        dbDelta( "CREATE TABLE {$p}myvh_organisation_types (
            Id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name               VARCHAR(100) NOT NULL,
            Description        VARCHAR(255),
            IsSystem           TINYINT(1) NOT NULL DEFAULT 0,
            IsDefault          TINYINT(1) NOT NULL DEFAULT 0,
            Created            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY         uq_name (Name),
            INDEX              idx_is_system (IsSystem),
            INDEX              idx_is_default (IsDefault)
        ) {$collate};" );

        // ── Organisation Members ───────────────────────────────────────────────────
        //
        dbDelta( "CREATE TABLE {$p}myvh_organisation_members (
            Id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            OrganisationId      INT UNSIGNED NOT NULL,
            CustomerId          INT UNSIGNED NOT NULL,
            IsOrganisationAdmin TINYINT(1) NOT NULL DEFAULT 0,
            Created             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX          idx_customer (CustomerId),
            UNIQUE KEY     uq_member (OrganisationId, CustomerId)
        ) {$collate};" );

        dbDelta( "CREATE TABLE {$p}myvh_organisation_member_requests (
            Id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            OrganisationId       INT UNSIGNED NOT NULL,
            CustomerId           INT UNSIGNED NOT NULL,
            Status               VARCHAR(20) NOT NULL DEFAULT 'pending',
            RequestMessage       VARCHAR(500),
            ReviewedByCustomerId INT UNSIGNED NULL,
            ReviewedAt           DATETIME NULL,
            Created              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_org_status (OrganisationId, Status),
            INDEX idx_customer_status (CustomerId, Status)
        ) {$collate};" );

    // ── Customers ─────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_customers (
            Id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            Name            VARCHAR(100) NOT NULL,
            WPUserId        BIGINT UNSIGNED,
            Email           VARCHAR(100) NOT NULL,
            EmailVerified   TINYINT(1)   DEFAULT 0,
            PhoneNumber     VARCHAR(100),
            PostCode        VARCHAR(100),
            AddressLine1    VARCHAR(100),
            AddressLine2    VARCHAR(100),
            AllowAutoConfirm   TINYINT(1)   NOT NULL DEFAULT 0,
            IsSystem        TINYINT(1)   NOT NULL DEFAULT 0,
            Created         DATETIME     DEFAULT CURRENT_TIMESTAMP,
            Updated         DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX      idx_email          (Email),
            UNIQUE KEY uq_wpuser (WPUserId)
        ) {$collate};" );

        // ── Bookings ──────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_bookings (
            Id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            CustomerId         INT UNSIGNED NOT NULL,
            OrganisationId     INT UNSIGNED NOT NULL,
            RoomId             INT UNSIGNED NOT NULL,
            Status             VARCHAR(12)  NOT NULL,
            StartDate          DATE         NOT NULL,
            EndDate            DATE         NOT NULL,
            StartTime          TIME         NOT NULL,
            EndTime            TIME         NOT NULL,
            ChargeableHours    INT UNSIGNED DEFAULT NULL,
            Public             TINYINT(1)   DEFAULT 1,
            NoInvoiceRequired  TINYINT(1)   NOT NULL DEFAULT 0,
            Description        VARCHAR(255),
            RecurringPatternId INT UNSIGNED  DEFAULT NULL,
            Created            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user          (CustomerId),
            INDEX idx_organisation  (OrganisationId),
            INDEX idx_room          (RoomId),
            INDEX idx_dates         (StartDate, EndDate),
            INDEX idx_recurring     (RecurringPatternId)
        ) {$collate};" );

        // ── Recurring Patterns ────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_recurring_patterns (
            Id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            ParentBookingId    INT UNSIGNED  NOT NULL,
            RecurrenceType     VARCHAR(50)   NOT NULL,
            RecurrenceInterval INT UNSIGNED  DEFAULT 1,
            RecurrenceDay      VARCHAR(20),
            RecurrenceWeek     VARCHAR(20),
            StartDate          DATE          NOT NULL,
            EndDate            DATE,
            MaxOccurrences     INT UNSIGNED  DEFAULT NULL,
            OccurrenceCount    INT UNSIGNED  DEFAULT 0,
            IsActive           TINYINT(1)    DEFAULT 1,
            Created            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parent (ParentBookingId),
            INDEX idx_active (IsActive)
        ) {$collate};" );

        // ── Room Rates ────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_room_rates (
            Id                  INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            RoomId              INT UNSIGNED   NOT NULL,
            OrganisationTypeId  INT UNSIGNED   DEFAULT NULL,
            ChargeType          VARCHAR(12)    NOT NULL,
            Rate                DECIMAL(10,2)  NOT NULL,
            Name                VARCHAR(100)   NOT NULL,
            Description         VARCHAR(255),
            MinimumHours        DECIMAL(5,2)   DEFAULT NULL,
            IsActive            TINYINT(1)     DEFAULT 1,
            ValidFrom           DATE           DEFAULT NULL,
            ValidTo             DATE           DEFAULT NULL,
            Priority            INT UNSIGNED   DEFAULT 0,
            Created             TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room           (RoomId),
            INDEX idx_org_type       (OrganisationTypeId),
            INDEX idx_active         (IsActive),
            INDEX idx_valid_dates    (ValidFrom, ValidTo),
            INDEX idx_priority       (Priority)
        ) {$collate};" );

        // ── Add-ons ───────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_addons (
            Id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            Name            VARCHAR(100)  NOT NULL,
            Description     VARCHAR(255),
            Price           DECIMAL(10,2) NOT NULL,
            ChargeType      VARCHAR(12)   NOT NULL,
            RoomId          INT UNSIGNED  DEFAULT NULL,
            VenueId         INT UNSIGNED  DEFAULT NULL,
            IsActive        TINYINT(1)    DEFAULT 1,
            DisplayOrder    INT UNSIGNED  DEFAULT 0,
            Created         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            ArchivedAt      DATETIME      DEFAULT NULL,
            INDEX idx_room           (RoomId),
            INDEX idx_venue          (VenueId),
            INDEX idx_active         (IsActive),
            INDEX idx_order          (DisplayOrder),
            INDEX idx_archived       (ArchivedAt)
        ) {$collate};" );

        // ── Booking Charges ───────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_booking_charges (
            Id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            BookingId    INT UNSIGNED   NOT NULL,
            RoomRateId   INT UNSIGNED   NOT NULL,
            ChargeType   VARCHAR(12)    NOT NULL,
            Description  VARCHAR(255),
            Quantity     DECIMAL(10,2)  NOT NULL DEFAULT 1,
            UnitPrice    DECIMAL(10,2)  NOT NULL,
            TotalAmount  DECIMAL(10,2)  NOT NULL,
            TaxRate      DECIMAL(5,2)   DEFAULT 0.00,
            TaxAmount    DECIMAL(10,2)  DEFAULT 0.00,
            Created      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking   (BookingId),
            INDEX idx_room_rate (RoomRateId)
        ) {$collate};" );

        // ── Booking Add-ons ───────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_booking_addons (
            Id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            BookingId   INT UNSIGNED   NOT NULL,
            AddonId     INT UNSIGNED   NOT NULL,
            Quantity    DECIMAL(10,2)  NOT NULL DEFAULT 1,
            UnitPrice   DECIMAL(10,2)  NOT NULL,
            TotalAmount DECIMAL(10,2)  NOT NULL,
            Description VARCHAR(255),
            Created     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (BookingId),
            INDEX idx_addon   (AddonId)
        ) {$collate};" );

        // ── Discounts ─────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_discounts (
            Id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            Code            VARCHAR(50)    NOT NULL,
            Description     VARCHAR(255),
            DiscountType    VARCHAR(20)    NOT NULL,
            DiscountValue   DECIMAL(10,2)  NOT NULL,
            MinimumAmount   DECIMAL(10,2)  DEFAULT 0.00,
            MaximumDiscount DECIMAL(10,2)  DEFAULT NULL,
            ValidFrom       DATE           DEFAULT NULL,
            ValidTo         DATE           DEFAULT NULL,
            UsageLimit      INT UNSIGNED   DEFAULT NULL,
            UsageCount      INT UNSIGNED   DEFAULT 0,
            IsActive        TINYINT(1)     DEFAULT 1,
            RoomId          INT UNSIGNED   DEFAULT NULL,
            VenueId         INT UNSIGNED   DEFAULT NULL,
            Created         TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_code         (Code),
            INDEX      idx_code        (Code),
            INDEX      idx_active      (IsActive),
            INDEX      idx_valid_dates (ValidFrom, ValidTo),
            INDEX      idx_room        (RoomId),
            INDEX      idx_venue       (VenueId)
        ) {$collate};" );

        // ── Booking Discounts ─────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_booking_discounts (
            Id             INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            BookingId      INT UNSIGNED   NOT NULL,
            DiscountId     INT UNSIGNED   DEFAULT NULL,
            DiscountCode   VARCHAR(50),
            DiscountType   VARCHAR(20)    NOT NULL,
            DiscountValue  DECIMAL(10,2)  NOT NULL,
            DiscountAmount DECIMAL(10,2)  NOT NULL,
            Description    VARCHAR(255),
            Created        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking  (BookingId),
            INDEX idx_discount (DiscountId)
        ) {$collate};" );

        // ── Invoices ──────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_invoices (
            Id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            InvoiceNumber VARCHAR(50)    NOT NULL,
            CustomerId    INT UNSIGNED   NOT NULL,
            BillingName   VARCHAR(150)   NULL,
            BillingOrganisationName VARCHAR(150) NULL,
            BillingEmail  VARCHAR(150)   NULL,
            BillingAddressLine1 VARCHAR(255) NULL,
            BillingAddressLine2 VARCHAR(255) NULL,
            BillingTownCity VARCHAR(120) NULL,
            BillingPostcode VARCHAR(30) NULL,
            BillingReference VARCHAR(100) NULL,
            InvoiceDate   DATE           NOT NULL,
            DueDate       DATE,
            SubTotal      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            TaxAmount     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            TotalAmount   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            AmountPaid    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            AmountDue     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            Status        VARCHAR(50)    NOT NULL DEFAULT 'Draft',
            Notes         TEXT,
            PdfPath       VARCHAR(500)   NULL,
            Created       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            Updated       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_invoice_number (InvoiceNumber),
            INDEX      idx_customer      (CustomerId),
            INDEX      idx_status        (Status),
            INDEX      idx_dates         (InvoiceDate, DueDate)
        ) {$collate};" );

        // ── Invoice Items ─────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_invoice_items (
            Id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            InvoiceId    INT UNSIGNED   NOT NULL,
            BookingId    INT UNSIGNED   DEFAULT NULL,
            Description  VARCHAR(255)   NOT NULL,
            Quantity     DECIMAL(10,2)  NOT NULL DEFAULT 1,
            UnitPrice    DECIMAL(10,2)  NOT NULL,
            TaxRate      DECIMAL(5,2)   DEFAULT 0.00,
            TaxAmount    DECIMAL(10,2)  DEFAULT 0.00,
            TotalAmount  DECIMAL(10,2)  NOT NULL,
            DisplayOrder INT UNSIGNED   DEFAULT 0,
            INDEX idx_invoice (InvoiceId),
            INDEX idx_booking (BookingId),
            INDEX idx_order   (DisplayOrder)
        ) {$collate};" );

        // ── Payments ──────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_payments (
            Id                   INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            InvoiceId            INT UNSIGNED   NOT NULL,
            PaymentDate          DATE           NOT NULL,
            Amount               DECIMAL(10,2)  NOT NULL,
            PaymentMethod        VARCHAR(50),
            TransactionReference VARCHAR(100),
            Notes                TEXT,
            Created              TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_invoice (InvoiceId),
            INDEX idx_date    (PaymentDate)
        ) {$collate};" );

        // ── Audit Log ─────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_audit_log (
            Id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Action      VARCHAR(20)     NOT NULL,
            EntityType  VARCHAR(50)     NOT NULL,
            EntityId    BIGINT UNSIGNED NULL,
            ActorUserId BIGINT UNSIGNED NOT NULL DEFAULT 0,
            Origin      VARCHAR(20)     NOT NULL,
            Summary     LONGTEXT        NULL,
            CreatedAt   DATETIME        NOT NULL,
            INDEX idx_action (Action),
            INDEX idx_entity (EntityType, EntityId),
            INDEX idx_actor (ActorUserId),
            INDEX idx_origin (Origin),
            INDEX idx_created (CreatedAt)
        ) {$collate};" );

        // Site Provisioning Service.  Uses base_prefix since it operates at the network level and needs to be accessible from the main site.
        if ( is_multisite() ) {
            $table = $wpdb->base_prefix . 'myvh_site_provisioning';
            $sql = "CREATE TABLE $table (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                token VARCHAR(64) NOT NULL,
                subdomain VARCHAR(100) NOT NULL,
                site_name VARCHAR(255) NOT NULL,
                admin_email VARCHAR(255) NOT NULL,
                admin_first_name VARCHAR(255) NOT NULL,
                admin_last_name VARCHAR(255) NOT NULL,
                admin_password VARCHAR(255) NOT NULL,
                logo_url VARCHAR(255) NULL,
                user_id BIGINT UNSIGNED NULL,
                blog_id BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL,
                error TEXT NULL,
                logo_url VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY token (token),
                KEY status (status),
                KEY blog_id (blog_id)
            ) $collate;";

            dbDelta($sql);
        }
    }

    // ── Foreign keys ──────────────────────────────────────────────────────────

    /**
     * Add all foreign-key constraints.
     *
     * Each constraint is checked for existence before being applied, so this
     * method is idempotent and safe to call on re-activation.
     *
     * @param  wpdb  $wpdb
     * @return array Result log: [ ['constraint'=>…, 'status'=>'added|skipped|error', 'message'=>…] ]
     */
    private static function add_foreign_keys( wpdb $wpdb ): array {

        $p = $wpdb->prefix;

        /**
         * Each entry: [ table, constraint name, ALTER TABLE SQL ]
         *
         * ON DELETE RESTRICT  — prevents orphaned records.
         * ON UPDATE CASCADE   — keeps FKs in sync if a parent PK changes.
         */
        $foreign_keys = [

        // ── Organisations ─────────────────────────────────────────────────────────

        // Organisations → Organisation Types
        [ "{$p}myvh_organisations", 'fk_organisations_org_type',
          "ALTER TABLE {$p}myvh_organisations
           ADD CONSTRAINT fk_organisations_org_type
           FOREIGN KEY (OrganisationTypeId) REFERENCES {$p}myvh_organisation_types(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Organisation Members → Organisations
        [ "{$p}myvh_organisation_members", 'fk_org_members_organisation',
          "ALTER TABLE {$p}myvh_organisation_members
           ADD CONSTRAINT fk_org_members_organisation
           FOREIGN KEY (OrganisationId) REFERENCES {$p}myvh_organisations(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Organisation Members → Users
        [ "{$p}myvh_organisation_members", 'fk_org_members_user',
          "ALTER TABLE {$p}myvh_organisation_members
           ADD CONSTRAINT fk_org_members_user
           FOREIGN KEY (CustomerId) REFERENCES {$p}myvh_customers(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

                // Organisation Member Requests → Organisations
                [ "{$p}myvh_organisation_member_requests", 'fk_org_member_requests_organisation',
                    "ALTER TABLE {$p}myvh_organisation_member_requests
                     ADD CONSTRAINT fk_org_member_requests_organisation
                     FOREIGN KEY (OrganisationId) REFERENCES {$p}myvh_organisations(Id)
                     ON DELETE RESTRICT ON UPDATE CASCADE" ],

                // Organisation Member Requests → Customers (requesting member)
                [ "{$p}myvh_organisation_member_requests", 'fk_org_member_requests_customer',
                    "ALTER TABLE {$p}myvh_organisation_member_requests
                     ADD CONSTRAINT fk_org_member_requests_customer
                     FOREIGN KEY (CustomerId) REFERENCES {$p}myvh_customers(Id)
                     ON DELETE RESTRICT ON UPDATE CASCADE" ],

                // Organisation Member Requests → Customers (reviewing admin)
                [ "{$p}myvh_organisation_member_requests", 'fk_org_member_requests_reviewer',
                    "ALTER TABLE {$p}myvh_organisation_member_requests
                     ADD CONSTRAINT fk_org_member_requests_reviewer
                     FOREIGN KEY (ReviewedByCustomerId) REFERENCES {$p}myvh_customers(Id)
                     ON DELETE SET NULL ON UPDATE CASCADE" ],

        // ── Venues & Rooms ────────────────────────────────────────────────────────

        // Rooms → Venues
        [ "{$p}myvh_rooms", 'fk_rooms_venue',
          "ALTER TABLE {$p}myvh_rooms
           ADD CONSTRAINT fk_rooms_venue
           FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

                // Venue Hours → Venues
                [ "{$p}myvh_venue_hours", 'fk_venue_hours_venue',
                    "ALTER TABLE {$p}myvh_venue_hours
                     ADD CONSTRAINT fk_venue_hours_venue
                     FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
                     ON DELETE CASCADE ON UPDATE CASCADE" ],

                // Room Hours → Rooms
                [ "{$p}myvh_room_hours", 'fk_room_hours_room',
                    "ALTER TABLE {$p}myvh_room_hours
                     ADD CONSTRAINT fk_room_hours_room
                     FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
                     ON DELETE CASCADE ON UPDATE CASCADE" ],

        // ── Bookings ──────────────────────────────────────────────────────────────

        // Bookings → Customers
        [ "{$p}myvh_bookings", 'fk_bookings_customer',
          "ALTER TABLE {$p}myvh_bookings
           ADD CONSTRAINT fk_bookings_customer
           FOREIGN KEY (CustomerId) REFERENCES {$p}myvh_customers(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Bookings → Organisations
        [ "{$p}myvh_bookings", 'fk_bookings_organisation',
          "ALTER TABLE {$p}myvh_bookings
           ADD CONSTRAINT fk_bookings_organisation
           FOREIGN KEY (OrganisationId) REFERENCES {$p}myvh_organisations(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Bookings → Rooms
        [ "{$p}myvh_bookings", 'fk_bookings_room',
          "ALTER TABLE {$p}myvh_bookings
           ADD CONSTRAINT fk_bookings_room
           FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Bookings → Recurring Patterns (nullable — SET NULL on delete)
        [ "{$p}myvh_bookings", 'fk_bookings_recurring_pattern',
          "ALTER TABLE {$p}myvh_bookings
           ADD CONSTRAINT fk_bookings_recurring_pattern
           FOREIGN KEY (RecurringPatternId) REFERENCES {$p}myvh_recurring_patterns(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // Recurring Patterns → Bookings (parent)
        [ "{$p}myvh_recurring_patterns", 'fk_recurring_patterns_parent_booking',
          "ALTER TABLE {$p}myvh_recurring_patterns
           ADD CONSTRAINT fk_recurring_patterns_parent_booking
           FOREIGN KEY (ParentBookingId) REFERENCES {$p}myvh_bookings(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // ── Room Rates ────────────────────────────────────────────────────────────

        // Room Rates → Rooms
        [ "{$p}myvh_room_rates", 'fk_room_rates_room',
          "ALTER TABLE {$p}myvh_room_rates
           ADD CONSTRAINT fk_room_rates_room
           FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Room Rates → Organisation Types (nullable)
        [ "{$p}myvh_room_rates", 'fk_room_rates_org_type',
          "ALTER TABLE {$p}myvh_room_rates
           ADD CONSTRAINT fk_room_rates_org_type
           FOREIGN KEY (OrganisationTypeId) REFERENCES {$p}myvh_organisation_types(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // ── Add-ons ───────────────────────────────────────────────────────────────

        // Add-ons → Rooms (nullable)
        [ "{$p}myvh_addons", 'fk_addons_room',
          "ALTER TABLE {$p}myvh_addons
           ADD CONSTRAINT fk_addons_room
           FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // Add-ons → Venues (nullable)
        [ "{$p}myvh_addons", 'fk_addons_venue',
          "ALTER TABLE {$p}myvh_addons
           ADD CONSTRAINT fk_addons_venue
           FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // ── Booking Charges ───────────────────────────────────────────────────────

        // Booking Charges → Bookings
        [ "{$p}myvh_booking_charges", 'fk_booking_charges_booking',
          "ALTER TABLE {$p}myvh_booking_charges
           ADD CONSTRAINT fk_booking_charges_booking
           FOREIGN KEY (BookingId) REFERENCES {$p}myvh_bookings(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Booking Charges → Room Rates
        [ "{$p}myvh_booking_charges", 'fk_booking_charges_room_rate',
          "ALTER TABLE {$p}myvh_booking_charges
           ADD CONSTRAINT fk_booking_charges_room_rate
           FOREIGN KEY (RoomRateId) REFERENCES {$p}myvh_room_rates(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // ── Booking Add-ons ───────────────────────────────────────────────────────

        // Booking Add-ons → Bookings
        [ "{$p}myvh_booking_addons", 'fk_booking_addons_booking',
          "ALTER TABLE {$p}myvh_booking_addons
           ADD CONSTRAINT fk_booking_addons_booking
           FOREIGN KEY (BookingId) REFERENCES {$p}myvh_bookings(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Booking Add-ons → Add-ons
        [ "{$p}myvh_booking_addons", 'fk_booking_addons_addon',
          "ALTER TABLE {$p}myvh_booking_addons
           ADD CONSTRAINT fk_booking_addons_addon
           FOREIGN KEY (AddonId) REFERENCES {$p}myvh_addons(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // ── Discounts ─────────────────────────────────────────────────────────────

        // Discounts → Rooms (nullable)
        [ "{$p}myvh_discounts", 'fk_discounts_room',
          "ALTER TABLE {$p}myvh_discounts
           ADD CONSTRAINT fk_discounts_room
           FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // Discounts → Venues (nullable)
        [ "{$p}myvh_discounts", 'fk_discounts_venue',
          "ALTER TABLE {$p}myvh_discounts
           ADD CONSTRAINT fk_discounts_venue
           FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // ── Booking Discounts ─────────────────────────────────────────────────────

        // Booking Discounts → Bookings
        [ "{$p}myvh_booking_discounts", 'fk_booking_discounts_booking',
          "ALTER TABLE {$p}myvh_booking_discounts
           ADD CONSTRAINT fk_booking_discounts_booking
           FOREIGN KEY (BookingId) REFERENCES {$p}myvh_bookings(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Booking Discounts → Discounts (nullable — discount code may be ad-hoc)
        [ "{$p}myvh_booking_discounts", 'fk_booking_discounts_discount',
          "ALTER TABLE {$p}myvh_booking_discounts
           ADD CONSTRAINT fk_booking_discounts_discount
           FOREIGN KEY (DiscountId) REFERENCES {$p}myvh_discounts(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // ── Invoices ──────────────────────────────────────────────────────────────

        // Invoices → Customers
        [ "{$p}myvh_invoices", 'fk_invoices_customer',
          "ALTER TABLE {$p}myvh_invoices
           ADD CONSTRAINT fk_invoices_customer
           FOREIGN KEY (CustomerId) REFERENCES {$p}myvh_customers(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // ── Invoice Items ─────────────────────────────────────────────────────────

        // Invoice Items → Invoices
        [ "{$p}myvh_invoice_items", 'fk_invoice_items_invoice',
          "ALTER TABLE {$p}myvh_invoice_items
           ADD CONSTRAINT fk_invoice_items_invoice
           FOREIGN KEY (InvoiceId) REFERENCES {$p}myvh_invoices(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

        // Invoice Items → Bookings (nullable)
        [ "{$p}myvh_invoice_items", 'fk_invoice_items_booking',
          "ALTER TABLE {$p}myvh_invoice_items
           ADD CONSTRAINT fk_invoice_items_booking
           FOREIGN KEY (BookingId) REFERENCES {$p}myvh_bookings(Id)
           ON DELETE SET NULL ON UPDATE CASCADE" ],

        // ── Payments ──────────────────────────────────────────────────────────────

        // Payments → Invoices
        [ "{$p}myvh_payments", 'fk_payments_invoice',
          "ALTER TABLE {$p}myvh_payments
           ADD CONSTRAINT fk_payments_invoice
           FOREIGN KEY (InvoiceId) REFERENCES {$p}myvh_invoices(Id)
           ON DELETE RESTRICT ON UPDATE CASCADE" ],

    ];
        $results = [];

        foreach ( $foreign_keys as [ $table, $constraint, $sql ] ) {

            // Skip if the constraint already exists (safe for re-activation)
            $exists = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*)
                   FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND TABLE_NAME        = %s
                    AND CONSTRAINT_NAME   = %s
                    AND CONSTRAINT_TYPE   = 'FOREIGN KEY'",
                $table,
                $constraint
            ) );

            if ( $exists ) {
                $results[] = [ 'constraint' => $constraint, 'status' => 'skipped', 'message' => 'Already exists' ];
                continue;
            }

            $ok = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            $results[] = $ok !== false
                ? [ 'constraint' => $constraint, 'status' => 'added',   'message' => 'Added successfully' ]
                : [ 'constraint' => $constraint, 'status' => 'error',   'message' => $wpdb->last_error ];
        }

        return $results;
    }

    public static function backfill_opening_hours_by_day( wpdb $wpdb ): void {
            $p = $wpdb->prefix;
            $venue_table = "{$p}myvh_venues";
            $room_table = "{$p}myvh_rooms";
            $venue_hours_table = "{$p}myvh_venue_hours";
            $room_hours_table = "{$p}myvh_room_hours";

            $days_sql = '(SELECT 0 AS DayOfWeek UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6)';

            $wpdb->query(
                    "INSERT INTO {$venue_hours_table} (VenueId, DayOfWeek, IsClosed, OpeningTime, ClosingTime)
                        SELECT v.Id, d.DayOfWeek, 0, v.OpeningTime, v.ClosingTime
                            FROM {$venue_table} v
                            JOIN {$days_sql} d
                            LEFT JOIN {$venue_hours_table} vh
                                ON vh.VenueId = v.Id
                            AND vh.DayOfWeek = d.DayOfWeek
                        WHERE vh.Id IS NULL"
            );

            $wpdb->query(
                    "INSERT INTO {$room_hours_table} (RoomId, DayOfWeek, UseVenueHours, IsClosed, OpeningTime, ClosingTime)
                        SELECT r.Id, d.DayOfWeek, 1, 0, r.OpeningTime, r.ClosingTime
                            FROM {$room_table} r
                            JOIN {$days_sql} d
                            LEFT JOIN {$room_hours_table} rh
                                ON rh.RoomId = r.Id
                            AND rh.DayOfWeek = d.DayOfWeek
                        WHERE rh.Id IS NULL"
            );
    }

    /**
     * Create or update the system customer record, linking it to the WordPress super/admin user.
     *
     * On multisite: uses the first super admin (lowest user ID).
     * On single-site: uses the first user with manage_options capability (lowest user ID).
     *
     * If no qualifying admin exists, creates a minimal system customer with safe defaults.
     *
     * @global wpdb $wpdb
     */
    public static function add_system_customer(): void {
        global $wpdb;

        $customers_table = $wpdb->prefix . 'myvh_customers';

        // Fetch the WordPress admin user
        $wp_user = self::get_wp_admin_user();

        // Determine Name and Email for the system customer
        $name = 'System';
        $email = '';
        $wp_user_id = null;

        if ( $wp_user ) {
            $name = sanitize_text_field( $wp_user->display_name ?: $wp_user->user_login );
            $email = sanitize_email( $wp_user->user_email );
            $wp_user_id = (int) $wp_user->ID;
        }

        // Check if a system customer already exists
        $system_customer = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$customers_table} WHERE IsSystem = 1 LIMIT 1"),
            'ARRAY_A'
        );

        if ( empty($system_customer['Id']) ) {
            // Insert new system customer
            $insert_data = [
                'Name' => $name,
                'Email' => $email,
                'IsSystem' => 1,
            ];

            if ( $wp_user_id ) {
                $insert_data['WPUserId'] = $wp_user_id;
            }

            $wpdb->insert($customers_table, $insert_data);
        }
    }

    /**
     * Retrieve the WordPress super admin or first admin user.
     *
     * On multisite: returns the first super admin (by lowest user ID).
     * On single-site: returns the first user with manage_options capability (by lowest user ID).
     *
     * @return \WP_User|null The admin user, or null if none found.
     */
    private static function get_wp_admin_user(): ?\WP_User {
        if ( is_multisite() ) {
            // Get all super admins, sorted by user ID (lowest first)
            $super_admins = get_super_admins();

            if ( empty($super_admins) ) {
                return null;
            }

            // Get the user object for the first super admin
            $user = get_user_by('login', $super_admins[0]);
            return $user instanceof \WP_User ? $user : null;
        } else {
            // On single-site, get the first user with manage_options capability (lowest ID)
            $users = get_users([
                'capability' => 'manage_options',
                'orderby' => 'ID',
                'order' => 'ASC',
                'number' => 1,
                'fields' => 'all_with_meta',
            ]);

            return ! empty($users) ? $users[0] : null;
        }
    }

    public static function add_personal_organisation_type($wpdb): int {
        $types_table = $wpdb->prefix . 'myvh_organisation_types';

        $person_type = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$types_table} WHERE Name = %s LIMIT 1", 'Person'),
            'ARRAY_A'
        );

        if ( empty($person_type['Id']) ) {
            $wpdb->insert($types_table, [
                'Name' => 'Person',
                'Description' => 'Individual person',
                'IsSystem' => 1,
                'IsDefault' => 0,
            ]);
            $person_type_id = (int) $wpdb->insert_id;
        } else {
            $person_type_id = (int) $person_type['Id'];
            $wpdb->update($types_table, [
                'Description' => 'Individual person',
                'IsSystem' => 1,
                'IsDefault' => 0,
            ], ['Id' => $person_type_id]);
        }

        return $person_type_id;
    }

    public static function add_personal_organisation($wpdb, $org_type) : void {
        $orgs_table = $wpdb->prefix . 'myvh_organisations';

        $personal_org = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$orgs_table} WHERE Name = %s LIMIT 1", 'Personal booking'),
            'ARRAY_A'
        );

        if ( empty($personal_org['Id']) ) {
            $wpdb->insert($orgs_table, [
                'Name' => 'Personal booking',
                'OrganisationTypeId' => $org_type,
                'IsDefault' => 1,
                'IsSystem' => 1,
                'IsActive' => 1,
            ]);
        }
    }

    public static function add_default_organisation_type($wpdb) : void {
        $types_table = $wpdb->prefix . 'myvh_organisation_types';

        $default_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$types_table} WHERE IsDefault = 1");
        if ( $default_count === 0 ) {
            $wpdb->insert($types_table, ['Name' => 'Default',
                                        'Description' => 'Default Organsisation Type',
                                        'IsDefault' => 1,
                                        'IsSystem' => 0]);
            $default_count = 1;
        }

        if ( $default_count > 1 ) {
            $keep_default_id = (int) $wpdb->get_var("SELECT Id FROM {$types_table} WHERE IsDefault = 1 ORDER BY Id ASC LIMIT 1");
            if ( $keep_default_id > 0 ) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$types_table} SET IsDefault = 0 WHERE IsDefault = 1 AND Id != %d",
                    $keep_default_id
                ));
            }
        }
    }

    // Set up defaults like organisation types and organisations if they don't already exist
    public static function set_default_data($wpdb): void {

        // Create or update the system customer linked to the WP admin user
        self::add_system_customer($wpdb);
        $personal_org_type = self::add_personal_organisation_type($wpdb);
        self::add_default_organisation_type($wpdb);
        self::add_personal_organisation($wpdb, $personal_org_type);

    }

    public static function tidy_up(): void {
        global $wpdb;

        self::drop_tables($wpdb);
        self::delete_all_options_by_prefix($wpdb);
        self::delete_all_transients($wpdb);
    }

    private static function drop_tables($wpdb): void {

        $tables = [
            'myvh_audit_log',
            'myvh_bookings',
            'myvh_organisations',
            'myvh_organisation_types',
            'myvh_organisation_members',
            'myvh_organisation_member_requests',
            'myvh_addons',
            'myvh_booking_addons',
            'myvh_invoices',
            'myvh_invoice_items',
            'myvh_rooms',
            'myvh_venues',
            'myvh_customers',
            'myvh_recurring_patterns',
            'myvh_room_rates',
            'myvh_booking_charges',
            'myvh_discounts',
            'myvh_booking_discounts',
            'myvh_payments',
            'myvh_venue_hours',
            'myvh_room_hours',
        ];

        foreach ($tables as $table) {
            $sql = "SET FOREIGN_KEY_CHECKS = 0;";
            $wpdb->query($sql);
            $sql = "DROP TABLE IF EXISTS {$wpdb->prefix}{$table};
                    SET FOREIGN_KEY_CHECKS = 1;";
            $wpdb->query($sql);
        }
    }

    private static function delete_all_options_by_prefix($wpdb): void {

        $prefix = $wpdb->esc_like('myvh_') . '%';

        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix
            )
        );

        foreach ($options as $option) {
            delete_option($option);
        }

        if ( is_multisite() ) {
            $network_options = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                    $prefix
                )
            );

            foreach ($network_options as $option) {
                delete_site_option($option);
            }
        }

    }

    private static function delete_all_transients($wpdb): void {

        $prefixes = [
            $wpdb->esc_like('_transient_myvh_') . '%',
            $wpdb->esc_like('_transient_timeout_myvh_') . '%',
        ];

        $transients = [];

        foreach ( $prefixes as $prefix ) {
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $prefix
                )
            );

            if ( is_array($rows) && !empty($rows) ) {
                $transients = array_merge($transients, $rows);
            }
        }

        $transients = array_values(array_unique($transients));

        foreach ($transients as $transient) {
            delete_option($transient);
        }

        if ( is_multisite() ) {
            $network_prefixes = [
                $wpdb->esc_like('_site_transient_myvh_') . '%',
                $wpdb->esc_like('_site_transient_timeout_myvh_') . '%',
            ];

            $network_transients = [];

            foreach ( $network_prefixes as $prefix ) {
                $rows = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                        $prefix
                    )
                );

                if ( is_array($rows) && !empty($rows) ) {
                    $network_transients = array_merge($network_transients, $rows);
                }
            }

            $network_transients = array_values(array_unique($network_transients));

            foreach ($network_transients as $transient) {
                delete_site_option($transient);
            }
        }

    }

}
