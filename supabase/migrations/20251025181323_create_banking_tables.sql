/*
  # Create Banking System Tables

  1. New Tables
    - `users`
      - `id` (uuid, primary key) - Unique user identifier
      - `username` (text) - User's display name
      - `email` (text, unique) - User's email address
      - `password` (text) - Hashed password
      - `phone` (text) - User's phone number
      - `created_at` (timestamptz) - Account creation timestamp
    
    - `amounts`
      - `id` (uuid, primary key) - Unique record identifier
      - `user_id` (uuid, foreign key) - References users table
      - `total_balance` (numeric) - Checking account balance
      - `income` (numeric) - Total income amount
      - `expenses` (numeric) - Total expenses amount
      - `savings` (numeric) - Savings account balance
      - `updated_at` (timestamptz) - Last update timestamp
    
    - `transactions`
      - `id` (uuid, primary key) - Unique transaction identifier
      - `user_id` (uuid, foreign key) - References users table
      - `type` (text) - Transaction type: transfer_sent, transfer_received, expense, income
      - `amount` (numeric) - Transaction amount
      - `description` (text) - Transaction description
      - `from_account` (text) - Source account name
      - `to_account` (text) - Destination account/email
      - `status` (text) - Transaction status: completed, pending, failed
      - `created_at` (timestamptz) - Transaction timestamp

  2. Security
    - Enable RLS on all tables
    - Add policies for authenticated users to manage their own data
    - Users can only view their own transactions and account information

  3. Important Notes
    - All tables use UUID primary keys for better security
    - Timestamps are automatically managed
    - Foreign keys ensure referential integrity
    - Default values prevent null issues
*/

-- Create users table
CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  username text NOT NULL,
  email text UNIQUE NOT NULL,
  password text NOT NULL,
  phone text DEFAULT '',
  created_at timestamptz DEFAULT now()
);

-- Create amounts table
CREATE TABLE IF NOT EXISTS amounts (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  total_balance numeric(12,2) DEFAULT 0.00,
  income numeric(12,2) DEFAULT 0.00,
  expenses numeric(12,2) DEFAULT 0.00,
  savings numeric(12,2) DEFAULT 0.00,
  updated_at timestamptz DEFAULT now(),
  UNIQUE(user_id)
);

-- Create transactions table
CREATE TABLE IF NOT EXISTS transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type text NOT NULL CHECK (type IN ('transfer_sent', 'transfer_received', 'expense', 'income')),
  amount numeric(12,2) NOT NULL,
  description text DEFAULT '',
  from_account text DEFAULT '',
  to_account text DEFAULT '',
  status text DEFAULT 'completed' CHECK (status IN ('completed', 'pending', 'failed')),
  created_at timestamptz DEFAULT now()
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type);
CREATE INDEX IF NOT EXISTS idx_amounts_user_id ON amounts(user_id);

-- Enable Row Level Security
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE amounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;

-- Users table policies
CREATE POLICY "Users can view own profile"
  ON users FOR SELECT
  TO authenticated
  USING (auth.uid() = id);

CREATE POLICY "Users can update own profile"
  ON users FOR UPDATE
  TO authenticated
  USING (auth.uid() = id)
  WITH CHECK (auth.uid() = id);

-- Amounts table policies
CREATE POLICY "Users can view own amounts"
  ON amounts FOR SELECT
  TO authenticated
  USING (auth.uid() = user_id);

CREATE POLICY "Users can update own amounts"
  ON amounts FOR UPDATE
  TO authenticated
  USING (auth.uid() = user_id)
  WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can insert own amounts"
  ON amounts FOR INSERT
  TO authenticated
  WITH CHECK (auth.uid() = user_id);

-- Transactions table policies
CREATE POLICY "Users can view own transactions"
  ON transactions FOR SELECT
  TO authenticated
  USING (auth.uid() = user_id);

CREATE POLICY "Users can insert own transactions"
  ON transactions FOR INSERT
  TO authenticated
  WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update own transactions"
  ON transactions FOR UPDATE
  TO authenticated
  USING (auth.uid() = user_id)
  WITH CHECK (auth.uid() = user_id);

-- Function to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update amounts.updated_at
DROP TRIGGER IF EXISTS update_amounts_updated_at ON amounts;
CREATE TRIGGER update_amounts_updated_at
  BEFORE UPDATE ON amounts
  FOR EACH ROW
  EXECUTE FUNCTION update_updated_at_column();
