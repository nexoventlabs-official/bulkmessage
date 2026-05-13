import React, { useState } from 'react';
import { Admin } from '../api/client';
import { Shield } from 'lucide-react';

export default function AdminLogin({ onLogin }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    if (!username || !password) return;
    setLoading(true);
    setError('');
    try {
      const res = await Admin.login(username, password);
      onLogin(res.token);
    } catch (err) {
      setError(err.response?.data?.error || 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="h-full flex items-center justify-center bg-gradient-to-br from-admin-accent/10 via-white to-admin-accent/5">
      <div className="w-full max-w-md mx-4">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-admin-accent text-white mb-4">
            <Shield size={32} />
          </div>
          <h1 className="text-2xl font-bold text-admin-text">Admin Panel</h1>
          <p className="text-sm text-admin-muted mt-1">Manage users, webhooks, and credentials</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-premium p-8 space-y-5">
          <div>
            <label className="text-xs font-medium text-admin-muted uppercase tracking-wide">Username</label>
            <input value={username} onChange={e => setUsername(e.target.value)} placeholder="Admin username" autoFocus
              className="w-full mt-1.5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-admin-accent/30 focus:border-admin-accent" />
          </div>
          <div>
            <label className="text-xs font-medium text-admin-muted uppercase tracking-wide">Password</label>
            <input type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="Password"
              className="w-full mt-1.5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-admin-accent/30 focus:border-admin-accent" />
          </div>
          {error && <div className="text-sm text-red-600 bg-red-50 rounded-lg p-3">{error}</div>}
          <button type="submit" disabled={loading || !username || !password}
            className="w-full py-3 bg-admin-accent text-white rounded-xl font-medium text-sm hover:bg-admin-accentHover disabled:opacity-50 transition">
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>
      </div>
    </div>
  );
}
