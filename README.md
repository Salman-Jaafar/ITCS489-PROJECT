**Project: Library Management (Frontend + Minimal API)**

Quick start (development)

- Prereqs: Node.js (v16+), MySQL server (or Docker), and optionally Python for static server testing.

1) Import the provided database SQL into MySQL

PowerShell (adjust paths/user/password as needed):

```powershell
# Create the database and import
mysql -u root -p < .\database\489DB.sql
```

If you prefer Docker, run a MySQL container and import the dump into it (not covered here).

2) Configure environment for the Node API

Copy `.env.example` to `.env` and set your DB credentials:

```powershell
copy .env.example .env
# edit .env with your values (e.g. using notepad)
notepad .env
```

3) Install and run the Node API

```powershell
npm install
npm start
# API will run on http://localhost:3000 by default
```

4) Test the frontend (static files)

You can run the project in static-only mode (no backend). This uses `data/library.json` and client-side JS to simulate API data.

Static-only (no Node, no npm):

```powershell
# from project root
python -m http.server 8000
# open http://localhost:8000/templates/librarian-dashboard.html
```

Notes about static mode:
- The frontend attempts to fetch `/api/...` endpoints if an API is running, but falls back to `data/library.json` when no backend is present.
- `js/db-client.js` exposes `window.lmsData` and dispatches a `lms-data-ready` CustomEvent so all templates (`librarian`, `admin`, `user`) can use the same sample data without a server.
- Static mode is useful for UI development and design; to connect to a real database later you can start the included Node API or convert to a PHP/Python backend.

How it works
- The frontend `templates/librarian-dashboard.html` loads `js/db-client.js`.
- `db-client.js` attempts to fetch `/api/dashboard`. If the Node API is not running it falls back to `data/library.json`.
- The Node API (`server.js`) connects to your MySQL `LibraryDB` (see `.env`) and exposes `/api/dashboard` which returns JSON used by the dashboard.

Next steps (optional)
- Add authentication and protect API endpoints.
- Serve the frontend from the Node server (static middleware) for a single host.
- Add create/update endpoints to manage books, members, loans, and fines.
