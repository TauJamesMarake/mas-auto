# 🚗 MAS Auto — Ride With Confidence

Professional automotive workshop website for MAS Auto, Polokwane, South Africa.

---

## 📋 Overview

| | |
|---|---|
| **Business** | MAS Auto |
| **Location** | Polokwane Space Park, 22 Doloriet Street, Ladanna |
| **Phone** | 060 756 0744 |
| **Hours** | Mon–Fri 08:00–17:30 · Sat 08:00–13:00 |
| **Stack** | HTML · CSS · Vanilla JS · PHP · MySQL |
| **Hosting** | HostAfrica (Apache, shared hosting) |
| **Email** | PHPMailer + Resend SMTP |

---

## 📁 Project Structure

```
mas-auto/                          ← Git repo root
│
├── .gitignore                     ← Excludes .env, vendor/, logs
├── .env.example                   ← Credential template (commit this, not .env)
├── .htaccess                      ← HTTPS redirect, security headers, Gzip, caching
├── robots.txt                     ← Search engine crawl rules
├── sitemap.xml                    ← SEO sitemap
├── README.md
│
├── index.html                     ← Homepage
│
├── pages/
│   ├── about.html                 ← Workshop story, team, brand logos
│   ├── services.html              ← All 6 services with scroll anchors
│   ├── gallery.html               ← Filterable image grid + lightbox
│   └── contact.html               ← Contact form + booking form + map
│
├── css/
│   ├── global.css                 ← CSS variables, navbar, footer, animations
│   ├── home.css                   ← Hero, counter, marquee, service cards
│   ├── about.css                  ← Story, pillars, stats, team
│   ├── services.css               ← Service detail layout, nav strip
│   ├── gallery.css                ← Filter, grid, lightbox
│   └── contact.css                ← Contact strip, forms, map, booking
│
├── js/
│   ├── global.js                  ← Hamburger nav, scroll-shrink, scroll-reveal, toast
│   ├── gallery.js                 ← Filter logic, lightbox, keyboard nav
│   └── contact.js                 ← Form validation, POST to PHP, WhatsApp fallback
│
├── assets/
│   ├── logo/
│   │   ├── mas-logo.svg           ← Primary logo (SVG)
│   │   ├── mas-logo-dark.png      ← For light backgrounds
│   │   └── favicon.ico            ← Browser tab icon
│   └── images/
│       ├── hero-bg.jpg            ← Homepage hero background
│       ├── og-image.jpg           ← Social share preview (1200×630px)
│       ├── workshop/              ← Gallery: workshop shots
│       ├── engine/                ← Gallery: engine work
│       ├── audio/                 ← Gallery: sound system installs
│       └── team/                  ← About page: staff photos
│
└── api/
    ├── .htaccess                  ← Blocks direct access to db.php, mailer.php, schema.sql
    ├── db.php                     ← MySQL connection via .env (singleton)
    ├── mailer.php                 ← PHPMailer + Resend SMTP (3 email functions)
    ├── booking.php                ← POST endpoint: CORS, rate limit, honeypot, whitelist
    ├── contact.php                ← POST endpoint: CORS, rate limit, spam filter
    └── schema.sql                 ← Run once in phpMyAdmin to create tables
```

> **Note:** `.env` and `vendor/` live **above** `public_html` on the server — they are never committed to Git and never web-accessible.

---

## 🔒 Security Measures

| Layer | Implementation |
|---|---|
| Credentials | `.env` above web root, loaded via `vlucas/phpdotenv` |
| CORS | Locked to `SITE_URL` domain only — no wildcard |
| Rate limiting | 5 bookings / 10 messages per IP per hour (MySQL-based) |
| Honeypot | Hidden `website` field — bots fill it, real users don't |
| Input validation | Server-side: length limits, regex, date checks, email validation |
| Service whitelist | Only the 7 defined services are accepted |
| Spam filter | Keyword filter + URL count limit on contact messages |
| SQL injection | Prepared statements throughout — no string concatenation |
| File access | `.htaccess` blocks `db.php`, `mailer.php`, `schema.sql` from browser |
| HTTPS | Root `.htaccess` forces 301 redirect on all HTTP traffic |
| Security headers | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` |

---

## 🚀 Deployment

### First-time setup on HostAfrica

```bash
# 1. SSH into server or use cPanel Terminal
# 2. Navigate above public_html
cd ~

# 3. Create .env from example
cp public_html/.env.example .env
nano .env   # fill in real credentials

# 4. Install Composer dependencies
cd ~/public_html
composer require vlucas/phpdotenv phpmailer/phpmailer

# 5. Run schema.sql
# cPanel → phpMyAdmin → select database → SQL tab → paste schema.sql → Go
```

### Pushing updates

```bash
git add .
git commit -m "Your message"
git push origin main
# Then pull on server: git pull origin main
```

### What NEVER goes to GitHub
- `.env` (real credentials)
- `vendor/` (Composer packages — regenerated with `composer install`)

---

## 🎨 Design Tokens

| Token | Value |
|---|---|
| Primary red | `#C8102E` |
| Background | `#080808` |
| Card | `#141414` |
| Silver | `#B0B0B0` |
| Heading font | Barlow Condensed |
| Body font | Exo 2 |

---

## 📄 License

All rights reserved. © 2026 MAS Auto  
Developed by Tau J. Marake