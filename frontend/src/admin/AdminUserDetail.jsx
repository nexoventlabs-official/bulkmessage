import React, { useEffect, useState } from 'react';
import { Admin } from '../api/client';
import { ArrowLeft, Copy, Check, RefreshCw, Trash2, Save, Link2, Key, Eye, EyeOff } from 'lucide-react';

export default function AdminUserDetail({ userId, onBack }) {
  const [user, setUser] = useState(null);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({});
  const [copied, setCopied] = useState('');
  const [showToken, setShowToken] = useState(false);
  const [newPassword, setNewPassword] = useState('');
  const [toast, setToast] = useState('');

  useEffect(() => {
    loadUser();
    Admin.getUserStats(userId).then(setStats).catch(() => {});
  }, [userId]);

  async function loadUser() {
    setLoading(true);
    try {
      const u = await Admin.getUser(userId);
      setUser(u);
      setForm({
        displayName: u.displayName || '',
        phoneNumber: u.phoneNumber || '',
        active: u.active,
        metaAccessToken: u.metaAccessToken || '',
        metaPhoneNumberId: u.metaPhoneNumberId || '',
        metaWabaId: u.metaWabaId || '',
        metaAppId: u.metaAppId || '',
        metaAppSecret: u.metaAppSecret || '',
        metaGraphVersion: u.metaGraphVersion || 'v21.0',
      });
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  function update(key, val) { setForm(prev => ({ ...prev, [key]: val })); }

  function showToastMsg(msg) { setToast(msg); setTimeout(() => setToast(''), 3000); }

  function copy(text, label) {
    navigator.clipboard.writeText(text);
    setCopied(label);
    setTimeout(() => setCopied(''), 2000);
  }

  async function handleSave() {
    setSaving(true);
    try {
      const payload = { ...form };
      if (newPassword) payload.password = newPassword;
      const updated = await Admin.updateUser(userId, payload);
      setUser(updated);
      setNewPassword('');
      showToastMsg('Saved successfully');
    } catch (e) {
      alert('Save failed: ' + (e.response?.data?.error || e.message));
    } finally {
      setSaving(false);
    }
  }

  async function handleRegenToken() {
    if (!confirm('Regenerate the verify token? You will need to update the webhook in Meta.')) return;
    try {
      const res = await Admin.regenerateToken(userId);
      showToastMsg('Token regenerated');
      loadUser();
    } catch (e) {
      alert('Failed: ' + (e.response?.data?.error || e.message));
    }
  }

  async function handleDelete() {
    if (!confirm(`DELETE user "${user?.username}"? This will permanently remove ALL their data (contacts, messages, templates).`)) return;
    if (!confirm('Are you absolutely sure? This cannot be undone.')) return;
    try {
      await Admin.deleteUser(userId);
      onBack();
    } catch (e) {
      alert('Delete failed: ' + (e.response?.data?.error || e.message));
    }
  }

  const backendUrl = import.meta.env.VITE_API_URL || window.location.origin;
  const webhookUrl = user ? `${backendUrl}/api/webhook/${user._id}` : '';

  if (loading) return <div className="p-6 text-admin-muted">Loading…</div>;
  if (!user) return <div className="p-6 text-red-600">User not found</div>;

  return (
    <div className="p-6 max-w-3xl mx-auto">
      <button onClick={onBack} className="flex items-center gap-2 text-sm text-admin-muted hover:text-admin-text mb-6">
        <ArrowLeft size={16} /> Back to Users
      </button>

      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-xl font-bold text-admin-text">{user.username}</h2>
          <p className="text-sm text-admin-muted">{user.displayName} · Created {new Date(user.createdAt).toLocaleDateString()}</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={handleSave} disabled={saving} className="px-4 py-2 text-sm bg-admin-accent text-white rounded-lg hover:bg-admin-accentHover flex items-center gap-2 disabled:opacity-50">
            <Save size={16} /> {saving ? 'Saving…' : 'Save Changes'}
          </button>
          <button onClick={handleDelete} className="px-3 py-2 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100 flex items-center gap-2">
            <Trash2 size={16} /> Delete
          </button>
        </div>
      </div>

      {toast && (
        <div className="mb-4 px-4 py-2 bg-green-100 text-green-800 rounded-lg text-sm">{toast}</div>
      )}

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="bg-white rounded-lg border border-admin-border p-3 text-center">
            <div className="text-lg font-bold text-admin-text">{stats.contacts}</div>
            <div className="text-xs text-admin-muted">Contacts</div>
          </div>
          <div className="bg-white rounded-lg border border-admin-border p-3 text-center">
            <div className="text-lg font-bold text-admin-text">{stats.messages}</div>
            <div className="text-xs text-admin-muted">Messages</div>
          </div>
          <div className="bg-white rounded-lg border border-admin-border p-3 text-center">
            <div className="text-lg font-bold text-admin-text">{stats.templates}</div>
            <div className="text-xs text-admin-muted">Templates</div>
          </div>
        </div>
      )}

      {/* Webhook Info */}
      <div className="bg-white rounded-xl border border-admin-border p-5 mb-5">
        <h3 className="font-semibold text-admin-text flex items-center gap-2 mb-4">
          <Link2 size={18} /> Webhook Configuration
        </h3>
        <div className="space-y-3">
          <div className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
            <div>
              <div className="text-xs text-admin-muted">Webhook URL</div>
              <div className="text-sm font-mono text-admin-text break-all">{webhookUrl}</div>
            </div>
            <button onClick={() => copy(webhookUrl, 'webhook')} className="p-2 rounded-lg hover:bg-gray-200 text-admin-muted">
              {copied === 'webhook' ? <Check size={16} className="text-green-600" /> : <Copy size={16} />}
            </button>
          </div>
          <div className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
            <div>
              <div className="text-xs text-admin-muted">Verify Token</div>
              <div className="text-sm font-mono text-admin-text break-all">{user.metaVerifyToken || '—'}</div>
            </div>
            <div className="flex items-center gap-1">
              <button onClick={() => copy(user.metaVerifyToken || '', 'verify')} className="p-2 rounded-lg hover:bg-gray-200 text-admin-muted">
                {copied === 'verify' ? <Check size={16} className="text-green-600" /> : <Copy size={16} />}
              </button>
              <button onClick={handleRegenToken} className="p-2 rounded-lg hover:bg-gray-200 text-admin-muted" title="Regenerate token">
                <RefreshCw size={16} />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Account Settings */}
      <div className="bg-white rounded-xl border border-admin-border p-5 mb-5">
        <h3 className="font-semibold text-admin-text mb-4">Account Settings</h3>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="text-xs font-medium text-admin-muted">Display Name</label>
            <input value={form.displayName} onChange={e => update('displayName', e.target.value)}
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">Phone Number</label>
            <input value={form.phoneNumber} onChange={e => update('phoneNumber', e.target.value)}
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">New Password (leave blank to keep)</label>
            <input type="text" value={newPassword} onChange={e => setNewPassword(e.target.value)} placeholder="Leave blank to keep current"
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted">Status</label>
            <select value={form.active ? 'true' : 'false'} onChange={e => update('active', e.target.value === 'true')}
              className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:border-admin-accent">
              <option value="true">Active</option>
              <option value="false">Disabled</option>
            </select>
          </div>
        </div>
      </div>

      {/* Meta Credentials */}
      <div className="bg-white rounded-xl border border-admin-border p-5">
        <h3 className="font-semibold text-admin-text flex items-center gap-2 mb-4">
          <Key size={18} /> Meta WhatsApp API Credentials
        </h3>
        <div className="space-y-4">
          <div>
            <label className="text-xs font-medium text-admin-muted">Access Token</label>
            <div className="flex items-center gap-2 mt-1">
              <input
                type={showToken ? 'text' : 'password'}
                value={form.metaAccessToken}
                onChange={e => update('metaAccessToken', e.target.value)}
                placeholder="EAAG…"
                className="flex-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono outline-none focus:border-admin-accent"
              />
              <button onClick={() => setShowToken(v => !v)} className="p-2.5 rounded-lg hover:bg-gray-100 text-admin-muted">
                {showToken ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-medium text-admin-muted">Phone Number ID</label>
              <input value={form.metaPhoneNumberId} onChange={e => update('metaPhoneNumberId', e.target.value)} placeholder="1234567890"
                className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono outline-none focus:border-admin-accent" />
            </div>
            <div>
              <label className="text-xs font-medium text-admin-muted">WABA ID</label>
              <input value={form.metaWabaId} onChange={e => update('metaWabaId', e.target.value)} placeholder="1234567890"
                className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono outline-none focus:border-admin-accent" />
            </div>
            <div>
              <label className="text-xs font-medium text-admin-muted">App ID</label>
              <input value={form.metaAppId} onChange={e => update('metaAppId', e.target.value)} placeholder="Optional"
                className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono outline-none focus:border-admin-accent" />
            </div>
            <div>
              <label className="text-xs font-medium text-admin-muted">App Secret</label>
              <input value={form.metaAppSecret} onChange={e => update('metaAppSecret', e.target.value)} placeholder="Optional"
                className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono outline-none focus:border-admin-accent" />
            </div>
            <div>
              <label className="text-xs font-medium text-admin-muted">Graph API Version</label>
              <input value={form.metaGraphVersion} onChange={e => update('metaGraphVersion', e.target.value)} placeholder="v21.0"
                className="w-full mt-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono outline-none focus:border-admin-accent" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
