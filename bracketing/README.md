# TOURNIVOX Bracketing Manager

This folder contains the complete tournament/bracketing workflow ported into TOURNIVOX. It uses TOURNIVOX branding, the central `tournivox` database, and the central TOURNIVOX user accounts.

## Included

- Bracket Admin dashboard
- Tournament create/edit/view/list
- Team registration and approval
- Player/roster management
- Manual registration-fee confirmation
- Random/manual/auto seeding controls
- Single Elimination
- Double Elimination
- Round Robin / Point-Based standings
- BO1 / BO2 / BO3 / BO5 / BO7 round configuration
- Match scheduling and series scoring
- TOURNIVOX Round Robin BO2 scoring (2-0 win, 1-1 draw, 0-2 loss)
- Automatic winner advancement
- Automatic tournament champion finalization
- Format/stage manager
- Announcements
- Notifications
- Reports
- Activity logs
- User/profile/password management needed by the bracket module
- 14 sample teams in the master SQL

## Not Included

No broadcasting, stream-control, OBS, scoreboard broadcast overlay, draft overlay, lower-third, sponsor, or other broadcast module from the older project is included in this port.

## Setup with XAMPP

1. Put the `Tournivox` folder inside `htdocs`.
2. Start Apache and MySQL.
3. Import `Tournivox/database/tournivox.sql` in phpMyAdmin.
4. Open `http://localhost/Tournivox/auth/login.php`.
5. Sign in as the Bracket Administrator.

Optional setup page: `http://localhost/Tournivox/bracketing/install.php`

- Setup unlock password: `TournivoxSetup@2026`

### Default Bracket Administrator

- Username: `bracketadmin`
- Password: `TournivoxBracketAdmin@2026`

The password is stored as an Argon2id hash.

## Important database note

The master SQL preserves the central `users` table but rebuilds the development bracketing/tournament tables so they exactly match this complete module. Back up any bracket data you want to keep before re-importing it.
