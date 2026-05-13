const Contact = require('../models/Contact');
const Message = require('../models/Message');
const Template = require('../models/Template');
const meta = require('../services/metaService');
const { emitToUser } = require('../services/socketService');
const metaErrors = require('../utils/metaErrors');
const { deleteByUrl } = require('../config/cloudinary');
const redis = require('../services/redisService');

function substitutePlaceholders(text, params) {
  if (!text) return '';
  return text.replace(/\{\{\s*(\d+)\s*\}\}/g, (_, idx) => {
    const p = params?.[Number(idx) - 1];
    if (!p) return '';
    if (p.type === 'text') return p.text ?? '';
    if (p.type === 'currency') return p.currency?.fallback_value ?? '';
    if (p.type === 'date_time') return p.date_time?.fallback_value ?? '';
    return p.text ?? '';
  });
}

function findComp(components, typeLower) {
  if (!Array.isArray(components)) return null;
  return components.find(c => (c?.type || '').toLowerCase() === typeLower) || null;
}

function resolveButtons(tpl, components) {
  const buttonsComp = findComp(components, 'button');
  const btnParamByIndex = {};
  (buttonsComp?.parameters || []).forEach((p, i) => { btnParamByIndex[i] = p; });
  return (tpl.buttons || []).map((btn, i) => {
    const out = { type: btn.type, text: btn.text || '' };
    if (btn.type === 'URL') {
      let href = btn.url || '';
      const p = btnParamByIndex[i];
      if (p?.type === 'text') href = href.replace(/\{\{\s*\d+\s*\}\}/, p.text || '');
      out.url = href;
    } else if (btn.type === 'PHONE_NUMBER') {
      out.phone_number = btn.phone_number || '';
    }
    return out;
  });
}

function renderBodyOnly(tpl, components) {
  const bodyParams = findComp(components, 'body')?.parameters || [];
  return substitutePlaceholders(tpl.body || '', bodyParams);
}

function renderTemplateText(tpl, components) {
  const body = renderBodyOnly(tpl, components);
  const lines = [];
  if (body) lines.push(body);
  if (tpl.footer) lines.push(tpl.footer);
  for (const btn of resolveButtons(tpl, components)) {
    if (btn.type === 'URL' && btn.url) lines.push(`🔗 ${btn.text || 'Link'}: ${btn.url}`);
    else if (btn.type === 'PHONE_NUMBER' && btn.phone_number) lines.push(`📞 ${btn.text || 'Call'}: ${btn.phone_number}`);
  }
  return lines.join('\n\n');
}

function resolveHeaderMediaUrl(tpl, components) {
  const headerComp = findComp(components, 'header');
  const p = headerComp?.parameters?.[0];
  if (p) {
    const kind = p.type;
    const obj = p[kind];
    if (obj?.link) return { url: obj.link, kind };
  }
  if (tpl.header?.mediaUrl && tpl.header.type !== 'NONE' && tpl.header.type !== 'TEXT') {
    return { url: tpl.header.mediaUrl, kind: (tpl.header.type || '').toLowerCase() };
  }
  return null;
}

function isWindowOpen(contact) {
  if (!contact.lastCustomerMessageAt) return false;
  return Date.now() - contact.lastCustomerMessageAt.getTime() < 24 * 60 * 60 * 1000;
}

exports.listMessages = async (req, res) => {
  const { contactId } = req.params;
  // Verify contact belongs to this user
  const contact = await Contact.findOne({ _id: contactId, user: req.user._id });
  if (!contact) return res.status(404).json({ error: 'Contact not found' });

  const { limit = 200, before } = req.query;
  const filter = { contact: contactId };
  if (before) filter.createdAt = { $lt: new Date(before) };
  const msgs = await Message.find(filter).sort({ createdAt: -1, seq: -1 }).limit(Number(limit));
  const out = msgs.reverse().map(m => {
    const o = m.toObject();
    if (o.status === 'failed' && o.failureReason) {
      o.failureSummary = metaErrors.summarize(o.failureReason);
    }
    return o;
  });
  res.json(out);
};

exports.sendText = async (req, res) => {
  try {
    const { contactId } = req.params;
    const { text, replyTo } = req.body;
    const contact = await Contact.findOne({ _id: contactId, user: req.user._id });
    if (!contact) return res.status(404).json({ error: 'Not found' });
    if (!isWindowOpen(contact)) return res.status(400).json({ error: 'WINDOW_CLOSED', message: '24h session window closed. Use a template.' });
    if (!text || !text.trim()) return res.status(400).json({ error: 'Text required' });

    const creds = req.user.getMetaCredentials();
    const resp = await meta.sendText(creds, contact.waId, text, replyTo || undefined);
    const wamid = resp?.messages?.[0]?.id;
    const seq = await redis.nextSeq();

    const msg = await Message.create({
      user: req.user._id,
      contact: contact._id,
      waId: contact.waId,
      direction: 'outbound',
      wamid,
      type: 'text',
      text,
      replyToWamid: replyTo || null,
      status: 'sent',
      seq,
    });

    contact.lastMessageAt = new Date();
    contact.lastMessagePreview = text.slice(0, 100);
    await contact.save();

    emitToUser(req.user._id, 'message:new', msg);
    emitToUser(req.user._id, 'contact:upsert', contact);
    res.json(msg);
  } catch (e) {
    console.error('[sendText]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};

exports.sendMedia = async (req, res) => {
  try {
    const { contactId } = req.params;
    const { type, url, caption, filename } = req.body;
    const contact = await Contact.findOne({ _id: contactId, user: req.user._id });
    if (!contact) return res.status(404).json({ error: 'Not found' });
    if (!isWindowOpen(contact)) return res.status(400).json({ error: 'WINDOW_CLOSED' });
    if (!url || !type) return res.status(400).json({ error: 'url & type required' });

    const creds = req.user.getMetaCredentials();
    let mediaRef;
    try {
      const { buffer, mime } = await meta.fetchUrlToBuffer(url);
      const mimeOverride =
        type === 'image' ? 'image/jpeg' :
        type === 'video' ? 'video/mp4' :
        type === 'audio' ? 'audio/ogg' :
        type === 'document' ? 'application/pdf' :
        mime;
      const finalMime = mime && mime !== 'application/octet-stream' ? mime : mimeOverride;
      const upl = await meta.uploadMediaToMeta(creds, {
        buffer,
        mime: finalMime,
        filename: filename || url.split('/').pop()?.split('?')[0] || 'media',
      });
      mediaRef = { id: upl.id };
    } catch (err) {
      console.warn('[sendMedia] media-id upload failed, falling back to link:', err.message);
      mediaRef = { link: url };
    }
    const resp = await meta.sendMedia(creds, contact.waId, type, mediaRef, caption, filename);
    const wamid = resp?.messages?.[0]?.id;
    const seq = await redis.nextSeq();

    const msg = await Message.create({
      user: req.user._id,
      contact: contact._id,
      waId: contact.waId,
      direction: 'outbound',
      wamid,
      type,
      mediaUrl: url,
      caption: caption || '',
      mediaFilename: filename || '',
      status: 'sent',
      seq,
    });

    contact.lastMessageAt = new Date();
    contact.lastMessagePreview = caption || `[${type}]`;
    await contact.save();

    emitToUser(req.user._id, 'message:new', msg);
    emitToUser(req.user._id, 'contact:upsert', contact);
    res.json(msg);
  } catch (e) {
    console.error('[sendMedia]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};

exports.sendReaction = async (req, res) => {
  try {
    const { contactId } = req.params;
    const { wamid, emoji } = req.body;
    const contact = await Contact.findOne({ _id: contactId, user: req.user._id });
    if (!contact) return res.status(404).json({ error: 'Not found' });
    const creds = req.user.getMetaCredentials();
    await meta.sendReaction(creds, contact.waId, wamid, emoji);
    const msg = await Message.findOne({ wamid });
    if (msg) {
      msg.reactions = msg.reactions.filter(r => r.from !== 'agent');
      if (emoji) msg.reactions.push({ emoji, from: 'agent', at: new Date() });
      await msg.save();
      emitToUser(req.user._id, 'message:update', msg);
    }
    res.json({ ok: true });
  } catch (e) {
    console.error('[sendReaction]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};

exports.deleteMessage = async (req, res) => {
  const { id } = req.params;
  const msg = await Message.findOne({ _id: id, user: req.user._id });
  if (!msg) return res.status(404).json({ error: 'Not found' });
  if (msg.mediaUrl && !msg.templateName && !(msg.templateData?.header?.mediaUrl)) {
    try { await deleteByUrl(msg.mediaUrl); } catch (e) { console.error('[deleteMessage cloudinary]', e.message); }
  }
  const contactId = msg.contact;
  await msg.deleteOne();
  emitToUser(req.user._id, 'message:delete', { id, contactId });
  res.json({ ok: true });
};

exports.sendTemplate = async (req, res) => {
  try {
    const { contactId } = req.params;
    const { templateName, language, components: rawComponents, previewText } = req.body;
    const contact = await Contact.findOne({ _id: contactId, user: req.user._id });
    if (!contact) return res.status(404).json({ error: 'Not found' });

    const creds = req.user.getMetaCredentials();
    const tpl = await Template.findOne({ user: req.user._id, name: templateName, language });

    // Free-form short-circuit when 24h window is open
    if (tpl && isWindowOpen(contact)) {
      const buttons = resolveButtons(tpl, rawComponents || []);
      const bodyText = renderBodyOnly(tpl, rawComponents || []);
      const headerMedia = resolveHeaderMediaUrl(tpl, rawComponents || []);
      const seq = await redis.nextSeq();

      const templateData = {
        header: tpl.header?.type === 'TEXT'
          ? { type: 'TEXT', text: tpl.header?.text || '' }
          : headerMedia
            ? { type: (tpl.header?.type || headerMedia.kind.toUpperCase()), mediaUrl: headerMedia.url }
            : { type: 'NONE' },
        body: bodyText,
        footer: tpl.footer || '',
        buttons,
      };

      const mediaKind = headerMedia ? headerMedia.kind : null;
      const inlinedText = renderTemplateText(tpl, rawComponents || []);
      let metaResp, msgDoc;

      if (mediaKind) {
        metaResp = await meta.sendMedia(creds, contact.waId, mediaKind, { link: headerMedia.url }, inlinedText);
        msgDoc = await Message.create({
          user: req.user._id,
          contact: contact._id,
          waId: contact.waId,
          direction: 'outbound',
          wamid: metaResp?.messages?.[0]?.id,
          type: mediaKind,
          mediaUrl: headerMedia.url,
          caption: inlinedText,
          text: inlinedText,
          templateName,
          templateData,
          status: 'sent',
          seq,
        });
      } else {
        let finalText = inlinedText;
        if (tpl.header?.type === 'TEXT' && tpl.header?.text) {
          finalText = `*${tpl.header.text}*\n\n${finalText}`;
        }
        metaResp = await meta.sendText(creds, contact.waId, finalText);
        msgDoc = await Message.create({
          user: req.user._id,
          contact: contact._id,
          waId: contact.waId,
          direction: 'outbound',
          wamid: metaResp?.messages?.[0]?.id,
          type: 'text',
          text: finalText,
          templateName,
          templateData,
          status: 'sent',
          seq,
        });
      }

      contact.lastMessageAt = new Date();
      contact.lastMessagePreview = `[template/free] ${templateName}`;
      await contact.save();

      emitToUser(req.user._id, 'message:new', msgDoc);
      emitToUser(req.user._id, 'contact:upsert', contact);
      return res.json(msgDoc);
    }

    // Paid template send (outside window)
    const components = JSON.parse(JSON.stringify(rawComponents || []));
    for (const comp of components) {
      if (comp.type !== 'header' && comp.type !== 'HEADER') continue;
      for (const p of comp.parameters || []) {
        const mediaType = p.type;
        const mediaObj = p[mediaType];
        if (!mediaObj || !mediaObj.link || mediaObj.id) continue;
        try {
          const { buffer, mime } = await meta.fetchUrlToBuffer(mediaObj.link);
          const mimeOverride =
            mediaType === 'image' ? 'image/jpeg' :
            mediaType === 'video' ? 'video/mp4' :
            mediaType === 'document' ? 'application/pdf' :
            mime;
          const finalMime = mime && mime !== 'application/octet-stream' ? mime : mimeOverride;
          const filename = (mediaObj.link.split('/').pop() || 'media').split('?')[0];
          const upl = await meta.uploadMediaToMeta(creds, { buffer, mime: finalMime, filename });
          delete mediaObj.link;
          mediaObj.id = upl.id;
        } catch (e) {
          console.error('[sendTemplate] media upload to Meta failed', e.response?.data || e.message);
          throw new Error('Failed to upload header media to Meta: ' + (e.response?.data?.error?.message || e.message));
        }
      }
    }

    const resp = await meta.sendTemplateMessage(creds, contact.waId, templateName, language, components);
    const wamid = resp?.messages?.[0]?.id;
    const seq = await redis.nextSeq();

    const templateData = tpl ? {
      header: tpl.header?.type === 'TEXT'
        ? { type: 'TEXT', text: tpl.header?.text || '' }
        : tpl.header?.mediaUrl
          ? { type: tpl.header.type, mediaUrl: tpl.header.mediaUrl }
          : { type: 'NONE' },
      body: renderBodyOnly(tpl, rawComponents || []),
      footer: tpl.footer || '',
      buttons: resolveButtons(tpl, rawComponents || []),
    } : undefined;

    const msg = await Message.create({
      user: req.user._id,
      contact: contact._id,
      waId: contact.waId,
      direction: 'outbound',
      wamid,
      type: 'template',
      templateName,
      templateData,
      text: previewText || `[template: ${templateName}]`,
      status: 'sent',
      seq,
    });

    contact.lastMessageAt = new Date();
    contact.lastMessagePreview = `[template] ${templateName}`;
    await contact.save();

    emitToUser(req.user._id, 'message:new', msg);
    emitToUser(req.user._id, 'contact:upsert', contact);
    res.json(msg);
  } catch (e) {
    console.error('[sendTemplate]', e.response?.data || e.message);
    res.status(500).json({ error: 'Failed', details: e.response?.data || e.message });
  }
};
