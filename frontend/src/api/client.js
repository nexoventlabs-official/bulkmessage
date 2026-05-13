import axios from 'axios';

const BASE = import.meta.env.VITE_API_URL || '';

const api = axios.create({ baseURL: BASE });

// Attach admin token for admin routes, user token for user routes
api.interceptors.request.use((config) => {
  if (config.url && config.url.startsWith('/api/admin/')) {
    const token = localStorage.getItem('bc:adminToken');
    if (token) {
      config.headers = config.headers || {};
      config.headers.Authorization = `Bearer ${token}`;
    }
  } else {
    const token = localStorage.getItem('bc:userToken');
    if (token) {
      config.headers = config.headers || {};
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

// Admin API
export const Admin = {
  login: (u, p) => api.post('/api/admin/login', { username: u, password: p }).then(r => r.data),
  me: () => api.get('/api/admin/me').then(r => r.data),
  listUsers: () => api.get('/api/admin/users').then(r => r.data),
  getUser: (id) => api.get(`/api/admin/users/${id}`).then(r => r.data),
  getUserStats: (id) => api.get(`/api/admin/users/${id}/stats`).then(r => r.data),
  createUser: (d) => api.post('/api/admin/users', d).then(r => r.data),
  updateUser: (id, d) => api.patch(`/api/admin/users/${id}`, d).then(r => r.data),
  deleteUser: (id) => api.delete(`/api/admin/users/${id}`).then(r => r.data),
  regenerateToken: (id) => api.post(`/api/admin/users/${id}/regenerate-token`).then(r => r.data),
};

// User Auth API
export const Auth = {
  login: (u, p) => api.post('/api/auth/login', { username: u, password: p }).then(r => r.data),
  me: () => api.get('/api/auth/me').then(r => r.data),
};

// Contacts API (user-scoped)
export const Contacts = {
  list: (params) => api.get('/api/contacts', { params }).then(r => r.data),
  get: (id) => api.get(`/api/contacts/${id}`).then(r => r.data),
  create: (d) => api.post('/api/contacts', d).then(r => r.data),
  update: (id, d) => api.patch(`/api/contacts/${id}`, d).then(r => r.data),
  markRead: (id) => api.post(`/api/contacts/${id}/read`).then(r => r.data),
  addNote: (id, text) => api.post(`/api/contacts/${id}/notes`, { text }).then(r => r.data),
  deleteNote: (id, noteId) => api.delete(`/api/contacts/${id}/notes/${noteId}`).then(r => r.data),
  clearChat: (id) => api.delete(`/api/contacts/${id}/chat`).then(r => r.data),
};

// Messages API (user-scoped)
export const Messages = {
  list: (contactId, params) => api.get(`/api/messages/${contactId}`, { params }).then(r => r.data),
  sendText: (contactId, text, replyTo) => api.post(`/api/messages/${contactId}/text`, { text, replyTo }).then(r => r.data),
  sendMedia: (contactId, d) => api.post(`/api/messages/${contactId}/media`, d).then(r => r.data),
  sendReaction: (contactId, wamid, emoji) => api.post(`/api/messages/${contactId}/reaction`, { wamid, emoji }).then(r => r.data),
  sendTemplate: (contactId, d) => api.post(`/api/messages/${contactId}/template`, d).then(r => r.data),
  delete: (id) => api.delete(`/api/messages/${id}`).then(r => r.data),
};

// Templates API (user-scoped)
export const Templates = {
  list: () => api.get('/api/templates').then(r => r.data),
  sync: () => api.post('/api/templates/sync').then(r => r.data),
  create: (d) => api.post('/api/templates', d).then(r => r.data),
  submit: (id) => api.post(`/api/templates/${id}/submit`).then(r => r.data),
  refresh: (id) => api.post(`/api/templates/${id}/refresh`).then(r => r.data),
  updateReplies: (id, replies) => api.patch(`/api/templates/${id}/replies`, { replies }).then(r => r.data),
  delete: (id) => api.delete(`/api/templates/${id}`).then(r => r.data),
};

// Uploads API (user-scoped)
export const Uploads = {
  upload: (file, waId) => {
    const fd = new FormData();
    fd.append('file', file);
    if (waId) fd.append('waId', waId);
    return api.post('/api/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then(r => r.data);
  },
};

export default api;
