# WTSBill ERP

A premium commercial billing and accounting ERP software designed for Indian small-to-medium businesses.

## Folder Directory Map

- **`frontend/`**: Visual React interface, layout themes, and browser-side client routers.
- **`backend/`**: Eloquent ORM PHP business logic layers, config values, and service operations.
- **`api/`** or **`backend/routes/`**: Communication endpoints matching browser requests to backend controller logic.
- **`database/`** or **`backend/database/`**: Migrations schemas and initial mock data seed scripts.
- **`docs/`**: Details explaining authentication workflows, tables relationships, permissions guides, and folder structure.

---

## How to Get Started

### 1. Database Migrations & Seeding
To run database schema updates and seed demo user accounts:
```powershell
php backend/seed_runner.php
```

### 2. Run Backend Server
Boot the PHP local development server context:
```powershell
php -S localhost:8000 -t backend/public
```

### 3. Run Frontend Server
Install npm modules and boot the client-side SPA compiler:
```powershell
cd frontend
npm install
npm run dev
```

For detailed specifications, check the **`docs/`** index files.
