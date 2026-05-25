<?php
namespace MYVH\Portal\Ajax;

use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Subscriptions\Services\TrialService;

class PortalPeoplePageRenderer {
    public function __construct(
        private ClientAdminService $client_admin_service,
        private CustomerService $customer_service,
        private SingleBookingAutoInvoiceRuleRepository $rule_repository,
        private RecurringBookingAutoInvoiceRuleRepository $recurring_booking_rule_repository,
        private ?AccountRepository $account_repository = null,
        private ?AccountService $account_service = null,
        private ?SubscriptionRepository $subscription_repository = null,
        private ?PlanRepository $plan_repository = null,
        private ?PlanChangePolicyService $plan_change_policy_service = null,
        private ?TrialService $trial_service = null,
        private ?SettingsService $settings_service = null
    ) {}

    public function render_account(): void {
        include MYVH_PLUGIN_DIR . 'templates/Portal/account.php';
    }

    public function render_client_admins(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $client_admins = $this->client_admin_service->get_assigned_users_for_blog(get_current_blog_id());
        $accessible_sites = $this->client_admin_service->get_accessible_sites_for_user(get_current_user_id());
        include MYVH_PLUGIN_DIR . 'templates/Portal/client-admins.php';
    }

    public function render_customers(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $customers = $this->customer_service->get_all([
            'orderby' => 'Name',
            'order' => 'ASC',
        ]);

        include MYVH_PLUGIN_DIR . 'templates/Portal/customers.php';
    }

    public function render_customer_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $customer_id = \intval($_GET['id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error('Invalid customer ID', 400);
        }

        $customer = $this->customer_service->get($customer_id);
        $single_booking_rule_options = $this->rule_repository->get_rule_options();
        $recurring_booking_rule_options = $this->recurring_booking_rule_repository->get_rule_options();
        include MYVH_PLUGIN_DIR . 'templates/Portal/customer-edit.php';
    }

    public function render_customer_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $single_booking_rule_options = $this->rule_repository->get_rule_options();
        $recurring_booking_rule_options = $this->recurring_booking_rule_repository->get_rule_options();
        include MYVH_PLUGIN_DIR . 'templates/Portal/customer-add.php';
    }

    public function render_subscription_upgrade(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        if (
            !$this->account_repository instanceof AccountRepository
            || !$this->subscription_repository instanceof SubscriptionRepository
            || !$this->plan_repository instanceof PlanRepository
            || !$this->plan_change_policy_service instanceof PlanChangePolicyService
        ) {
            wp_send_json_error('Subscription services unavailable', 500);
        }

        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $account = $blog_id > 0 ? $this->account_repository->get_by_blog_id($blog_id) : null;
        if (!is_array($account) && $blog_id > 0 && $this->account_service instanceof AccountService) {
            $account = $this->account_service->resolveAccountFromBlogId($blog_id);
        }

        if (!is_array($account) && $this->account_service instanceof AccountService) {
            $site_name = sanitize_text_field((string) get_bloginfo('name'));
            $admin_email = sanitize_email((string) get_option('admin_email', ''));
            if ($site_name !== '' && $admin_email !== '') {
                $account = $this->account_service->createAccount($site_name, $admin_email);
            }
        }

        $account_id = is_array($account) ? (int) ($account['id'] ?? 0) : 0;

        $subscription = $account_id > 0
            ? $this->subscription_repository->get_active_by_account_id($account_id)
            : null;

        if (
            $account_id > 0
            && !$subscription instanceof Subscription
            && $this->trial_service instanceof TrialService
        ) {
            $subscription = $this->trial_service->createTrialSubscription($account_id);
        }

        $current_plan_label = __('Unknown', 'my-village-hall');
        if ($subscription instanceof Subscription) {
            $current_plan = $this->resolve_plan($subscription);
            if ($current_plan instanceof Plan) {
                $current_plan_label = $current_plan->getName();
            }

            if ($subscription->isTrial()) {
                $trial_plan = $this->plan_repository instanceof PlanRepository
                    ? $this->plan_repository->getByCode('trial')
                    : null;

                $current_plan_label = $trial_plan instanceof Plan
                    ? $trial_plan->getName()
                    : __('Trial', 'my-village-hall');
            }
        }

        $plan_options = $account_id > 0
            ? $this->plan_change_policy_service->getPlanOptionsForAccount($account_id)
            : [];
        $trial_days = $this->settings_service instanceof SettingsService
            ? $this->settings_service->getTrialDays()
            : 0;

        $is_wordpress_super_user = $this->client_admin_service->is_global_admin(get_current_user_id());

        include MYVH_PLUGIN_DIR . 'templates/Portal/subscription-upgrade.php';
    }

    private function resolve_plan(Subscription $subscription): ?Plan {
        if (!$this->plan_repository instanceof PlanRepository) {
            return null;
        }

        $plan_code = $subscription->getPlanCode();
        if ($plan_code !== '') {
            $plan = $this->plan_repository->getByCode($plan_code);
            if ($plan instanceof Plan) {
                return $plan;
            }
        }

        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0) {
            $plan = $this->plan_repository->get_by_id($plan_id);
            if ($plan instanceof Plan) {
                return $plan;
            }
        }

        return null;
    }
}