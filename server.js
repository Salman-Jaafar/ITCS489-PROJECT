require('dotenv').config();
const path = require('path');
const express = require('express');
const cors = require('cors');
const db = require('./src/db');
const session = require('express-session');

const app = express();
const port = process.env.PORT || 3000;

// parse JSON bodies
app.use(express.json());

// Sessions (development only memory store)
app.use(session({
  secret: process.env.SESSION_SECRET || 'devsecret',
  resave: false,
  saveUninitialized: false,
  cookie: { secure: false }
}));

const DEBUG_AUTH = process.env.DEBUG_AUTH === 'true';

// CORS: only allow configured origins (comma-separated in ALLOWED_ORIGINS)
const allowedOrigins = process.env.ALLOWED_ORIGINS
  ? process.env.ALLOWED_ORIGINS.split(',').map(s => s.trim())
  : ['http://localhost:3000', 'http://localhost:8000'];
app.use(cors({
  origin: function (origin, callback) {
    // allow non-browser (curl, server-to-server) or same-origin
    if (!origin) return callback(null, true);
    if (allowedOrigins.indexOf(origin) !== -1) return callback(null, true);
    return callback(new Error('Not allowed by CORS'));
  }
}));

// Serve static frontend files from project root so templates/assets load from same host
app.use(express.static(path.join(__dirname)));

// API: dashboard summary
app.get('/api/dashboard', async (req, res) => {
  try {
    // Stats
    const [booksRows] = await db.promise().query('SELECT COUNT(*) AS totalBooks FROM Book');
    const [membersRows] = await db.promise().query("SELECT COUNT(*) AS activeMembers FROM `User` WHERE AccountStatus='Active'");
    const [dueRows] = await db.promise().query("SELECT COUNT(*) AS dueToday FROM Loan WHERE DueDate = CURDATE() AND ReturnDate IS NULL");
    const [finesRows] = await db.promise().query("SELECT IFNULL(SUM(Amount),0) AS pendingFines FROM Fine WHERE Status='Unpaid'");

    const stats = {
      totalBooks: booksRows[0].totalBooks || 0,
      activeMembers: membersRows[0].activeMembers || 0,
      dueToday: dueRows[0].dueToday || 0,
      pendingFines: finesRows[0].pendingFines || 0
    };

    // Recent transactions (loans)
    const [transactions] = await db.promise().query(`
      SELECT L.LoanID AS id, U.Name AS member, B.Title AS book,
        IF(L.ReturnDate IS NULL, 'Borrow', 'Return') AS type,
        L.CheckoutDate AS date, L.DueDate AS dueDate,
        CASE WHEN L.ReturnDate IS NULL THEN 'Active' ELSE 'Returned' END AS status
      FROM Loan L
      LEFT JOIN `User` U ON L.UserID = U.UserID
      LEFT JOIN LoanItem LI ON LI.LoanID = L.LoanID
      LEFT JOIN Book B ON B.ISBN = LI.ISBN
      ORDER BY L.CheckoutDate DESC
      LIMIT 10
    `);

    // Due books (due today and not returned)
    const [dueBooks] = await db.promise().query(`
      SELECT B.Title AS title, U.Name AS member, U.UserID AS memberId
      FROM Loan L
      JOIN LoanItem LI ON LI.LoanID = L.LoanID
      JOIN Book B ON B.ISBN = LI.ISBN
      JOIN `User` U ON U.UserID = L.UserID
      WHERE L.DueDate = CURDATE() AND L.ReturnDate IS NULL
      LIMIT 10
    `);

    // Recent activities: payments and registrations
    const [payments] = await db.promise().query(`
      SELECT 'Fine Payment' AS title, PaymentDate AS time, CONCAT('Payment of ', AmountPaid, ' for Fine ID ', FineID) AS description
      FROM Payment
      ORDER BY PaymentDate DESC
      LIMIT 5
    `);

    const [regs] = await db.promise().query(`
      SELECT 'New Member Registration' AS title, MembershipDate AS time, CONCAT(Name, ' registered as a new member') AS description
      FROM `User`
      WHERE MembershipDate IS NOT NULL
      ORDER BY MembershipDate DESC
      LIMIT 5
    `);

    const activities = payments.concat(regs).map(a => ({
      title: a.title,
      time: a.time ? new Date(a.time).toISOString() : '',
      description: a.description
    })).slice(0, 10);

    res.json({ stats, transactions, dueBooks, activities });
  } catch (err) {
    console.error('API /api/dashboard error:', err);
    res.status(500).json({ error: 'Database query failed', details: err.message });
  }
});

// Simple auth endpoints (development-focused)
app.post('/api/login', async (req, res) => {
  // In this scaffold we support debug login only (no password fields in DB schema).
  const { email } = req.body;
  if (!email) return res.status(400).json({ error: 'Email is required' });
  try {
    // Try to find an employee first (librarian/admin)
    const [emp] = await db.promise().query('SELECT * FROM Employee WHERE Email = ?', [email]);
    if (emp && emp.length > 0) {
      if (!DEBUG_AUTH) return res.status(403).json({ error: 'Debug auth disabled on server' });
      req.session.user = { id: emp[0].EmployeeID, role: emp[0].Role || 'Librarian', name: emp[0].Name };
      return res.json({ message: 'Logged in as employee', user: req.session.user });
    }
    // Try user
    const [u] = await db.promise().query('SELECT * FROM `User` WHERE Email = ?', [email]);
    if (u && u.length > 0) {
      if (!DEBUG_AUTH) return res.status(403).json({ error: 'Debug auth disabled on server' });
      req.session.user = { id: u[0].UserID, role: 'User', name: u[0].Name };
      return res.json({ message: 'Logged in as user', user: req.session.user });
    }
    return res.status(404).json({ error: 'User not found' });
  } catch (err) {
    console.error('POST /api/login error:', err);
    res.status(500).json({ error: 'Login failed' });
  }
});

app.post('/api/logout', (req, res) => {
  req.session.destroy(err => {
    if (err) return res.status(500).json({ error: 'Logout failed' });
    res.json({ message: 'Logged out' });
  });
});

// Middleware to require session auth; allows debug bypass via header X-Debug-Auth when DEBUG_AUTH is true
function requireAuth(req, res, next) {
  if (req.session && req.session.user) return next();
  if (DEBUG_AUTH && req.get('x-debug-auth') === 'true') {
    // allow attaching debug user via header 'x-debug-user-email' or 'x-debug-user-id'
    const debugEmail = req.get('x-debug-user-email');
    const debugId = req.get('x-debug-user-id');
    if (debugEmail) {
      // create minimal session for debug email (not persisted)
      req.session.user = { id: debugId || null, role: 'Developer', name: debugEmail };
      return next();
    }
    // if no debug user provided, still allow but mark anonymous
    req.session.user = { id: null, role: 'Developer', name: 'debug' };
    return next();
  }
  return res.status(401).json({ error: 'Authentication required' });
}

// ----------------------
// Create / Update endpoints
// ----------------------

// Books: create
app.post('/api/books', async (req, res) => {
  const { ISBN, Title, PublicationYear, Genre, Author, Status, ShelfLocation } = req.body;
  if (!ISBN || !Title) return res.status(400).json({ error: 'ISBN and Title are required' });
  try {
    await db.promise().execute(
      'INSERT INTO Book (ISBN, Title, PublicationYear, Genre, Author, Status, ShelfLocation) VALUES (?,?,?,?,?,?,?)',
      [ISBN, Title, PublicationYear || null, Genre || null, Author || null, Status || 'Available', ShelfLocation || null]
    );
    res.status(201).json({ message: 'Book created' });
  } catch (err) {
    if (err && err.code === 'ER_DUP_ENTRY') return res.status(409).json({ error: 'Book ISBN already exists' });
    console.error('POST /api/books error:', err);
    res.status(500).json({ error: 'Failed to create book' });
  }
});

// Books: update
app.put('/api/books/:isbn', async (req, res) => {
  const isbn = req.params.isbn;
  const fields = req.body;
  const allowed = ['Title','PublicationYear','Genre','Author','Status','ShelfLocation'];
  const set = [];
  const values = [];
  for (const k of allowed) {
    if (k in fields) {
      set.push(`${k} = ?`);
      values.push(fields[k]);
    }
  }
  if (set.length === 0) return res.status(400).json({ error: 'No updatable fields provided' });
  values.push(isbn);
  try {
    const [result] = await db.promise().execute(`UPDATE Book SET ${set.join(', ')} WHERE ISBN = ?`, values);
    if (result.affectedRows === 0) return res.status(404).json({ error: 'Book not found' });
    res.json({ message: 'Book updated' });
  } catch (err) {
    console.error('PUT /api/books/:isbn error:', err);
    res.status(500).json({ error: 'Failed to update book' });
  }
});

// Members: create
app.post('/api/members', async (req, res) => {
  const { Name, Email, Phone, Street, City, Zip, AccountStatus, MembershipDate } = req.body;
  if (!Name) return res.status(400).json({ error: 'Name is required' });
  try {
    const [result] = await db.promise().execute(
      'INSERT INTO `User` (Name, Email, Phone, Street, City, Zip, AccountStatus, MembershipDate) VALUES (?,?,?,?,?,?,?,?)',
      [Name, Email || null, Phone || null, Street || null, City || null, Zip || null, AccountStatus || 'Active', MembershipDate || null]
    );
    res.status(201).json({ message: 'Member created', userId: result.insertId });
  } catch (err) {
    console.error('POST /api/members error:', err);
    res.status(500).json({ error: 'Failed to create member' });
  }
});

// Members: update
app.put('/api/members/:id', async (req, res) => {
  const id = req.params.id;
  const fields = req.body;
  const allowed = ['Name','Email','Phone','Street','City','Zip','AccountStatus','MembershipDate'];
  const set = [];
  const values = [];
  for (const k of allowed) {
    if (k in fields) {
      set.push(`${k} = ?`);
      values.push(fields[k]);
    }
  }
  if (set.length === 0) return res.status(400).json({ error: 'No updatable fields provided' });
  values.push(id);
  try {
    const [result] = await db.promise().execute(`UPDATE `User` SET ${set.join(', ')} WHERE UserID = ?`, values);
    if (result.affectedRows === 0) return res.status(404).json({ error: 'Member not found' });
    res.json({ message: 'Member updated' });
  } catch (err) {
    console.error('PUT /api/members/:id error:', err);
    res.status(500).json({ error: 'Failed to update member' });
  }
});

// Loans: create (transactional)
app.post('/api/loans', requireAuth, async (req, res) => {
  const { UserID, DueDate, ISBNs } = req.body; // ISBNs: array of ISBN strings
  if (!UserID || !Array.isArray(ISBNs) || ISBNs.length === 0) return res.status(400).json({ error: 'UserID and ISBNs are required' });
  let conn;
  try {
    conn = await db.promise().getConnection();
    await conn.promise().beginTransaction();
    const [loanResult] = await conn.promise().execute('INSERT INTO Loan (UserID, CheckoutDate, DueDate) VALUES (?, CURDATE(), ?)', [UserID, DueDate || null]);
    const loanId = loanResult.insertId;
    for (const isbn of ISBNs) {
      await conn.promise().execute('INSERT INTO LoanItem (LoanID, ISBN) VALUES (?, ?)', [loanId, isbn]);
      // mark book as Borrowed
      await conn.promise().execute('UPDATE Book SET Status = ? WHERE ISBN = ?', ['Borrowed', isbn]);
    }
    await conn.promise().commit();
    res.status(201).json({ message: 'Loan created', loanId });
  } catch (err) {
    console.error('POST /api/loans error (transaction):', err);
    if (conn) {
      try { await conn.promise().rollback(); } catch (e) { console.error('Rollback failed', e); }
    }
    res.status(500).json({ error: 'Failed to create loan' });
  } finally {
    if (conn) conn.release();
  }
});

// Loans: update (e.g., mark returned)
app.put('/api/loans/:id', async (req, res) => {
  const id = req.params.id;
  const { ReturnDate, FineAmount } = req.body;
  const set = [];
  const values = [];
  if (ReturnDate !== undefined) { set.push('ReturnDate = ?'); values.push(ReturnDate); }
  if (FineAmount !== undefined) { set.push('FineAmount = ?'); values.push(FineAmount); }
  if (set.length === 0) return res.status(400).json({ error: 'No updatable fields provided' });
  values.push(id);
  try {
    const [result] = await db.promise().execute(`UPDATE Loan SET ${set.join(', ')} WHERE LoanID = ?`, values);
    if (result.affectedRows === 0) return res.status(404).json({ error: 'Loan not found' });
    res.json({ message: 'Loan updated' });
  } catch (err) {
    console.error('PUT /api/loans/:id error:', err);
    res.status(500).json({ error: 'Failed to update loan' });
  }
});

// Fines: create
app.post('/api/fines', async (req, res) => {
  const { LoanID, UserID, Amount, Description } = req.body;
  if (!LoanID || !UserID || Amount === undefined) return res.status(400).json({ error: 'LoanID, UserID and Amount are required' });
  try {
    const [result] = await db.promise().execute(
      'INSERT INTO Fine (LoanID, UserID, Amount, Description) VALUES (?,?,?,?)',
      [LoanID, UserID, Amount, Description || null]
    );
    res.status(201).json({ message: 'Fine created', fineId: result.insertId });
  } catch (err) {
    console.error('POST /api/fines error:', err);
    res.status(500).json({ error: 'Failed to create fine' });
  }
});

// Fines: update
app.put('/api/fines/:id', async (req, res) => {
  const id = req.params.id;
  const fields = req.body;
  const allowed = ['LoanID','UserID','IssueDate','Amount','Status','Description'];
  const set = [];
  const values = [];
  for (const k of allowed) {
    if (k in fields) { set.push(`${k} = ?`); values.push(fields[k]); }
  }
  if (set.length === 0) return res.status(400).json({ error: 'No updatable fields provided' });
  values.push(id);
  try {
    const [result] = await db.promise().execute(`UPDATE Fine SET ${set.join(', ')} WHERE FineID = ?`, values);
    if (result.affectedRows === 0) return res.status(404).json({ error: 'Fine not found' });
    res.json({ message: 'Fine updated' });
  } catch (err) {
    console.error('PUT /api/fines/:id error:', err);
    res.status(500).json({ error: 'Failed to update fine' });
  }
});

// Admin summary endpoint (connects admin dashboard)
app.get('/api/admin/summary', requireAuth, async (req, res) => {
  try {
    const [librarians] = await db.promise().query("SELECT COUNT(*) AS totalLibrarians FROM Employee WHERE Role IN ('Librarian','Administrator')");
    const [revenue] = await db.promise().query("SELECT IFNULL(SUM(AmountPaid),0) AS totalRevenue FROM Payment");
    res.json({ totalLibrarians: librarians[0].totalLibrarians || 0, totalRevenue: revenue[0].totalRevenue || 0 });
  } catch (err) {
    console.error('GET /api/admin/summary error:', err);
    res.status(500).json({ error: 'Failed to fetch admin summary' });
  }
});

// User summary endpoint (connects user dashboard) - returns counts for the specific user
app.get('/api/users/:id/summary', requireAuth, async (req, res) => {
  const uid = req.params.id;
  try {
    const [[borrowed]] = await db.promise().query([`SELECT COUNT(*) AS booksBorrowed FROM Loan L JOIN LoanItem LI ON LI.LoanID = L.LoanID WHERE L.UserID = ? AND L.ReturnDate IS NULL`, [uid]]);
    const [[dueSoon]] = await db.promise().query([`SELECT COUNT(*) AS dueSoon FROM Loan WHERE UserID = ? AND DueDate <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND ReturnDate IS NULL`, [uid]]);
    const [[reserved]] = await db.promise().query([`SELECT COUNT(*) AS reserved FROM Reservation WHERE UserID = ? AND Status = 'Active'`, [uid]]);
    const [[fines]] = await db.promise().query([`SELECT IFNULL(SUM(Amount),0) AS pendingFines FROM Fine WHERE UserID = ? AND Status = 'Unpaid'`, [uid]]);
    res.json({ booksBorrowed: borrowed.booksBorrowed || 0, dueSoon: dueSoon.dueSoon || 0, reserved: reserved.reserved || 0, pendingFines: fines.pendingFines || 0 });
  } catch (err) {
    console.error('GET /api/users/:id/summary error:', err);
    res.status(500).json({ error: 'Failed to fetch user summary' });
  }
});

app.listen(port, () => console.log(`LMS API listening on port ${port}`));
