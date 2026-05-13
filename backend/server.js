require('dotenv').config();
const express = require('express');
const cors = require('cors');
const http = require('http');
const { Server } = require('socket.io');

const { connectDB } = require('./config/db');
const { setIO } = require('./services/socketService');
const redis = require('./services/redisService');

const webhookRoutes = require('./routes/webhook');
const adminRoutes = require('./routes/admin');
const authRoutes = require('./routes/auth');
const contactsRoutes = require('./routes/contacts');
const messagesRoutes = require('./routes/messages');
const templatesRoutes = require('./routes/templates');
const uploadRoutes = require('./routes/upload');

const app = express();
const PORT = process.env.PORT || 5000;

app.use(cors({
  origin: process.env.FRONTEND_URL || '*',
  credentials: true,
}));
app.use(express.json({ limit: '10mb' }));

// Routes
app.use('/api/webhook', webhookRoutes);
app.use('/api/admin', adminRoutes);
app.use('/api/auth', authRoutes);
app.use('/api/contacts', contactsRoutes);
app.use('/api/messages', messagesRoutes);
app.use('/api/templates', templatesRoutes);
app.use('/api/upload', uploadRoutes);

// Health
app.get('/api/health', (_, res) => res.json({ ok: true, time: new Date().toISOString() }));

const server = http.createServer(app);

// Socket.IO
const io = new Server(server, {
  cors: { origin: process.env.FRONTEND_URL || '*', methods: ['GET', 'POST'] },
  path: '/socket.io',
});

setIO(io);

io.on('connection', (socket) => {
  // Clients join a user-specific room for multi-tenant isolation
  socket.on('join', ({ userId }) => {
    if (userId) {
      socket.join(`user:${userId}`);
    }
  });
  socket.on('agent:typing', (data) => {
    if (data?.userId) {
      io.to(`user:${data.userId}`).emit('customer:typing', data);
    }
  });
});

(async () => {
  await connectDB();
  redis.init();
  server.listen(PORT, () => {
    console.log(`[server] listening on :${PORT}`);
  });
})();
