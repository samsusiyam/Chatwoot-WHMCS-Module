# Chatwoot Live Chat & CRM for WHMCS

A complete, production-ready WHMCS Addon Module by **Bahari IT** that seamlessly integrates **Chatwoot Live Chat** into your WHMCS website and client area, automatically syncs logged-in clients, supports HMAC identity security, and embeds a real-time **WHMCS CRM Dashboard App** inside the Chatwoot Agent panel.

---

## 🚀 Key Features

1. **Automatic Live Chat Widget**:
   - Injects the Chatwoot live chat widget into WHMCS client area and public pages via hooks.
   - Compatible with `app.chatwoot.com` (Cloud) and any self-hosted Chatwoot server.
2. **Logged-in Client Auto-Identification**:
   - Automatically passes client's **Name**, **Email**, **Phone Number**, **Gravatar Avatar**, and **Client ID** to Chatwoot.
   - Prevents clients from having to re-enter their contact details.
3. **HMAC Identity Verification**:
   - Supports SHA-256 HMAC identity validation to prevent identity spoofing / user impersonation in Chatwoot.
4. **Live Custom Attributes Sync**:
   - Syncs Account Status, Currency, Active Services Count, Active Domains Count, Unpaid Invoices, and Credit Balance into Chatwoot contact attributes.
5. **Chatwoot Agent CRM Dashboard App (Sidebar CRM)**:
   - Enables support agents in Chatwoot to view live WHMCS customer data right beside their chat conversation:
     - 👤 Client Profile (Status, Balance, Phone, ID)
     - 🖥️ Active Services & Next Due Dates
     - 💳 Recent Invoices (Paid / Unpaid)
     - 🎫 Support Tickets
     - ⚡ 1-Click Quick Actions: "Open WHMCS Profile", "Login as Client"
6. **Visibility Controls**:
   - Option to display chat to **All Visitors** (Guests & Clients) or **Logged-in Clients Only**.
   - Widget position control (Bottom Right / Bottom Left).

---

## 📂 Installation Guide

1. Upload the `chatwoot` folder into your WHMCS installation:
   ```text
   /your_whmcs_root/modules/addons/chatwoot/
   ```
2. Log in to your **WHMCS Admin Area**.
3. Go to **System Settings** (or Setup ⚙️) ➔ **Addon Modules** (URL: `/admin/configaddonmods.php`).
4. Find **Chatwoot Live Chat & CRM** and click **Activate**.
5. Click **Configure**:
   - **Chatwoot Base URL**: Enter `https://app.chatwoot.com` or your self-hosted Chatwoot URL.
   - **Website Inbox Token**: Enter your Website Inbox token from Chatwoot (Settings &rarr; Inboxes &rarr; Website Channel).
   - **Identity Validation HMAC Secret**: (Optional) Enter the HMAC secret token configured in Chatwoot.
   - **Access Control**: Check **Full Administrator** (or your administrator role).
6. Click **Save Changes**.

---

## ⚙️ How to Setup Chatwoot Agent CRM Dashboard App

1. Go to **Addons ➔ Chatwoot Live Chat & CRM** in your WHMCS Admin Area.
2. Copy your **Dashboard App URL**:
   ```text
   https://your-whmcs.com/modules/addons/chatwoot/api.php?secret=YOUR_SECRET_KEY&email={{contact.email}}
   ```
3. Open your **Chatwoot Dashboard** (`app.chatwoot.com` or self-hosted).
4. Go to **Settings ➔ Dashboard Apps ➔ Add New App**.
5. Enter:
   - **App Name**: `WHMCS CRM`
   - **Endpoint URL**: Paste the URL copied from step 2.
6. Click **Save**.

Now, whenever an agent opens a conversation with any customer in Chatwoot, the right-hand **Dashboard Apps** panel will instantly display the customer's full WHMCS profile, services, and invoices!

---

## 📁 File Structure

```text
modules/addons/chatwoot/
├── chatwoot.php              # Main Module Entrypoint & Admin Settings Overview
├── hooks.php                 # Client Area Footer Hook (Widget & User Sync)
├── api.php                   # Secure Chatwoot Agent CRM Iframe Endpoint
├── whmcs.json                # WHMCS 8+ Apps & Integrations Manifest
├── logo.png                  # Module Logo
├── index.php                 # Security Redirect
└── README.md                 # Setup & Documentation
```

---

## 👨‍💻 Author & Support

- **Author**: Bahari IT
- **License**: Proprietary / GNU GPLv3
