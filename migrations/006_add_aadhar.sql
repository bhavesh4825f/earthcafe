-- migration 006: add aadhar column to users and employees

ALTER TABLE users
  ADD COLUMN aadhar VARCHAR(20) DEFAULT NULL AFTER phone;

ALTER TABLE employees
  ADD COLUMN aadhar VARCHAR(20) DEFAULT NULL AFTER contact;
