const User = require('../models/User');
const Contact = require('../models/Contact');
const Message = require('../models/Message');
const Template = require('../models/Template');
const { signAdminToken } = require('../middleware/auth');

// POST /api/admin/login
exports.login = (req, res) => {
  const { username, password } = req.body || {};
  const expectedUser = process.env.ADMIN_USER || 'admin';
  const expectedPass = process.env.ADMIN_PASS || 'admin';
  if (
    typeof username !== 'string' ||
    typeof password !== 'string' ||
    username !== expectedUser ||
    password !== expectedPass
  ) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }
  return res.json({
    token: signAdminToken(username),
    user: { username, role: 'admin' },
  });
};

// GET /api/admin/me
exports.me = (req, res) => {
  res.json({ ok: true, user: { username: req.admin?.sub, role: 'admin' } });
};

// ---- User (tenant) management ----

// GET /api/admin/users
exports.listUsers = async (req, res) => {
  const users = await User.find().sort({ createdAt: -1 }).lean();
  // Strip sensitive fields
  const safe = users.map(u => {
    const { password, metaAccessToken, metaAppSecret, ...rest } = u;
    return { ...rest, hasAccessToken: !!metaAccessToken, hasAppSecret: !!metaAppSecret };
  });
  res.json(safe);
};

// GET /api/admin/users/:id
exports.getUser = async (req, res) => {
  const user = await User.findById(req.params.id);
  if (!user) return res.status(404).json({ error: 'Not found' });
  res.json(user.toAdminJSON());
};

// POST /api/admin/users
exports.createUser = async (req, res) => {
  try {
    const {
      username, password, displayName, phoneNumber,
      metaAccessToken, metaPhoneNumberId, metaWabaId,
      metaAppId, metaAppSecret, metaGraphVersion,
    } = req.body;

    if (!username || !password) {
      return res.status(400).json({ error: 'username and password required' });
    }

    const existing = await User.findOne({ username: username.toLowerCase().trim() });
    if (existing) return res.status(409).json({ error: 'Username already exists' });

    const user = new User({
      username: username.toLowerCase().trim(),
      password,
      displayName: displayName || username,
      phoneNumber: phoneNumber || '',
      metaAccessToken: metaAccessToken || '',
      metaPhoneNumberId: metaPhoneNumberId || '',
      metaWabaId: metaWabaId || '',
      metaAppId: metaAppId || '',
      metaAppSecret: metaAppSecret || '',
      metaGraphVersion: metaGraphVersion || 'v21.0',
    });

    // Auto-generate verify token
    user.generateVerifyToken();

    await user.save();

    const backendUrl = process.env.BACKEND_URL || `${req.protocol}://${req.get('host')}`;
    res.json({
      user: user.toSafeJSON(),
      webhookUrl: `${backendUrl}/api/webhook/${user._id}`,
      verifyToken: user.metaVerifyToken,
      credentials: {
        username: user.username,
        password: req.body.password, // Return plain password once (not stored)
      },
    });
  } catch (e) {
    console.error('[createUser]', e.message);
    res.status(500).json({ error: 'Failed', details: e.message });
  }
};

// PATCH /api/admin/users/:id
exports.updateUser = async (req, res) => {
  try {
    const user = await User.findById(req.params.id);
    if (!user) return res.status(404).json({ error: 'Not found' });

    const fields = [
      'displayName', 'phoneNumber', 'active',
      'metaAccessToken', 'metaPhoneNumberId', 'metaWabaId',
      'metaAppId', 'metaAppSecret', 'metaGraphVersion',
    ];
    for (const f of fields) {
      if (req.body[f] !== undefined) user[f] = req.body[f];
    }
    // Password change
    if (req.body.password) {
      user.password = req.body.password; // pre-save hook hashes it
    }

    await user.save();
    res.json(user.toAdminJSON());
  } catch (e) {
    console.error('[updateUser]', e.message);
    res.status(500).json({ error: 'Failed', details: e.message });
  }
};

// POST /api/admin/users/:id/regenerate-token
exports.regenerateVerifyToken = async (req, res) => {
  const user = await User.findById(req.params.id);
  if (!user) return res.status(404).json({ error: 'Not found' });
  const token = user.generateVerifyToken();
  await user.save();
  const backendUrl = process.env.BACKEND_URL || `${req.protocol}://${req.get('host')}`;
  res.json({
    verifyToken: token,
    webhookUrl: `${backendUrl}/api/webhook/${user._id}`,
  });
};

// DELETE /api/admin/users/:id
exports.deleteUser = async (req, res) => {
  try {
    const user = await User.findById(req.params.id);
    if (!user) return res.status(404).json({ error: 'Not found' });

    // Delete all user data
    await Message.deleteMany({ user: user._id });
    await Contact.deleteMany({ user: user._id });
    await Template.deleteMany({ user: user._id });
    await user.deleteOne();

    res.json({ ok: true });
  } catch (e) {
    console.error('[deleteUser]', e.message);
    res.status(500).json({ error: 'Failed', details: e.message });
  }
};

// GET /api/admin/users/:id/stats
exports.getUserStats = async (req, res) => {
  const userId = req.params.id;
  const [contacts, messages, templates] = await Promise.all([
    Contact.countDocuments({ user: userId }),
    Message.countDocuments({ user: userId }),
    Template.countDocuments({ user: userId }),
  ]);
  res.json({ contacts, messages, templates });
};
