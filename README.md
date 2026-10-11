# Marketplace

A multi-country classifieds marketplace (Laravel 13, Livewire 4, Tailwind CSS 4), developed as a separate application from the Dev Talk website.

## Documentation

- Project instructions: [CLAUDE.md](CLAUDE.md)
- Progress and decisions: [docs/PROGRESS.md](docs/PROGRESS.md)
- Requirements, architecture, design and implementation plan: [docs/](docs/)

## Local Development (Windows)

This project uses a standalone PHP 8.4 at `C:\php8.4` (not on PATH) and its own Composer:

```bash
# Git Bash, per terminal session only
export PATH="/c/php8.4:$PATH"
export COMPOSER_HOME="C:/php8.4/composer-home"
alias composer='php /c/php8.4/composer.phar'

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
php artisan test
```

Composer's platform is pinned to PHP 8.3 (`composer.json` → `config.platform.php`) so the lock file installs on PHP 8.3 or 8.4 hosting.

## Quality Checks (Same as CI)

```bash
composer lint      # Pint code style (no changes)
composer analyse   # Larastan level 5
composer test      # PHPUnit (SQLite in memory)
composer ci        # all three
```

CI (`.github/workflows/ci.yml`) also runs the suite on PHP 8.4 and on MySQL 8.4, and runs `composer audit`.

To run the suite against the local MariaDB 10.11 test database (`marketplace_test`, port 3307), set the `DB_*` variables in the shell. They take precedence over `phpunit.xml`:

```bash
DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=marketplace_test \
DB_USERNAME=marketplace DB_PASSWORD='<from .env>' php artisan test
```
