<?php
namespace MYVH\Events;

class CustomerListener {
    private $verification_service;

    public function __construct($verification_service = null) {
        if ($verification_service !== null) {
            $this->verification_service = $verification_service;
            return;
        }

        $class = '\\MYVH\\Login\\CustomerEmailVerificationService';
        $this->verification_service = class_exists($class) ? new $class() : null;
    }

    public function register(): void {
        add_action('myvh_event_' . CustomerEvents::REGISTERED, [$this, 'handle_customer_registered']);
    }

    public function handle_customer_registered($payload): void {
        $payload = is_array($payload) ? $payload : [];

        $customer_id = (int) ($payload['customer_id'] ?? 0);
        $user_id = (int) ($payload['user_id'] ?? 0);
        $email = (string) ($payload['email'] ?? '');
        $name = (string) ($payload['name'] ?? '');

        if ($customer_id <= 0 || $user_id <= 0 || $email === '' || !is_email($email)) {
            return;
        }

        if ($this->verification_service === null || !method_exists($this->verification_service, 'send_verification_email')) {
            return;
        }

        $this->verification_service->send_verification_email($customer_id, $user_id, $email, $name);
    }
}