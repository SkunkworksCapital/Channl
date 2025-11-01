<?php
return [
  'app_env' => getenv('APP_ENV') ?: 'prod',
  'db' => [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'name' => getenv('DB_NAME') ?: 'sagejyou_Channl',
    'user' => getenv('DB_USER') ?: 'sagejyou_Channl',
    'pass' => getenv('DB_PASS') ?: 'bali744aide664',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
  ],
  'crypto' => [
    'file_key' => getenv('FILE_KEY') ?: 'hex-32-bytes',
    'file_iv' => getenv('FILE_IV') ?: 'hex-16-bytes',
  ],
  'security' => [
    'csrf_key' => getenv('CSRF_KEY') ?: 'random-32-bytes',
  ],
  'rates' => [
    'sms' => (float)(getenv('RATE_SMS') ?: 0.03),
    'whatsapp' => (float)(getenv('RATE_WHATSAPP') ?: 0.02),
    'email' => (float)(getenv('RATE_EMAIL') ?: 0.002),
  ],
  'integrations' => [
    'sms' => [
      'provider' => getenv('SMS_PROVIDER') ?: 'twilio',
      'sid' => getenv('SMS_SID') ?: '',
      'token' => getenv('SMS_TOKEN') ?: '',
      'from' => getenv('SMS_FROM') ?: '+44...'
    ],
    'whatsapp' => [
      'access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
      'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
    ],
    'email' => [
      'mode' => getenv('EMAIL_MODE') ?: 'smtp',
      'host' => getenv('EMAIL_HOST') ?: 'smtp.example.com',
      'port' => (int)(getenv('EMAIL_PORT') ?: 587),
      'user' => getenv('EMAIL_USER') ?: '',
      'pass' => getenv('EMAIL_PASS') ?: '',
      'sendgrid_api_key' => getenv('SENDGRID_API_KEY') ?: '',
      'from' => getenv('EMAIL_FROM') ?: 'no-reply@channl.sagejyoung.com',
    ]
  ]
];


