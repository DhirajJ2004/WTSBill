<?php

/**
 * WTSBill ERP - Authentication Configuration
 */

return [
    'session_lifetime' => 7200, // 2 hours inactivity timeout in seconds
    'password_reset_expiry' => 3600, // 1 hour token expiration
    'remember_me_lifetime' => 86400 * 30, // 30 days
    'bcrypt_rounds' => 10,
    'lockout_threshold' => 5, // 5 failed attempts
    'lockout_duration' => 900, // 15 minutes lockout
];
