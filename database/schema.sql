-- ============================================================
-- Digital Library Repository - Database Schema
-- Modeled on DSpace's core architecture:
--   Community -> Collection -> Item -> Bitstream (file)
-- Designed and indexed to hold 100,000+ items efficiently.
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','librarian','user') NOT NULL DEFAULT 'user',
  membership_no VARCHAR(20) UNIQUE DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  max_loans INT UNSIGNED NOT NULL DEFAULT 3,
  membership_status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_membership_no (membership_no)
) ENGINE=InnoDB;

-- Communities can be nested (a community may belong to a parent community),
-- exactly like DSpace's Community hierarchy (e.g. "Faculty of Science" ->
-- "Department of Computer Science").
CREATE TABLE IF NOT EXISTS communities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT DEFAULT NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES communities(id) ON DELETE CASCADE,
  INDEX idx_parent (parent_id)
) ENGINE=InnoDB;

-- Collections belong to exactly one community and hold items.
CREATE TABLE IF NOT EXISTS collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
  INDEX idx_community (community_id)
) ENGINE=InnoDB;

-- Items are the digital objects themselves (a book, thesis, article, etc.).
-- Common Dublin-Core-style fields are kept as real columns (fast, indexed,
-- sortable, full-text searchable) rather than pure key/value, which is what
-- lets this table stay fast at 100,000+ rows.
CREATE TABLE IF NOT EXISTS items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  submitter_id INT NOT NULL,
  title VARCHAR(500) NOT NULL,
  author VARCHAR(300) DEFAULT NULL,
  abstract TEXT,
  publisher VARCHAR(200) DEFAULT NULL,
  publication_date DATE DEFAULT NULL,
  language VARCHAR(50) DEFAULT 'en',
  item_type ENUM('book','thesis','article','report','image','dataset','other') NOT NULL DEFAULT 'book',
  rights VARCHAR(200) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  download_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  FOREIGN KEY (submitter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status),
  INDEX idx_collection (collection_id),
  INDEX idx_submitter (submitter_id),
  INDEX idx_pubdate (publication_date),
  INDEX idx_type (item_type),
  INDEX idx_status_date (status, publication_date),
  FULLTEXT KEY ft_core (title, author, abstract)
) ENGINE=InnoDB;

-- Extra, repeatable Dublin-Core-style metadata (a Item can have many
-- subjects, many contributors, many identifiers) without altering the
-- items table -- the same flexible design DSpace itself uses.
-- field_name examples: dc.subject, dc.contributor, dc.identifier
CREATE TABLE IF NOT EXISTS item_metadata (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT NOT NULL,
  field_name VARCHAR(60) NOT NULL,
  field_value VARCHAR(500) NOT NULL,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  INDEX idx_item (item_id),
  INDEX idx_field_value (field_name, field_value),
  FULLTEXT KEY ft_value (field_value)
) ENGINE=InnoDB;

-- Bitstreams = the actual uploaded files attached to an item.
-- An item can have more than one file (e.g. the book PDF + a cover image).
CREATE TABLE IF NOT EXISTS bitstreams (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  mime_type VARCHAR(120) DEFAULT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  INDEX idx_item (item_id)
) ENGINE=InnoDB;

-- One row per download, for statistics (and so download_count can be
-- rebuilt/audited later if needed).
CREATE TABLE IF NOT EXISTS downloads (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT NOT NULL,
  bitstream_id BIGINT NOT NULL,
  user_id INT DEFAULT NULL,
  downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  FOREIGN KEY (bitstream_id) REFERENCES bitstreams(id) ON DELETE CASCADE,
  INDEX idx_item (item_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CIRCULATION (physical library) TABLES
-- An item can have zero or more physical copies (in addition to
-- zero or more digital bitstreams), so the same catalog record can
-- be a physical book, a downloadable file, or both.
-- ============================================================

-- One row per physical copy of an item, identified by a barcode
-- (like the sticker on the inside cover of a real library book).
CREATE TABLE IF NOT EXISTS copies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT NOT NULL,
  barcode VARCHAR(50) NOT NULL UNIQUE,
  shelf_location VARCHAR(50) DEFAULT NULL,
  status ENUM('available','checked_out','reserved','lost','damaged') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  INDEX idx_item (item_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- One row per checkout. return_date is NULL while the loan is active.
CREATE TABLE IF NOT EXISTS loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  copy_id INT NOT NULL,
  member_id INT NOT NULL,
  loan_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE DEFAULT NULL,
  renewed_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (copy_id) REFERENCES copies(id) ON DELETE CASCADE,
  FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_member (member_id),
  INDEX idx_copy (copy_id),
  INDEX idx_active (member_id, return_date),
  INDEX idx_due (due_date, return_date)
) ENGINE=InnoDB;

-- A hold is placed on a TITLE (item), not a specific copy -- the
-- first copy returned fulfills the oldest pending hold, exactly
-- like a real library reservation queue.
CREATE TABLE IF NOT EXISTS holds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT NOT NULL,
  member_id INT NOT NULL,
  hold_date DATE NOT NULL,
  status ENUM('pending','ready','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  ready_copy_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (ready_copy_id) REFERENCES copies(id) ON DELETE SET NULL,
  INDEX idx_item_status (item_id, status),
  INDEX idx_member (member_id)
) ENGINE=InnoDB;

-- Overdue fines, generated automatically when an overdue loan is
-- returned (see circulation.php), or added manually by staff.
CREATE TABLE IF NOT EXISTS fines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT DEFAULT NULL,
  member_id INT NOT NULL,
  amount DECIMAL(6,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  paid_at TIMESTAMP DEFAULT NULL,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE SET NULL,
  FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_member_status (member_id, status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Starter data: one admin account, one community/collection tree
-- so the site is usable immediately after import.
-- Default admin password below is: Admin1234
-- (hash generated with PHP password_hash - change this after import!)
-- ------------------------------------------------------------
INSERT INTO users (full_name, email, username, password, role, membership_no) VALUES
('Repository Administrator', 'admin@example.com', 'admin',
 '$2y$10$2TE3L8N7thuUrUfWmaexm.9uFXNhg/o6/mwbYxSyvbtpvgM46xTli', 'admin', 'LIB-000001');

INSERT INTO communities (id, parent_id, name, description) VALUES
(1, NULL, 'Faculty of Computing', 'Digital materials from the Faculty of Computing'),
(2, NULL, 'University Library', 'General library holdings');

INSERT INTO collections (id, community_id, name, description) VALUES
(1, 1, 'Computer Science Books', 'Textbooks and reference books in Computer Science'),
(2, 1, 'Student Theses', 'Undergraduate and graduate theses'),
(3, 2, 'General Collection', 'General books, reports, and other materials');
