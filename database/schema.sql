-- File Server Enterprise - MySQL Schema (Phase 1)
-- Compatible with SQLite (fallback) with minor adjustments via PHP installer

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS folders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  parent_id INTEGER NULL,
  name VARCHAR(255) NOT NULL,
  created_by INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  FOREIGN KEY(parent_id) REFERENCES folders(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  folder_id INTEGER NULL,
  name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  extension VARCHAR(20) NULL,
  mime_type VARCHAR(120) NULL,
  size BIGINT NOT NULL DEFAULT 0,
  storage_provider VARCHAR(30) NOT NULL DEFAULT 'local',
  storage_path VARCHAR(500) NOT NULL,
  storage_file_id VARCHAR(255) NULL,
  thumbnail_path VARCHAR(500) NULL,
  uploaded_by INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE SET NULL,
  FOREIGN KEY(uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS file_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_id INTEGER NOT NULL,
  version_no INTEGER NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  size BIGINT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(file_id) REFERENCES files(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS starred_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  file_id INTEGER NULL,
  folder_id INTEGER NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(user_id, file_id, folder_id),
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(file_id) REFERENCES files(id) ON DELETE CASCADE,
  FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS file_shares (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_id INTEGER NULL,
  folder_id INTEGER NULL,
  shared_by INTEGER NOT NULL,
  shared_with INTEGER NULL,
  share_token VARCHAR(64) NULL,
  permission VARCHAR(20) NOT NULL DEFAULT 'viewer',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(file_id) REFERENCES files(id) ON DELETE CASCADE,
  FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  action VARCHAR(50) NOT NULL,
  target_type VARCHAR(20) NOT NULL,
  target_id INTEGER NULL,
  target_name VARCHAR(255) NULL,
  details TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS storage_connections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider VARCHAR(30) NOT NULL,
  config_json TEXT NULL,
  is_active INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- MySQL variant (XAMPP) - run this if using MySQL:
-- Replace AUTOINCREMENT with AUTO_INCREMENT and adjust types as needed.
-- The PHP installer automatically handles both.
