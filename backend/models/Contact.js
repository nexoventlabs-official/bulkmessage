const mongoose = require('mongoose');

const ContactSchema = new mongoose.Schema(
  {
    user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, index: true },
    waId: { type: String, required: true, index: true },
    name: { type: String, default: '' },
    profileName: { type: String, default: '' },
    profilePicUrl: { type: String, default: '' },
    lastMessageAt: { type: Date, default: null },
    lastMessagePreview: { type: String, default: '' },
    unreadCount: { type: Number, default: 0 },
    lastCustomerMessageAt: { type: Date, default: null },
    tags: [{ type: String }],
    notes: [
      new mongoose.Schema(
        {
          text: { type: String, required: true, trim: true },
        },
        { timestamps: { createdAt: true, updatedAt: false }, _id: true }
      ),
    ],
  },
  { timestamps: true }
);

// Compound unique: same waId per user
ContactSchema.index({ user: 1, waId: 1 }, { unique: true });

ContactSchema.virtual('windowExpiresAt').get(function () {
  if (!this.lastCustomerMessageAt) return null;
  return new Date(this.lastCustomerMessageAt.getTime() + 24 * 60 * 60 * 1000);
});

ContactSchema.set('toJSON', { virtuals: true });
ContactSchema.set('toObject', { virtuals: true });

module.exports = mongoose.model('Contact', ContactSchema);
