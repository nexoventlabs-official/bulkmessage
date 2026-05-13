import React, { useState } from 'react';
import { Admin } from '../api/client';
import { ArrowLeft, Copy, Check } from 'lucide-react';

export default function AdminCreateUser({ onBack, onCreated }) {
  const [form, setForm] = useState({
    username: '',
    password: '',
    displayName: '',
    phoneNumber: '',
    metaAccessToken: '',
    metaPhoneNumberId: '',
    metaWabaId: '',
    metaAppId: '',
    metaAppSecret: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);
  const [copied, setCopied] = useState('');

  function update(key, val) {
    setForm(prev => ({ ...prev, [key]: val }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.username || !form.password) {
      setError('Username and password are required');
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      const res = await Admin.createUser(form);
      setResult(res);
    } catch (err) {
      setError(err.response?.data?.error || err.message);
    } finally {
      setSubmitting(false);
    }
  }

  function copyToClipboard(text, label) {
    navigator.clipboard.writeText(text);
    setCopied(label);
    setTimeout(() => setCopied(''), 2000);
  }

  if (result) {
    return (
      <div className="p-6 max-w-2xl mx-auto">
        <button onClick={onBack} className="flex items-center gap-2 text-sm text-admin-muted hover:text-admin-text mb-6">
          <ArrowLeft size={16} /> Back to Users
        </button>

        <div className="bg-white rounded-xl border border-admin-border p-6">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
              <Check size={20} className="text-green-600" />
            </div>
            <div>
              <h3 className="font-bold text-admin-text">User Created Successfully</h3>
              <p className="text-sm text-admin-muted">Share these credentials with the user</p>
            </div>
          </div>

          <div className="space-y-4">
            <InfoRow label="Username" value={result.credentials.username} onCopy={() => copyToClipboard(result.credentials.username, 'username')} copied={copied === 'username'} />
            <InfoRow label="Password" value={result.credentials.password} onCopy={() => copyToClipboard(result.credentials.password, 'password')} copied={copied === 'password'} />
            <InfoRow label="Webhook URL" value={result.webhookUrl} onCopy={() => copyToClipboard(result.webhookUrl, 'webhook')} copied={copied === 'webhook'} />
            <InfoRow label="Verify Token" value={result.verifyToken} onCopy={() => copyToClipboard(result.verifyToken, 'verify')} copied={copied === 'verify'} />
          </div>

          <div className="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200 text-sm text-yellow-800">
            <strong>Important:</strong> The password is shown only once. If lost, you can reset it from the user detail page.
          </div>

          <div className="mt-6 flex gap-3">
            <button onClick={onBack} className="px-4 py-2 text-sm bg-gray-100 rounded-lg hover:bg-gray-200">Back to Users</button>
            <button onClick={() => onCreated(result.user._id)} className="px-4 py-2 text-sm bg-admin-accent text-white rounded-lg hover:bg-admin-accentHover">View User Details</button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-2xl mx-auto">
      <button onClick={onBack} className="flex items-center gap-2 text-sm text-admin-muted hover:text-admin-text mb-6">
        <ArrowLeft size={16} /> Back to Users
      </button>

      <h2 className="text-xl font-bold text-admin-text mb-1">Create New User</h2>
      <p className="text-sm text-admin-muted mb-6">Set up a new WhatsApp panel account</p>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-admin-border p-6 space-y-5">
        <h3 className="font-semibold text-admin-text border-b border-admin-border pb-2">Account Details</h3>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="text-xs font-medium text-admin-muted">Username *</label>
            <input value={form.username} onChange={e => update('username', e.target.value)} placeholder="e.g. john_business"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">Password *</label>
            <input type="text" value={form.password} onChange={e => update('password', e.target.value)} placeholder="Strong password"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">Display Name</label>
            <input value={form.displayName} onChange={e => update('displayName', e.target.value)} placeholder="John's Business"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">Phone Number</label>
            <input value={form.phoneNumber} onChange={e => update('phoneNumber', e.target.value)} placeholder="919876543210"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
        </div>

        <h3 className="font-semibold text-admin-text border-b border-admin-border pb-2 pt-2">Meta WhatsApp API Credentials</h3>
        <p className="text-xs text-admin-muted -mt-3">These can be added later from the user detail page</p>

        <div className="grid grid-cols-2 gap-4">
          <div className="col-span-2">
            <label className="text-xs font-medium text-admin-muted">Access Token</label>
            <input value={form.metaAccessToken} onChange={e => update('metaAccessToken', e.target.value)} placeholder="EAAG…"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent font-mono" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">Phone Number ID</label>
            <input value={form.metaPhoneNumberId} onChange={e => update('metaPhoneNumberId', e.target.value)} placeholder="1234567890"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent font-mono" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">WABA ID</label>
            <input value={form.metaWabaId} onChange={e => update('metaWabaId', e.target.value)} placeholder="1234567890"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent font-mono" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">App ID</label>
            <input value={form.metaAppId} onChange={e => update('metaAppId', e.target.value)} placeholder="Optional"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent font-mono" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">App Secret</label>
            <input value={form.metaAppSecret} onChange={e => update('metaAppSecret', e.target.value)} placeholder="Optional"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent font-mono" />
          </div>
        </div>

        {error && <div className="text-sm text-red-600 bg-red-50 rounded-lg p-3">{error}</div>}

        <div className="flex gap-3 pt-2">
          <button type="button" onClick={onBack} className="px-4 py-2.5 text-sm bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button type="submit" disabled={submitting} className="px-6 py-2.5 text-sm bg-admin-accent text-white rounded-lg hover:bg-admin-accentHover disabled:opacity-50">
            {submitting ? 'Creating…' : 'Create User'}
          </button>
        </div>
      </form>
    </div>
  );
}

function InfoRow({ label, value, onCopy, copied }) {
  return (
    <div className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
      <div>
        <div className="text-xs text-admin-muted">{label}</div>
        <div className="text-sm font-mono text-admin-text break-all">{value}</div>
      </div>
      <button onClick={onCopy} className="p-2 rounded-lg hover:bg-gray-200 text-admin-muted shrink-0" title="Copy">
        {copied ? <Check size={16} className="text-green-600" /> : <Copy size={16} />}
      </button>
    </div>
  );
}
