const router = require('express').Router();
const { upload, uploadBuffer } = require('../config/cloudinary');
const { requireUser } = require('../middleware/auth');

router.use(requireUser);

router.post('/', upload.single('file'), async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'No file' });
    const rawWaId = (req.body.waId || req.query.waId || '').toString().replace(/\D/g, '');
    const folder = rawWaId
      ? `bulk_campaign/${req.user._id}/${rawWaId}`
      : `bulk_campaign/${req.user._id}/misc`;
    const result = await uploadBuffer(req.file.buffer, {
      folder,
      filename: req.file.originalname,
      mime: req.file.mimetype,
    });
    res.json({
      url: result.secure_url,
      public_id: result.public_id,
      mimetype: req.file.mimetype,
      size: req.file.size,
      originalname: req.file.originalname,
    });
  } catch (err) {
    console.error('[upload]', err.message);
    res.status(500).json({ error: 'Upload failed', details: err.message });
  }
});

module.exports = router;
