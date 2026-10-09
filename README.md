# Portfolio Management System

A web-based Portfolio Management System developed as a BCA 4th-semester academic project.

The system helps users manage multiple Demat accounts, record their holdings and total investments, and access company and IPO/news information.

> **Note:** This system is for portfolio record management and academic purposes. It does not provide live market data, trading, or real-time portfolio valuation.

## Features

- User registration and authentication
- Multiple Demat account management
- Holdings management
- Total investment tracking
- Company search and selection
- IPO and stock-market news
- Administrative management
- Company CSV import
- RSS-based news import

## Technology

- PHP
- MySQL / MariaDB
- HTML
- CSS
- Vanilla JavaScript
- XAMPP

No frontend or backend framework is used.

## Status

🚧 **Under Development**

This project is currently being developed as part of a BCA 4th-semester academic project.

## Scheduled News Import

Set `RSS_FEED_URL` in the local `.env` file, then configure Windows Task Scheduler to run this command daily at midnight:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\portfolio-management-system-v2\scripts\import-news.php
```

The command imports new entries as pending and skips duplicates. The administrator must publish pending entries from `news_admin.php`.

## License

This project is developed for academic purposes.