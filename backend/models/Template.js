const mongoose = require('mongoose');

const TemplateSchema = new mongoose.Schema(
  {
    user: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, index: true },
    metaId: { type: String, index: true },
    name: { type: String, required: true, index: true },
    language: { type: String, default: 'en_US' },
    category: { type: String, default: 'MARKETING' },
    status: {
      type: String,
      enum: ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'IN_APPEAL'],
      default: 'DRAFT',
    },
    rejectedReason: { type: String, default: '' },
    header: {
      type: { type: String, enum: ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'], default: 'NONE' },
      text: String,
      mediaUrl: String,
    },
    body: { type: String, required: true },
    footer: { type: String, default: '' },
    buttons: [
      {
        type: { type: String, enum: ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'], default: 'QUICK_REPLY' },
        text: String,
        url: String,
        phone_number: String,
        replyText: { type: String, default: '' },
      },
    ],
    components: { type: Object },
    lastSyncedAt: Date,
  },
  { timestamps: true }
);

TemplateSchema.index({ user: 1, name: 1, language: 1 });

module.exports = mongoose.model('Template', TemplateSchema);
