# Passkey Authentication (PHP + WebAuthn)

Simple Passkey authentication system using PHP and the `lbuchs/webauthn` library.

## Features

* Passkey Registration
* Passkey Login
* Session Authentication
* Single User Mode
* No Database Required
* Works on Shared Hosting / cPanel

---

# Requirements

* PHP 8.1+
* Modern Web Servers (Nginx, Apache, Litespeed etc)
* HTTPS Enabled
* Composer
* Modern Browser (Chrome, Edge, Safari, Firefox) that supports ``navigator.credentials`` method.

---

# Installation

Install Composer(if not installed):

```bash
curl -sS https://getcomposer.org/installer | php
```

Install WebAuthn library:

```bash
composer require lbuchs/webauthn
```

Project structure:

```text
project/
├── vendor/
├── register_passkey.php
├── login_passkey.php
├── logout_passkey.php
├── protected.php
├── credential.dat
└── composer.json
```

---

# Registering a Passkey

Open:

```text
https://your-domain.com/register_passkey.php
```

Click:

```text
Register Device
```

After successful registration:

```text
SUCCESS
```

A credential object will be generated.

Store it:

```php
file_put_contents(
    'credential.dat',
    serialize($credentialData)
);
```

---

# Login

Open:

```text
https://your-domain.com/login_passkey.php
```

Click:

```text
Login
```

The browser will display the Passkey prompt.

Successful verification:

```php
session_regenerate_id(true);

$_SESSION['passkey_auth'] = true;
$_SESSION['auth_time'] = time();
```

---

# Protecting Pages

Create:

```php
<?php

session_start();

if (
    empty($_SESSION['passkey_auth'])
) {
    header('Location: login.php');
    exit;
}
```

Example:

```php
<?php

session_start();

if (
    empty($_SESSION['passkey_auth'])
) {
    header('Location: login.php');
    exit;
}

echo "Authenticated";
```

---

# Session Timeout

Example: 1 hour

```php
<?php

session_start();

$maxAge = 3600;

if (
    empty($_SESSION['passkey_auth'])
) {
    header('Location: login.php');
    exit;
}

if (
    time() - $_SESSION['auth_time']
    > $maxAge
) {

    session_destroy();

    header('Location: login.php');
    exit;
}
```

---

# Logout

logout.php

```php
<?php

session_start();

$_SESSION = [];

session_destroy();

header('Location: login.php');
exit;
```

---

# Security Recommendations

## Disable Registration After Setup

```php
if (
    file_exists('credential.dat')
) {
    die('Passkey already registered.');
}
```

## Regenerate Session ID

```php
session_regenerate_id(true);
```

## Force HTTPS

Passkeys require HTTPS in production environments.

---

# Troubleshooting

## invalid client data

Verify:

* HTTPS is enabled
* Correct RP ID
* Correct challenge
* Correct processCreate argument order

For older versions of `lbuchs/webauthn`:

```php
$webauthn->processCreate(
    $clientDataJSON,
    $attestationObject,
    $challenge
);
```

## NotAllowedError

Common causes:

* User cancelled prompt
* Browser timeout
* Invalid RP ID
* Unsupported browser

---

# License

MIT License
