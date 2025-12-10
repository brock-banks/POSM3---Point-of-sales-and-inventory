<?php
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'POSM3';
const DB_USER    = 'root';      // default XAMPP user
const DB_PASS    = '';          // default XAMPP password
const DB_CHARSET = 'utf8mb4';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}