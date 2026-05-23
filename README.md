# Hospital Management System

This is a compact hospital management portal built with PHP and MySQL for local development. It includes patient-facing pages, an admin dashboard, appointment management, simple billing, ambulance tracking, blood bank support, organ donation workflow, and vaccination records.

## Overview

This project is designed as a practical demo of a small hospital or clinic operations system. It is not meant to be a full commercial product, but it shows how to manage:

- patient registration and login
- doctor scheduling and appointment booking
- admin controls for patients, doctors, appointments, and billing
- ambulance support for emergency transport
- blood donor registry and blood stock inventory
- organ donation registrations and requests
- vaccine inventory and vaccination tracking

## Getting Started

1. Install XAMPP and start Apache + MySQL.
2. Copy the project into `c:\xampp\htdocs\make_simple\`.
3. Open `http://localhost/make_simple/setup.php` once to create the database and sample data.
4. Open `http://localhost/make_simple/index.php` to begin using the app.

## Default Login

- Admin: `admin` / `admin123`
- Patient: create a new account through the app.

## Notes

- The database connection is configured for XAMPP defaults.
- If you are using a different MySQL login, update `db.php`.
- Run `setup.php` once after copying the files. It creates tables and seed data without overwriting existing rows.

## Main Tables

- `admins` — administrator accounts
- `patients` — registered patients
- `doctors` — doctor profiles and specialties
- `appointments` — doctor appointments
- `billings` — billing records and invoice details

- `drivers` — ambulance drivers
- `ambulances` — ambulance vehicles
- `ambulance_bookings` — emergency transport requests

- `blood_donors` — registered donors
- `blood_bank` — blood inventory
- `blood_requests` — blood requests

- `organ_donors` — organ donor registrations
- `organ_requests` — organ transplant requests

- `vaccine_inventory` — vaccine batches and stock
- `vaccinations` — administered vaccinations
- `vaccination_reminders` — follow-up reminders

## How to Use

### Patient

- register and log in
- book appointments with available doctors
- request ambulance support when needed
- review billing status and vaccination records

### Admin

- log in to the admin panel
- manage patients, doctors, and appointments
- update billing status
- handle ambulance, blood bank, organ donation, and vaccination operations
- review reports and system status from the dashboard

## Technology Stack

- PHP
- MySQL / MariaDB
- HTML + CSS
- JavaScript for UI interactions
- PHP sessions for authentication

## Development Notes

- Passwords use `password_hash()` and `password_verify()`.
- Prepared statements are used in key form handlers.
- This is a demo application intended for local testing and learning.

## Possible Improvements

- add email or SMS alerts
- add charts or charts library for reports
- separate CSS into its own stylesheet
- add a mobile-first design system
- implement stronger access control and validation
