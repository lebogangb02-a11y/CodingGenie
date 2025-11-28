# Secure DKIM Key Storage

Place your DKIM private key file here as `dkim_private.key` (PEM format).

Do not commit this key to source control. On the server, set file permissions to owner-read only:

```
chmod 600 secure/dkim_private.key
```

Update `config_application.php` with:

```
define('DKIM_DOMAIN', 'edubridgesa.co.za');
define('DKIM_SELECTOR', 'edubridgesa');
define('DKIM_PRIVATE_KEY_PATH', __DIR__ . '/../secure/dkim_private.key');
define('DKIM_PASSPHRASE', '');
```

Ensure you publish the DKIM TXT record: `edubridgesa._domainkey.edubridgesa.co.za` with your public key.