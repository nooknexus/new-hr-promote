# Agent Guide

## Project

This is a Laravel 12 application using PHP 8.2+, Livewire 3, Vite, Tailwind CSS, MySQL, and database-backed sessions, cache, and queues.

The application includes ThaiD, ProviderID/HealthID, and MyMOPH authentication integrations. Treat all OAuth credentials, API keys, personal identifiers, and production data as sensitive.

## Local Setup

Run commands from the repository root:

```bash
composer install
npm install
```

Create `.env` from `.env.example` only when `.env` does not exist, then configure the local database and integration values. Never commit `.env` or expose its secrets.

The usual local database configuration is MySQL. When MySQL runs in Docker while Laravel runs on macOS, use `DB_HOST=127.0.0.1` and the host port published by Docker. When Laravel also runs inside Docker, use the Docker Compose service name as `DB_HOST` instead.

After the database is reachable:

```bash
php artisan migrate
php artisan storage:link
composer run dev
```

Open `http://localhost:8000`. `composer run dev` starts Laravel, the queue listener, and Vite together.

For a frontend production build:

```bash
npm run build
```

## Development Rules

- Follow Laravel conventions and keep changes within the owning controller, Livewire component, model, service, route, or view.
- Inspect nearby routes, Livewire components, and migrations before changing behavior.
- Preserve existing authentication and authorization checks, especially around ThaiD, ProviderID, and MyMOPH callbacks.
- Use migrations for schema changes and keep them reversible where practical.
- Validate request input with Laravel validation. Prefer Eloquent and existing services over duplicated query logic.
- Do not log access tokens, client secrets, API keys, passwords, PIDs, or full personal profiles.
- Keep generated files, `vendor/`, `node_modules/`, and local environment files out of commits.
- Add or update focused tests for behavior changes.

## Verification

Before finishing a change, run the narrowest relevant checks. Common checks are:

```bash
php artisan test
php artisan route:list
php artisan config:clear
npm run build
```

If configuration, database, queue, or OAuth behavior is involved, verify the relevant local service is running and report any unavailable dependency instead of assuming it works.

## Deployment Notes

For a real web-server deployment, the web server document root must point to the project's `public/` directory. The full project directory should remain outside the public document root.

The repository contains `Dockerfile.txt`, but its build commands are not the authoritative local workflow. Check the current `composer.json`, `package.json`, and deployment environment before using that image definition; in particular, confirm the package manager and frontend build script match the current project.

## Existing References

- `SKILL.md`: ThaiD login development notes and authentication flow.
- `README.md`: scheduler and Docker notes.
- `composer.json`: PHP dependencies and development scripts.
- `package.json`: Vite scripts and JavaScript dependencies.