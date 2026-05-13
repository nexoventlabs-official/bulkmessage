import React, { useState } from 'react';
import { Auth } from './api/client';
import { MessageSquare } from 'lucide-react';

export default function UserLogin({ onLogin }) {
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
      const res = await Auth.login(username, password);
      onLogin(res.user, res.token);
    } catch (err) {
      setError(err.response?.data?.error || 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="h-full flex items-center justify-center bg-gradient-to-br from-wati-primary/10 via-white to-wati-primary/5">
      <div className="w-full max-w-md mx-4">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-wati-primary text-white mb-4">
            <MessageSquare size={32} />
          </div>
          <h1 className="text-2xl font-bold text-wati-text">WhatsApp Panel</h1>
          <p className="text-sm text-wati-muted mt-1">Sign in to manage your conversations</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-premium p-8 space-y-5">
          <div>
            <label className="text-xs font-medium text-wati-muted uppercase tracking-wide">Username</label>
            <input
              value={username}
              onChange={e => setUsername(e.target.value)}
              placeholder="Enter username"
              autoFocus
              className="w-full mt-1.5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-wati-primary/30 focus:border-wati-primary"
            />
          </div>
          <div>
            <label className="text-xs font-medium text-wati-muted uppercase tracking-wide">Password</label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="Enter password"
              className="w-full mt-1.5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-wati-primary/30 focus:border-wati-primary"
            />
          </div>

          {error && <div className="text-sm text-red-600 bg-red-50 rounded-lg p-3">{error}</div>}

          <button
            type="submit"
            disabled={loading || !username || !password}
            className="w-full py-3 bg-wati-primary text-white rounded-xl font-medium text-sm hover:brightness-110 disabled:opacity-50 transition"
          >
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>

        <p className="text-center text-xs text-wati-muted mt-6">
          Admin? <a href="/admin" className="text-wati-primary hover:underline">Go to Admin Panel</a>
        </p>
      </div>
    </div>
  );
}
