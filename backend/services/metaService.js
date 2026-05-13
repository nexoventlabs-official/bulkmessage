const axios = require('axios');
const FormData = require('form-data');

// All functions accept a `creds` object:
// { accessToken, phoneNumberId, wabaId, appId, appSecret, graphVersion }
// This makes the service multi-tenant: each user passes their own credentials.

function GRAPH(creds) {
  return `https://graph.facebook.com/${creds.graphVersion || 'v21.0'}`;
}
function authHeaders(creds) {
  return { Authorization: `Bearer ${creds.accessToken}` };
}

async function uploadMediaToMeta(creds, { buffer, mime, filename }) {
  const form = new FormData();
  form.append('messaging_product', 'whatsapp');
  form.append('type', mime || 'application/octet-stream');
  form.append('file', buffer, {
    filename: (filename || 'upload').replace(/[^A-Za-z0-9._-]/g, '_'),
    contentType: mime || 'application/octet-stream',
  });
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/media`, form, {
    headers: { ...authHeaders(creds), ...form.getHeaders() },
    maxBodyLength: Infinity,
    maxContentLength: Infinity,
  });
  return data;
}

async function fetchUrlToBuffer(url) {
  const r = await axios.get(url, { responseType: 'arraybuffer' });
  return { buffer: Buffer.from(r.data), mime: r.headers['content-type'] };
}

async function sendText(creds, to, body, context) {
  const payload = {
    messaging_product: 'whatsapp',
    recipient_type: 'individual',
    to,
    type: 'text',
    text: { body, preview_url: true },
  };
  if (context) payload.context = { message_id: context };
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/messages`, payload, { headers: authHeaders(creds) });
  return data;
}

async function sendMedia(creds, to, type, source, caption, filename) {
  const media = (typeof source === 'string') ? { link: source } : { ...source };
  if (caption && (type === 'image' || type === 'video' || type === 'document')) media.caption = caption;
  if (filename && type === 'document') media.filename = filename;
  const payload = {
    messaging_product: 'whatsapp',
    to,
    type,
    [type]: media,
  };
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/messages`, payload, { headers: authHeaders(creds) });
  return data;
}

async function sendInteractive(creds, to, { kind, header, body, footer, action, context }) {
  const interactive = { type: kind };
  if (header) {
    if (header.type === 'text') {
      interactive.header = { type: 'text', text: header.text || '' };
    } else if (['image', 'video', 'document'].includes(header.type)) {
      const mediaObj = header.link
        ? { link: header.link }
        : header.mediaId ? { id: header.mediaId } : null;
      if (mediaObj) {
        if (header.type === 'document' && header.filename) mediaObj.filename = header.filename;
        interactive.header = { type: header.type, [header.type]: mediaObj };
      }
    }
  }
  if (body) interactive.body = { text: body };
  if (footer) interactive.footer = { text: footer };
  interactive.action = action;

  const payload = {
    messaging_product: 'whatsapp',
    to,
    type: 'interactive',
    interactive,
  };
  if (context) payload.context = { message_id: context };
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/messages`, payload, { headers: authHeaders(creds) });
  return data;
}

async function sendReaction(creds, to, messageId, emoji) {
  const payload = {
    messaging_product: 'whatsapp',
    to,
    type: 'reaction',
    reaction: { message_id: messageId, emoji: emoji || '' },
  };
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/messages`, payload, { headers: authHeaders(creds) });
  return data;
}

async function markAsRead(creds, messageId) {
  try {
    await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/messages`, {
      messaging_product: 'whatsapp',
      status: 'read',
      message_id: messageId,
    }, { headers: authHeaders(creds) });
  } catch (e) { /* noop */ }
}

async function sendTemplateMessage(creds, to, templateName, language, components) {
  const payload = {
    messaging_product: 'whatsapp',
    to,
    type: 'template',
    template: {
      name: templateName,
      language: { code: language || 'en_US' },
      ...(components && components.length ? { components } : {}),
    },
  };
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.phoneNumberId}/messages`, payload, { headers: authHeaders(creds) });
  return data;
}

async function getMediaUrl(creds, mediaId) {
  const { data } = await axios.get(`${GRAPH(creds)}/${mediaId}`, { headers: authHeaders(creds) });
  return data;
}

async function downloadMedia(creds, mediaUrl) {
  const { data, headers } = await axios.get(mediaUrl, {
    headers: authHeaders(creds),
    responseType: 'arraybuffer',
  });
  return { buffer: Buffer.from(data), contentType: headers['content-type'] };
}

async function listTemplates(creds) {
  const { data } = await axios.get(`${GRAPH(creds)}/${creds.wabaId}/message_templates`, {
    headers: authHeaders(creds),
    params: { limit: 200 },
  });
  return data;
}

async function getTemplateById(creds, id) {
  const { data } = await axios.get(`${GRAPH(creds)}/${id}`, {
    headers: authHeaders(creds),
    params: { fields: 'name,status,category,language,components,id,rejected_reason' },
  });
  return data;
}

async function createTemplate(creds, payload) {
  const { data } = await axios.post(`${GRAPH(creds)}/${creds.wabaId}/message_templates`, payload, { headers: authHeaders(creds) });
  return data;
}

async function deleteTemplate(creds, name) {
  const { data } = await axios.delete(`${GRAPH(creds)}/${creds.wabaId}/message_templates`, {
    headers: authHeaders(creds),
    params: { name },
  });
  return data;
}

async function uploadHeaderSample(creds, { fileUrl, fileName, fileType }) {
  if (!creds.appId || !creds.appSecret) {
    return { header_handle: fileUrl };
  }
  const fileResp = await axios.get(fileUrl, { responseType: 'arraybuffer' });
  const buffer = Buffer.from(fileResp.data);
  const respMime = (fileResp.headers['content-type'] || '').split(';')[0].trim().toLowerCase();
  const mime = fileType || respMime || 'application/octet-stream';

  const appAccessToken = `${creds.appId}|${creds.appSecret}`;
  const createResp = await axios.post(
    `${GRAPH(creds)}/${creds.appId}/uploads`,
    null,
    {
      params: {
        file_name: fileName || 'header',
        file_length: buffer.length,
        file_type: mime,
        access_token: appAccessToken,
      },
    }
  );
  const sessionId = createResp.data.id;

  const uploadResp = await axios.post(
    `${GRAPH(creds)}/${sessionId}`,
    buffer,
    {
      headers: {
        Authorization: `OAuth ${creds.accessToken}`,
        file_offset: '0',
        'Content-Type': mime,
      },
      maxBodyLength: Infinity,
      maxContentLength: Infinity,
    }
  );
  const handle = uploadResp.data?.h;
  if (!handle) throw new Error('No header handle returned from Meta upload');
  return { header_handle: handle };
}

module.exports = {
  sendText,
  sendMedia,
  sendInteractive,
  sendReaction,
  sendTemplateMessage,
  markAsRead,
  getMediaUrl,
  downloadMedia,
  listTemplates,
  getTemplateById,
  createTemplate,
  deleteTemplate,
  uploadHeaderSample,
  uploadMediaToMeta,
  fetchUrlToBuffer,
};
