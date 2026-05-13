const User = require('../models/User');
const { signUserToken } = require('../middleware/auth');

// POST /api/auth/login  { username, password }
exports.login = async (req, res) => {
  try {
    const { username, password } = req.body || {};
    if (!username || !password) {
      return res.status(400).json({ error: 'Username and password required' });
    }

    const user = await User.findOne({ username: username.toLowerCase().trim() });
    if (!user) return res.status(401).json({ error: 'Invalid credentials' });
    if (!user.active) return res.status(401).json({ error: 'Account disabled' });

    const match = await user.comparePassword(password);
    if (!match) return res.status(401).json({ error: 'Invalid credentials' });

    const token = signUserToken(user._id.toString(), user.username);
    res.json({
      token,
      user: {
        id: user._id,
        username: user.username,
        displayName: user.displayName,
        phoneNumber: user.phoneNumber,
      },
    });
  } catch (e) {
    console.error('[auth login]', e.message);
    res.status(500).json({ error: 'Login failed' });
  }
};

// GET /api/auth/me
exports.me = (req, res) => {
  res.json({
    ok: true,
    user: {
      id: req.user._id,
      username: req.user.username,
      displayName: req.user.displayName,
      phoneNumber: req.user.phoneNumber,
    },
  });
};
