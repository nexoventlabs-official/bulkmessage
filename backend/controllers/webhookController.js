const User = require('../models/User');
const Contact = require('../models/Contact');
const Message = require('../models/Message');
const Template = require('../models/Template');
const meta = require('../services/metaService');
const redis = require('../services/redisService');
const { emitToUser } = require('../services/socketService');
const { uploadBuffer } = require('../config/cloudinary');
const metaErrors = require('../utils/metaErrors');

// GET /api/webhook/:userId — Meta verification
exports.verify = async (req, res) => {
  const mode = req.query['hub.mode'];
  const token = req.query['hub.verify_token'];
  const challenge = req.query['hub.challenge'];

  const user = await User.findById(req.params.userId);
  if (!user) return res.sendStatus(404);

  if (mode === 'subscribe' && token === user.metaVerifyToken) {
    console.log(`[webhook] verified for user ${user.username}`);
    return res.status(200).send(challenge);
  }
  return res.sendStatus(403);
};

// POST /api/webhook/:userId — inbound messages & status updates
exports.receive = async (req, res) => {
  // Immediately acknowledge
  res.sendStatus(200);

  try {
    const user = await User.findById(req.params.userId);
    if (!user) return;

    const body = req.body;
    if (body?.object !== 'whatsapp_business_account') return;

    for (const entry of body.entry || []) {
      for (const change of entry.changes || []) {
        if (change.field !== 'messages') continue;
        const val = change.value || {};

        // Status updates
        if (val.statuses) {
          for (const s of val.statuses) {
            await handleStatus(user, s);
          }
        }

        // Inbound messages
        if (val.messages) {
          for (const m of val.messages) {
            await handleInbound(user, m, val.contacts || []);
          }
        }
      }
    }
  } catch (e) {
    console.error('[webhook receive]', e.message);
  }
};

async function handleStatus(user, s) {
  const wamid = s.id;
  if (!wamid) return;

  const msg = await Message.findOne({ user: user._id, wamid });
  if (!msg) return;

  const rank = { queued: 0, sent: 1, delivered: 2, read: 3, failed: 4 };
  const current = rank[msg.status] ?? -1;
  const next = rank[s.status] ?? -1;

  if (s.status === 'failed') {
    msg.status = 'failed';
    msg.failureReason = JSON.stringify(s.errors || []);
  } else if (next > current) {
    msg.status = s.status;
  } else {
    return;
  }

  await msg.save();

  const out = msg.toObject();
  if (out.status === 'failed' && out.failureReason) {
    out.failureSummary = metaErrors.summarize(out.failureReason);
  }

  emitToUser(user._id, 'message:update', out);
}

async function handleInbound(user, m, whatsappContacts) {
  const waId = m.from;
  if (!waId) return;

  const creds = user.getMetaCredentials();
  const profileName = (whatsappContacts || []).find(c => c.wa_id === waId)?.profile?.name || '';

  // Find or create contact
  let contact = await Contact.findOne({ user: user._id, waId });
  if (!contact) {
    contact = await Contact.create({
      user: user._id,
      waId,
      profileName,
    });
  } else if (profileName && !contact.profileName) {
    contact.profileName = profileName;
  }

  const type = m.type || 'text';
  let text = '';
  let mediaUrl = '';
  let mediaMime = '';
  let mediaFilename = '';
  let caption = '';

  if (type === 'text') {
    text = m.text?.body || '';
  } else if (['image', 'video', 'audio', 'document', 'sticker'].includes(type)) {
    const mediaObj = m[type] || {};
    caption = mediaObj.caption || '';
    mediaMime = mediaObj.mime_type || '';
    mediaFilename = mediaObj.filename || '';
    // Download & upload to Cloudinary
    try {
      const mediaInfo = await meta.getMediaUrl(creds, mediaObj.id);
      const downloaded = await meta.downloadMedia(creds, mediaInfo.url);
      const ext = (mediaMime.split('/')[1] || 'bin').split(';')[0];
      const result = await uploadBuffer(downloaded.buffer, {
        folder: `bulk_campaign/${user._id}/${waId}`,
        filename: mediaFilename || `${type}_${Date.now()}.${ext}`,
        mime: mediaMime || downloaded.contentType,
      });
      mediaUrl = result.secure_url;
    } catch (e) {
      console.error('[webhook] media download/upload failed', e.message);
    }
  } else if (type === 'reaction') {
    // Handle reactions to outbound messages
    const reaction = m.reaction || {};
    const targetWamid = reaction.message_id;
    if (targetWamid) {
      const targetMsg = await Message.findOne({ user: user._id, wamid: targetWamid });
      if (targetMsg) {
        targetMsg.reactions = targetMsg.reactions.filter(r => r.from !== 'customer');
        if (reaction.emoji) targetMsg.reactions.push({ emoji: reaction.emoji, from: 'customer', at: new Date() });
        await targetMsg.save();
        emitToUser(user._id, 'message:update', targetMsg);
      }
    }
    return;
  } else if (type === 'button') {
    text = m.button?.text || '';
    // Auto-reply for template button
    await handleButtonAutoReply(user, contact, creds, text);
  } else if (type === 'interactive') {
    const reply = m.interactive?.button_reply || m.interactive?.list_reply || {};
    text = reply.title || reply.id || '';
    await handleButtonAutoReply(user, contact, creds, text);
  } else if (type === 'location') {
    const loc = m.location || {};
    text = `📍 Location: ${loc.latitude}, ${loc.longitude}${loc.name ? ` (${loc.name})` : ''}`;
  } else if (type === 'contacts') {
    text = '[Contact card]';
  } else {
    text = `[${type}]`;
  }

  const seq = await redis.nextSeq();
  const preview = text || caption || `[${type}]`;

  const msg = await Message.create({
    user: user._id,
    contact: contact._id,
    waId,
    direction: 'inbound',
    wamid: m.id,
    type,
    text: text || caption,
    caption,
    mediaUrl,
    mediaMime,
    mediaFilename,
    status: 'delivered',
    seq,
    raw: m,
  });

  contact.lastMessageAt = new Date();
  contact.lastCustomerMessageAt = new Date();
  contact.lastMessagePreview = preview.slice(0, 100);
  contact.unreadCount = (contact.unreadCount || 0) + 1;
  await contact.save();

  emitToUser(user._id, 'message:new', msg);
  emitToUser(user._id, 'contact:upsert', contact);

  // Mark as read on Meta side
  meta.markAsRead(creds, m.id).catch(() => {});
}

async function handleButtonAutoReply(user, contact, creds, buttonText) {
  if (!buttonText) return;
  const tpls = await Template.find({ user: user._id, 'buttons.replyText': { $exists: true, $ne: '' } });
  for (const tpl of tpls) {
    const btn = tpl.buttons.find(b => b.text === buttonText && b.replyText);
    if (btn) {
      try {
        await meta.sendText(creds, contact.waId, btn.replyText);
        const seq = await redis.nextSeq();
        const autoMsg = await Message.create({
          user: user._id,
          contact: contact._id,
          waId: contact.waId,
          direction: 'outbound',
          type: 'text',
          text: btn.replyText,
          status: 'sent',
          seq,
        });
        contact.lastMessageAt = new Date();
        contact.lastMessagePreview = btn.replyText.slice(0, 100);
        await contact.save();
        emitToUser(user._id, 'message:new', autoMsg);
        emitToUser(user._id, 'contact:upsert', contact);
      } catch (e) {
        console.error('[autoReply] failed', e.message);
      }
      break;
    }
  }
}
