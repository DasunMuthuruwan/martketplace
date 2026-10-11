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
