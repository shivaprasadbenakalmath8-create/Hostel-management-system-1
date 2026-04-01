CREATE DATABASE IF NOT EXISTS hostel_management;
USE hostel_management;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student', 'staff') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    course VARCHAR(50),
    year INT,
    phone VARCHAR(15),
    address TEXT,
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(15),
    joining_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Staff table
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    staff_id VARCHAR(20) UNIQUE NOT NULL,
    designation VARCHAR(50),
    department VARCHAR(50),
    phone VARCHAR(15),
    address TEXT,
    joining_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Hostels table
CREATE TABLE hostels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('boys', 'girls', 'co-ed') NOT NULL,
    total_floors INT,
    total_rooms INT,
    address TEXT,
    warden_name VARCHAR(100),
    warden_phone VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Rooms table
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hostel_id INT,
    room_number VARCHAR(20) NOT NULL,
    floor INT,
    capacity INT DEFAULT 2,
    occupied INT DEFAULT 0,
    room_type ENUM('single', 'double', 'triple', 'dormitory') DEFAULT 'double',
    rent DECIMAL(10,2),
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room (hostel_id, room_number)
);

-- Allocations table
CREATE TABLE allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    room_id INT,
    bed_number INT,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Mess menu table
CREATE TABLE mess_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    day_of_week ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
    breakfast TEXT,
    lunch TEXT,
    snacks TEXT,
    dinner TEXT,
    special_item TEXT
);

-- Mess attendance table
CREATE TABLE mess_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    meal_type ENUM('breakfast','lunch','snacks','dinner') NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent') DEFAULT 'present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_meal (student_id, meal_type, date)
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    payment_type ENUM('hostel_fee', 'mess_fee', 'caution_deposit', 'other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    due_date DATE,
    status ENUM('paid', 'pending', 'overdue') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Leave applications table
CREATE TABLE leave_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    remarks TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Visitor management table
CREATE TABLE visitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_name VARCHAR(100) NOT NULL,
    student_id INT,
    relation VARCHAR(50),
    phone VARCHAR(15),
    purpose TEXT,
    check_in DATETIME NOT NULL,
    check_out DATETIME,
    id_proof VARCHAR(255),
    status ENUM('checked_in', 'checked_out') DEFAULT 'checked_in',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Complaints table
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    complaint_type ENUM('maintenance', 'cleanliness', 'food', 'electricity', 'plumbing', 'other') NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
    assigned_to INT,
    resolution_text TEXT,
    resolved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES staff(id)
);

-- Inventory table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category ENUM('furniture', 'electronics', 'kitchen', 'cleaning', 'other') NOT NULL,
    quantity INT DEFAULT 0,
    unit VARCHAR(20),
    min_quantity INT DEFAULT 5,
    max_quantity INT,
    location VARCHAR(100),
    status ENUM('available', 'low_stock', 'out_of_stock') DEFAULT 'available',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notices table
CREATE TABLE notices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    audience ENUM('all', 'students', 'staff') DEFAULT 'all',
    priority ENUM('normal', 'important', 'urgent') DEFAULT 'normal',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert sample data
INSERT INTO users (username, password, email, full_name, role) VALUES
('admin', MD5('admin123'), 'admin@hostel.com', 'System Administrator', 'admin'),
('john_doe', MD5('student123'), 'john@example.com', 'John Doe', 'student'),
('jane_smith', MD5('student123'), 'jane@example.com', 'Jane Smith', 'student'),
('staff1', MD5('staff123'), 'staff@hostel.com', 'Michael Johnson', 'staff');

INSERT INTO hostels (name, type, total_floors, total_rooms, warden_name, warden_phone) VALUES
('Boys Hostel A', 'boys', 4, 80, 'Mr. Sharma', '9876543210'),
('Girls Hostel B', 'girls', 3, 60, 'Mrs. Gupta', '9876543211'),
('International Hostel', 'co-ed', 5, 100, 'Dr. Kumar', '9876543212');

-- Add more sample data as needed
