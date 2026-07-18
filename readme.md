# xDeploy

> Modern Deployment Management Platform for Linux Servers

xDeploy is a modular deployment management platform that simplifies installing, managing and monitoring services on Linux servers.

The MVP focuses on VPN-related modules while the architecture is designed to support any Linux service in the future.

---

## Features (MVP)

- Modular architecture
- SSH-based deployment
- Module lifecycle management
- Server monitoring dashboard
- Deployment Engine
- Single VPS support

---

## Technology Stack

- Laravel
- Livewire
- Mary UI
- DaisyUI
- MySQL
- phpseclib

---

## Project Structure

```text
app/

Core/
Domain/
Infrastructure/
Support/

docs/
```

---

## Documentation

Project documentation is available under the `docs/` directory.

- Product Vision
- Architecture
- Domain Boundaries
- Database Design
- Sprint Planning

---

## Development

Clone the project:

```bash
git clone https://github.com/<your-org>/xdeploy.git
```

Install dependencies:

```bash
composer install
npm install
```

Create environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

Start the development server:

```bash
php artisan serve
npm run dev
```

---

## Roadmap

Current milestone:

- ✅ Sprint 01 — Foundation

Next milestone:

- Deployment Engine
- Module System
- Dashboard
- MVP Release

---

## License

This project is licensed under the MIT License.
