<?php

return [
    'student_email_domain' => strtolower(env('USP_STUDENT_EMAIL_DOMAIN', 'student.usp.ac.fj')),
    'otp_expires_minutes' => (int) env('MFA_OTP_EXPIRES_MINUTES', 10),
    'otp_max_attempts' => (int) env('MFA_OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('MFA_RESEND_COOLDOWN_SECONDS', 60),
];
