<?php
/**
 * MYVH_Installer
 *
 * Handles all database table creation and schema migrations.
 * Called once during plugin activation via MYVH_Installer::run().
 *
 * Tables are created with WordPress's dbDelta() function, which compares
 * the desired schema against existing tables and applies only the
 * necessary changes — making it safe to re-run on updates.
 *
 * Table inventory:
 *   Venues & Rooms        myvh_venues, myvh_rooms
 *   Customers & Groups    myvh_customers, myvh_customer_groups
 *   Bookings              myvh_bookings, myvh_recurring_patterns
 *   Pricing               myvh_room_rates, myvh_addons
 *   Booking line items    myvh_booking_charges, myvh_booking_addons, myvh_booking_discounts
 *   Discounts             myvh_discounts
 *   Invoices & Payments   myvh_invoices, myvh_invoice_items, myvh_payments
 *
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYVH_Installer {

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
        self::add_foreign_keys( $wpdb );
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
            Description             VARCHAR(255),
            BufferBefore            INT UNSIGNED DEFAULT 0,
            BufferAfter             INT UNSIGNED DEFAULT 0,
            Capacity                INT UNSIGNED DEFAULT 0,
            OpeningTime             TIME,
            ClosingTime             TIME,
            AllowMultiDayBookings   TINYINT(1) DEFAULT 0 NOT NULL,
            CalcClosedHours         TINYINT(1) DEFAULT 0 NOT NULL,
            INDEX idx_venue (VenueId),
            INDEX idx_name  (Name)
        ) {$collate};" );

        // ── Customer Groups ───────────────────────────────────────────────────
        // Defined before Customers because Customers has a FK to this table.
        dbDelta( "CREATE TABLE {$p}myvh_customer_groups (
            Id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name               VARCHAR(100) NOT NULL,
            Description        VARCHAR(255),
            DiscountPercentage DECIMAL(5,2)  DEFAULT 0.00,
            IsDefault          TINYINT(1)    DEFAULT 0,
            IsActive           TINYINT(1)    DEFAULT 1,
            DisplayOrder       INT UNSIGNED  DEFAULT 0,
            Created            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_name    (Name),
            INDEX      idx_default (IsDefault),
            INDEX      idx_active  (IsActive),
            INDEX      idx_order   (DisplayOrder)
        ) {$collate};" );

        // ── Customers ─────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_customers (
            Id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            UserId          BIGINT UNSIGNED NULL,
            Name            VARCHAR(100) NOT NULL,
            Email           VARCHAR(100) NOT NULL,
            EmailVerified   TINYINT(1)   DEFAULT 0,
            PhoneNumber     VARCHAR(100),
            PostCode        VARCHAR(100),
            AddressLine1    VARCHAR(100),
            CustomerGroupId INT UNSIGNED  DEFAULT NULL,
            Created         DATETIME     DEFAULT CURRENT_TIMESTAMP,
            Updated         DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_client     (UserId),
            INDEX      idx_user           (UserId),
            INDEX      idx_email          (Email),
            INDEX      idx_customer_group (CustomerGroupId)
        ) {$collate};" );

        // ── Bookings ──────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$p}myvh_bookings (
            Id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            CustomerId         INT UNSIGNED NOT NULL,
            RoomId             INT UNSIGNED NOT NULL,
            Status             VARCHAR(12)  NOT NULL,
            StartDate          DATE         NOT NULL,
            EndDate            DATE         NOT NULL,
            StartTime          TIME         NOT NULL,
            EndTime            TIME         NOT NULL,
            ChargeableHours    INT UNSIGNED DEFAULT NULL,
            Public             TINYINT(1)   DEFAULT 1,
            Description        VARCHAR(255),
            RecurringPatternId INT UNSIGNED  DEFAULT NULL,
            Created            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer  (CustomerId),
            INDEX idx_room      (RoomId),
            INDEX idx_dates     (StartDate, EndDate),
            INDEX idx_recurring (RecurringPatternId)
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
            Id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
            RoomId          INT UNSIGNED   NOT NULL,
            CustomerGroupId INT UNSIGNED   DEFAULT NULL,
            ChargeType      VARCHAR(12)    NOT NULL,
            Rate            DECIMAL(10,2)  NOT NULL,
            Name            VARCHAR(100)   NOT NULL,
            Description     VARCHAR(255),
            MinimumHours    DECIMAL(5,2)   DEFAULT NULL,
            IsActive        TINYINT(1)     DEFAULT 1,
            ValidFrom       DATE           DEFAULT NULL,
            ValidTo         DATE           DEFAULT NULL,
            Priority        INT UNSIGNED   DEFAULT 0,
            Created         TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room           (RoomId),
            INDEX idx_customer_group (CustomerGroupId),
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
            CustomerGroupId INT UNSIGNED  DEFAULT NULL,
            ChargeType      VARCHAR(12)   NOT NULL,
            RoomId          INT UNSIGNED  DEFAULT NULL,
            VenueId         INT UNSIGNED  DEFAULT NULL,
            IsActive        TINYINT(1)    DEFAULT 1,
            DisplayOrder    INT UNSIGNED  DEFAULT 0,
            Created         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_group (CustomerGroupId),
            INDEX idx_room           (RoomId),
            INDEX idx_venue          (VenueId),
            INDEX idx_active         (IsActive),
            INDEX idx_order          (DisplayOrder)
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
            InvoiceDate   DATE           NOT NULL,
            DueDate       DATE,
            SubTotal      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            TaxAmount     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            TotalAmount   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            AmountPaid    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            AmountDue     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            Status        VARCHAR(50)    NOT NULL DEFAULT 'Draft',
            Notes         TEXT,
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

            // Rooms → Venues
            [ "{$p}myvh_rooms", 'fk_rooms_venue',
              "ALTER TABLE {$p}myvh_rooms
               ADD CONSTRAINT fk_rooms_venue
               FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Customers → Customer Groups
            [ "{$p}myvh_customers", 'fk_customers_customer_group',
              "ALTER TABLE {$p}myvh_customers
               ADD CONSTRAINT fk_customers_customer_group
               FOREIGN KEY (CustomerGroupId) REFERENCES {$p}myvh_customer_groups(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Bookings → Customers
            [ "{$p}myvh_bookings", 'fk_bookings_customer',
              "ALTER TABLE {$p}myvh_bookings
               ADD CONSTRAINT fk_bookings_customer
               FOREIGN KEY (CustomerId) REFERENCES {$p}myvh_customers(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Bookings → Rooms
            [ "{$p}myvh_bookings", 'fk_bookings_room',
              "ALTER TABLE {$p}myvh_bookings
               ADD CONSTRAINT fk_bookings_room
               FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Bookings → Recurring Patterns
            [ "{$p}myvh_bookings", 'fk_bookings_recurring_pattern',
              "ALTER TABLE {$p}myvh_bookings
               ADD CONSTRAINT fk_bookings_recurring_pattern
               FOREIGN KEY (RecurringPatternId) REFERENCES {$p}myvh_recurring_patterns(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Recurring Patterns → Bookings (parent)
            [ "{$p}myvh_recurring_patterns", 'fk_recurring_patterns_parent_booking',
              "ALTER TABLE {$p}myvh_recurring_patterns
               ADD CONSTRAINT fk_recurring_patterns_parent_booking
               FOREIGN KEY (ParentBookingId) REFERENCES {$p}myvh_bookings(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Room Rates → Rooms
            [ "{$p}myvh_room_rates", 'fk_room_rates_room',
              "ALTER TABLE {$p}myvh_room_rates
               ADD CONSTRAINT fk_room_rates_room
               FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Room Rates → Customer Groups
            [ "{$p}myvh_room_rates", 'fk_room_rates_customer_group',
              "ALTER TABLE {$p}myvh_room_rates
               ADD CONSTRAINT fk_room_rates_customer_group
               FOREIGN KEY (CustomerGroupId) REFERENCES {$p}myvh_customer_groups(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Add-ons → Customer Groups
            [ "{$p}myvh_addons", 'fk_addons_customer_group',
              "ALTER TABLE {$p}myvh_addons
               ADD CONSTRAINT fk_addons_customer_group
               FOREIGN KEY (CustomerGroupId) REFERENCES {$p}myvh_customer_groups(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Add-ons → Rooms
            [ "{$p}myvh_addons", 'fk_addons_room',
              "ALTER TABLE {$p}myvh_addons
               ADD CONSTRAINT fk_addons_room
               FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Add-ons → Venues
            [ "{$p}myvh_addons", 'fk_addons_venue',
              "ALTER TABLE {$p}myvh_addons
               ADD CONSTRAINT fk_addons_venue
               FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

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

            // Booking Discounts → Bookings
            [ "{$p}myvh_booking_discounts", 'fk_booking_discounts_booking',
              "ALTER TABLE {$p}myvh_booking_discounts
               ADD CONSTRAINT fk_booking_discounts_booking
               FOREIGN KEY (BookingId) REFERENCES {$p}myvh_bookings(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Booking Discounts → Discounts
            [ "{$p}myvh_booking_discounts", 'fk_booking_discounts_discount',
              "ALTER TABLE {$p}myvh_booking_discounts
               ADD CONSTRAINT fk_booking_discounts_discount
               FOREIGN KEY (DiscountId) REFERENCES {$p}myvh_discounts(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Discounts → Rooms
            [ "{$p}myvh_discounts", 'fk_discounts_room',
              "ALTER TABLE {$p}myvh_discounts
               ADD CONSTRAINT fk_discounts_room
               FOREIGN KEY (RoomId) REFERENCES {$p}myvh_rooms(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Discounts → Venues
            [ "{$p}myvh_discounts", 'fk_discounts_venue',
              "ALTER TABLE {$p}myvh_discounts
               ADD CONSTRAINT fk_discounts_venue
               FOREIGN KEY (VenueId) REFERENCES {$p}myvh_venues(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Invoices → Customers
            [ "{$p}myvh_invoices", 'fk_invoices_customer',
              "ALTER TABLE {$p}myvh_invoices
               ADD CONSTRAINT fk_invoices_customer
               FOREIGN KEY (CustomerId) REFERENCES {$p}myvh_customers(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Invoice Items → Invoices
            [ "{$p}myvh_invoice_items", 'fk_invoice_items_invoice',
              "ALTER TABLE {$p}myvh_invoice_items
               ADD CONSTRAINT fk_invoice_items_invoice
               FOREIGN KEY (InvoiceId) REFERENCES {$p}myvh_invoices(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

            // Invoice Items → Bookings
            [ "{$p}myvh_invoice_items", 'fk_invoice_items_booking',
              "ALTER TABLE {$p}myvh_invoice_items
               ADD CONSTRAINT fk_invoice_items_booking
               FOREIGN KEY (BookingId) REFERENCES {$p}myvh_bookings(Id)
               ON DELETE RESTRICT ON UPDATE CASCADE" ],

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

    public static function tidy_up() {
        global $wpdb;

        self::drop_tables($wpdb);
        self::delete_all_options_by_prefix($wpdb);
        self::delete_all_transients($wpdb);
    }

    private static function drop_tables($wpdb) {

        $tables = [
            'myvh_bookings',
            'myvh_addons',
            'myvh_booking_addons',
            'myvh_invoices',
            'myvh_invoice_items',
            'myvh_rooms',
            'myvh_venues',
            'myvh_customers',
            'myvh_customer_groups',
            'myvh_recurring_pattens',
            'myvh_room_rates',
            'myvh_booking_charges',
            'myvh_discounts',
            'myvh_booking_discounts',
            'myvh_payments'
        ];

        foreach ($tables as $table) {
            $sql = "SET FOREIGN_KEY_CHECKS = 0;";
            $wpdb->query($sql);
            $sql = "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}";
            $wpdb->query($sql);
        }
    }

    private static function delete_all_options_by_prefix($wpdb) {

        $prefix = $wpdb->esc_like('myvh_') . '%';

        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix
            )
        );

        foreach ($options as $option) {
            self::delete_option($option);
        }

    }

    private static function delete_all_transients($wpdb) {

        $prefix = $wpdb->esc_like('_transient_myvh_') . '%';

        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix
            )
        );

        foreach ($transients as $transient) {
            self::delete_option($transient);
        }

    }

    private static function delete_option($option) {
        if ( is_multisite() ) {
            return delete_site_option( $option );
        }
        return delete_option( $option );
    }

}
