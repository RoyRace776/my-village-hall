<?php
/**
 * Plugin Name: My Village Hall
 * Plugin URI: https://example.com/my-village-hall
 * Description: A comprehensive venue and room booking management system with multi-client support, recurring bookings, and customer portal
 * Version: 0.1.0
 * Author: Richard Barrett
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-village-hall
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package MyVillageHall
 */

// Prevent direct access to this file for security
// This ensures the file can only be loaded through WordPress
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 * These constants are used throughout the plugin to reference paths, URLs, and version info
 */
define('MYVH_VERSION', '0.1.0');                        // Plugin version for cache busting and updates
define('MYVH_PLUGIN_DIR', plugin_dir_path(__FILE__));  // Full server path to plugin directory
define('MYVH_PLUGIN_URL', plugin_dir_url(__FILE__));   // Full URL to plugin directory
define('MYVH_PLUGIN_BASENAME', plugin_basename(__FILE__)); // Plugin basename for WordPress functions

/**
 * Main My Village Hall Class
 *
 * This is the core plugin class that initializes and manages the plugin.
 * It uses the Singleton pattern to ensure only one instance exists.
 *
 * Key responsibilities:
 * - Plugin activation/deactivation
 * - Admin menu creation
 * - Asset enqueueing (CSS/JS files)
 * - Loading required files
 * - Creating database tables
 *
 * @since 0.1.0
 */
class My_Village_Hall {

    /**
     * Single instance of this class
     *
     * @var My_Village_Hall|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * This method implements the Singleton pattern, ensuring only one instance
     * of the plugin class exists. This prevents conflicts and duplicate initialization.
     *
     * @return My_Village_Hall The single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to enforce singleton pattern
     *
     * The constructor is private so it can't be called directly.
     * Use get_instance() instead to get the plugin instance.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * Sets up all the WordPress action and filter hooks that the plugin needs.
     * These hooks tell WordPress when to run specific plugin functions.
     */
    private function init_hooks() {
        global $myvh_container;

        // Load plugin text domain for translations after all plugins are loaded
        add_action('plugins_loaded', array($this, 'load_plugin'));

        // Add admin menu pages
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Add the functionality for settings
        MYVH_Settings_Registry::auto_register(
            MYVH_PLUGIN_DIR . 'modules/settings'
        );

        $settings_page = new MYVH_Settings_Page();
        $settings_page->init();

        // Enqueue admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        add_action('admin_post_myvh_save_booking', function () {
            global $myvh_container;
            $myvh_container->get('booking_controller')->save();
        });

        add_action('admin_post_myvh_cancel_booking', function () {
            global $myvh_container;
            $myvh_container->get('booking_controller')->cancel();
        });

        add_action('admin_post_myvh_save_venue', function () {
            global $myvh_container;
            $myvh_container->get('venue_controller')->save();
        });

        add_action('admin_post_myvh_delete_venue', function () {
            global $myvh_container;
            $myvh_container->get('venue_controller')->delete();
        });

        add_action('admin_post_myvh_save_room', function () {
            global $myvh_container;
            $myvh_container->get('room_controller')->save();
        });

        add_action('admin_post_myvh_delete_room', function () {
            global $myvh_container;
            $myvh_container->get('room_controller')->delete();
        });

        add_action('admin_post_myvh_save_customer_group', function () {
            global $myvh_container;
            $myvh_container->get('customer_group_controller')->save();
        });

        add_action('admin_post_myvh_delete_customer_group', function () {
            global $myvh_container;
            $myvh_container->get('customer_group_controller')->delete();
        });

        add_action('admin_post_myvh_save_rate', function () {
            global $myvh_container;
            $myvh_container->get('room_rate_controller')->save();
        });

        add_action('admin_post_myvh_delete_rate', function () {
            global $myvh_container;
            $myvh_container->get('room_rate_controller')->delete();
        });

        // Addon actions
        add_action('admin_post_myvh_save_addon', function () {
            global $myvh_container;
            $myvh_container->get('addon_controller')->save();
        });

        add_action('admin_post_myvh_delete_addon', function () {
            global $myvh_container;
            $myvh_container->get('addon_controller')->delete();
        });

        // Customer actions
        add_action('admin_post_myvh_save_customer', function () {
            global $myvh_container;
            $myvh_container->get('customer_controller')->save();
        });

        add_action('admin_post_myvh_delete_customer', function () {
            global $myvh_container;
            $myvh_container->get('customer_controller')->delete();
        });

        // Invoice actions
        add_action('admin_post_myvh_save_invoice', function () {
            global $myvh_container;
            $myvh_container->get('invoice_controller')->save();
        });

        add_action('admin_post_myvh_delete_invoice', function () {
            global $myvh_container;
            $myvh_container->get('invoice_controller')->delete();
        });

        add_action('admin_post_myvh_update_invoice_status', function () {
            global $myvh_container;
            $myvh_container->get('invoice_controller')->update_status();
        });

        add_action('admin_post_myvh_record_payment', function () {
            global $myvh_container;
            $myvh_container->get('invoice_controller')->record_payment();
        });

        // Recurring pattern actions
        add_action('admin_post_myvh_save_recurring_pattern', function () {
            global $myvh_container;
            $myvh_container->get('recurring_pattern_controller')->save();
        });

        add_action('admin_post_myvh_delete_recurring_pattern', function () {
            global $myvh_container;
            $myvh_container->get('recurring_pattern_controller')->delete();
        });

        add_action('admin_post_myvh_deactivate_recurring_pattern', function () {
            global $myvh_container;
            $myvh_container->get('recurring_pattern_controller')->deactivate();
        });

        add_action('admin_post_myvh_delete_future_bookings', function () {
            global $myvh_container;
            $myvh_container->get('recurring_pattern_controller')->delete_future_bookings();
        });

        add_action('admin_post_myvh_process_patterns', function () {
            global $myvh_container;
            $myvh_container->get('recurring_pattern_controller')->process_patterns();
        });
    }

    /**
     * Plugin activation handler
     *
     * This function runs when the plugin is activated. It creates all necessary
     * database tables and inserts default data.
     *
     * IMPORTANT: This uses dbDelta() which is WordPress's safe way to create/update
     * tables. It compares the desired schema with existing tables and makes only
     * the necessary changes.
     *
     * @global wpdb $wpdb WordPress database abstraction object
     */
    public function activate() {
        global $wpdb;

        // Get the character set and collation for database tables
        // This ensures tables use the same encoding as WordPress
        $charset_collate = $wpdb->get_charset_collate();

        // Include WordPress upgrade functions (needed for dbDelta)
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


        /**
         * Create Venues table
         *
         * A venue is a physical location that contains rooms.
         */
        $table_name = $wpdb->prefix . 'myvh_venues';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name VARCHAR(100) NOT NULL,
            ShortName VARCHAR(100),
            PostCode VARCHAR(100),
            AddressLine1 VARCHAR(100),
            OpeningTime TIME DEFAULT '09:00:00',
            ClosingTime TIME DEFAULT '17:00:00',
            INDEX idx_name (Name)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Rooms table
         *
         * Rooms are bookable spaces within venues.
         */
        $table_name = $wpdb->prefix . 'myvh_rooms';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            VenueId INT UNSIGNED NOT NULL,
            Name VARCHAR(100) NOT NULL,
            Description VARCHAR(255),
            BufferBefore INT UNSIGNED DEFAULT 0,
            BufferAfter INT UNSIGNED DEFAULT 0,
            Capacity INT UNSIGNED DEFAULT 0,
            OpeningTime TIME,
            ClosingTime TIME,
            INDEX idx_venue (VenueId),
            INDEX idx_name (Name)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Bookings table
         */
        $table_name = $wpdb->prefix . 'myvh_bookings';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            CustomerId INT UNSIGNED NOT NULL,
            RoomId INT UNSIGNED NOT NULL,
            Status VARCHAR(12) NOT NULL,
            StartDate DATE NOT NULL,
            EndDate DATE NOT NULL,
            StartTime TIME NOT NULL,
            EndTime TIME NOT NULL,
            Public TINYINT(1) DEFAULT 1,
            Description VARCHAR(255),
            RecurringPatternId INT UNSIGNED DEFAULT NULL,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (CustomerId),
            INDEX idx_room (RoomId),
            INDEX idx_dates (StartDate, EndDate),
            INDEX idx_recurring (RecurringPatternId)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Recurring Patterns table
         */
        $table_name = $wpdb->prefix . 'myvh_recurring_patterns';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ParentBookingId INT UNSIGNED NOT NULL,
            RecurrenceType VARCHAR(50) NOT NULL,
            RecurrenceInterval INT UNSIGNED DEFAULT 1,
            RecurrenceDay VARCHAR(20),
            RecurrenceWeek VARCHAR(20),
            StartDate DATE NOT NULL,
            EndDate DATE,
            MaxOccurrences INT UNSIGNED DEFAULT NULL,
            OccurrenceCount INT UNSIGNED DEFAULT 0,
            IsActive TINYINT(1) DEFAULT 1,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parent (ParentBookingId),
            INDEX idx_active (IsActive)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Customer Groups table
         *
         * Defines different customer categories (Standard, Charity, Local Resident, etc.)
         * Each group can have different pricing applied
         */
        $table_name = $wpdb->prefix . 'myvh_customer_groups';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name VARCHAR(100) NOT NULL,
            Description VARCHAR(255),
            DiscountPercentage DECIMAL(5,2) DEFAULT 0.00,
            IsDefault TINYINT(1) DEFAULT 0,
            IsActive TINYINT(1) DEFAULT 1,
            DisplayOrder INT UNSIGNED DEFAULT 0,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY name_unique (Name),
            INDEX idx_default (IsDefault),
            INDEX idx_active (IsActive),
            INDEX idx_order (DisplayOrder)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * UPDATE Customers table to include CustomerGroupId
         * Note: Since you're using dbDelta, you need to include the FULL table definition
         * with the new CustomerGroupId field added
         */
        $table_name = $wpdb->prefix . 'myvh_customers';
        $sql = "CREATE TABLE $table_name (
                Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                UserId BIGINT UNSIGNED NULL,
                Name VARCHAR(100) NOT NULL,
                Email VARCHAR(100) NOT NULL,
                EmailVerified TINYINT(1) DEFAULT 0,
                PhoneNumber VARCHAR(100),
                PostCode VARCHAR(100),
                AddressLine1 VARCHAR(100),
                CustomerGroupId INT UNSIGNED DEFAULT NULL,
                Created DATETIME DEFAULT CURRENT_TIMESTAMP,
                Updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (UserId),
                INDEX idx_email (Email),
                INDEX idx_customer_group (CustomerGroupId),
                UNIQUE KEY unique_user_client (UserId)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Room Rates table
         *
         * NOW WITH CUSTOMER GROUP SUPPORT
         * Each rate can be assigned to a specific customer group
         * If CustomerGroupId is NULL, it's available to all groups
         */
        $table_name = $wpdb->prefix . 'myvh_room_rates';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            RoomId INT UNSIGNED NOT NULL,
            CustomerGroupId INT UNSIGNED DEFAULT NULL,
            ChargeType VARCHAR(12) NOT NULL,
            Rate DECIMAL(10,2) NOT NULL,
            Name VARCHAR(100) NOT NULL,
            Description VARCHAR(255),
            MinimumHours DECIMAL(5,2) DEFAULT NULL,
            IsActive TINYINT(1) DEFAULT 1,
            ValidFrom DATE DEFAULT NULL,
            ValidTo DATE DEFAULT NULL,
            Priority INT UNSIGNED DEFAULT 0,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room (RoomId),
            INDEX idx_customer_group (CustomerGroupId),
            INDEX idx_active (IsActive),
            INDEX idx_valid_dates (ValidFrom, ValidTo),
            INDEX idx_priority (Priority)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Add-ons table
         *
         * NOW WITH CUSTOMER GROUP SUPPORT
         * Add-ons can have different prices for different customer groups
         */
        $table_name = $wpdb->prefix . 'myvh_addons';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Name VARCHAR(100) NOT NULL,
            Description VARCHAR(255),
            Price DECIMAL(10,2) NOT NULL,
            CustomerGroupId INT UNSIGNED DEFAULT NULL,
            ChargeType VARCHAR(12) NOT NULL,
            RoomId INT UNSIGNED DEFAULT NULL,
            VenueId INT UNSIGNED DEFAULT NULL,
            IsActive TINYINT(1) DEFAULT 1,
            DisplayOrder INT UNSIGNED DEFAULT 0,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_group (CustomerGroupId),
            INDEX idx_room (RoomId),
            INDEX idx_venue (VenueId),
            INDEX idx_active (IsActive),
            INDEX idx_order (DisplayOrder)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Booking Charges table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_booking_charges';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            BookingId INT UNSIGNED NOT NULL,
            RoomRateId INT UNSIGNED NOT NULL,
            ChargeType VARCHAR(12) NOT NULL,
            Description VARCHAR(255),
            Quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            UnitPrice DECIMAL(10,2) NOT NULL,
            TotalAmount DECIMAL(10,2) NOT NULL,
            TaxRate DECIMAL(5,2) DEFAULT 0.00,
            TaxAmount DECIMAL(10,2) DEFAULT 0.00,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (BookingId),
            INDEX idx_room_rate (RoomRateId)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Booking Add-ons table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_booking_addons';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            BookingId INT UNSIGNED NOT NULL,
            AddonId INT UNSIGNED NOT NULL,
            Quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            UnitPrice DECIMAL(10,2) NOT NULL,
            TotalAmount DECIMAL(10,2) NOT NULL,
            Description VARCHAR(255),
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (BookingId),
            INDEX idx_addon (AddonId)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Invoices table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_invoices';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            InvoiceNumber VARCHAR(50) NOT NULL,
            CustomerId INT UNSIGNED NOT NULL,
            InvoiceDate DATE NOT NULL,
            DueDate DATE,
            SubTotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            TaxAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            TotalAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            AmountPaid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            AmountDue DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            Status VARCHAR(50) NOT NULL DEFAULT 'Draft',
            Notes TEXT,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY invoice_number_unique (InvoiceNumber),
            INDEX idx_customer (CustomerId),
            INDEX idx_status (Status),
            INDEX idx_dates (InvoiceDate, DueDate)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Invoice Items table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_invoice_items';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            InvoiceId INT UNSIGNED NOT NULL,
            BookingId INT UNSIGNED DEFAULT NULL,
            Description VARCHAR(255) NOT NULL,
            Quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            UnitPrice DECIMAL(10,2) NOT NULL,
            TaxRate DECIMAL(5,2) DEFAULT 0.00,
            TaxAmount DECIMAL(10,2) DEFAULT 0.00,
            TotalAmount DECIMAL(10,2) NOT NULL,
            DisplayOrder INT UNSIGNED DEFAULT 0,
            INDEX idx_invoice (InvoiceId),
            INDEX idx_booking (BookingId),
            INDEX idx_order (DisplayOrder)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Payments table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_payments';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            InvoiceId INT UNSIGNED NOT NULL,
            PaymentDate DATE NOT NULL,
            Amount DECIMAL(10,2) NOT NULL,
            PaymentMethod VARCHAR(50),
            TransactionReference VARCHAR(100),
            Notes TEXT,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_invoice (InvoiceId),
            INDEX idx_date (PaymentDate)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Discounts table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_discounts';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Code VARCHAR(50) NOT NULL,
            Description VARCHAR(255),
            DiscountType VARCHAR(20) NOT NULL,
            DiscountValue DECIMAL(10,2) NOT NULL,
            MinimumAmount DECIMAL(10,2) DEFAULT 0.00,
            MaximumDiscount DECIMAL(10,2) DEFAULT NULL,
            ValidFrom DATE DEFAULT NULL,
            ValidTo DATE DEFAULT NULL,
            UsageLimit INT UNSIGNED DEFAULT NULL,
            UsageCount INT UNSIGNED DEFAULT 0,
            IsActive TINYINT(1) DEFAULT 1,
            RoomId INT UNSIGNED DEFAULT NULL,
            VenueId INT UNSIGNED DEFAULT NULL,
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY code_client_unique (Code),
            INDEX idx_code (Code),
            INDEX idx_active (IsActive),
            INDEX idx_valid_dates (ValidFrom, ValidTo),
            INDEX idx_room (RoomId),
            INDEX idx_venue (VenueId)
        ) $charset_collate;";
        dbDelta($sql);

        /**
         * Create Booking Discounts table (unchanged)
         */
        $table_name = $wpdb->prefix . 'myvh_booking_discounts';
        $sql = "CREATE TABLE $table_name (
            Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            BookingId INT UNSIGNED NOT NULL,
            DiscountId INT UNSIGNED DEFAULT NULL,
            DiscountCode VARCHAR(50),
            DiscountType VARCHAR(20) NOT NULL,
            DiscountValue DECIMAL(10,2) NOT NULL,
            DiscountAmount DECIMAL(10,2) NOT NULL,
            Description VARCHAR(255),
            Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (BookingId),
            INDEX idx_discount (DiscountId)
        ) $charset_collate;";
        dbDelta($sql);

        $this->add_foreign_keys();

        // Insert default data
        $this->insert_default_customer_groups();

        // Store plugin version
        update_option('myvh_version', MYVH_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Insert default customer groups
     */
    private function insert_default_customer_groups() {

    }

    private function add_foreign_keys() {
        global $wpdb;

        $results = array();
        $prefix = $wpdb->prefix;

        // Array of all foreign key constraints to add
        $foreign_keys = array(
            // Rooms -> Venues
            array(
                'table' => "{$prefix}myvh_rooms",
                'constraint' => 'fk_rooms_venue',
                'sql' => "ALTER TABLE {$prefix}myvh_rooms
                        ADD CONSTRAINT fk_rooms_venue
                        FOREIGN KEY (VenueId) REFERENCES {$prefix}myvh_venues(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Bookings -> Customers
            array(
                'table' => "{$prefix}myvh_bookings",
                'constraint' => 'fk_bookings_customer',
                'sql' => "ALTER TABLE {$prefix}myvh_bookings
                        ADD CONSTRAINT fk_bookings_customer
                        FOREIGN KEY (CustomerId) REFERENCES {$prefix}myvh_customers(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Bookings -> Rooms
            array(
                'table' => "{$prefix}myvh_bookings",
                'constraint' => 'fk_bookings_room',
                'sql' => "ALTER TABLE {$prefix}myvh_bookings
                        ADD CONSTRAINT fk_bookings_room
                        FOREIGN KEY (RoomId) REFERENCES {$prefix}myvh_rooms(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Bookings -> Recurring Patterns
            array(
                'table' => "{$prefix}myvh_bookings",
                'constraint' => 'fk_bookings_recurring_pattern',
                'sql' => "ALTER TABLE {$prefix}myvh_bookings
                        ADD CONSTRAINT fk_bookings_recurring_pattern
                        FOREIGN KEY (RecurringPatternId) REFERENCES {$prefix}myvh_recurring_patterns(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Recurring Patterns -> Bookings (Parent)
            array(
                'table' => "{$prefix}myvh_recurring_patterns",
                'constraint' => 'fk_recurring_patterns_parent_booking',
                'sql' => "ALTER TABLE {$prefix}myvh_recurring_patterns
                        ADD CONSTRAINT fk_recurring_patterns_parent_booking
                        FOREIGN KEY (ParentBookingId) REFERENCES {$prefix}myvh_bookings(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Customers -> Customer Groups
            array(
                'table' => "{$prefix}myvh_customers",
                'constraint' => 'fk_customers_customer_group',
                'sql' => "ALTER TABLE {$prefix}myvh_customers
                        ADD CONSTRAINT fk_customers_customer_group
                        FOREIGN KEY (CustomerGroupId) REFERENCES {$prefix}myvh_customer_groups(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Room Rates -> Rooms
            array(
                'table' => "{$prefix}myvh_room_rates",
                'constraint' => 'fk_room_rates_room',
                'sql' => "ALTER TABLE {$prefix}myvh_room_rates
                        ADD CONSTRAINT fk_room_rates_room
                        FOREIGN KEY (RoomId) REFERENCES {$prefix}myvh_rooms(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Room Rates -> Customer Groups
            array(
                'table' => "{$prefix}myvh_room_rates",
                'constraint' => 'fk_room_rates_customer_group',
                'sql' => "ALTER TABLE {$prefix}myvh_room_rates
                        ADD CONSTRAINT fk_room_rates_customer_group
                        FOREIGN KEY (CustomerGroupId) REFERENCES {$prefix}myvh_customer_groups(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Add-ons -> Customer Groups
            array(
                'table' => "{$prefix}myvh_addons",
                'constraint' => 'fk_addons_customer_group',
                'sql' => "ALTER TABLE {$prefix}myvh_addons
                        ADD CONSTRAINT fk_addons_customer_group
                        FOREIGN KEY (CustomerGroupId) REFERENCES {$prefix}myvh_customer_groups(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Add-ons -> Rooms
            array(
                'table' => "{$prefix}myvh_addons",
                'constraint' => 'fk_addons_room',
                'sql' => "ALTER TABLE {$prefix}myvh_addons
                        ADD CONSTRAINT fk_addons_room
                        FOREIGN KEY (RoomId) REFERENCES {$prefix}myvh_rooms(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Add-ons -> Venues
            array(
                'table' => "{$prefix}myvh_addons",
                'constraint' => 'fk_addons_venue',
                'sql' => "ALTER TABLE {$prefix}myvh_addons
                        ADD CONSTRAINT fk_addons_venue
                        FOREIGN KEY (VenueId) REFERENCES {$prefix}myvh_venues(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Booking Charges -> Bookings
            array(
                'table' => "{$prefix}myvh_booking_charges",
                'constraint' => 'fk_booking_charges_booking',
                'sql' => "ALTER TABLE {$prefix}myvh_booking_charges
                        ADD CONSTRAINT fk_booking_charges_booking
                        FOREIGN KEY (BookingId) REFERENCES {$prefix}myvh_bookings(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Booking Charges -> Room Rates
            array(
                'table' => "{$prefix}myvh_booking_charges",
                'constraint' => 'fk_booking_charges_room_rate',
                'sql' => "ALTER TABLE {$prefix}myvh_booking_charges
                        ADD CONSTRAINT fk_booking_charges_room_rate
                        FOREIGN KEY (RoomRateId) REFERENCES {$prefix}myvh_room_rates(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Booking Add-ons -> Bookings
            array(
                'table' => "{$prefix}myvh_booking_addons",
                'constraint' => 'fk_booking_addons_booking',
                'sql' => "ALTER TABLE {$prefix}myvh_booking_addons
                        ADD CONSTRAINT fk_booking_addons_booking
                        FOREIGN KEY (BookingId) REFERENCES {$prefix}myvh_bookings(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Booking Add-ons -> Add-ons
            array(
                'table' => "{$prefix}myvh_booking_addons",
                'constraint' => 'fk_booking_addons_addon',
                'sql' => "ALTER TABLE {$prefix}myvh_booking_addons
                        ADD CONSTRAINT fk_booking_addons_addon
                        FOREIGN KEY (AddonId) REFERENCES {$prefix}myvh_addons(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Invoices -> Customers
            array(
                'table' => "{$prefix}myvh_invoices",
                'constraint' => 'fk_invoices_customer',
                'sql' => "ALTER TABLE {$prefix}myvh_invoices
                        ADD CONSTRAINT fk_invoices_customer
                        FOREIGN KEY (CustomerId) REFERENCES {$prefix}myvh_customers(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Invoice Items -> Invoices
            array(
                'table' => "{$prefix}myvh_invoice_items",
                'constraint' => 'fk_invoice_items_invoice',
                'sql' => "ALTER TABLE {$prefix}myvh_invoice_items
                        ADD CONSTRAINT fk_invoice_items_invoice
                        FOREIGN KEY (InvoiceId) REFERENCES {$prefix}myvh_invoices(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Invoice Items -> Bookings
            array(
                'table' => "{$prefix}myvh_invoice_items",
                'constraint' => 'fk_invoice_items_booking',
                'sql' => "ALTER TABLE {$prefix}myvh_invoice_items
                        ADD CONSTRAINT fk_invoice_items_booking
                        FOREIGN KEY (BookingId) REFERENCES {$prefix}myvh_bookings(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Payments -> Invoices
            array(
                'table' => "{$prefix}myvh_payments",
                'constraint' => 'fk_payments_invoice',
                'sql' => "ALTER TABLE {$prefix}myvh_payments
                        ADD CONSTRAINT fk_payments_invoice
                        FOREIGN KEY (InvoiceId) REFERENCES {$prefix}myvh_invoices(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Discounts -> Rooms
            array(
                'table' => "{$prefix}myvh_discounts",
                'constraint' => 'fk_discounts_room',
                'sql' => "ALTER TABLE {$prefix}myvh_discounts
                        ADD CONSTRAINT fk_discounts_room
                        FOREIGN KEY (RoomId) REFERENCES {$prefix}myvh_rooms(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Discounts -> Venues
            array(
                'table' => "{$prefix}myvh_discounts",
                'constraint' => 'fk_discounts_venue',
                'sql' => "ALTER TABLE {$prefix}myvh_discounts
                        ADD CONSTRAINT fk_discounts_venue
                        FOREIGN KEY (VenueId) REFERENCES {$prefix}myvh_venues(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Booking Discounts -> Bookings
            array(
                'table' => "{$prefix}myvh_booking_discounts",
                'constraint' => 'fk_booking_discounts_booking',
                'sql' => "ALTER TABLE {$prefix}myvh_booking_discounts
                        ADD CONSTRAINT fk_booking_discounts_booking
                        FOREIGN KEY (BookingId) REFERENCES {$prefix}myvh_bookings(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),

            // Booking Discounts -> Discounts
            array(
                'table' => "{$prefix}myvh_booking_discounts",
                'constraint' => 'fk_booking_discounts_discount',
                'sql' => "ALTER TABLE {$prefix}myvh_booking_discounts
                        ADD CONSTRAINT fk_booking_discounts_discount
                        FOREIGN KEY (DiscountId) REFERENCES {$prefix}myvh_discounts(Id)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE"
            ),
        );

        // Execute each foreign key constraint
        foreach ($foreign_keys as $fk) {
            // Check if constraint already exists
            $check_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND CONSTRAINT_NAME = %s
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                $fk['table'],
                $fk['constraint']
            );

            $exists = $wpdb->get_var($check_query);

            if ($exists) {
                $results[] = array(
                    'constraint' => $fk['constraint'],
                    'table' => $fk['table'],
                    'status' => 'skipped',
                    'message' => 'Constraint already exists'
                );
                continue;
            }

            // Add the foreign key
            $result = $wpdb->query($fk['sql']);

            if ($result === false) {
                $results[] = array(
                    'constraint' => $fk['constraint'],
                    'table' => $fk['table'],
                    'status' => 'error',
                    'message' => $wpdb->last_error
                );
            } else {
                $results[] = array(
                    'constraint' => $fk['constraint'],
                    'table' => $fk['table'],
                    'status' => 'success',
                    'message' => 'Foreign key added successfully'
                );
            }
        }

        return $results;
    }

    /**
     * Plugin deactivation handler
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_plugin() {
        load_plugin_textdomain(
            'my-village-hall',
            false,
            dirname(MYVH_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page - Bookings
        add_menu_page(
            __('My Village Hall', 'my-village-hall'),
            __('My Village Hall', 'my-village-hall'),
            'manage_options',
            'my-village-hall',
            array($this, 'render_bookings_page'),
            'dashicons-calendar-alt',
            30
        );

        // All Bookings (duplicate of main page for submenu)
        add_submenu_page(
            'my-village-hall',
            __('All Bookings', 'my-village-hall'),
            __('All Bookings', 'my-village-hall'),
            'manage_options',
            'my-village-hall',
            array($this, 'render_bookings_page')
        );

        // Booking Calendar
        add_submenu_page(
            'my-village-hall',
            __('Booking Calendar', 'my-village-hall'),
            __('Calendar', 'my-village-hall'),
            'manage_options',
            'myvh-calendar',
            array($this, 'render_calendar_page')
        );

        // Customers
        add_submenu_page(
            'my-village-hall',
            __('Customers', 'my-village-hall'),
            __('Customers', 'my-village-hall'),
            'manage_options',
            'myvh-customers',
            array($this, 'render_customers_page')
        );

        // Customer Groups (NEW)
        add_submenu_page(
            'my-village-hall',
            __('Customer Groups', 'my-village-hall'),
            __('Customer Groups', 'my-village-hall'),
            'manage_options',
            'myvh-customer-groups',
            array($this, 'render_customer_groups_page')
        );

        // Separator (using a disabled submenu as visual separator)
        add_submenu_page(
            'my-village-hall',
            '',
            '<span style="display:block; margin:5px 0; padding:0; border-top:1px solid #555; opacity:0.5;"></span>',
            'manage_options',
            '#',
            ''
        );

        // Venues & Rooms section
        add_submenu_page(
            'my-village-hall',
            __('Venues', 'my-village-hall'),
            __('Venues', 'my-village-hall'),
            'manage_options',
            'myvh-venues',
            array($this, 'render_venues_page')
        );

        add_submenu_page(
            'my-village-hall',
            __('Rooms', 'my-village-hall'),
            __('Rooms', 'my-village-hall'),
            'manage_options',
            'myvh-rooms',
            array($this, 'render_rooms_page')
        );

        // Separator
        add_submenu_page(
            'my-village-hall',
            '',
            '<span style="display:block; margin:5px 0; padding:0; border-top:1px solid #555; opacity:0.5;"></span>',
            'manage_options',
            '#',
            ''
        );

        // Pricing & Billing section
        add_submenu_page(
            'my-village-hall',
            __('Room Rates', 'my-village-hall'),
            __('Room Rates', 'my-village-hall'),
            'manage_options',
            'myvh-room-rates',
            array($this, 'render_room_rates_page')
        );

        add_submenu_page(
            'my-village-hall',
            __('Add-ons', 'my-village-hall'),
            __('Add-ons', 'my-village-hall'),
            'manage_options',
            'myvh-addons',
            array($this, 'render_addons_page')
        );

        add_submenu_page(
            'my-village-hall',
            __('Invoices', 'my-village-hall'),
            __('Invoices', 'my-village-hall'),
            'manage_options',
            'myvh-invoices',
            array($this, 'render_invoices_page')
        );

        // Separator
        add_submenu_page(
            'my-village-hall',
            '',
            '<span style="display:block; margin:5px 0; padding:0; border-top:1px solid #555; opacity:0.5;"></span>',
            'manage_options',
            '#',
            ''
        );

        // Recurring Bookings
        add_submenu_page(
            'my-village-hall',
            __('Recurring Bookings', 'my-village-hall'),
            __('Recurring Patterns', 'my-village-hall'),
            'manage_options',
            'myvh-recurring',
            array($this, 'render_recurring_page')
        );

    }

    /**
     * Enqueue admin assets (CSS and JavaScript)
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'my-village-hall') === false && strpos($hook, 'myvh-') === false) {
            return;
        }

        wp_enqueue_style(
            'myvh-admin-css',
            MYVH_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MYVH_VERSION
        );

        wp_enqueue_script(
            'myvh-admin-js',
            MYVH_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MYVH_VERSION,
            true
        );

        wp_localize_script('myvh-admin-js', 'myvhAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('myvh-ajax-nonce')
        ));

        // Load calendar assets on calendar page
        if (strpos($hook, 'myvh-calendar') !== false) {
            $this->enqueue_calendar_assets();
        }
    }

    /**
     * Enqueue calendar-specific assets (DayPilot Lite)
     */
    private function enqueue_calendar_assets() {
        // DayPilot Lite – free, no licence key required
        wp_enqueue_script(
            'daypilot-lite',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            array(),
            '2026.1',
            true
        );

        wp_enqueue_style(
            'myvh-calendar-css',
            MYVH_PLUGIN_URL . 'assets/css/calendar.css',
            array(),
            MYVH_VERSION
        );

        wp_enqueue_script(
            'myvh-calendar-js',
            MYVH_PLUGIN_URL . 'assets/js/calendar.js',
            array('jquery', 'daypilot-lite'),
            MYVH_VERSION,
            true
        );

        wp_localize_script('myvh-calendar-js', 'myvhAdminCal', array(
            'ajax_url'  => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce'     => wp_create_nonce('myvh-ajax-nonce'),
            'strings'   => array(
                'error'          => __('An error occurred. Please try again.', 'my-village-hall'),
                'confirmCancel'  => __('Cancel this booking?', 'my-village-hall'),
                'selectRoom'     => __('Please select a room', 'my-village-hall'),
                'selectCustomer' => __('Please select a customer', 'my-village-hall'),
                'newBooking'     => __('New Booking', 'my-village-hall'),
                'editBooking'    => __('Edit Booking', 'my-village-hall'),
                'save'           => __('Save Booking', 'my-village-hall'),
            ),
        ));
    }

    /**
     * Render admin pages
     */
    public function render_bookings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        // Check if we're adding, editing, or viewing a booking
        if (isset($_GET['add']) || isset($_GET['edit']) || isset($_GET['view'])) {
            include MYVH_PLUGIN_DIR . 'includes/admin/views/booking-form-page.php';
        } else {
            include MYVH_PLUGIN_DIR . 'includes/admin/views/bookings-page.php';
        }
    }

    public function render_customers_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/customers-page.php';
    }

    public function render_venues_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/venues-page.php';
    }

    public function render_rooms_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/rooms-page.php';
    }

    public function render_recurring_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/recurring-page.php';
    }

    public function render_calendar_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/calendar-page.php';
    }

    public function render_customer_groups_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/customer-groups-page.php';
    }

    public function render_room_rates_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/room-rates-page.php';
    }

    public function render_addons_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/addons-page.php';
    }

    public function render_invoices_page() {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
                esc_html__('Access Denied', 'my-village-hall'),
                array('response' => 403)
            );
        }
        include MYVH_PLUGIN_DIR . 'includes/admin/views/invoices-page.php';
    }

}

//
// Activation and deactivation hooks
//

function myvh_activate($network_wide) {
    if (is_multisite() && $network_wide) {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('manage_myvh')) {
            $role->add_cap('manage_myvh');
        }
        // Run activation on each site
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            My_Village_Hall::get_instance()->activate();
            restore_current_blog();
        }
    } else {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('manage_myvh')) {
            $role->add_cap('manage_myvh');
        }
        My_Village_Hall::get_instance()->activate();
    }
}
register_activation_hook(__FILE__, 'myvh_activate');

function myvh_deactivate($network_wide) {
    if (is_multisite() && $network_wide) {
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            My_Village_Hall::get_instance()->deactivate();
            restore_current_blog();
        }
    } else {
        My_Village_Hall::get_instance()->deactivate();
    }
}
register_deactivation_hook(__FILE__, 'myvh_deactivate');


add_action('wp_initialize_site', function($new_site) {
    switch_to_blog($new_site->blog_id);
    My_Village_Hall::get_instance()->activate();
    restore_current_blog();
    });

/**
 * Load required files
 * Wrapped in output buffering to prevent any accidental output during load
 * from triggering WordPress's "unexpected output" activation error.
 */
ob_start();
//  Add in any utlility classes
require_once MYVH_PLUGIN_DIR . 'core/support/time-helpers.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/settings-helper.php';
require_once MYVH_PLUGIN_DIR .'includes/admin/class-myvh-admin-notices.php';

require_once MYVH_PLUGIN_DIR .'core/container/class-myvh-container.php';

global $myvh_container;
$myvh_container = require MYVH_PLUGIN_DIR . '/bootstrap/myvh-container.php';

//  Add in settings management
require_once MYVH_PLUGIN_DIR . 'modules/settings/class-myvh-settings-base.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/class-myvh-general-settings.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/class-myvh-booking-settings.php';
require_once MYVH_PLUGIN_DIR . 'modules/settings/settings-registry.php';
require_once MYVH_PLUGIN_DIR . 'includes/admin/views/settings-page.php';


// Include the repository access classes
require_once MYVH_PLUGIN_DIR . 'bootstrap/myvh-repositories.php';

// Bootstrap
require_once MYVH_PLUGIN_DIR . 'bootstrap/myvh-bootstrap.php';

// Include frontend shortcodes
require_once MYVH_PLUGIN_DIR . 'modules/calendar/class-myvh-calendar-shortcode.php';

// Admin calendar AJAX handlers
require_once MYVH_PLUGIN_DIR . 'includes/admin/class-myvh-calendar-ajax.php';

// Include the network specific classes
require_once MYVH_PLUGIN_DIR . 'includes/network/class-myvh-network-dashboard.php';
require_once MYVH_PLUGIN_DIR . 'includes/network/class-myvh-network-sites-table.php';
require_once MYVH_PLUGIN_DIR . 'includes/network/class-myvh-network-stats.php';
ob_end_clean();

/**
 * Initialize the plugin
 */
function myvh_init() {
    $plugin = My_Village_Hall::get_instance();

    // Frontend calendar shortcode + REST endpoint
    ( new MYVH_Calendar_Shortcode() )->init();

    // Admin calendar AJAX handlers
    ( new MYVH_Calendar_Ajax() )->register();

    // Network dashboard (multisite only)
    if (is_multisite() && is_network_admin()) {
        (new MYVH_Network_Dashboard())->init();
    }

    return $plugin;
}

// Start the plugin
add_action('plugins_loaded', 'myvh_init');
