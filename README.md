# Bulk Campaign – WhatsApp Chat Panel

A multi-tenant **Wati-style WhatsApp chat panel** built with **Node.js + React**. Admins create user accounts with Meta WhatsApp API credentials and webhook configuration. Users log in to a full chat interface to manage contacts, send messages, create templates, and receive real-time inbound messages.

## Architecture

```
┌─────────────────────────────────────┐
│   Frontend (React + Vite + Tailwind)│
│   - User Panel  (/)                 │
│   - Admin Panel (/admin)            │
└──────────────┬──────────────────────┘
               │ HTTP + WebSocket
┌──────────────▼──────────────────────┐
│   Backend (Node.js + Express)       │
│   - JWT Auth (admin + user)         │
│   - Multi-tenant data isolation     │
│   - Socket.IO real-time events      │
└──┬────────┬────────┬────────┬───────┘
   │        │        │        │
┌──▼──┐ ┌──▼──┐ ┌──▼──┐ ┌──▼────────┐
│Mongo│ │Redis│ │Cloud│ │Meta Cloud  │
│ DB  │ │     │ │inary│ │API (v21.0) │
└─────┘ └─────┘ └─────┘ └────────────┘
```

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Node.js, Express, Socket.IO |
| Database | MongoDB (Mongoose) |
| Cache | Redis |
| Media | Cloudinary |
| API | Meta WhatsApp Cloud API |
| Frontend | React 18, Vite, TailwindCSS |
| Auth | JWT (admin + user roles) |
| Deployment | Render (backend) + Vercel (frontend) |

## Quick Start

```bash
# Clone
git clone <repo-url> && cd bulk-campaign

# Backend
cd backend
cp .env.example .env   # fill in credentials
npm install
npm run dev

# Frontend (separate terminal)
cd frontend
cp .env.example .env   # set VITE_API_URL=http://localhost:5000
npm install
npm run dev
```

## Environment Variables

See `backend/.env.example` for all required variables:
- `MONGO_URI` – MongoDB connection string
- `JWT_SECRET` – Secret for signing JWT tokens
- `ADMIN_USER` / `ADMIN_PASS` – Admin panel credentials
- `CLOUDINARY_*` – Media storage
- `REDIS_*` – Cache and message ordering

## Features

### Admin Panel (`/admin`)
- Create/manage user accounts with Meta API credentials
- Auto-generate webhook URLs and verify tokens per user
- View user statistics (contacts, messages, templates)
- Enable/disable user accounts, reset passwords

### User Panel (`/`)
- Login with admin-provided credentials
- **Contacts sidebar** with search, unread counts, add new
- **Chat panel** with real-time messaging, reactions, replies
- **Template management** – create, submit to Meta, send
- **Media support** – images, video, audio, documents
- **24h window timer** with template-only fallback
- **Desktop notifications** + audio chime for inbound messages
- **Contact details** with notes, name editing, window countdown

## Webhook Setup

Each user gets a unique webhook URL:
```
<BACKEND_URL>/api/webhook/<userId>
```
Configure in Meta Developer Console → Webhooks with the user's verify token.

## Deployment

- **Backend → Render**: Use `render.yaml` blueprint
- **Frontend → Vercel**: Push `frontend/` folder, auto-detects Vite

## Project Structure

```
bulk-campaign/
├── backend/
│   ├── config/          # db.js, cloudinary.js
│   ├── controllers/     # admin, auth, contacts, messages, templates, webhook
│   ├── middleware/       # JWT auth (admin + user)
│   ├── models/          # User, Contact, Message, Template
│   ├── routes/          # Express route files
│   ├── services/        # metaService, redisService, socketService
│   ├── utils/           # metaErrors
│   ├── server.js        # Entry point
│   └── .env.example
├── frontend/
│   ├── src/
│   │   ├── admin/       # AdminApp, Dashboard, CreateUser, UserDetail
│   │   ├── api/         # Axios client, Socket.IO
│   │   ├── components/  # Sidebar, ChatPanel, MessageBubble, Templates, etc.
│   │   ├── utils/       # time, country, notify, useClickAway
│   │   ├── App.jsx      # User chat panel
│   │   └── main.jsx     # Entry (routes / vs /admin)
│   ├── index.html
│   └── vercel.json
├── render.yaml          # Render deployment blueprint
└── README.md
```

## License

MIT
