# FixWordPressUrlLinks
This script can be used to fix URL problems encountered when changing site Domains. It will check and update the option values in the 'wp_options' table and traverse serialized arrays, specially those theme related values. 

## Just customize the following named constants according to your needs
```php
define('DB_SERVER',             '127.0.0.1');
define('DB_PORT',               '3306');
define('DB_NAME',               'wordpress_site');
define('DB_USER',               'root');
define('DB_PASS',               'abc123');

define('OLD_DOMAIN',            'olddomain.com');
define('NEW_DOMAIN',            'mynewdomain.net');
```
