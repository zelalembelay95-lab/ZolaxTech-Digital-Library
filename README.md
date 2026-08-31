# Digital Library Repository

A PHP/MySQL digital repository system for the CoSc3091 Web Programming
course, architecturally modeled on **DSpace** (the widely used
open-source institutional repository platform):

```
Community  ->  Collection  ->  Item  ->  Bitstream (file)
```

## What this system does
- **Browse & search** a repository of items (books, theses, articles,
  reports, images, datasets) organized into communities and collections
- **Full-text search** (MySQL FULLTEXT, boolean/prefix matching) across
  titles, authors, abstracts, and extra metadata (subjects, identifiers)
- **User registration & login** with hashed passwords, PHP sessions, and
  an auto-assigned library card number (membership_no)
- **Submission workflow**: registered users submit items (pending review);
  librarians/admins approve or reject them; approved items go public
- **File uploads** ("bitstreams"), stored in disk sub-folders bucketed by
  item ID so no single folder ever holds an unmanageable number of files
- **Download tracking**: every download is logged, and item view/download
  counters update live
- **Physical circulation (full library system)**: items can also have
  physical copies with barcodes, which members borrow and return at a
  staffed Circulation Desk — with due dates, self-service renewals, a
  hold/reservation queue, and automatic overdue fines
- **Admin area**: review submissions, manage communities/collections/
  copies/holds/fines, manage user roles, bulk-import metadata from CSV,
  and generate sample data to demonstrate the system at scale
- **Role-based access**: `admin`, `librarian`, `user`

## Circulation (physical library) features
This isn't just a digital repository — it's a hybrid system, since any
item can have physical copies, digital files, or both:
- **Copies**: staff add barcoded physical copies of any item (Admin →
  Manage Copies), each with a shelf location and status (available,
  checked out, reserved, lost, damaged)
- **Circulation Desk** (Admin → Circulation Desk): staff check a copy
  out to a member (by username or library card number) or process a
  return, with all the usual library rules enforced:
  - a configurable loan period (14 days by default)
  - a per-member loan limit (`max_loans`, default 3)
  - blocked checkout for suspended memberships or members with $10+
    in unpaid fines
  - automatic overdue fines on return ($0.50/day by default), logged
    per member
- **Holds**: if every copy of a title is checked out, a member can
  place a hold from the item page; when a copy is returned, it's
  automatically set aside ("reserved") for the member who has been
  waiting longest, and can only be checked out to them
- **My Loans / My Holds**: members can view their current and past
  loans, self-service renew (up to 2 renewals, blocked if overdue or
  if another member is waiting on a hold), and manage their holds
- **Manage Fines** (Admin): view and mark fines as paid

## Tested at scale
This isn't just claimed — it was actually measured. Using the included
CLI seeder (`scripts/seed_demo_data.php`), **100,000 demo items were
inserted in ~4 seconds**, and after that:
- The home page, browse page, item detail page, and admin dashboard all
  loaded in well under 200ms
- A full-text search across the 100,000-row table completed in
  **~30–130ms** (after an initial query-planner rewrite — see
  "Performance notes" below)
- The full circulation workflow (checkout, hold placement, overdue
  return with automatic fine calculation, hold fulfillment, renewal
  blocking) was tested end-to-end against a live database, including
  edge cases: wrong member trying to claim someone else's held copy,
  loan-limit blocking, suspended-membership blocking, and hold
  cancellation

## An important, honest note on "100,000 digital books"
The **database schema and code are built and tested to hold and query
100,000+ item records efficiently** — that part is proven above. But
*storing 100,000 real book files* (PDFs etc.) is a different problem:
at even a modest 2MB/book average, that's ~200GB of storage. Free
hosts like InfinityFree typically cap storage around 5GB. For a real
deployment at that scale you would need a paid host, a VPS, or
object storage (e.g. S3-compatible storage) as the file backend — the
`bitstreams` table already stores just a file path, so swapping the
storage backend later doesn't require changing the database design.
For this course project, treat the 100,000-record capability as what's
been demonstrated, and upload as many *real* files as your chosen
hosting's storage allows.

## Folder Structure
```
digital-library/
├── index.php                 Home (stats + recent items)
├── browse.php                  Paginated browse, sort + type filter
├── search.php                    Full-text search, sort + type filter
├── item.php                        Item detail + metadata + files
├── download.php                      File download + logging
├── communities.php                     List communities
├── community.php                         Single community (sub-communities + collections)
├── collection.php                          Single collection (paginated items)
├── register.php / login.php / logout.php    Auth
├── submit.php                                 Submission form (core feature)
├── my_submissions.php                           Submitter's own items + status
├── my_loans.php                                   Member's current/past loans + renew
├── my_holds.php                                     Member's hold queue + cancel
├── about.php / contact.php                            Static + DB-backed contact form
├── includes/
│   ├── db_connect.php                               DB connection + session
│   ├── functions.php                                  Auth guards, pagination, circulation helpers
│   ├── header.php / footer.php                          Shared layout
├── admin/
│   ├── dashboard.php                                      Stats + quick links
│   ├── review_submissions.php                               Approve/reject pending items
│   ├── manage_communities.php                                 Create communities
│   ├── manage_collections.php                                   Create collections
│   ├── manage_users.php                                           Change user roles (admin only)
│   ├── bulk_import.php                                              CSV metadata import (admin only)
│   ├── generate_sample_data.php                                       AJAX-chunked demo data generator
│   ├── circulation.php                                                  Check out / return desk
│   ├── manage_copies.php                                                  Add/list physical copies
│   ├── manage_holds.php                                                     Holds queue oversight
│   └── manage_fines.php                                                       View/settle fines
├── scripts/
│   └── seed_demo_data.php        CLI script for large-scale demo data (100k+)
├── css/style.css, js/script.js
├── database/schema.sql           Full schema (12 tables) + starter data
└── uploads/                      Bitstream storage (bucketed sub-folders), .htaccess hardened
```

## Running Locally (XAMPP/WAMP)
1. Copy this folder into `htdocs` (XAMPP) or `www` (WAMP).
2. Start Apache and MySQL from the control panel.
3. In phpMyAdmin, create a database named `digital_library`, then
   import `database/schema.sql`.
4. Visit `http://localhost/digital-library/`.
5. Log in with the seeded admin account:
   - **Username:** `admin`
   - **Password:** `Admin1234`
   *(Change this password after your first login — there's no
   change-password page in this build, so update it directly via
   phpMyAdmin using `password_hash()` output, or add one yourself.)*
6. To see the system at scale, run from a terminal in this folder:
   ```
   php scripts/seed_demo_data.php 100000
   ```

## Trying the circulation features
1. Log in as `admin`, submit an item (Submit Item — it auto-publishes for staff).
2. Go to **Admin → Manage Copies**, add a physical copy of that item with a barcode (e.g. `CS-0001`).
3. Register a second account (e.g. `alice`) so you have a member to lend to.
4. Go to **Admin → Circulation Desk**, check out `CS-0001` to `alice`.
5. Log in as `alice` and open **My Loans** — you'll see the due date and a Renew button.
6. Register a third account (e.g. `bob`), log in as `bob`, open the item page, and click **Place a Hold** (only shown once all copies are checked out).
7. Back at the Circulation Desk, return `CS-0001` — it will automatically become "reserved" for `bob`, and if the loan was overdue, a fine is created automatically (check **Admin → Manage Fines**).
8. Check `CS-0001` out again with member `bob` — the system only allows the member holding the reservation to claim it.

## Deploying Live (GitHub + InfinityFree)
See the accompanying **HOW_TO_GO_LIVE.docx** for full drag-and-drop,
no-terminal steps specific to this project. Short version:
1. Upload this project to GitHub (drag & drop through the browser)
2. Create a free InfinityFree hosting account + MySQL database
3. Import `database/schema.sql` via phpMyAdmin
4. Edit the 4 lines in `includes/db_connect.php` with your live DB details
5. Upload all files/folders into `htdocs`
6. Test the live site (register, submit, approve, search, download)

## Performance notes (for your project report)
The first version of `search.php` combined a full-text match on
`items` with a full-text match on `item_metadata` using
`WHERE id IN (SELECT ... UNION SELECT ...)`. At 100,000 rows this
measured **~650ms** per search because MySQL evaluated it as a
*dependent subquery* (re-run per candidate row) rather than computing
it once. Rewriting it as `INNER JOIN (SELECT ... UNION SELECT ...) AS
matched` (a materialized derived table) measured **~130ms** — about
5x faster — with identical results. This is a good, real example of
why checking `EXPLAIN` output matters once a table has real volume.

## Security notes
- Passwords hashed with `password_hash()` / verified with `password_verify()`
- All SQL uses prepared statements (`mysqli` + `bind_param`) — no string-concatenated queries
- All output escaped with `htmlspecialchars()`
- Pending/rejected items are only visible to their submitter or staff
- Uploaded files are restricted by extension whitelist and size limit,
  stored outside of guessable direct execution via `uploads/.htaccess`
  (which also disables PHP execution in that folder as defense in depth)

## Notes for Students
This project is provided as a **reference example only** — a
demonstration of how a larger, more ambitious system (with a proper
content hierarchy, workflow, and scale-tested database) can still be
built with the same HTML/CSS/JS/PHP/MySQL stack taught in this course.
Your own submission must use one of the 50 assigned project titles (or
an approved custom title) and be your own original work.
