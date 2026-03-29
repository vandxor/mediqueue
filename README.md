# 🏥 MediQueue v3 — Clinical Queue Management System

A real-time hospital queue management system built with PHP, MySQL, and JavaScript.
Designed for multi-department clinics to manage patient flow efficiently.

---

## 📌 Features

- Patient self-registration with department selection
- Real-time live queue board — no page refresh needed (AJAX polling)
- Multi-department support — General, Ortho, Gynae, Paeds, Cardiology, Surgery
- Priority queue for emergency patients — jumps to top automatically
- Separate token numbers per department per day (GEN-001, ORT-001 etc.)
- Browser push notifications — alerts patient when turn is near
- Doctor login system — each doctor sees only their department
- Admin login — sees all departments + global emergency tab
- Estimated wait time calculation
- Secure — prepared statements, session auth, XSS protection

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Backend | PHP 8.2 |
| Database | MySQL (via mysqli) |
| Server | Apache (XAMPP) |
| Real-time | AJAX polling via Fetch API |
| Notifications | Web Notifications API + Web Audio API |
| Remote access | ngrok (for phone notifications over HTTPS) |

---

## 📁 File Structure
```
mediqueue_v3/
│
├── db.php              → Database connection
├── auth.php            → Session management and login helpers
├── setup.sql           → Database schema and default data
│
├── index.php           → Patient registration form
├── add_patient.php     → Processes registration, assigns token
├── queue.php           → Live patient-facing queue board
├── api_queue.php       → JSON API polled every second by JavaScript
│
├── login.php           → Doctor / staff login page
├── logout.php          → Destroys session
├── admin.php           → Doctor and admin dashboard
│
├── delete_patient.php  → Remove patient from queue
├── next_patient.php    → Mark patient as done
│
└── style.css           → Master stylesheet (dark medical theme)
```

---

## ⚙️ Setup Instructions

### 1. Requirements
- XAMPP (Apache + MySQL + PHP 8.2)
- A modern browser (Chrome recommended)

### 2. Installation
1. Copy the `mediqueue_v3` folder to:
```
   C:\xampp\htdocs\mediqueue_v3\
```

2. Start **Apache** and **MySQL** in XAMPP Control Panel

3. Open phpMyAdmin:
```
   http://localhost/phpmyadmin
```

4. Click the **SQL** tab and paste the contents of `setup.sql` → click **Go**

5. Open the app:
```
   http://localhost/mediqueue_v3/index.php
```

---

## 🔐 Default Login Credentials

| Username | Password | Access |
|----------|----------|--------|
| `admin` | `admin123` | All departments + emergencies |
| `general` | `general123` | General / OPD only |
| `ortho` | `ortho123` | Orthopaedics only |
| `gynae` | `gynae123` | Gynaecology only |
| `paeds` | `paeds123` | Paediatrics only |
| `cardio` | `cardio123` | Cardiology only |
| `surgery` | `surgery123` | Surgery only |

---

## 📱 Phone Notifications Setup

Notifications require HTTPS. Use ngrok for local testing:

1. Download ngrok from https://ngrok.com
2. Run:
```
   ngrok http 80
```
3. Copy the `https://` link and open it on your phone
4. Open `queue.php` → select department → enter token number → tap **Notify Me**
5. Allow notifications when prompted

---

## 🔄 How It Works
```
Patient → index.php → selects dept + fills form → add_patient.php
       → gets token (e.g. ORT-002) → queue.php to track position

Doctor → login.php → admin.php → sees own dept queue
       → clicks Done → next patient called → queue updates live

api_queue.php → called every 1 second by JS → returns JSON
             → JS updates UI + fires notification if turn is near
```

---

## 🚨 Priority Queue Logic

- Emergency patients get sorted to top automatically
- SQL ORDER BY: `CASE WHEN priority='emergency' THEN 0 ELSE 1 END ASC`
- Token numbers are still sequential (1, 2, 3) — no special prefix
- Emergency rows highlighted red in doctor dashboard
- Separate emergency tab visible to all doctors and admin

---

## 🔒 Security

- All DB queries use prepared statements (prevents SQL injection)
- All output uses `htmlspecialchars()` (prevents XSS)
- Doctor pages protected by PHP session (`requireLogin()`)
- Patient ID cast to `int` before delete queries
- Passwords stored as plain text (suitable for college demo — use bcrypt in production)

---

## 👩‍💻 Developer

Built as a college project — MCA / BCA Final Year  
Stack: PHP + MySQL + Vanilla JS + CSS  
Local server: XAMPP  
```

---
