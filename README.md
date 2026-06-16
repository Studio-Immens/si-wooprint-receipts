# SI Print Receipts — POS Receipt & Thermal Printer for WooCommerce

[![WordPress.org](https://img.shields.io/badge/WordPress.org-si--wooprint--receipts-blue)](https://wordpress.org/plugins/si-wooprint-receipts)
[![Landing Page](https://img.shields.io/badge/Landing_Page-studioimmens.com-blue)](https://studioimmens.com/si-wooprint-receipts)
[![Version](https://img.shields.io/badge/version-1.0.0-blue)]()
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-brightgreen)]()
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)]()
[![License](https://img.shields.io/badge/license-GPL_v3-green)](https://www.gnu.org/licenses/gpl-3.0.html)

> Browser-based thermal receipt printing for WooCommerce orders.

---

## 🔗 Links

- 🌐 **Landing Page:** [studioimmens.com/si-wooprint-receipts](https://studioimmens.com/si-wooprint-receipts)
- 📦 **WordPress.org:** [wordpress.org/plugins/si-wooprint-receipts](https://wordpress.org/plugins/si-wooprint-receipts)
- ⭐ **PRO Version:** [studioimmens.com/si-wooprint-receipts-pro](https://studioimmens.com/si-wooprint-receipts-pro)
- 💬 **Support:** [info@studioimmens.com](mailto:info@studioimmens.com)

---

## Overview

SI Print Receipts adds thermal receipt printing capability to WooCommerce. Print receipts directly from the browser for any order — ideal for retail shops, restaurants, and any business that needs physical receipts at the point of sale.

---

## Features

### 🖨️ Browser-Based Printing
- Print receipts directly from the WooCommerce order screen
- No additional software required for basic printing
- Works with any printer connected to the POS computer

### 📋 Receipt Templates
- Customizable receipt layout with HTML/CSS templates
- Include order details, customer info, payment method, and more
- Custom logo and store information

### ⚡ Quick Actions
- One-click print from the order list
- Bulk print for multiple orders
- Print on order status change

### 🎨 Customizable Design
- Custom receipt width and formatting
- Configurable header and footer
- Color and font customization

---

## PRO Version

Upgrade to [WooPrint Receipts PRO](https://studioimmens.com/si-wooprint-receipts-pro) for:

- **ESC/POS Network Printing** — direct thermal printer support over TCP/IP
- **USB Print Node** — USB and serial printer support
- **Advanced Rules** — filter by product categories, tags, and specific products
- **Cash Drawer Support** — automatic cash drawer kick-out
- **Barcode on Receipts** — print barcodes with product SKUs
- **Automatic Updates** — built-in license and update system

---

## Installation

### Via WordPress.org
1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "SI Print Receipts"
3. Click **Install Now** and then **Activate**

### Manual Installation
```bash
git clone https://github.com/Studio-Immens/si-wooprint-receipts.git
```

1. Upload the `si-wooprint-receipts` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **WooCommerce → Settings → Print Receipts** to configure

---

## Usage

### Print a Receipt
1. Go to **WooCommerce → Orders**
2. Hover over any order and click **Print Receipt**
3. The receipt opens in a new window — use your browser's print dialog (Ctrl+P)

### Configure Receipt Template
1. **WooCommerce → Settings → Print Receipts**
2. Customize the store logo, header text, and footer
3. Choose which order fields to display
4. Set receipt width and font size

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| WooCommerce | 5.0+ |

---

## Architecture

```
si-wooprint-receipts/
├── si-wooprint-receipts.php        # Bootstrap
├── includes/                        # Core logic
├── templates/                       # Receipt HTML templates
├── assets/                          # CSS/JS
├── img/                             # Images
├── languages/                       # Translation-ready
└── uninstall.php                    # Cleanup on uninstall
```

---

## License

GPL v3 or later — see [LICENSE](LICENSE).

---

Built by [Studio Immens](https://studioimmens.com) — WordPress & AI integration specialists.
