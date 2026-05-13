import React from 'react';
import clsx from 'clsx';
import { LayoutDashboard, LogOut, MessageSquare } from 'lucide-react';

export default function AdminShell({ children, onLogout, onNavigate, currentView }) {
  const navItems = [
    { id: 'dashboard', label: 'Users', icon: LayoutDashboard },
  ];

  return (
    <div className="h-full flex bg-admin-bg">
      {/* Sidebar */}
      <aside className="w-56 shrink-0 bg-white border-r border-admin-border flex flex-col">
        <div className="px-5 py-5 border-b border-admin-border">
          <h1 className="text-lg font-bold text-admin-text">Admin Panel</h1>
          <p className="text-xs text-admin-muted mt-0.5">Bulk Campaign</p>
        </div>

        <nav className="flex-1 px-3 py-4 space-y-1">
          {navItems.map(item => (
            <button
              key={item.id}
              onClick={() => onNavigate(item.id)}
              className={clsx(
                'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors',
                currentView === item.id
                  ? 'bg-admin-accent/10 text-admin-accent font-medium'
                  : 'text-admin-muted hover:bg-gray-100'
              )}
            >
              <item.icon size={18} />
              {item.label}
            </button>
          ))}

          <a
            href="/"
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-admin-muted hover:bg-gray-100 transition-colors"
          >
            <MessageSquare size={18} />
            Chat Panel
          </a>
        </nav>

        <div className="px-3 py-4 border-t border-admin-border">
          <button
            onClick={onLogout}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-600 hover:bg-red-50 transition-colors"
          >
            <LogOut size={18} />
            Logout
          </button>
        </div>
      </aside>

      {/* Content */}
      <main className="flex-1 overflow-y-auto thin-scroll">
        {children}
      </main>
    </div>
  );
}
