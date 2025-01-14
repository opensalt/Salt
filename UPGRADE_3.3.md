# MySQL

## Authentication plugin change

Due to updates in the upstream MySQL you may need to update from
`mysql_native_password` to`caching_sha2_password` for authentication.

One can use the following to migrate a user to the new auth plugin:

```sql
ALTER USER 'user'@'host' IDENTIFIED WITH caching_sha2_password;
ALTER USER 'user'@'host' IDENTIFIED BY 'password';
```

## Database schema updates

Each release may have updates to the database schema.  To apply database migrations run:

```bash
docker compose run --rm web bin/console doctrine:migrations:migrate --no-interaction
```


# Docker Compose

## Service changes

- The `crontab` service has been replaced by the `scheduler` service.
  - The system now uses a messaging system to run background tasks.
    The cron jobs have been replaced by scheduled tasks.
- The `php` service has been removed and incorporated into the `web` service.
  - The Caddy -> PHP-FPM model using two containers has been replaced by using
    [Caddy](https://caddyserver.com) as the web server with a PHP module to
    execute the PHP code (using [FrankenPHP](https://frankenphp.dev) for the
    App Server).


# Environment variables

## Define SERVER_NAME

- A `SERVER_NAME` variable should be set with the public name of the server and
  passed into the containers.

## Mercure config changes

- The environment variable `CADDY_MERCURE_JWT_SECRET` should now be set
  instead of using `MERCURE_JWT_TOKEN`.
  The token will be generated using the secret.
