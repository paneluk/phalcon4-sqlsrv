# Phalcon - MS SQL Server (PDO) Adapter

- Phalcon 3.4+ support

- PHP 7.3+ support


- Compatible with PHP 7.3.13 + Phalcon 3.4.3 .

```php
$di->set('db', function() use ($config) {
	return new \Phalcon\Db\Adapter\Pdo\Sqlsrv(array(
		"host"         => $config->database->host,
		"username"     => $config->database->username,
		"password"     => $config->database->password,
		"dbname"       => $config->database->name
	));
});


```
