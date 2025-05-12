-- Insert initial admin users
INSERT INTO users (username, email, password, name, role, status, created_at)
VALUES
  (
    'shanisbsg',
    'shani@backsureglobalsupport.com',
    '$2y$10$mhMnb9OEd/gclyBE3s1jQuZC4Fdb5NxiM0Ee8DwR8nkZz7uzKWU.C',  -- Admin@123
    'Shanis BSG',
    'admin',
    'active',
    NOW()
  ),
  (
    'superadmin',
    'super@backsureglobalsupport.com',
    '$2y$10$V9dp94iCrxkULYT6YlxiHeMKttFbZVXwRxU8vlZTKJY/hkT78DNrm',  -- Super@123
    'Super Admin',
    'superadmin',
    'active',
    NOW()
  );
