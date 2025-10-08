# 📘 ChatGPT Async Proxy (PHP, SQLite)

---

## 🇬🇧 English

### 🔧 ChatGPT Async Proxy

This project is a **lightweight asynchronous proxy** for OpenAI’s ChatGPT API written in **pure PHP**.
It lets you send a prompt, receive a request ID, and **fetch the result later** — ideal for long tasks, background jobs, or backend integrations.

---

### 🚀 Features

* ✅ Async request → ID → result flow
* ✅ SQLite storage (no external DB)
* ✅ HMAC SHA256 signature validation
* ✅ Configurable API key (file or env)
* ✅ Per-IP rate limiting
* ✅ Worker with retries and TTL
* ✅ PowerShell client for Windows
* ✅ Daemon mode (`php index.php daemon`)

---

### 📁 Project structure

```
chatgpt-proxy/
├─ index.php             # Main proxy script
├─ .env.example.php      # Example configuration
├─ .env.php              # Real config (ignored by git)
├─ requests.sqlite       # SQLite database
├─ send-request.ps1      # Example PowerShell client
└─ logs/                 # Worker logs
```

---

### ⚙️ Setup

1. Install **PHP 8.1+** with `pdo_sqlite` and `curl`.
2. Copy config:

   ```bash
   cp .env.example.php .env.php
   ```
3. Edit `.env.php` and add your **OpenAI API key**.

---

### ▶️ Running the proxy

Start the server:

```bash
php -S localhost:8000
```

Run worker once:

```bash
php index.php worker
```

Run worker continuously:

```bash
php index.php daemon
```

---

### 🧪 Sending a request

Run the included PowerShell client:

```powershell
.\send-request.ps1
```

It will:

1. Generate an HMAC signature
2. Send the request
3. Poll the result
4. Print the final ChatGPT response

---

### 🛠️ API Endpoints

| Endpoint                             | Method | Description              |
| ------------------------------------ | ------ | ------------------------ |
| `POST /index.php?action=request`     | POST   | Create a new request     |
| `GET /index.php?action=result&id=ID` | GET    | Get result by request ID |
| `GET /index.php?action=worker`       | GET    | Manually trigger worker  |
| `GET /index.php?action=health`       | GET    | Health check             |

---

### 🔐 Security

* All requests must include the `X-Proxy-Key` header.
* Request body must include `sign` (HMAC SHA256).

---

### 📄 License

MIT — free to use, modify, and distribute.

---

## 🇷🇺 Русский

### 🔧 Асинхронный прокси для ChatGPT

Проект — это **лёгкий асинхронный прокси-сервер** для ChatGPT API на **чистом PHP**.
Позволяет отправить запрос, получить ID и **получить результат позже** — удобно для долгих задач, очередей и интеграций.

---

### 🚀 Возможности

* ✅ Асинхронный цикл: запрос → ID → результат
* ✅ SQLite без внешней БД
* ✅ Проверка подписи (HMAC SHA256)
* ✅ API-ключ из файла или окружения
* ✅ Лимитирование по IP
* ✅ Воркеры с ретраями и TTL
* ✅ PowerShell-клиент для Windows
* ✅ Демон-режим (`php index.php daemon`)

---

### 📁 Структура проекта

```
chatgpt-proxy/
├─ index.php             # Основной прокси-скрипт
├─ .env.example.php      # Пример конфигурации
├─ .env.php              # Настоящий конфиг (в .gitignore)
├─ requests.sqlite       # База SQLite
├─ send-request.ps1      # Клиент на PowerShell
└─ logs/                 # Логи воркера
```

---

### ⚙️ Установка

1. Установите **PHP 8.1+** с расширениями `pdo_sqlite` и `curl`.
2. Скопируйте конфиг:

   ```bash
   cp .env.example.php .env.php
   ```
3. Отредактируйте `.env.php` и добавьте свой ключ OpenAI.

---

### ▶️ Запуск прокси

Старт сервера:

```bash
php -S localhost:8000
```

Однократный запуск воркера:

```bash
php index.php worker
```

Запуск воркера в фоне:

```bash
php index.php daemon
```

---

### 🧪 Отправка запроса

Запустите PowerShell-клиент:

```powershell
.\send-request.ps1
```

Он:

1. Генерирует HMAC-подпись
2. Отправляет запрос
3. Проверяет статус
4. Выводит результат ChatGPT

---

### 🛠️ API Эндпоинты

| Endpoint                             | Метод | Описание                   |
| ------------------------------------ | ----- | -------------------------- |
| `POST /index.php?action=request`     | POST  | Создание нового запроса    |
| `GET /index.php?action=result&id=ID` | GET   | Получение результата по ID |
| `GET /index.php?action=worker`       | GET   | Ручной запуск обработки    |
| `GET /index.php?action=health`       | GET   | Проверка состояния         |

---

### 🔐 Безопасность

* Все запросы должны содержать заголовок `X-Proxy-Key`.
* Тело запроса должно содержать валидную подпись `sign` (HMAC SHA256).

---

### 📄 Лицензия

MIT — свободное использование, изменение и распространение.

---

### 🌐 Language switch

* 🇬🇧 English — scroll to top
* 🇷🇺 Русский — scroll below
