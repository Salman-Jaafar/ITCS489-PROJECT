# API Examples (curl)

Note: This project uses a development-friendly debug auth. Set `DEBUG_AUTH=true` in `.env` to allow login by email without a password. Alternatively send header `X-Debug-Auth: true` and `X-Debug-User-Email: you@example.com`.

Base URL (PHP mode): `http://localhost:3000`

Login (debug mode, PHP):

```bash
curl -X POST http://localhost:3000/api/login.php -H "Content-Type: application/json" -d '{"email":"admin@library.com"}' -c cookies.txt
```

The `-c cookies.txt` flag saves cookies (session) to be used by subsequent requests.

Get dashboard (uses session cookie, PHP):

```bash
curl http://localhost:3000/api/dashboard.php -b cookies.txt
```

Create a book (requires auth):

```bash
curl -X POST http://localhost:3000/api/books -H "Content-Type: application/json" -d '{"ISBN":"978-0-123456-47-2","Title":"New Book","Author":"Author Name"}' -b cookies.txt
```

Create a loan (transactional, PHP):

```bash
curl -X POST http://localhost:3000/api/loans.php -H "Content-Type: application/json" -d '{"UserID":1,"DueDate":"2025-12-15","ISBNs":["978-0-123456-47-2"]}' -b cookies.txt
```

Use debug header instead of login (no cookies, PHP):

```bash
curl -H "X-Debug-Auth: true" -H "X-Debug-User-Email: dev@local" http://localhost:3000/api/admin_summary.php
```

Create fine example:

```bash
curl -X POST http://localhost:3000/api/fines -H "Content-Type: application/json" -d '{"LoanID":1,"UserID":1,"Amount":5.00,"Description":"Late fee"}' -b cookies.txt
```

Postman: create a collection with the above endpoints, and enable cookie storage to preserve sessions. To use debug bypass in Postman, add a header `X-Debug-Auth: true` and `X-Debug-User-Email`.
