CREATE DATABASE IF NOT EXISTS LibraryDB;
USE LibraryDB;

CREATE TABLE User (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE,
    Phone VARCHAR(20),
    Street VARCHAR(100),
    City VARCHAR(50),
    Zip VARCHAR(10),
    AccountStatus VARCHAR(20) DEFAULT 'Active',
    MembershipDate DATE
);

CREATE TABLE Employee (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE,
    Position VARCHAR(50),
    AccessLevel INT DEFAULT 1,
    Role ENUM('Librarian','Administrator','Other') DEFAULT 'Librarian'
);

CREATE TABLE Book (
    ISBN VARCHAR(20) PRIMARY KEY,
    Title VARCHAR(200) NOT NULL,
    PublicationYear YEAR,
    Genre VARCHAR(100),
    Author VARCHAR(100),
    Status ENUM('Available','Borrowed','Reserved','Lost') DEFAULT 'Available',
    ShelfLocation VARCHAR(50)
);

CREATE TABLE BookAuthors (
    ISBN VARCHAR(20),
    AuthorName VARCHAR(100),
    PRIMARY KEY (ISBN, AuthorName),
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE RelatedBooks (
    ISBN VARCHAR(20),
    RelatedISBN VARCHAR(20),
    PRIMARY KEY (ISBN, RelatedISBN),
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN)
        ON DELETE CASCADE,
    FOREIGN KEY (RelatedISBN) REFERENCES Book(ISBN)
        ON DELETE CASCADE
);

CREATE TABLE Policy (
    PolicyID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100),
    LoanPeriodDays INT DEFAULT 14,
    MaxBooks INT DEFAULT 5,
    FineRatePerDay DECIMAL(5,2) DEFAULT 0.50,
    GracePeriodDays INT DEFAULT 2
);

CREATE TABLE Reservation (
    ReservationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ReservationDate DATE DEFAULT (CURRENT_DATE),
    ExpiryDate DATE,
    Status ENUM('Active','Expired','Cancelled') DEFAULT 'Active',
    FOREIGN KEY (UserID) REFERENCES User(UserID)
        ON DELETE CASCADE
);

CREATE TABLE ReservationItem (
    ReservationID INT,
    ISBN VARCHAR(20),
    PRIMARY KEY (ReservationID, ISBN),
    FOREIGN KEY (ReservationID) REFERENCES Reservation(ReservationID)
        ON DELETE CASCADE,
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN)
        ON DELETE RESTRICT
);

CREATE TABLE Loan (
    LoanID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    CheckoutDate DATE DEFAULT (CURRENT_DATE),
    DueDate DATE,
    ReturnDate DATE,
    FineAmount DECIMAL(8,2) DEFAULT 0.00,
    FOREIGN KEY (UserID) REFERENCES User(UserID)
        ON DELETE CASCADE
);

CREATE TABLE LoanItem (
    LoanID INT,
    ISBN VARCHAR(20),
    PRIMARY KEY (LoanID, ISBN),
    FOREIGN KEY (LoanID) REFERENCES Loan(LoanID)
        ON DELETE CASCADE,
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN)
        ON DELETE RESTRICT
);

CREATE TABLE Fine (
    FineID INT AUTO_INCREMENT PRIMARY KEY,
    LoanID INT NOT NULL,
    UserID INT NOT NULL,
    IssueDate DATE DEFAULT (CURRENT_DATE),
    Amount DECIMAL(8,2) NOT NULL,
    Status ENUM('Unpaid','Paid') DEFAULT 'Unpaid',
    Description VARCHAR(255),
    FOREIGN KEY (LoanID) REFERENCES Loan(LoanID)
        ON DELETE CASCADE,
    FOREIGN KEY (UserID) REFERENCES User(UserID)
        ON DELETE CASCADE
);

CREATE TABLE Payment (
    PaymentID INT AUTO_INCREMENT PRIMARY KEY,
    FineID INT NOT NULL,
    PaymentDate DATE DEFAULT (CURRENT_DATE),
    AmountPaid DECIMAL(8,2),
    PaymentMethod ENUM('Cash','Credit','Online','Other') DEFAULT 'Cash',
    FOREIGN KEY (FineID) REFERENCES Fine(FineID)
        ON DELETE CASCADE
);

CREATE TABLE Report (
    ReportID INT AUTO_INCREMENT PRIMARY KEY,
    GeneratedBy INT,
    GenerationDate DATE DEFAULT (CURRENT_DATE),
    Type VARCHAR(50),
    Content TEXT,
    FOREIGN KEY (GeneratedBy) REFERENCES Employee(EmployeeID)
        ON DELETE SET NULL
);

CREATE TABLE ProcessedBy (
    ProcessID INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID INT NOT NULL,
    EntityType ENUM('Loan','Reservation','Book'),
    EntityID INT NOT NULL,
    ActionDate DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID)
        ON DELETE CASCADE
);
