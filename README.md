# Davao Local Directory

A working PHP + MySQL directory for small businesses in Davao City.

## Setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Open `http://localhost/final-project-mel-main/setup.php` once.
3. Open `http://localhost/final-project-mel-main/`.

The setup page creates the SQL database, tables, roles, categories, sample business, demo accounts, and 40 additional fictional Davao directory listings.

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@davaolocal.test` | `Admin123!` |
| Business owner | `owner@davaolocal.test` | `Owner123!` |

## Included working features

- SQL-backed customer and business owner registration/login
- Public business directory with keyword, category, and area search
- Owner business listing creation and service tagging
- Admin approval/rejection workflow; only approved listings appear publicly
- Business profile with contact details, OpenStreetMap location/directions, reviews, favorites, and customer inquiries
- In-app notifications and role-specific dashboards

## Adding the demo directory data later

Open `http://localhost/final-project-mel-main/demo_seed.php`. The seeder is repeat-safe: it checks each business name before inserting, so it will not delete or duplicate Maria's Printing Services, existing users, categories, reviews, or other records.

## Administration upgrade

After an existing installation, open `http://localhost/final-project-mel-main/upgrade.php` once. This repeat-safe migration adds account-status, last-login, and business rejection-reason fields without deleting or recreating any table or record.

Administrators are sent to the upgraded console at `admin.php`; business owners are sent to `owner.php`. Both use the same persistent `users`, `businesses`, reviews, categories, notifications, and activity-log data already used by the directory.

`schema.sql` contains the normalized MySQL schema. Change database credentials in `config.php` if your XAMPP MySQL installation differs from the normal `root` / blank-password setup.

## Feature upgrade migration

For an existing installation, open `http://localhost/final-project-mel-main/upgrade.php` once after pulling these changes. The migration is repeat-safe and preserves existing data. It adds verified-business status, gallery storage metadata, report and review-report tables, view analytics, appointment records, and richer notification links.

Business owners manage logos and gallery images from **Owner dashboard → My Business → Manage gallery**. Customers can request appointments from an approved business profile and review their requests in `appointments.php`; owners use the same page to manage requests. Customer business reports are sent from the business profile and administrators manage them at `admin.php?view=reports`.
