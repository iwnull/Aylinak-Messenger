# 🚀 Aylinak Messenger (Multi-Room Edition)

A professional, lightweight, and secure PHP-based chat application designed for private communications. This messenger supports multiple rooms, file sharing, and voice notes without the need for a heavy SQL database.

---

## 🌟 Key Features | ویژگی‌های کلیدی

* **Multi-Room System:** Supports 5 independent, isolated chat rooms via 6-digit access codes.
* **No Database Required:** Uses high-performance Flat-file (JSON) storage with PHP security headers.
* **Media Support:** Send/Receive images, documents, and high-quality Voice Messages.
* **Modern GUI:** Clean Telegram-inspired interface with Full Dark Mode & Light Mode support.
* **Reply & Context:** Advanced message reply system for organized conversations.
* **Secure Auth:** Dual-layer protection (Room Code + Global Password).
* **Mobile Ready:** Fully responsive PWA-ready design for Android and iOS.

---

## 🛠️ Technical Overview | جزئیات فنی

* **Backend:** Pure PHP (Single file architecture for easy deployment).
* **Frontend:** Vanilla JS & CSS3 (No heavy frameworks like React/Vue).
* **Storage:** Secure `.php` JSON files with `die()` protection to prevent direct URL access to data.
* **Security:** Built-in XSS filtering, Session hijacking protection (User-Agent validation), and CSRF-resistant logic.

---

## 🚀 Installation | راهنمای نصب

1.  **Upload:** Upload `index.php` to your web server (Apache/Nginx).
2.  **Permissions:** Create a folder named `uploads` and set its permissions to `755` (or `777` depending on your host).
3.  **Config:** Open `index.php` and customize these variables at the top:
    * `$password`: Your global entry password.
    * `$allowed_rooms`: The list of 6-digit room codes.
4.  **SSL (Important):** For **Voice Messages** to work, your site **MUST** have an SSL certificate (HTTPS).

---

## 🇮🇷 توضیحات فارسی

**پیام‌رسان ایلیناک (نسخه چند اتاقه)** یک سیستم چت سبک و سریع بر پایه PHP است که برای گفتگوهای خصوصی و تیمی طراحی شده است.

### قابلیت‌های برجسته:
* **سیستم چند اتاقه:** پشتیبانی از ۵ اتاق مجزا با کدهای ورود اختصاصی.
* **عدم نیاز به دیتابیس:** ذخیره‌سازی هوشمند در فایل‌های JSON با لایه‌های امنیتی.
* **ارسال مالتی‌مدیا:** قابلیت ارسال عکس، فایل و پیام صوتی (Voice).
* **رابط کاربری مدرن:** طراحی الهام گرفته از تلگرام با قابلیت تغییر تم (تاریک/روشن).
* **امنیت بالا:** جلوگیری از دسترسی مستقیم به فایل‌های چت و مقابله با حملات XSS.

---

## 🛡️ License
Distributed under the **MIT License**. See `LICENSE` for more information.

## 👨‍💻 Developer
Created by **Iwnull** Follow me on Telegram: [@iwnull]
