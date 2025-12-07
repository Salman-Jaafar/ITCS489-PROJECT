-- Insert seed users for testing
-- Password for users John Doe and James Bond: 12345678 (hashed with bcrypt)
-- Password for librarian and admin: abcdefgh (hashed with bcrypt)

-- User 1: John Doe (Regular User, Staff Member)
INSERT INTO User (FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, ProfilePhoto, AccountStatus, MembershipDate)
VALUES (
    'John',
    'Doe',
    'john@gmail.com',
    '1234567890',
    '43A',
    '$2y$10$BvwrU1MHHlRb8LU6.MlqsuLxBiaxGIWAk1QHGqWtKwxXDiUqAWCm2',
    1,
    'Doctor',
    6,
    NULL,
    'Active',
    NOW()
);

-- User 2: James Bond (Regular User, Not Staff)
INSERT INTO User (FirstName, LastName, Email, Phone, Address, Password, IsStaff, UserType, MaxBooks, ProfilePhoto, AccountStatus, MembershipDate)
VALUES (
    'James',
    'Bond',
    'james@gmail.com',
    '0987654321',
    '43A',
    '$2y$10$BvwrU1MHHlRb8LU6.MlqsuLxBiaxGIWAk1QHGqWtKwxXDiUqAWCm2',
    0,
    'User',
    3,
    NULL,
    'Active',
    NOW()
);

-- Employee 1: Al-Sayed Mustafa (Librarian)
INSERT INTO Employee (FirstName, LastName, Email, Phone, Address, Password, Position, AccessLevel, Role, MaxBooks, ProfilePhoto, AccountStatus, JoinDate)
VALUES (
    'Al-Sayed',
    'Al-Haddar',
    'sayed@gmail.com',
    '1111111111',
    '245-B',
    '$2y$10$8Dt4FqVpMXXhIVj7u6CPkuMSIxIdVqgqNCDMI8F6rNb7IvCuQPkWi',
    'Librarian',
    2,
    'Librarian',
    0,
    NULL,
    'Active',
    NOW()
);

-- Employee 2: Baqir (Administrator)
INSERT INTO Employee (FirstName, LastName, Email, Phone, Address, Password, Position, AccessLevel, Role, MaxBooks, ProfilePhoto, AccountStatus, JoinDate)
VALUES (
    'Baqir',
    'Al-Haddar',
    'baqir@gmail.com',
    '2222222222',
    '4B',
    '$2y$10$8Dt4FqVpMXXhIVj7u6CPkuMSIxIdVqgqNCDMI8F6rNb7IvCuQPkWi',
    'Administrator',
    3,
    'Administrator',
    0,
    NULL,
    'Active',
    NOW()
);

-- Create reservations for John Doe (1 book)
-- First, get John Doe's UserID and create a reservation
INSERT INTO Reservation (UserID, ReservationDate, ExpiryDate, Status)
SELECT UserID, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Active'
FROM User WHERE Email = 'john@gmail.com'
LIMIT 1;

-- Add a book to the reservation
INSERT INTO ReservationItem (ReservationID, ISBN)
SELECT r.ReservationID, b.ISBN
FROM Reservation r, Book b
WHERE r.UserID = (SELECT UserID FROM User WHERE Email = 'john@gmail.com')
  AND b.ISBN = (SELECT ISBN FROM Book LIMIT 1)
LIMIT 1;
