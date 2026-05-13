const mongoose = require('mongoose');
const bcrypt = require('bcryptjs');
const crypto = require('crypto');

const UserSchema = new mongoose.Schema(
  {
    username: { type: String, required: true, unique: true, trim: true, lowercase: true },
    password: { type: String, required: true },
    displayName: { type: String, default: '' },
    active: { type: Boolean, default: true },

    // Meta WhatsApp Cloud API credentials
    metaAccessToken: { type: String, default: '' },
    metaPhoneNumberId: { type: String, default: '' },
    metaWabaId: { type: String, default: '' },
    metaAppId: { type: String, default: '' },
    metaAppSecret: { type: String, default: '' },
    metaGraphVersion: { type: String, default: 'v21.0' },
    metaVerifyToken: { type: String, default: '' },

    // The business phone number (for display)
    phoneNumber: { type: String, default: '' },

    // Webhook URL is: <BACKEND_URL>/api/webhook/<user._id>
    // Verify token is auto-generated or set by admin
  },
  { timestamps: true }
);

// Hash password before saving
UserSchema.pre('save', async function (next) {
  if (!this.isModified('password')) return next();
  this.password = await bcrypt.hash(this.password, 10);
  next();
});

UserSchema.methods.comparePassword = function (candidate) {
  return bcrypt.compare(candidate, this.password);
};

// Generate a random verify token
UserSchema.methods.generateVerifyToken = function () {
  this.metaVerifyToken = crypto.randomBytes(24).toString('hex');
  return this.metaVerifyToken;
};

// Return Meta credentials object (used by metaService)
UserSchema.methods.getMetaCredentials = function () {
  return {
    accessToken: this.metaAccessToken,
    phoneNumberId: this.metaPhoneNumberId,
    wabaId: this.metaWabaId,
    appId: this.metaAppId,
    appSecret: this.metaAppSecret,
    graphVersion: this.metaGraphVersion || 'v21.0',
  };
};

// Don't return sensitive fields in JSON
UserSchema.methods.toSafeJSON = function () {
  const obj = this.toObject();
  delete obj.password;
  delete obj.metaAccessToken;
  delete obj.metaAppSecret;
  return obj;
};

// Admin-facing: includes Meta creds (but not password)
UserSchema.methods.toAdminJSON = function () {
  const obj = this.toObject();
  delete obj.password;
  return obj;
};

module.exports = mongoose.model('User', UserSchema);
