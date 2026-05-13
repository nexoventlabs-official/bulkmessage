const Contact = require('../models/Contact');
const Message = require('../models/Message');
const { emitToUser } = require('../services/socketService');
const { deleteFolder } = require('../config/cloudinary');

exports.listContacts = async (req, res) => {
  const userId = req.user._id;
  const { q, from, to } = req.query;
  const filter = { user: userId };
  if (q) {
    const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
    filter.$or = [{ name: re }, { waId: re }, { profileName: re }];
  }
  if (from || to) {
    filter.lastMessageAt = {};
    if (from) filter.lastMessageAt.$gte = new Date(from);
    if (to) filter.lastMessageAt.$lte = new Date(to);
  }
  const contacts = await Contact.find(filter).sort({ lastMessageAt: -1, updatedAt: -1 }).limit(500);
  res.json(contacts);
};

exports.getContact = async (req, res) => {
  const contact = await Contact.findOne({ _id: req.params.id, user: req.user._id });
  if (!contact) return res.status(404).json({ error: 'Not found' });
  res.json(contact);
};

exports.createContact = async (req, res) => {
  const { waId, name } = req.body;
  if (!waId) return res.status(400).json({ error: 'waId required' });
  const normalized = String(waId).replace(/\D/g, '');
  let contact = await Contact.findOne({ user: req.user._id, waId: normalized });
  if (!contact) {
    contact = await Contact.create({ user: req.user._id, waId: normalized, name: name || '' });
  } else if (name) {
    contact.name = name;
    await contact.save();
  }
  emitToUser(req.user._id, 'contact:upsert', contact);
  res.json(contact);
};

exports.updateContact = async (req, res) => {
  const { name, tags } = req.body;
  const contact = await Contact.findOne({ _id: req.params.id, user: req.user._id });
  if (!contact) return res.status(404).json({ error: 'Not found' });
  if (name !== undefined) contact.name = name;
  if (tags !== undefined) contact.tags = tags;
  await contact.save();
  emitToUser(req.user._id, 'contact:upsert', contact);
  res.json(contact);
};

exports.addNote = async (req, res) => {
  const { text } = req.body || {};
  const trimmed = String(text || '').trim();
  if (!trimmed) return res.status(400).json({ error: 'text required' });
  const contact = await Contact.findOne({ _id: req.params.id, user: req.user._id });
  if (!contact) return res.status(404).json({ error: 'Not found' });
  contact.notes.push({ text: trimmed });
  await contact.save();
  emitToUser(req.user._id, 'contact:upsert', contact);
  res.json(contact);
};

exports.deleteNote = async (req, res) => {
  const contact = await Contact.findOne({ _id: req.params.id, user: req.user._id });
  if (!contact) return res.status(404).json({ error: 'Not found' });
  const before = contact.notes.length;
  contact.notes = contact.notes.filter(n => String(n._id) !== String(req.params.noteId));
  if (contact.notes.length === before) return res.status(404).json({ error: 'Note not found' });
  await contact.save();
  emitToUser(req.user._id, 'contact:upsert', contact);
  res.json(contact);
};

exports.markRead = async (req, res) => {
  const contact = await Contact.findOne({ _id: req.params.id, user: req.user._id });
  if (!contact) return res.status(404).json({ error: 'Not found' });
  contact.unreadCount = 0;
  await contact.save();
  emitToUser(req.user._id, 'contact:upsert', contact);
  res.json({ ok: true });
};

exports.clearChat = async (req, res) => {
  try {
    const contact = await Contact.findOne({ _id: req.params.id, user: req.user._id });
    if (!contact) return res.status(404).json({ error: 'Not found' });

    const del = await Message.deleteMany({ contact: contact._id });

    deleteFolder(`bulk_campaign/${req.user._id}/${contact.waId}`).catch(e =>
      console.error('[clearChat] cloudinary folder delete failed', e.message)
    );

    contact.lastMessageAt = null;
    contact.lastMessagePreview = '';
    contact.unreadCount = 0;
    contact.lastCustomerMessageAt = null;
    contact.notes = [];
    await contact.save();

    emitToUser(req.user._id, 'chat:cleared', { contactId: contact._id });
    emitToUser(req.user._id, 'contact:upsert', contact);
    res.json({ ok: true, deletedMessages: del.deletedCount });
  } catch (e) {
    console.error('[clearChat]', e.message);
    res.status(500).json({ error: 'Failed', details: e.message });
  }
};
