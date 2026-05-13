const cloudinary = require('cloudinary').v2;
const multer = require('multer');

cloudinary.config({
  cloud_name: process.env.CLOUDINARY_CLOUD_NAME,
  api_key: process.env.CLOUDINARY_API_KEY,
  api_secret: process.env.CLOUDINARY_API_SECRET,
  secure: true,
});

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 32 * 1024 * 1024 },
});

function resourceTypeOf(mime) {
  if (!mime) return 'auto';
  if (mime === 'application/pdf') return 'image';
  if (mime.startsWith('image/')) return 'image';
  if (mime.startsWith('video/') || mime.startsWith('audio/')) return 'video';
  return 'raw';
}

function uploadBuffer(buffer, { folder = 'bulk_campaign', filename, mime } = {}) {
  return new Promise((resolve, reject) => {
    const resource_type = resourceTypeOf(mime);
    const base = filename
      ? `${Date.now()}_${filename
          .replace(/\.[^.]+$/, '')
          .replace(/[^A-Za-z0-9._-]+/g, '_')
          .replace(/_+/g, '_')
          .replace(/^_+|_+$/g, '')
          || 'file'}`
      : undefined;
    const options = {
      folder,
      resource_type,
      public_id: base,
    };
    if (mime === 'application/pdf') options.format = 'pdf';
    if (mime && mime.startsWith('audio/')) options.format = 'mp3';
    const stream = cloudinary.uploader.upload_stream(options,
      (err, result) => (err ? reject(err) : resolve(result))
    );
    stream.end(buffer);
  });
}

function toDownloadUrl(url) {
  if (!url || typeof url !== 'string') return url;
  if (!url.includes('res.cloudinary.com')) return url;
  if (url.includes('/fl_attachment')) return url;
  return url.replace(/\/upload\//, '/upload/fl_attachment/');
}

function parseCloudinaryUrl(url) {
  if (!url || typeof url !== 'string') return null;
  if (!url.includes('res.cloudinary.com')) return null;
  try {
    const u = new URL(url);
    const parts = u.pathname.split('/').filter(Boolean);
    const rtIdx = parts.findIndex(p => ['image', 'video', 'raw'].includes(p));
    if (rtIdx === -1) return null;
    const resourceType = parts[rtIdx];
    let i = rtIdx + 2;
    if (parts[i] && /^v\d+$/.test(parts[i])) i += 1;
    const rest = parts.slice(i).join('/');
    const publicId = resourceType === 'raw'
      ? rest
      : rest.replace(/\.[^.]+$/, '');
    return { publicId: decodeURIComponent(publicId), resourceType };
  } catch { return null; }
}

async function deleteByUrl(url) {
  const parsed = parseCloudinaryUrl(url);
  if (!parsed) return { skipped: true };
  try {
    const res = await cloudinary.uploader.destroy(parsed.publicId, {
      resource_type: parsed.resourceType,
      invalidate: true,
    });
    return res;
  } catch (e) {
    console.error('[cloudinary deleteByUrl] failed', parsed, e.message);
    return { error: e.message };
  }
}

async function deleteFolder(folderPath) {
  if (!folderPath) return;
  const types = ['image', 'video', 'raw'];
  for (const t of types) {
    try {
      await cloudinary.api.delete_resources_by_prefix(folderPath, { resource_type: t, invalidate: true });
    } catch (e) {
      if (!/not found/i.test(e.message || '')) {
        console.error(`[cloudinary deleteFolder] ${t}`, e.message);
      }
    }
  }
  try { await cloudinary.api.delete_folder(folderPath); }
  catch (e) { /* folder may be empty or already gone */ }
}

module.exports = { cloudinary, upload, uploadBuffer, toDownloadUrl, parseCloudinaryUrl, deleteByUrl, deleteFolder };
