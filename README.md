# Davao Local Directory

A local PHP + MySQL directory application for finding businesses in Davao City. It includes public browsing, customer accounts, owner listing tools, and an administrator review console.

## Run locally with XAMPP

1. Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open `http://localhost/Web2Final-main/setup.php` once for a fresh local database. Setup uses `CREATE TABLE IF NOT EXISTS`, repeat-safe migrations, and insert-if-missing sample data; it does not drop or reset the database.
3. Open `http://localhost/Web2Final-main/`.
4. For an existing installation, open `http://localhost/Web2Final-main/upgrade.php` to apply repeat-safe schema additions before testing newer features.

Default database connection values are in `config.php` (`127.0.0.1`, database `davao_local_directory`, XAMPP's default `root` with a blank password). Change these only to match your local MySQL setup.

### Fresh-install classroom accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@davaolocal.test` | `Admin123!` |
| Business owner | `owner@davaolocal.test` | `Owner123!` |
| Demo customer | `demo.customer@davaolocal.test` | `DemoCustomer123!` |

The demo credentials are for local classroom use only. Existing accounts keep their existing passwords; rerunning setup does not reset them.
This working database also has a workflow test customer: `classroom.customer@davaolocal.test` / `ClassroomDemo123!`. It is labeled as a classroom demo account. The public listing named “Fictional classroom workflow listing” is also test data and is marked **Fictional demo**.

## Current features

- Public home page and approved-business directory with search across business names, descriptions, products, and services; category, area, rating, owner verification, hours, contact, map-location, services, price, and sort filters.
- Business profiles with services, owner-set status and weekly hours, customer reviews, favorites, inquiries/private messages, appointment requests, and reports.
- OpenStreetMap/Leaflet map at `index.php?page=map`. It shows approved non-sample listings only when valid coordinates were saved. It never geocodes or invents business coordinates. Tile and location-permission failures leave directory/profile links available.
- Customer registration, sign-in, saved businesses, reviews, inquiries/messages, notifications, and appointment history.
- Owner listing submission (new listings stay pending), profile/hours/services/products/gallery management, messages, reviews, and appointment management.
- Administrator approval/rejection and a separate verification control, plus user/category/review/report management.

## Fictional sample data

Setup creates clearly marked fictional sample businesses and demo accounts for practice. Sample business cards and profiles display **Fictional demo**; sample data does not represent real businesses or usable contact details. Sample listings are excluded from the public map even if older records contain coordinates. Do not use sample addresses, phone numbers, or reviews as real-world information.

To add the fictional sample directory later, use `http://localhost/Web2Final-main/demo_seed.php`. The seeder is repeat-safe and does not delete records. It creates demonstration content only; it does not create real businesses or real map locations.

## Data preservation

`setup.php` and `upgrade.php` do not drop or recreate existing tables. Schema upgrades add missing columns/tables and mark known fixtures as sample records. Avoid manual database resets. Owner-submitted listings remain pending until an administrator approves them.

## Map notes

Map tiles come from the community OpenStreetMap tile service and require internet access. OpenStreetMap attribution is shown on the map. Tile availability is best-effort; when tiles are blocked or offline, the mapped-business links and directory remain usable. Real marker testing requires an approved non-sample listing with coordinates supplied by its owner/admin.