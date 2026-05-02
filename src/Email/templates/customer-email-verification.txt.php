<?php
if (!defined('ABSPATH')) {
    exit;
}

echo "Verify your email address\n\n";
echo 'Hello ' . (string) ($customer_name ?? 'there') . ",\n\n";
echo 'Please verify your email address to finish setting up your account on ' . (string) ($site_name ?? 'our site') . ".\n\n";
echo 'Verify link: ' . (string) ($verification_url ?? '') . "\n\n";
echo 'This link expires in ' . (string) ($verification_ttl_hours ?? '24') . " hour(s).\n";
