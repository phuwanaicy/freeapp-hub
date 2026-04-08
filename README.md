# 🛡️ FreeApp HUB
**Secure Access Platform** — เข้าถึง Cookie / Session ของแอปสตรีมมิ่งชั้นนำผ่าน Web UI ที่สวยงาม

![FreeApp HUB](https://img.shields.io/badge/FreeApp-HUB-8b5cf6?style=for-the-badge&logo=shield&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-22d3ee?style=for-the-badge)

---

## ✨ Features

- 🎬 รองรับแอปสตรีมมิ่งกว่า **15+ แอป** (Netflix, YouTube, Prime Video, HBO Max, Viu, WeTV, iQIYI, Bilibili, YOUKU, Crunchyroll, Dramabox, Melolo, Flickreels, Netshort, iflix)
- 🔑 ระบบ **Demo Key** อัตโนมัติ — ไม่ต้องสมัครสมาชิก ใช้ได้เลย
- 🧠 **IP Session** — จำการล็อกอินไว้ตาม IP อัตโนมัติ (ไม่ต้องล็อกอินซ้ำ)
- 🎭 **Fingerprint Randomizer** — สร้าง Device ID ใหม่ทุกครั้งเพื่อหลีกเลี่ยง Duplicate
- 📦 รับ **Cookie JSON** พร้อม Download ได้ทันที
- 🌌 UI ธีม **Purple Void** สวยงามพร้อม Animated Star Canvas
- 📱 Responsive รองรับทั้ง Desktop และ Mobile

---

## 📁 โครงสร้างไฟล์

```
freeapp-hub/
├── index.html      # Frontend — UI หลักทั้งหมด (Single File)
├── api.php         # Backend — API Proxy + IP Session Manager
├── log.txt         # Auto-generated — เก็บ IP Sessions (อย่า commit ไฟล์นี้)
└── README.md
```

---

## ⚙️ Requirements

| Component | เวอร์ชันขั้นต่ำ |
|-----------|--------------|
| PHP       | 7.4+         |
| Extension | `curl`, `json` |
| Web Server | Apache / Nginx / PHP Built-in |

---

## 🚀 Installation

### วิธีที่ 1 — PHP Built-in Server (Development)

```bash
git clone https://github.com/YOUR_USERNAME/freeapp-hub.git
cd freeapp-hub
php -S localhost:8000
```
แล้วเปิด `http://localhost:8000/index.html`

### วิธีที่ 2 — Apache / Nginx (Production)

1. อัปโหลดไฟล์ทั้งหมดขึ้น Web Hosting
2. ตั้ง Document Root ชี้ไปที่โฟลเดอร์โปรเจกต์
3. ให้ PHP มีสิทธิ์เขียนไฟล์ `log.txt` ในโฟลเดอร์เดียวกัน

```bash
chmod 755 .
chmod 644 api.php index.html
touch log.txt && chmod 666 log.txt
```

---

## 🔧 Configuration

แก้ค่าคงที่ในไฟล์ `api.php`:

```php
define('SECRET_KEY', 'OTP24HRHUB_PROTECT');  // XOR Decode Key
define('API_BASE',   'https://...');           // Upstream API URL
define('MAX_USES',   5);                       // จำนวนครั้งสูงสุดต่อ IP (Demo)
```

---

## 🛡️ API Endpoints

`api.php` รับ GET parameter `?action=` ดังนี้:

| Action | Method | คำอธิบาย |
|--------|--------|----------|
| `check_session` | GET | ตรวจสอบ session ของ IP นี้ |
| `create_demo` | POST | สร้าง Demo License Key ใหม่อัตโนมัติ |
| `login` | POST | ล็อกอินด้วย License Key + ดึงรายชื่อแอป |
| `get_nodes` | POST | ดึงรายการเซิร์ฟเวอร์ของแอปที่เลือก |
| `get_cookie` | POST | ดึง Cookie JSON + นับจำนวนการใช้งาน |
| `debug_demo` | GET | Debug raw response จาก upstream API |

---

## 🔒 Security Notes

- ไฟล์ `log.txt` เก็บข้อมูล IP + License Key — **อย่า commit** ขึ้น Git
- เพิ่ม `.gitignore` เพื่อป้องกัน:

```gitignore
log.txt
*.log
```

- พิจารณาเพิ่ม Rate Limiting ฝั่ง Nginx/Apache หากใช้ใน Production

---

## 📄 License

MIT License — ใช้ได้อย่างอิสระ, แก้ไขได้, แต่ต้องเก็บ Attribution ไว้

---

> Made with 💜 — FreeApp HUB
