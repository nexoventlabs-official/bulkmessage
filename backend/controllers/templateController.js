const Template = require('../models/Template');
const meta = require('../services/metaService');
const { emitToUser } = require('../services/socketService');
const { deleteByUrl } = require('../config/cloudinary');
const axios = require('axios');

const HEADER_MIME_BY_FORMAT = {
  IMAGE: ['image/jpeg', 'image/png'],
  VIDEO: ['video/mp4', 'video/3gpp'],
  DOCUMENT: ['application/pdf'],
};

const EXT_FOR_MIME = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'video/mp4': 'mp4',
  'video/3gpp': '3gp',
  'application/pdf': 'pdf',
};

function guessMimeFromUrl(url, format) {
  const lower = (url.split('?')[0] || '').toLowerCase();
  if (format === 'IMAGE') {
    if (lower.endsWith('.png')) return 'image/png';
    if (lower.endsWith('.jpg') || lower.endsWith('.jpeg')) return 'image/jpeg';
  } else if (format === 'VIDEO') {
    if (lower.endsWith('.mp4')) return 'video/mp4';
  } else if (format === 'DOCUMENT') {
    if (lower.endsWith('.pdf')) return 'application/pdf';
  }
  return null;
}

exports.listTemplates = async (req, res) => {
  const templates = await Template.find({ user: req.user._id }).sort({ updatedAt: -1 });
  res.json(templates);
};

exports.syncTemplates = async (req, res) => {
  try {
    const creds = req.user.getMetaCredentials();
    const { data } = await meta.listTemplates(creds);
    const items = data || [];
    const results = [];
    for (const t of items) {
      const existing = await Template.findOne({ user: req.user._id, name: t.name, language: t.language });
      const derived = derivedFromComponents(t.components);
      if (existing?.header?.mediaUrl && !existing.header.mediaUrl.includes('whatsapp.net')) {
        derived.header = { ...derived.header, mediaUrl: existing.header.mediaUrl };
      }
      if (existing?.buttons?.length && derived.buttons?.length) {
        derived.buttons = derived.buttons.map((b) => {
          const prior = existing.buttons.find(x => x.type === b.type && x.text === b.text);
          return prior?.replyText ? { ...b, replyText: prior.replyText } : b;
        });
      }
      const doc = await Template.findOneAndUpdate(
        { user: req.user._id, name: t.name, language: t.language },
        {
          user: req.user._id,
          metaId: t.id,
          name: t.name,
          language: t.language,
          category: t.category,
          status: t.status,
          rejectedReason: t.rejected_reason || '',
          components: t.components,
          lastSyncedAt: new Date(),
          ...derived,
        },
        { upsert: true, new: true, setDefaultsOnInsert: true }
      );
      results.push(doc);
      emitToUser(req.user._id, 'template:update', doc);
    }
    res.json({ count: results.length, templates: results });
  } catch (e) {
    console.error('[syncTemplates]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};

function derivedFromComponents(components = []) {
  const header = (components || []).find(c => c.type === 'HEADER');
  const body = (components || []).find(c => c.type === 'BODY');
  const footer = (components || []).find(c => c.type === 'FOOTER');
  const buttons = (components || []).find(c => c.type === 'BUTTONS');
  return {
    header: header
      ? {
          type: header.format || 'TEXT',
          text: header.format === 'TEXT' ? header.text : '',
          mediaUrl: header.example?.header_handle?.[0] || '',
        }
      : { type: 'NONE' },
    body: body?.text || '',
    footer: footer?.text || '',
    buttons: (buttons?.buttons || []).map(b => ({
      type: b.type,
      text: b.text,
      url: b.url,
      phone_number: b.phone_number,
    })),
  };
}

exports.createTemplate = async (req, res) => {
  try {
    const { name, language = 'en_US', category = 'MARKETING', header, body, footer, buttons } = req.body;
    if (!name || !body) return res.status(400).json({ error: 'name & body required' });

    const components = [];
    if (header && header.type && header.type !== 'NONE') {
      if (header.type === 'TEXT') {
        components.push({ type: 'HEADER', format: 'TEXT', text: header.text || '' });
      } else {
        components.push({
          type: 'HEADER',
          format: header.type,
          example: { header_handle: [header.mediaUrl] },
        });
      }
    }
    components.push({ type: 'BODY', text: body });
    if (footer) components.push({ type: 'FOOTER', text: footer });
    if (buttons && buttons.length) {
      components.push({
        type: 'BUTTONS',
        buttons: buttons.map(b => {
          if (b.type === 'URL') return { type: 'URL', text: b.text, url: b.url };
          if (b.type === 'PHONE_NUMBER') return { type: 'PHONE_NUMBER', text: b.text, phone_number: b.phone_number };
          return { type: 'QUICK_REPLY', text: b.text };
        }),
      });
    }

    let doc = await Template.create({
      user: req.user._id,
      name,
      language,
      category,
      status: 'DRAFT',
      header: header || { type: 'NONE' },
      body,
      footer: footer || '',
      buttons: buttons || [],
      components,
    });
    emitToUser(req.user._id, 'template:update', doc);
    res.json(doc);
  } catch (e) {
    console.error('[createTemplate]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};

exports.submitTemplate = async (req, res) => {
  try {
    const doc = await Template.findOne({ _id: req.params.id, user: req.user._id });
    if (!doc) return res.status(404).json({ error: 'Not found' });

    const creds = req.user.getMetaCredentials();
    const components = JSON.parse(JSON.stringify(doc.components || []));
    const header = components.find(c => c.type === 'HEADER');
    if (header && ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(header.format)) {
      const mediaUrl = doc.header?.mediaUrl || header.example?.header_handle?.[0];
      if (!mediaUrl) return res.status(400).json({ error: 'Header media URL missing' });
      if (!/^https?:\/\//i.test(mediaUrl) || /whatsapp\.net|lookaside\.fbsbx\.com/i.test(mediaUrl)) {
        return res.status(400).json({ error: 'Header media URL is not re-uploadable.' });
      }

      const allowed = HEADER_MIME_BY_FORMAT[header.format];
      let detectedMime = null;
      try {
        const head = await axios.head(mediaUrl, { timeout: 10000 });
        detectedMime = (head.headers['content-type'] || '').split(';')[0].trim().toLowerCase();
      } catch (_) { }
      let fileType = detectedMime && allowed.includes(detectedMime)
        ? detectedMime
        : guessMimeFromUrl(mediaUrl, header.format) || allowed[0];

      let rawName = (mediaUrl.split('/').pop() || 'header').split('?')[0];
      try { rawName = decodeURIComponent(rawName); } catch { }
      const safeStem = rawName.replace(/[\/<@%\s]/g, '_').replace(/\.[^.]+$/, '').trim() || 'header';
      const fileName = `${safeStem}.${EXT_FOR_MIME[fileType] || EXT_FOR_MIME[allowed[0]]}`;

      const { header_handle } = await meta.uploadHeaderSample(creds, { fileUrl: mediaUrl, fileName, fileType });
      header.example = { header_handle: [header_handle] };
    }

    const payload = { name: doc.name, language: doc.language, category: doc.category, components };

    let resp;
    try {
      resp = await meta.createTemplate(creds, payload);
    } catch (e) {
      const msg = e.response?.data?.error?.message || '';
      const alreadyExists = /already exists|exists with the same name/i.test(msg);
      if (!alreadyExists) throw e;
      const list = await meta.listTemplates(creds);
      const found = (list.data || []).find(t => t.name === doc.name && t.language === doc.language);
      if (!found) throw e;
      resp = { id: found.id, status: found.status };
    }

    doc.metaId = resp.id;
    doc.status = (resp.status || 'PENDING').toUpperCase();
    doc.components = components;
    doc.lastSyncedAt = new Date();
    await doc.save();
    emitToUser(req.user._id, 'template:update', doc);
    res.json(doc);
  } catch (e) {
    console.error('[submitTemplate]', e.response?.data || e.message);
    const metaErr = e.response?.data?.error;
    const friendly = metaErr?.error_user_msg || metaErr?.message || e.message || 'Failed to submit template';
    res.status(500).json({ error: friendly, details: e.response?.data || e.message });
  }
};

exports.refreshTemplate = async (req, res) => {
  try {
    const doc = await Template.findOne({ _id: req.params.id, user: req.user._id });
    if (!doc) return res.status(404).json({ error: 'Not found' });
    if (!doc.metaId) return res.status(400).json({ error: 'Not submitted yet' });
    const creds = req.user.getMetaCredentials();
    const data = await meta.getTemplateById(creds, doc.metaId);
    doc.status = (data.status || doc.status).toUpperCase();
    doc.rejectedReason = data.rejected_reason || '';
    doc.lastSyncedAt = new Date();
    await doc.save();
    emitToUser(req.user._id, 'template:update', doc);
    res.json(doc);
  } catch (e) {
    console.error('[refreshTemplate]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};

exports.updateButtonReplies = async (req, res) => {
  try {
    const doc = await Template.findOne({ _id: req.params.id, user: req.user._id });
    if (!doc) return res.status(404).json({ error: 'Not found' });
    const replies = req.body?.replies || {};
    let touched = 0;
    doc.buttons = doc.buttons.map((b) => {
      if (b.type !== 'QUICK_REPLY') return b;
      if (!Object.prototype.hasOwnProperty.call(replies, b.text)) return b;
      const next = String(replies[b.text] || '');
      if ((b.replyText || '') !== next) touched += 1;
      b.replyText = next;
      return b;
    });
    await doc.save();
    emitToUser(req.user._id, 'template:update', doc);
    res.json({ ok: true, updated: touched, template: doc });
  } catch (e) {
    console.error('[updateButtonReplies]', e.message);
    res.status(500).json({ error: 'Failed', details: e.message });
  }
};

exports.deleteTemplate = async (req, res) => {
  try {
    const doc = await Template.findOne({ _id: req.params.id, user: req.user._id });
    if (!doc) return res.status(404).json({ error: 'Not found' });

    const creds = req.user.getMetaCredentials();
    const result = { meta: null, cloudinary: null, mongo: null };

    const everSubmitted = !!doc.metaId || (doc.status && doc.status !== 'DRAFT');
    if (everSubmitted) {
      try {
        await meta.deleteTemplate(creds, doc.name);
        result.meta = 'deleted';
      } catch (e) {
        const msg = e.response?.data?.error?.message || e.message;
        const looksNotFound = /does not exist|not found|no template/i.test(msg);
        result.meta = looksNotFound ? 'not_found_on_meta' : `error: ${msg}`;
        if (!looksNotFound) {
          return res.status(502).json({ ok: false, error: msg, result });
        }
      }
    } else {
      result.meta = 'skipped_never_submitted';
    }

    if (doc.header?.mediaUrl) {
      try {
        await deleteByUrl(doc.header.mediaUrl);
        result.cloudinary = 'deleted';
      } catch (e) {
        result.cloudinary = `error: ${e.message}`;
      }
    }

    await doc.deleteOne();
    result.mongo = 'deleted';

    emitToUser(req.user._id, 'template:delete', { id: req.params.id });
    res.json({ ok: true, result });
  } catch (e) {
    console.error('[deleteTemplate]', e.message);
    res.status(500).json({ error: 'Failed', details: e.message });
  }
};
