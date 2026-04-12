# 🏙️ CitySync — Intelligent Urban Complaint Routing System

A hackathon-ready municipal complaint management platform with AI classification,
multilingual support, anonymous users, and department routing.

---

## 📁 Project Structure

```
citysync/
├── frontend/
│   ├── index.html          ← Citizen complaint submission form
│   ├── dashboard.html      ← Public complaint tracker
│   ├── officer.html        ← Officer login + management portal
│   ├── css/
│   │   └── style.css       ← All styles
│   └── js/
│       └── app.js          ← Shared JS utilities
│
├── backend/
│   ├── config.php          ← DB credentials + OpenAI key ← EDIT THIS
│   ├── db.php              ← PDO connection + user helpers
│   ├── ai_classification.php ← OpenAI API + keyword fallback
│   ├── submit_complaint.php  ← POST: submit + classify
│   ├── get_complaints.php    ← GET: fetch complaints
│   ├── update_status.php     ← POST: update complaint status
│   └── officer_login.php     ← POST: officer authentication
│
├── database/
│   └── schema.sql          ← Run this first!
│
└── uploads/                ← Auto-created, stores complaint images
```

---

## ⚙️ Setup Instructions

### Step 1 — Requirements

Make sure you have:
- **PHP 8.0+** (with cURL and PDO MySQL extensions enabled)
- **MySQL 5.7+** or MariaDB
- A web browser (Chrome recommended for voice input)

---

### Step 2 — Database Setup

1. Open **phpMyAdmin** or your MySQL client (terminal/Workbench)
2. Run the contents of `database/schema.sql`

**Via terminal:**
```bash
mysql -u root -p < database/schema.sql
```

**Via phpMyAdmin:**
- Go to phpMyAdmin → Import → select `schema.sql` → Execute

This creates the `citysync` database with all tables and seeds default officer accounts.

---

### Step 3 — Configure Backend

Open `backend/config.php` and update:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'citysync');
define('DB_USER', 'root');       // ← Your MySQL username
define('DB_PASS', '');           // ← Your MySQL password

define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE'); // ← Your OpenAI key
```

> **Don't have an OpenAI key?** No problem — the system has a built-in keyword-based
> fallback classifier that works without any API key. It handles English + basic Hindi/Marathi.

---

### Step 4 — Run the PHP Server

**Option A: Using XAMPP / WAMP / MAMP**
1. Copy the entire `citysync/` folder into your `htdocs` (XAMPP) or `www` (WAMP) folder
2. Start Apache + MySQL from the control panel
3. Open: `http://localhost/citysync/frontend/index.html`

**Option B: PHP Built-in Server (easiest for development)**
```bash
# From the citysync/ root folder:
php -S localhost:8000

# Then open:
# http://localhost:8000/frontend/index.html
```

**Option C: VS Code + PHP Server extension**
- Install "PHP Server" extension in VS Code
- Right-click `frontend/index.html` → "PHP Server: Serve Project"

---

### Step 5 — Open the App

| Page | URL | Purpose |
|------|-----|---------|
| Submit Complaint | `/frontend/index.html` | Citizens file complaints |
| My Complaints | `/frontend/dashboard.html` | Track all complaints |
| Officer Portal | `/frontend/officer.html` | Department officer login |

---

## 🔐 Officer Login Credentials

All officers use the password: `officer123`

| Username | Department |
|----------|------------|
| `pwd_officer` | PWD (Roads) |
| `sanitation_officer` | Sanitation (Garbage) |
| `water_officer` | Water Department |
| `electricity_officer` | Electrical Department |
| `general_officer` | General |

---

## 🤖 AI Classification

The AI reads the complaint text and returns:
```json
{
  "category": "Road | Garbage | Water | Electricity | Other",
  "priority": "High | Medium | Low"
}
```

**With OpenAI API key:** Uses GPT-3.5-turbo — supports all Indian languages perfectly.
**Without API key (fallback):** Keyword-based classifier handles English + Hinglish.

### Category → Department Routing

| Category | Department |
|----------|------------|
| Road | PWD |
| Garbage | Sanitation |
| Water | Water Department |
| Electricity | Electrical Department |
| Other | General |

---

## 🌍 Supported Languages

- English
- Hindi (Devanagari + Romanized/Hinglish)
- Marathi
- Mixed language input

---

## 🎤 Voice Input

- Uses browser's built-in **SpeechRecognition API**
- Best supported in **Google Chrome**
- Set to `hi-IN` language (understands both Hindi and English)
- Click the 🎤 button → speak → text fills the complaint box

---

## 🔍 Duplicate Detection

If a complaint with **≥70% text similarity** is submitted for the **same location** within 7 days,
it is automatically flagged as a duplicate using PHP's `similar_text()` function.

---

## 📸 Image Upload

- Supported formats: JPG, JPEG, PNG, GIF, WebP
- Max size: 5MB
- Stored in `uploads/` directory (auto-created)

---

## 🗃️ Database Tables

### `users`
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| email | VARCHAR | User's real email (hidden) |
| anonymous_id | VARCHAR | Public ID like "User#1234" |

### `complaints`
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| user_id | INT FK | References users.id |
| description | TEXT | Complaint text |
| image_path | VARCHAR | Relative path to uploaded image |
| category | VARCHAR | Road/Garbage/Water/Electricity/Other |
| priority | VARCHAR | High/Medium/Low |
| department | VARCHAR | Assigned department |
| status | VARCHAR | Pending/In Progress/Resolved |
| location | VARCHAR | User-entered location |
| is_duplicate | TINYINT | 1 if duplicate |
| duplicate_of | INT | ID of original complaint |

### `officers`
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| username | VARCHAR | Login username |
| password | VARCHAR | Bcrypt hash |
| department | VARCHAR | Officer's department |

---

## 🐛 Troubleshooting

**"Database connection failed"**
→ Check MySQL is running and credentials in `config.php` are correct.

**"Cannot connect to server"**
→ Make sure PHP server is running and URLs in HTML match your setup.
→ If using file:// protocol directly, AJAX won't work — use a PHP server.

**Voice input not working**
→ Use Chrome browser. Allow microphone permission when prompted.

**Images not uploading**
→ Ensure `uploads/` folder exists and is writable (`chmod 755 uploads/`).

**OpenAI returning errors**
→ Check your API key. The keyword fallback activates automatically.

---

## 🚀 Hackathon Bonus Tips

1. **Add Google Maps**: Use Google Maps JavaScript API to show complaint markers
2. **SMS Alerts**: Add Twilio integration in `submit_complaint.php`
3. **Export**: Add CSV export in `get_complaints.php`
4. **Analytics**: Add charts using Chart.js on the dashboard

---

Built with ❤️ for smart city governance.
