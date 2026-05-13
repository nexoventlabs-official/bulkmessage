import React, { useState, useEffect } from 'react';
import { Admin } from '../api/client';
import AdminLogin from './AdminLogin.jsx';
import AdminShell from './AdminShell.jsx';
import AdminDashboard from './AdminDashboard.jsx';
import AdminUserDetail from './AdminUserDetail.jsx';
import AdminCreateUser from './AdminCreateUser.jsx';

export default function AdminApp() {
  const [authed, setAuthed] = useState(false);
  const [checking, setChecking] = useState(true);
  const [view, setView] = useState('dashboard'); // dashboard | user-detail | create-user
  const [selectedUserId, setSelectedUserId] = useState(null);

  useEffect(() => {
    const token = localStorage.getItem('bc:adminToken');
    if (!token) { setChecking(false); return; }
    Admin.me()
      .then(() => setAuthed(true))
      .catch(() => localStorage.removeItem('bc:adminToken'))
      .finally(() => setChecking(false));
  }, []);

  function handleLogin(token) {
    localStorage.setItem('bc:adminToken', token);
    setAuthed(true);
  }

  function handleLogout() {
    localStorage.removeItem('bc:adminToken');
    setAuthed(false);
    setView('dashboard');
  }

  function goToUser(id) {
    setSelectedUserId(id);
    setView('user-detail');
  }

  if (checking) return <div className="h-full flex items-center justify-center bg-admin-bg text-admin-muted">Loading…</div>;
  if (!authed) return <AdminLogin onLogin={handleLogin} />;

  let content;
  if (view === 'user-detail' && selectedUserId) {
    content = <AdminUserDetail userId={selectedUserId} onBack={() => setView('dashboard')} />;
  } else if (view === 'create-user') {
    content = <AdminCreateUser onBack={() => setView('dashboard')} onCreated={(id) => { goToUser(id); }} />;
  } else {
    content = <AdminDashboard onSelectUser={goToUser} onCreateUser={() => setView('create-user')} />;
  }

  return (
    <AdminShell
      onLogout={handleLogout}
      onNavigate={(v) => setView(v)}
      currentView={view}
    >
      {content}
    </AdminShell>
  );
}
