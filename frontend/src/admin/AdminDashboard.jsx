import React, { useEffect, useState } from 'react';
import { Admin } from '../api/client';
import { Plus, RefreshCw, Users, MessageSquare, FileText, ChevronRight } from 'lucide-react';

export default function AdminDashboard({ onSelectUser, onCreateUser }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const data = await Admin.listUsers();
      setUsers(data);
    } catch (e) {
      console.error('Failed to load users', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-xl font-bold text-admin-text">User Management</h2>
          <p className="text-sm text-admin-muted mt-0.5">Create and manage WhatsApp panel users</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={load} disabled={loading} className="px-3 py-2 text-sm bg-white border border-admin-border rounded-lg hover:bg-gray-50 text-admin-muted flex items-center gap-2">
            <RefreshCw size={16} className={loading ? 'animate-spin' : ''} /> Refresh
          </button>
          <button onClick={onCreateUser} className="px-4 py-2 text-sm bg-admin-accent text-white rounded-lg hover:bg-admin-accentHover flex items-center gap-2">
            <Plus size={16} /> Add User
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-admin-border p-4 flex items-center gap-4">
          <div className="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600"><Users size={20} /></div>
          <div><div className="text-2xl font-bold text-admin-text">{users.length}</div><div className="text-xs text-admin-muted">Total Users</div></div>
        </div>
        <div className="bg-white rounded-xl border border-admin-border p-4 flex items-center gap-4">
          <div className="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center text-green-600"><MessageSquare size={20} /></div>
          <div><div className="text-2xl font-bold text-admin-text">{users.filter(u => u.active).length}</div><div className="text-xs text-admin-muted">Active Users</div></div>
        </div>
        <div className="bg-white rounded-xl border border-admin-border p-4 flex items-center gap-4">
          <div className="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-600"><FileText size={20} /></div>
          <div><div className="text-2xl font-bold text-admin-text">{users.filter(u => u.hasAccessToken).length}</div><div className="text-xs text-admin-muted">Configured</div></div>
        </div>
      </div>

      {/* User list */}
      <div className="bg-white rounded-xl border border-admin-border overflow-hidden">
        <div className="px-4 py-3 border-b border-admin-border bg-gray-50">
          <div className="grid grid-cols-12 text-xs font-medium text-admin-muted uppercase tracking-wide">
            <div className="col-span-3">Username</div>
            <div className="col-span-3">Display Name</div>
            <div className="col-span-2">Phone</div>
            <div className="col-span-2">Status</div>
            <div className="col-span-2">API</div>
          </div>
        </div>
        {loading && users.length === 0 ? (
          <div className="p-8 text-center text-admin-muted text-sm">Loading users…</div>
        ) : users.length === 0 ? (
          <div className="p-8 text-center text-admin-muted text-sm">
            No users yet. Click "Add User" to create the first one.
          </div>
        ) : (
          users.map(u => (
            <button
              key={u._id}
              onClick={() => onSelectUser(u._id)}
              className="w-full grid grid-cols-12 items-center px-4 py-3 hover:bg-gray-50 border-b border-gray-100 text-left group"
            >
              <div className="col-span-3 text-sm font-medium text-admin-text truncate">{u.username}</div>
              <div className="col-span-3 text-sm text-admin-muted truncate">{u.displayName || '—'}</div>
              <div className="col-span-2 text-sm text-admin-muted">{u.phoneNumber || '—'}</div>
              <div className="col-span-2">
                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${u.active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                  {u.active ? 'Active' : 'Disabled'}
                </span>
              </div>
              <div className="col-span-2 flex items-center justify-between">
                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${u.hasAccessToken ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>
                  {u.hasAccessToken ? 'Configured' : 'No Token'}
                </span>
                <ChevronRight size={16} className="text-gray-300 group-hover:text-admin-accent transition-colors" />
              </div>
            </button>
          ))
        )}
      </div>
    </div>
  );
}
