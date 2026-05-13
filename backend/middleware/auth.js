const jwt = require('jsonwebtoken');
const User = require('../models/User');

function getSecret() {
  return process.env.JWT_SECRET || 'change-me-in-prod';
}

// Sign JWT for admin
function signAdminToken(username) {
  return jwt.sign(
    { sub: username, role: 'admin' },
    getSecret(),
    { expiresIn: '12h' }
  );
}

// Sign JWT for a user (tenant)
function signUserToken(userId, username) {
  return jwt.sign(
    { sub: userId, username, role: 'user' },
    getSecret(),
    { expiresIn: '24h' }
  );
}

// Middleware: require admin JWT
function requireAdmin(req, res, next) {
  const header = req.headers.authorization || '';
  const m = /^Bearer\s+(.+)$/i.exec(header.trim());
  if (!m) return res.status(401).json({ error: 'No token' });
  try {
    const decoded = jwt.verify(m[1], getSecret());
    if (decoded.role !== 'admin') {
      return res.status(403).json({ error: 'Forbidden' });
    }
    req.admin = decoded;
    return next();
  } catch (err) {
    return res.status(401).json({ error: 'Invalid or expired token' });
  }
}

// Middleware: require user JWT, attaches req.user (full Mongo doc)
async function requireUser(req, res, next) {
  const header = req.headers.authorization || '';
  const m = /^Bearer\s+(.+)$/i.exec(header.trim());
  if (!m) return res.status(401).json({ error: 'No token' });
  try {
    const decoded = jwt.verify(m[1], getSecret());
    if (decoded.role !== 'user') {
      return res.status(403).json({ error: 'Forbidden - not a user token' });
    }
    const user = await User.findById(decoded.sub);
    if (!user || !user.active) {
      return res.status(401).json({ error: 'Account disabled or not found' });
    }
    req.user = user;
    return next();
  } catch (err) {
    return res.status(401).json({ error: 'Invalid or expired token' });
  }
}

module.exports = { signAdminToken, signUserToken, requireAdmin, requireUser, getSecret };
